<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Ingredient;
use App\Models\MatchaType;
use App\Models\Menu;
use App\Models\MenuVariant;
use App\Models\Topping;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Refund;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\RefundService;
use App\Services\RecipeStockService;
use App\Services\BranchMenuCategoryCatalog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const MATCHA_GRADE_SLUGS = [
        'regular-grade',
        'regular-grade-matcha',
        'ceremonial-grade',
        'ceremonial-grade-matcha',
    ];

    private const MATCHA_ORIGIN_SLUGS = [
        'kyoto',
        'osaka',
        'uji',
        'ginza',
    ];

    public function index()
    {
        return view('dashboard', $this->catalogData());
    }

    public function ownerDashboard()
    {
        $summary = Transaction::query()
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as total_transactions, COALESCE(SUM(grand_total), 0) as total_sales')
            ->first();

        $branchTotals = Transaction::query()
            ->selectRaw('branch_id, COUNT(*) as total_transactions, COALESCE(SUM(grand_total), 0) as total_sales')
            ->where('status', 'completed')
            ->whereNotNull('branch_id')
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        $branchSummaries = Branch::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(function (Branch $branch) use ($branchTotals) {
                $totals = $branchTotals->get($branch->getKey());

                return [
                    'branch' => $branch,
                    'total_transactions' => (int) optional($totals)->total_transactions,
                    'total_sales' => (float) optional($totals)->total_sales,
                ];
            });

        $unmappedSummary = Transaction::query()
            ->where('status', 'completed')
            ->whereNull('branch_id')
            ->selectRaw('COUNT(*) as total_transactions, COALESCE(SUM(grand_total), 0) as total_sales')
            ->first();
        $stockRows = BranchStock::query()->with('ingredient')->get();

        return view('dashboard.owner', [
            'activeBranchCount' => Branch::query()->where('is_active', true)->count(),
            'totalTransactions' => (int) $summary->total_transactions,
            'totalSales' => (float) $summary->total_sales,
            'branchSummaries' => $branchSummaries,
            'unmappedTransactions' => (int) $unmappedSummary->total_transactions,
            'unmappedSales' => (float) $unmappedSummary->total_sales,
            'lowStockCount' => $stockRows->filter(fn (BranchStock $stock) => $stock->ingredient && $stock->ingredient->is_active && (float) $stock->quantity <= (float) $stock->ingredient->minimum_stock)->count(),
            'stockRowCount' => $stockRows->count(),
        ]);
    }

    public function inventoryDashboard()
    {
        $activeBranch = request()->attributes->get('active_branch');

        abort_unless($activeBranch instanceof Branch, 409, 'Cabang aktif belum dipilih.');

        return view('dashboard.inventory', compact('activeBranch'));
    }

    public function showCheckout()
    {
        if (! session()->has('checkout_idempotency_key')) {
            session()->put('checkout_idempotency_key', (string) Str::uuid());
        }

        $branch = $this->activeBranch();
        return view('checkout', array_merge($this->checkoutData(), [
            'checkoutIdempotencyKey' => session('checkout_idempotency_key'),
            'checkoutTables' => DiningTable::query()->where('branch_id', $branch->id)->where('is_active', true)->orderBy('name')->get(),
            'checkoutCustomers' => Customer::query()->where('branch_id', $branch->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]));
    }

    public function latestReceipt(Request $request)
    {
        $transactionId = $request->session()->get('latest_transaction_id');

        if (! $transactionId) {
            return redirect()->route('dashboard');
        }

        return redirect()->route('receipt.show', $transactionId);
    }

    public function receipt(Transaction $transaction)
    {
        $transaction = $this->transactionForCurrentScope($transaction);
        $transaction->load(['items.menu', 'items.topping', 'items.matchaType', 'cashier']);
        $canViewRefunds = auth()->user()->hasAnyRole([User::ROLE_OWNER, User::ROLE_MANAGER]);
        $refundSummary = null;
        if ($canViewRefunds) {
            $transaction->load(['refunds.items.transactionItem', 'refunds.recorder', 'refunds.confirmer']);
            $refundSummary = app(RefundService::class)->summaryFor($transaction, auth()->user());
        }

        return view('receipt', compact('transaction', 'canViewRefunds', 'refundSummary'));
    }

    public function history(Request $request)
    {
        return view('history', array_merge($this->historyData($request), [
            'selectedTransaction' => null,
        ]));
    }

    public function historyShow(Request $request, Transaction $transaction)
    {
        $transaction = $this->transactionForCurrentScope($transaction);
        $transaction->load(['branch', 'items.menu', 'items.size', 'items.topping', 'items.matchaType', 'cashier']);
        $canViewRefunds = auth()->user()->hasAnyRole([User::ROLE_OWNER, User::ROLE_MANAGER]);
        $refundSummary = null;
        if ($canViewRefunds) {
            $transaction->load(['refunds.items.transactionItem', 'refunds.recorder', 'refunds.confirmer']);
            $refundSummary = app(RefundService::class)->summaryFor($transaction, auth()->user());
        }

        return view('history', array_merge($this->historyData($request), [
            'selectedTransaction' => $transaction,
            'selectedRefundSummary' => $refundSummary,
            'canViewRefunds' => $canViewRefunds,
        ]));
    }

    public function salesReport(Request $request)
    {
        $isOwner = $request->user()->isOwner();
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('is_active', true)],
        ]);

        $selectedDate = $validated['date'] ?? today()->toDateString();
        $startOfDay = Carbon::createFromFormat('Y-m-d', $selectedDate)->startOfDay();
        $endOfDay = (clone $startOfDay)->endOfDay();

        $activeBranch = $request->attributes->get('active_branch');
        $selectedBranch = null;

        if ($isOwner) {
            if (! empty($validated['branch_id'])) {
                $selectedBranch = Branch::query()->whereKey($validated['branch_id'])->where('is_active', true)->firstOrFail();
                app(\App\Services\BranchContext::class)->set($selectedBranch);
            }
        } else {
            abort_unless($activeBranch instanceof Branch, 403);
            if (! empty($validated['branch_id']) && (int) $validated['branch_id'] !== (int) $activeBranch->getKey()) {
                abort(403);
            }
            $selectedBranch = $activeBranch;
        }

        $reportQuery = Transaction::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$startOfDay, $endOfDay]);

        if ($selectedBranch instanceof Branch) {
            $reportQuery->where('branch_id', $selectedBranch->getKey());
        } elseif ($isOwner) {
            // Unknown historical branch attribution is reported separately, never blended into branch totals.
            $reportQuery->whereNotNull('branch_id');
        }

        $transactionIds = (clone $reportQuery)->select('transactions.id');

        $summary = (clone $reportQuery)
            ->selectRaw('COUNT(*) as total_transactions, COALESCE(SUM(grand_total), 0) as total_sales')
            ->first();

        $refundQuery = Refund::query()
            ->whereIn('refunds.status', ['recorded', 'manually_confirmed'])
            ->whereBetween('refunds.recorded_at', [$startOfDay, $endOfDay])
            ->whereHas('transaction', function ($query) {
                $query->where('transactions.status', 'completed')
                    ->whereColumn('transactions.branch_id', 'refunds.branch_id');
            });

        if ($selectedBranch instanceof Branch) {
            $refundQuery->where('refunds.branch_id', $selectedBranch->getKey());
        } elseif ($isOwner) {
            // Includes valid refunds from inactive branches, but excludes unmapped or mismatched attribution.
            $refundQuery->whereNotNull('refunds.branch_id');
        }

        // Aggregate headers only: joining refund_items here would multiply a refund with several item rows.
        $totalRefundCents = $this->reportMoneyToCents($refundQuery->sum('refunds.amount'));
        $grossSalesCents = $this->reportMoneyToCents($summary->total_sales);
        $netSalesCents = $grossSalesCents - $totalRefundCents;

        $paymentTotals = (clone $reportQuery)
            ->selectRaw('payment_method, SUM(grand_total) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method')
            ->all();

        $serviceTotals = (clone $reportQuery)
            ->selectRaw('service_type, COUNT(*) as total')
            ->groupBy('service_type')
            ->pluck('total', 'service_type')
            ->all();

        $totalItemsQuery = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->whereIn('transactions.id', $transactionIds);

        $totalItemsSold = (int) $totalItemsQuery->sum('transaction_items.quantity');

        $topProductsQuery = TransactionItem::query()
            ->selectRaw('transaction_items.menu_id, SUM(transaction_items.quantity) as quantity_sold')
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.status', 'completed')
            ->whereIn('transactions.id', $transactionIds)
            ->with('menu:id,name')
            ->groupBy('transaction_items.menu_id')
            ->orderByDesc('quantity_sold');

        $topProducts = $topProductsQuery->get();

        $transactions = (clone $reportQuery)
            ->with('branch:id,code,name')
            ->orderByDesc('sold_at')
            ->get();

        $unmappedTransactionCount = 0;
        if ($isOwner && ! $selectedBranch) {
            $unmappedTransactionCount = Transaction::query()
                ->where('status', 'completed')
                ->whereNull('branch_id')
                ->whereBetween('sold_at', [$startOfDay, $endOfDay])
                ->count();
        }

        return view('reports.sales', [
            'selectedDate' => $selectedDate,
            'isOwner' => $isOwner,
            'branches' => $isOwner ? Branch::query()->where('is_active', true)->orderBy('name')->get() : collect(),
            'selectedBranchId' => optional($selectedBranch)->getKey(),
            'selectedBranch' => $selectedBranch,
            'unmappedTransactionCount' => $unmappedTransactionCount,
            'transactions' => $transactions,
            'totalTransactions' => (int) $summary->total_transactions,
            'totalItemsSold' => $totalItemsSold,
            'totalSales' => $grossSalesCents / 100,
            'grossSales' => $grossSalesCents / 100,
            'totalRefunds' => $totalRefundCents / 100,
            'netSales' => $netSalesCents / 100,
            'paymentTotals' => $paymentTotals,
            'serviceTotals' => $serviceTotals,
            'topProducts' => $topProducts,
        ]);
    }

    private function reportMoneyToCents($amount): int
    {
        $amount = (string) $amount;
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            // SQLite may return a floating aggregate; normalize it to the schema's two-decimal currency scale.
            $amount = number_format((float) $amount, 2, '.', '');
            preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches);
        }

        $whole = (int) $matches[2];
        $fraction = isset($matches[3]) ? (int) str_pad($matches[3], 2, '0') : 0;
        $cents = $whole * 100 + $fraction;

        return isset($matches[1]) && $matches[1] === '-' ? -$cents : $cents;
    }

    public function storeCartItem(Request $request)
    {
        $activeBranch = $this->activeBranch();
        $quantity = $request->input('quantity');

        if (is_string($quantity) && preg_match('/^\d+$/', $quantity)) {
            $request->merge(['quantity' => (int) $quantity]);
        }

        $validated = $request->validate([
            'menu_id' => ['required', 'integer', Rule::exists('menus', 'id')->where('is_active', true)],
            'topping_ids' => ['nullable', 'array'],
            'topping_ids.*' => ['integer', Rule::exists('toppings', 'id')->where('is_active', true)],
            'matcha_type_id' => ['nullable', 'integer', Rule::exists('matcha_types', 'id')->where('is_active', true)],
            'matcha_origin_id' => ['nullable', 'integer', Rule::exists('matcha_types', 'id')->where('is_active', true)],
            'menu_variant_id' => ['nullable', 'integer', Rule::exists('menu_variants', 'id')->where('is_active', true)],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $menu = Menu::query()->with('category')->where('is_active', true)->findOrFail($validated['menu_id']);
        if (! $this->menuAvailableForBranch($menu, $activeBranch)) {
            throw ValidationException::withMessages(['menu_id' => 'Menu ini tidak tersedia di cabang aktif.']);
        }
        $recipeStock = app(RecipeStockService::class);
        if (! $recipeStock->menuRecipeReady($menu)) {
            throw ValidationException::withMessages(['menu_id' => 'Produk belum siap dijual karena resepnya belum lengkap.']);
        }
        $toppings = Topping::query()
            ->where('is_active', true)
            ->whereIn('id', $validated['topping_ids'] ?? [])
            ->orderBy('name')
            ->get();
        $matchaGrade = ! empty($validated['matcha_type_id'])
            ? MatchaType::query()
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereIn('slug', self::MATCHA_GRADE_SLUGS)
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%regular%'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%ceremonial%']);
                })
                ->findOrFail($validated['matcha_type_id'])
            : null;
        $matchaOrigin = ! empty($validated['matcha_origin_id'])
            ? MatchaType::query()
                ->where('is_active', true)
                ->whereIn('slug', self::MATCHA_ORIGIN_SLUGS)
                ->findOrFail($validated['matcha_origin_id'])
            : null;

        $isMatcha = optional($menu->category)->slug === 'matcha';
        $isPastry = optional($menu->category)->slug === 'pastry';
        $menuVariant = ! empty($validated['menu_variant_id'])
            ? MenuVariant::query()->where('is_active', true)->findOrFail($validated['menu_variant_id'])
            : null;

        if ($isMatcha) {
            if (! $matchaGrade) {
                throw ValidationException::withMessages([
                    'matcha_type_id' => 'Grade matcha wajib dipilih.',
                ]);
            }

            if ($this->isCeremonialGrade($matchaGrade) && ! $matchaOrigin) {
                throw ValidationException::withMessages([
                    'matcha_origin_id' => 'Origin wajib dipilih untuk ceremonial grade.',
                ]);
            }

            if (! $this->isCeremonialGrade($matchaGrade) && $matchaOrigin) {
                throw ValidationException::withMessages([
                    'matcha_origin_id' => 'Origin hanya tersedia untuk ceremonial grade.',
                ]);
            }
        } elseif ($matchaGrade || $matchaOrigin) {
            throw ValidationException::withMessages([
                'matcha_type_id' => 'Grade dan origin hanya tersedia untuk menu matcha.',
            ]);
        }

        if ($isPastry && ! empty($validated['topping_ids'])) {
            throw ValidationException::withMessages([
                'topping_ids' => 'Topping tidak tersedia untuk menu pastry.',
            ]);
        }

        if ($isPastry && (! $menuVariant || $menuVariant->menu_id !== $menu->id)) {
            throw ValidationException::withMessages([
                'menu_variant_id' => 'Pilih varian pastry terlebih dahulu.',
            ]);
        }

        if (! $isPastry && $menuVariant) {
            throw ValidationException::withMessages([
                'menu_variant_id' => 'Varian hanya tersedia untuk menu pastry.',
            ]);
        }

        $quantity = (int) $validated['quantity'];
        $unitPrice = $this->menuPriceForBranch($menu, $activeBranch)
            + (float) $toppings->sum('price')
            + (float) optional($matchaGrade)->price_adjustment
            + (float) optional($matchaOrigin)->price_adjustment
            + (float) optional($menuVariant)->price_adjustment;

        $cart = $this->getCart();
        $cartKey = implode('-', [
            $menu->id,
            $toppings->pluck('id')->implode(',') ?: 'no-topping',
            optional($matchaGrade)->id ?: 'no-grade',
            optional($matchaOrigin)->id ?: 'no-origin',
            optional($menuVariant)->id ?: 'no-variant',
        ]);

        $existingIndex = $cart->search(fn (array $item) => ($item['cart_key'] ?? null) === $cartKey);

        $payload = $this->cartPayload($menu, $toppings, $matchaGrade, $matchaOrigin, $menuVariant, $quantity, $unitPrice, $cartKey);
        $payload['recipe_fingerprint'] = $recipeStock->recipeFingerprintForItem($payload);

        if ($existingIndex !== false) {
            $existingItem = $cart->get($existingIndex);
            $payload['quantity'] = (int) $existingItem['quantity'] + $quantity;
            $payload['line_total'] = $payload['unit_price'] * $payload['quantity'];
            $cart->put($existingIndex, $payload);
        } else {
            $cart->push($payload);
        }

        foreach ($cart as $cartItem) {
            $storedRecipe = (string) ($cartItem['recipe_fingerprint'] ?? '');
            if ($storedRecipe === '' || ! hash_equals($storedRecipe, $recipeStock->recipeFingerprintForItem($cartItem))) {
                throw ValidationException::withMessages(['cart' => 'Resep berubah sejak produk masuk ke cart. Hapus item tersebut lalu tambahkan kembali.']);
            }
        }

        $requirements = $recipeStock->requirementsForCart($cart);
        if (! $recipeStock->hasEnoughStock($requirements, $activeBranch)) {
            throw ValidationException::withMessages(['cart' => 'Stok bahan pada cabang aktif tidak cukup untuk jumlah di cart.']);
        }

        $this->saveCart($cart);

        return $this->cartResponse($request, 'Item berhasil ditambahkan ke cart.');
    }

    public function increaseCartItem(Request $request, int $index)
    {
        return $this->updateCartQuantity($request, $index, 1);
    }

    public function decreaseCartItem(Request $request, int $index)
    {
        return $this->updateCartQuantity($request, $index, -1);
    }

    public function removeCartItem(Request $request, int $index)
    {
        $cart = $this->getCart();

        if ($cart->has($index)) {
            $cart->forget($index);
            $this->saveCart($cart);
        }

        return $this->cartResponse($request, 'Item berhasil dihapus dari cart.');
    }

    public function clearCart(Request $request)
    {
        session()->forget('pos_cart');

        return $this->cartResponse($request, 'Cart berhasil dikosongkan.');
    }

    public function checkout(Request $request)
    {
        $cart = $this->getCart();
        $branch = $this->activeBranch();
        $idempotencyKey = trim((string) ($request->header('Idempotency-Key') ?: $request->input('idempotency_key') ?: session('checkout_idempotency_key')));

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            'service_type' => ['required', Rule::in(['dine_in', 'take_away'])],
            'pager_number' => ['nullable', 'string', 'max:30'],
            'table_number' => ['nullable', 'string', 'max:30'],
            'table_id' => ['nullable', 'integer', Rule::exists('dining_tables', 'id')],
            'payment_method' => ['required', Rule::in(['cash', 'qris'])],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ]);
        $validated['coupon_code'] = ! empty($validated['coupon_code']) ? Str::upper(trim($validated['coupon_code'])) : null;

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw ValidationException::withMessages(['idempotency_key' => 'Kunci checkout tidak valid. Muat ulang halaman checkout lalu coba lagi.']);
        }

        $intentCart = $cart->isEmpty()
            ? collect(session('checkout_idempotency_cart', []))
            : $cart;
        $fingerprint = $intentCart->isNotEmpty()
            ? $this->checkoutFingerprint($intentCart, $validated)
            : null;

        $existing = Transaction::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if (! $this->idempotencyMatches($existing, $branch, $fingerprint)) {
                abort(409, 'Permintaan checkout tidak dapat diproses. Muat ulang halaman checkout lalu coba lagi.');
            }

            return $this->checkoutSuccessResponse($request, $existing, $fingerprint, $intentCart->all());
        }

        if ($cart->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Cart masih kosong.']);
        }

        $cart = $this->repriceCart($cart, $branch);

        $hasPastry = $cart->contains(fn (array $item) => ($item['category_slug'] ?? null) === 'pastry');
        $hasBeverage = $cart->contains(fn (array $item) => in_array($item['category_slug'] ?? null, ['matcha', 'non-matcha'], true));
        $requiresTableDelivery = $hasPastry && $validated['service_type'] === 'dine_in';
        $requiresPager = true;

        $selectedTable = null;
        if ($requiresTableDelivery) {
            $selectedTable = ! empty($validated['table_id'])
                ? DiningTable::query()->whereKey($validated['table_id'])->where('branch_id', $branch->id)->where('is_active', true)->first()
                : null;
        }
        if ($requiresTableDelivery && ! $selectedTable) {
            throw ValidationException::withMessages([
                'table_id' => 'Pilih meja aktif pada cabang ini untuk pesanan pastry dine-in.',
            ]);
        }

        $selectedCustomer = null;
        if (! empty($validated['customer_id'])) {
            $selectedCustomer = Customer::query()->whereKey($validated['customer_id'])
                ->where('branch_id', $branch->id)->where('is_active', true)->first();
            if (! $selectedCustomer) {
                throw ValidationException::withMessages(['customer_id' => 'Pelanggan tidak tersedia pada cabang aktif.']);
            }
        }

        if ($requiresPager && empty($validated['pager_number'])) {
            throw ValidationException::withMessages([
                'pager_number' => 'Nomor pager wajib diisi untuk pesanan self service.',
            ]);
        }

        $subtotalCents = (int) round((float) $cart->sum('line_total') * 100);
        $subtotal = $subtotalCents / 100;
        $coupon = null;
        $discountCents = 0;
        if ($validated['coupon_code']) {
            $coupon = Coupon::query()->where('code', $validated['coupon_code'])->first();
            if (! $coupon) {
                throw ValidationException::withMessages(['coupon_code' => 'Kode kupon tidak ditemukan atau tidak berlaku.']);
            }
            $discountCents = $this->couponDiscountCents($coupon, $subtotalCents, now());
        }
        $grandTotalCents = max(0, $subtotalCents - $discountCents);
        $amountPaidCents = $validated['payment_method'] === 'cash'
            ? $this->reportMoneyToCents($validated['cash_amount'] ?? 0)
            : $grandTotalCents;

        if ($validated['payment_method'] === 'cash' && $amountPaidCents < $grandTotalCents) {
            throw ValidationException::withMessages([
                'cash_amount' => 'Nominal uang customer belum cukup setelah diskon kupon.',
            ]);
        }

        $changeCents = max(0, $amountPaidCents - $grandTotalCents);
        $branchId = $branch->getKey();
        $validated['customer_name'] = $selectedCustomer ? $selectedCustomer->name : trim($validated['customer_name']);
        $validated['table_number'] = $selectedTable ? $selectedTable->name : null;
        $fingerprint = $this->checkoutFingerprint($cart, $validated);
        $cartIntent = $this->checkoutCartIntent($cart);

        try {
            $transaction = DB::transaction(function () use ($cart, $subtotalCents, $validated, $requiresTableDelivery, $requiresPager, $branchId, $branch, $idempotencyKey, $fingerprint, $coupon) {
            $customer = null;
            if (! empty($validated['customer_id'])) {
                $customer = Customer::query()->whereKey($validated['customer_id'])->where('branch_id', $branchId)
                    ->where('is_active', true)->lockForUpdate()->first();
                if (! $customer) throw ValidationException::withMessages(['customer_id' => 'Pelanggan tidak tersedia pada cabang aktif.']);
            }
            $table = null;
            if ($requiresTableDelivery) {
                $table = DiningTable::query()->whereKey($validated['table_id'] ?? null)->where('branch_id', $branchId)
                    ->where('is_active', true)->lockForUpdate()->first();
                if (! $table) throw ValidationException::withMessages(['table_id' => 'Meja sudah tidak aktif atau tidak tersedia pada cabang ini.']);
            }
            $lockedCoupon = null;
            $lockedDiscountCents = 0;
            if ($coupon) {
                $lockedCoupon = Coupon::query()->whereKey($coupon->getKey())->lockForUpdate()->first();
                if (! $lockedCoupon || $lockedCoupon->code !== $validated['coupon_code']) {
                    throw ValidationException::withMessages(['coupon_code' => 'Kupon sudah tidak tersedia.']);
                }
                $lockedDiscountCents = $this->couponDiscountCents($lockedCoupon, $subtotalCents, now());
            }
            $lockedGrandTotalCents = max(0, $subtotalCents - $lockedDiscountCents);
            $lockedAmountPaidCents = $validated['payment_method'] === 'cash'
                ? $this->reportMoneyToCents($validated['cash_amount'] ?? 0)
                : $lockedGrandTotalCents;
            if ($lockedAmountPaidCents < $lockedGrandTotalCents) {
                throw ValidationException::withMessages(['cash_amount' => 'Nominal uang customer belum cukup setelah diskon kupon.']);
            }
            $transactionNumber = 'MH-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);

            $transaction = Transaction::create([
                'transaction_number' => $transactionNumber,
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                'idempotency_fingerprint' => $fingerprint,
                'cashier_id' => auth()->id(),
                'branch_id' => $branchId,
                'customer_id' => $validated['customer_id'] ?? null,
                'dining_table_id' => $requiresTableDelivery ? ($validated['table_id'] ?? null) : null,
                'customer_name' => $customer ? $customer->name : $validated['customer_name'],
                'service_type' => $validated['service_type'],
                'fulfillment_type' => $requiresTableDelivery ? 'table_delivery' : 'self_service',
                'pager_number' => $requiresPager ? $validated['pager_number'] : null,
                'table_number' => $requiresTableDelivery ? $table->name : null,
                'subtotal' => $subtotalCents / 100,
                'discount_total' => $lockedDiscountCents / 100,
                'coupon_id' => optional($lockedCoupon)->getKey(),
                'coupon_code' => optional($lockedCoupon)->code,
                'tax_total' => 0,
                'grand_total' => $lockedGrandTotalCents / 100,
                'amount_paid' => $lockedAmountPaidCents / 100,
                'change_amount' => max(0, $lockedAmountPaidCents - $lockedGrandTotalCents) / 100,
                'payment_method' => $validated['payment_method'],
                'status' => 'completed',
                'sold_at' => now(),
            ]);

            foreach ($cart as $item) {
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'menu_id' => $item['menu_id'],
                    'menu_name' => $item['menu_name'],
                    'matcha_type_id' => $item['matcha_type_id'] ?? null,
                    'size_id' => null,
                    'topping_id' => $item['topping_ids'][0] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'menu_price' => $item['menu_price'],
                    'branch_price' => $item['branch_price'],
                    'line_total' => $item['line_total'],
                    'item_options' => $item['item_options'] ?? [],
                ]);
            }

            if ($lockedCoupon) {
                $lockedCoupon->used_count++;
                $lockedCoupon->save();
            }

            $this->deductStockForTransaction($transaction, $cart, $branch);

            return $transaction;
            });
        } catch (QueryException $exception) {
            $existing = Transaction::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing && $this->idempotencyMatches($existing, $branch, $fingerprint)) {
                return $this->checkoutSuccessResponse($request, $existing, $fingerprint, $cartIntent);
            }
            if ($existing) {
                abort(409, 'Permintaan checkout tidak dapat diproses. Muat ulang halaman checkout lalu coba lagi.');
            }

            throw $exception;
        }

        return $this->checkoutSuccessResponse($request, $transaction, $fingerprint, $cartIntent);
    }

    private function updateCartQuantity(Request $request, int $index, int $delta)
    {
        $cart = $this->getCart();

        if ($cart->has($index)) {
            $item = $cart->get($index);
            $item['quantity'] = max(1, (int) $item['quantity'] + $delta);
            $item['line_total'] = (float) $item['unit_price'] * $item['quantity'];
            $cart->put($index, $item);
            $this->saveCart($cart);
        }

        return $this->cartResponse($request, 'Cart berhasil diperbarui.');
    }

    private function catalogData(): array
    {
        $activeBranch = request()->attributes->get('active_branch');
        $categories = Category::query()
            ->where('is_active', true)
            ->with([
                'menus' => function ($query) {
                    $query->where('is_active', true)->with(['variants' => fn ($variantQuery) => $variantQuery->where('is_active', true)->orderBy('name'), 'branches'])->orderBy('name');
                },
            ])
            ->orderBy('sort_order')
            ->get();

        if ($activeBranch instanceof Branch) {
            $categories->each(function (Category $category) use ($activeBranch) {
                $category->setRelation('menus', $category->menus->filter(function (Menu $menu) use ($activeBranch) {
                    if (! $this->menuAvailableForBranch($menu, $activeBranch)) {
                        return false;
                    }

                    $menu->setAttribute('display_price', $this->menuPriceForBranch($menu, $activeBranch));
                    $menu->setAttribute('source_category_slug', $menu->category?->slug);
                    $recipeStock = app(RecipeStockService::class);
                    $availability = $recipeStock->availability($menu, $activeBranch);
                    $menu->setAttribute('recipe_ready', $availability['status'] !== 'recipe_incomplete');
                    $menu->setAttribute('availability_status', $availability['status']);
                    $menu->setAttribute('available_portions', $availability['available_portions']);
                    $menu->setAttribute('limiting_ingredient', $availability['limiting_ingredient']);

                    return true;
                })->values());
            });
            $resolver = app(BranchMenuCategoryCatalog::class);
            $groups = [];
            foreach ($categories as $sourceCategory) {
                foreach ($sourceCategory->menus as $menu) {
                    $resolved = $resolver->resolve($menu, $activeBranch);
                    $groups[$resolved['slug']] ??= (object) ['id' => $resolved['slug'], 'slug' => $resolved['slug'], 'name' => $resolved['name'], 'menus' => collect()];
                    $groups[$resolved['slug']]->menus->push($menu);
                }
            }
            $categories = collect($groups)->values();
        } else {
            $categories = $categories->filter(fn (Category $category) => $category->menus->isNotEmpty())->values();
        }

        $toppings = Topping::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->get();

        $matchaTypes = MatchaType::query()->where('is_active', true)->get();
        $matchaGrades = $matchaTypes
            ->filter(fn (MatchaType $type) => in_array($type->slug, ['regular-grade', 'ceremonial-grade'], true))
            ->sortBy(fn (MatchaType $type) => $type->slug === 'ceremonial-grade' ? 1 : 0)
            ->values();

        if ($matchaGrades->isEmpty()) {
            $matchaGrades = $matchaTypes
            ->filter(fn (MatchaType $type) => in_array($type->slug, self::MATCHA_GRADE_SLUGS, true) || Str::contains(Str::lower($type->name), ['regular', 'ceremonial']))
            ->groupBy(fn (MatchaType $type) => $this->isCeremonialGrade($type) ? 'ceremonial' : 'regular')
            ->map(fn (Collection $types) => $types->first())
            ->sortBy(fn (MatchaType $type) => $this->isCeremonialGrade($type) ? 1 : 0)
            ->values();
        }
        $matchaOrigins = $matchaTypes->filter(fn (MatchaType $type) => in_array($type->slug, self::MATCHA_ORIGIN_SLUGS, true))->sortBy(fn (MatchaType $type) => array_search($type->slug, ['kyoto', 'osaka', 'ginza', 'uji'], true))->values();
        $cart = $this->getCart();
        $cartSubtotal = (float) $cart->sum('line_total');
        $cartItemCount = (int) $cart->sum('quantity');

        return [
            'categories' => $categories,
            'toppings' => $toppings,
            'matchaGrades' => $matchaGrades,
            'matchaOrigins' => $matchaOrigins,
            'cart' => $cart->values(),
            'cartState' => $this->cartState($cart),
            'activeMenuCount' => $categories->sum(fn ($category) => $category->menus->count()),
            'cartSubtotal' => $cartSubtotal,
            'cartItemCount' => $cartItemCount,
            'cashAmount' => old('cash_amount', session('cash_amount')),
            'changeAmount' => 0,
            'ceremonialGradeId' => optional($matchaGrades->first(fn (MatchaType $type) => $this->isCeremonialGrade($type)))->id,
        ];
    }

    private function checkoutData(): array
    {
        $data = $this->catalogData();
        $data['cashChange'] = $this->cashChange((float) ($data['cashAmount'] ?? 0), $data['cartSubtotal']);

        return $data;
    }

    private function historyData(Request $request): array
    {
        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $transactionsQuery = Transaction::query()
            ->with(['branch', 'items.menu', 'cashier'])
            ->withCount('items')
            ->orderByDesc('sold_at')->orderByDesc('id');

        $activeBranch = request()->attributes->get('active_branch');
        $user = auth()->user();
        $canViewRefunds = $user->hasAnyRole([User::ROLE_OWNER, User::ROLE_MANAGER]);

        if ($user->isOwner()) {
            // Owners can inspect mapped and unmapped historical transactions, including inactive branches.
            if (! empty($filters['branch_id'])) $transactionsQuery->where('branch_id', $filters['branch_id']);
        } elseif ($activeBranch instanceof Branch) {
            if (! empty($filters['branch_id']) && (int) $filters['branch_id'] !== (int) $activeBranch->getKey()) abort(403);
            $transactionsQuery->where('branch_id', $activeBranch->getKey());
        } else {
            abort(403);
        }
        if (! empty($filters['date_from'])) $transactionsQuery->whereDate('sold_at', '>=', $filters['date_from']);
        if (! empty($filters['date_to'])) $transactionsQuery->whereDate('sold_at', '<=', $filters['date_to']);

        if ($canViewRefunds) {
            $transactionsQuery->with(['items', 'refunds.items.transactionItem', 'refunds.recorder', 'refunds.confirmer']);
        }

        $transactions = $transactionsQuery->paginate(10)->withQueryString();
        $refundSummaries = [];
        if ($canViewRefunds) {
            $refundService = app(RefundService::class);
            foreach ($transactions as $transaction) {
                $refundSummaries[$transaction->getKey()] = $refundService->summaryFor($transaction, $user);
            }
        }

        $branches = $user->isOwner() ? Branch::query()->orderBy('name')->get() : collect();
        return compact('transactions', 'refundSummaries', 'canViewRefunds', 'filters', 'branches');
    }

    private function transactionForCurrentScope(Transaction $transaction): Transaction
    {
        $user = auth()->user();
        if ($user->isOwner()) {
            return $transaction;
        }

        $query = Transaction::query()->whereKey($transaction->getKey());
        $activeBranch = request()->attributes->get('active_branch');

        if ($activeBranch instanceof Branch) {
            $query->where('branch_id', $activeBranch->getKey());
        } else {
            abort(403);
        }

        return $query->firstOrFail();
    }

    private function activeBranch(): Branch
    {
        $branch = request()->attributes->get('active_branch');

        abort_unless($branch instanceof Branch, 409, 'Cabang aktif belum dipilih.');

        return $branch;
    }

    private function menuAvailableForBranch(Menu $menu, Branch $branch): bool
    {
        $setting = $menu->relationLoaded('branches')
            ? $menu->branches->firstWhere('id', $branch->getKey())
            : $menu->branches()->whereKey($branch->getKey())->first();

        return $setting && (bool) $setting->pivot->is_available;
    }

    private function menuPriceForBranch(Menu $menu, Branch $branch): float
    {
        $setting = $menu->relationLoaded('branches')
            ? $menu->branches->firstWhere('id', $branch->getKey())
            : $menu->branches()->whereKey($branch->getKey())->first();

        return $setting && $setting->pivot->price !== null
            ? (float) $setting->pivot->price
            : (float) $menu->price;
    }

    private function repriceCart(Collection $cart, Branch $branch): Collection
    {
        return $cart->values()->map(function (array $item) use ($branch) {
            $menu = Menu::query()->with('category')->where('is_active', true)->find($item['menu_id'] ?? null);
            if (! $menu || ! $this->menuAvailableForBranch($menu, $branch)) {
                throw ValidationException::withMessages(['cart' => 'Salah satu menu sudah tidak tersedia di cabang aktif.']);
            }

            $toppingIds = array_values(array_unique(array_map('intval', $item['topping_ids'] ?? [])));
            $toppings = Topping::query()->where('is_active', true)->whereIn('id', $toppingIds)->get();
            if ($toppings->count() !== count($toppingIds)) {
                throw ValidationException::withMessages(['cart' => 'Salah satu topping sudah tidak tersedia.']);
            }

            $grade = ! empty($item['matcha_type_id']) ? MatchaType::query()->where('is_active', true)->where(function ($query) {
                $query->whereIn('slug', self::MATCHA_GRADE_SLUGS)
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%regular%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%ceremonial%']);
            })->find($item['matcha_type_id']) : null;
            $origin = ! empty($item['matcha_origin_id']) ? MatchaType::query()->where('is_active', true)->whereIn('slug', self::MATCHA_ORIGIN_SLUGS)->find($item['matcha_origin_id']) : null;
            $variant = ! empty($item['menu_variant_id']) ? MenuVariant::query()->where('is_active', true)->where('menu_id', $menu->id)->find($item['menu_variant_id']) : null;
            $isMatcha = optional($menu->category)->slug === 'matcha';
            $isPastry = optional($menu->category)->slug === 'pastry';

            if ($isMatcha && ! $grade) {
                throw ValidationException::withMessages(['cart' => 'Grade matcha pada cart sudah tidak valid.']);
            }
            if ($isMatcha && $this->isCeremonialGrade($grade) && ! $origin) {
                throw ValidationException::withMessages(['cart' => 'Origin matcha pada cart sudah tidak valid.']);
            }
            if ($isMatcha && ! $this->isCeremonialGrade($grade) && $origin) {
                throw ValidationException::withMessages(['cart' => 'Origin hanya tersedia untuk ceremonial grade.']);
            }
            if (! $isMatcha && ($grade || $origin)) {
                throw ValidationException::withMessages(['cart' => 'Opsi matcha tidak valid untuk menu ini.']);
            }
            if ($isPastry && (! $variant || $variant->menu_id !== $menu->id)) {
                throw ValidationException::withMessages(['cart' => 'Varian pastry pada cart sudah tidak tersedia.']);
            }
            if (! $isPastry && $variant) {
                throw ValidationException::withMessages(['cart' => 'Varian tidak valid untuk menu ini.']);
            }

            $quantity = max(1, min(99, (int) ($item['quantity'] ?? 1)));
            $menuPrice = $this->menuPriceForBranch($menu, $branch);
            $unitPrice = $menuPrice + (float) $toppings->sum('price') + (float) optional($grade)->price_adjustment + (float) optional($origin)->price_adjustment + (float) optional($variant)->price_adjustment;
            $item['menu_name'] = $menu->name;
            $item['menu_price'] = (float) $menu->price;
            $item['branch_price'] = $menuPrice;
            $item['topping_names'] = $toppings->pluck('name')->values()->all();
            $item['matcha_grade_name'] = optional($grade)->name;
            $item['matcha_origin_name'] = optional($origin)->name;
            $item['menu_variant_name'] = optional($variant)->name;
            $item['quantity'] = $quantity;
            $item['unit_price'] = $unitPrice;
            $item['line_total'] = $unitPrice * $quantity;
            $item['item_options'] = [
                'menu_name' => $menu->name,
                'category' => optional($menu->category)->name,
                'toppings' => $item['topping_names'],
                'matcha_grade' => optional($grade)->name,
                'matcha_origin' => optional($origin)->name,
                'variant' => optional($variant)->name,
                'matcha_grade_price' => (float) optional($grade)->price_adjustment,
                'matcha_origin_price' => (float) optional($origin)->price_adjustment,
                'variant_price' => (float) optional($variant)->price_adjustment,
                'menu_price' => (float) $menu->price,
                'branch_price' => $menuPrice,
                'topping_total' => (float) $toppings->sum('price'),
            ];

            return $item;
        });
    }

    private function cartState(Collection $cart): array
    {
        $items = $cart->values()->map(function (array $item, int $index) {
            return [
                'index' => $index,
                'cart_key' => $item['cart_key'] ?? null,
                'menu_name' => $item['menu_name'] ?? '-',
                'category_slug' => $item['category_slug'] ?? null,
                'matcha_grade_name' => $item['matcha_grade_name'] ?? null,
                'matcha_origin_name' => $item['matcha_origin_name'] ?? null,
                'menu_variant_name' => $item['menu_variant_name'] ?? null,
                'topping_names' => $item['topping_names'] ?? [],
                'quantity' => (int) ($item['quantity'] ?? 0),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'line_total' => (float) ($item['line_total'] ?? 0),
            ];
        })->all();

        return [
            'items' => $items,
            'subtotal' => (float) $cart->sum('line_total'),
            'item_count' => (int) $cart->sum('quantity'),
            'empty' => $cart->isEmpty(),
            'message' => session('success'),
        ];
    }

    private function cartResponse(Request $request, string $message)
    {
        $cart = $this->getCart();
        $state = $this->cartState($cart);
        $state['message'] = $message;

        if ($request->expectsJson()) {
            return response()->json($state);
        }

        session()->flash('success', $message);

        return redirect()->route('dashboard');
    }

    private function cartPayload(
        Menu $menu,
        Collection $toppings,
        ?MatchaType $matchaGrade,
        ?MatchaType $matchaOrigin,
        ?MenuVariant $menuVariant,
        int $quantity,
        float $unitPrice,
        string $cartKey
    ): array {
        return [
            'cart_key' => $cartKey,
            'menu_id' => $menu->id,
            'menu_name' => $menu->name,
            'category_slug' => optional($menu->category)->slug,
            'menu_price' => (float) $menu->price,
            'topping_ids' => $toppings->pluck('id')->values()->all(),
            'topping_names' => $toppings->pluck('name')->values()->all(),
            'topping_price' => (float) $toppings->sum('price'),
            'matcha_type_id' => optional($matchaGrade)->id,
            'matcha_grade_name' => optional($matchaGrade)->name,
            'matcha_grade_slug' => optional($matchaGrade)->slug,
            'matcha_grade_price' => (float) optional($matchaGrade)->price_adjustment,
            'matcha_origin_id' => optional($matchaOrigin)->id,
            'matcha_origin_name' => optional($matchaOrigin)->name,
            'matcha_origin_slug' => optional($matchaOrigin)->slug,
            'matcha_origin_price' => (float) optional($matchaOrigin)->price_adjustment,
            'menu_variant_id' => optional($menuVariant)->id,
            'menu_variant_name' => optional($menuVariant)->name,
            'menu_variant_price' => (float) optional($menuVariant)->price_adjustment,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice * $quantity,
            'item_options' => [
                'toppings' => $toppings->pluck('name')->values()->all(),
                'matcha_grade' => optional($matchaGrade)->name,
                'matcha_origin' => optional($matchaOrigin)->name,
                'variant' => optional($menuVariant)->name,
            ],
        ];
    }

    private function getCart(): Collection
    {
        return collect(session()->get('pos_cart', []));
    }

    private function saveCart(Collection $cart): void
    {
        session()->put('pos_cart', $cart->values()->all());
        session()->forget('checkout_idempotency_key');
        session()->forget(['checkout_idempotency_fingerprint', 'checkout_idempotency_cart']);
    }

    private function checkoutSuccessResponse(Request $request, Transaction $transaction, ?string $fingerprint = null, array $cartIntent = [])
    {
        session()->forget('pos_cart');
        if ($fingerprint !== null) {
            session()->put('checkout_idempotency_fingerprint', $fingerprint);
            session()->put('checkout_idempotency_cart', $cartIntent);
        }
        session()->put('latest_transaction_id', $transaction->id);
        session()->flash('success', 'Pembayaran berhasil disimpan.');

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'redirect' => route('receipt.show', $transaction),
                'transaction_number' => $transaction->transaction_number,
            ]);
        }

        return redirect()->route('receipt.show', $transaction);
    }

    private function idempotencyMatches(Transaction $transaction, Branch $branch, ?string $fingerprint): bool
    {
        return (int) $transaction->cashier_id === (int) auth()->id()
            && (int) $transaction->branch_id === (int) $branch->getKey()
            && $fingerprint !== null
            && is_string($transaction->idempotency_fingerprint)
            && hash_equals($transaction->idempotency_fingerprint, $fingerprint);
    }

    private function checkoutFingerprint(Collection $cart, array $validated): string
    {
        $canonical = [
            'cart' => $this->checkoutCartIntent($cart),
            'payment' => [
                'customer_name' => empty($validated['customer_id']) ? trim($validated['customer_name']) : null,
                'customer_id' => isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
                'service_type' => $validated['service_type'],
                'pager_number' => $validated['pager_number'] ?? null,
                'table_id' => isset($validated['table_id']) ? (int) $validated['table_id'] : null,
                'payment_method' => $validated['payment_method'],
                'coupon_code' => $validated['coupon_code'] ?? null,
                'cash_amount' => isset($validated['cash_amount']) ? number_format((float) $validated['cash_amount'], 2, '.', '') : null,
            ],
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function couponDiscountCents(Coupon $coupon, int $subtotalCents, Carbon $at): int
    {
        $valid = $coupon->is_active
            && $coupon->starts_at && $coupon->starts_at->lte($at)
            && $coupon->ends_at && $coupon->ends_at->gte($at)
            && ($coupon->usage_limit === null || $coupon->used_count < $coupon->usage_limit);
        if (! $valid) {
            throw ValidationException::withMessages(['coupon_code' => 'Kupon belum berlaku, sudah kedaluwarsa, nonaktif, atau batas penggunaannya tercapai.']);
        }

        $value = (float) $coupon->discount_value;
        $discountCents = $coupon->discount_type === 'percentage'
            ? (int) round($subtotalCents * ($value / 100), 0, PHP_ROUND_HALF_UP)
            : $this->reportMoneyToCents($coupon->discount_value);

        return min($subtotalCents, max(0, $discountCents));
    }

    private function checkoutCartIntent(Collection $cart): array
    {
        return $cart->values()->map(function (array $item) {
            $toppingIds = array_values(array_unique(array_map('intval', $item['topping_ids'] ?? [])));
            sort($toppingIds);

            return [
                'menu_id' => (int) ($item['menu_id'] ?? 0),
                'menu_variant_id' => isset($item['menu_variant_id']) ? (int) $item['menu_variant_id'] : null,
                'size_id' => isset($item['size_id']) ? (int) $item['size_id'] : null,
                'topping_ids' => $toppingIds,
                'matcha_type_id' => isset($item['matcha_type_id']) ? (int) $item['matcha_type_id'] : null,
                'matcha_origin_id' => isset($item['matcha_origin_id']) ? (int) $item['matcha_origin_id'] : null,
                'quantity' => (int) ($item['quantity'] ?? 0),
                'recipe_fingerprint' => (string) ($item['recipe_fingerprint'] ?? ''),
            ];
        })->all();
    }

    private function deductStockForTransaction(Transaction $transaction, Collection $cart, Branch $branch): void
    {
        $recipeStock = app(RecipeStockService::class);
        $requirements = $recipeStock->requirementsForCart($cart);
        foreach ($cart as $item) {
            $storedRecipe = (string) ($item['recipe_fingerprint'] ?? '');
            if ($storedRecipe === '' || ! hash_equals($storedRecipe, $recipeStock->recipeFingerprintForItem($item))) {
                throw ValidationException::withMessages(['cart' => 'Resep berubah sejak produk masuk ke cart. Hapus item tersebut lalu tambahkan kembali.']);
            }
        }
        if ($requirements->isEmpty()) {
            return;
        }

        // Serialize first-use stock creation for this branch when a stock row is absent.
        Branch::query()->whereKey($branch->getKey())->lockForUpdate()->firstOrFail();

        foreach ($requirements as $requirement) {
            $referenceKey = 'sale:'.$transaction->getKey().':'.$requirement['ingredient']->getKey();
            if (StockMovement::query()->where('reference_key', $referenceKey)->exists()) {
                continue;
            }

            $stock = BranchStock::query()
                ->where('branch_id', $branch->getKey())
                ->where('ingredient_id', $requirement['ingredient']->getKey())
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                $stock = BranchStock::create([
                    'branch_id' => $branch->getKey(),
                    'ingredient_id' => $requirement['ingredient']->getKey(),
                    'quantity' => 0,
                ]);
            }

            $stockScale = app(RecipeStockService::class);
            $beforeMilli = $stockScale->toMilli((string) $stock->quantity);
            $neededMilli = (int) $requirement['quantity_milli'];
            $afterMilli = $beforeMilli - $neededMilli;

            if ($afterMilli < 0) {
                throw ValidationException::withMessages([
                    'cart' => 'Stok '.$requirement['ingredient']->name.' tidak mencukupi. Tersedia '.$stockScale->formatMilli($beforeMilli).' '.$requirement['ingredient']->unit.', diperlukan '.$requirement['quantity'].' '.$requirement['ingredient']->unit.'.',
                ]);
            }
            $before = $stockScale->formatMilli($beforeMilli);
            $needed = $stockScale->formatMilli($neededMilli);
            $after = $stockScale->formatMilli($afterMilli);

            $stock->update(['quantity' => $after]);
            StockMovement::create([
                'branch_id' => $branch->getKey(),
                'ingredient_id' => $requirement['ingredient']->getKey(),
                'user_id' => auth()->id(),
                'transaction_id' => $transaction->getKey(),
                'reference_key' => $referenceKey,
                'type' => 'sale',
                'quantity_delta' => -$needed,
                'quantity_before' => $before,
                'quantity_after' => $after,
                'reason' => 'Pemakaian resep transaksi '.$transaction->transaction_number,
            ]);
        }
    }

    private function isCeremonialGrade(?MatchaType $matchaGrade): bool
    {
        return $matchaGrade
            ? Str::contains(Str::lower($matchaGrade->slug . ' ' . $matchaGrade->name), 'ceremonial')
            : false;
    }

    private function cashChange(float $cashAmount, float $subtotal): float
    {
        return is_numeric($cashAmount) ? max(0, $cashAmount - $subtotal) : 0;
    }
}
