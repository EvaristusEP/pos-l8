<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\MatchaType;
use App\Models\Menu;
use App\Models\MenuVariant;
use App\Models\Topping;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function showCheckout()
    {
        return view('checkout', $this->checkoutData());
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
        $transaction->load(['items.menu', 'items.topping', 'items.matchaType', 'cashier']);

        return view('receipt', compact('transaction'));
    }

    public function history()
    {
        return view('history', array_merge($this->historyData(), [
            'selectedTransaction' => null,
        ]));
    }

    public function historyShow(Transaction $transaction)
    {
        $transaction->load(['items.menu', 'items.size', 'items.topping', 'items.matchaType', 'cashier']);

        return view('history', array_merge($this->historyData(), [
            'selectedTransaction' => $transaction,
        ]));
    }

    public function salesReport(Request $request)
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $selectedDate = $validated['date'] ?? today()->toDateString();
        $startOfDay = Carbon::createFromFormat('Y-m-d', $selectedDate)->startOfDay();
        $endOfDay = (clone $startOfDay)->endOfDay();

        $reportQuery = Transaction::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$startOfDay, $endOfDay]);

        $summary = (clone $reportQuery)
            ->selectRaw('COUNT(*) as total_transactions, COALESCE(SUM(grand_total), 0) as total_sales')
            ->first();

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

        $totalItemsSold = (int) TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.sold_at', [$startOfDay, $endOfDay])
            ->sum('transaction_items.quantity');

        $topProducts = TransactionItem::query()
            ->selectRaw('transaction_items.menu_id, SUM(transaction_items.quantity) as quantity_sold')
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.sold_at', [$startOfDay, $endOfDay])
            ->with('menu:id,name')
            ->groupBy('transaction_items.menu_id')
            ->orderByDesc('quantity_sold')
            ->get();

        $transactions = (clone $reportQuery)
            ->orderByDesc('sold_at')
            ->get();

        return view('reports.sales', [
            'selectedDate' => $selectedDate,
            'transactions' => $transactions,
            'totalTransactions' => (int) $summary->total_transactions,
            'totalItemsSold' => $totalItemsSold,
            'totalSales' => (float) $summary->total_sales,
            'paymentTotals' => $paymentTotals,
            'serviceTotals' => $serviceTotals,
            'topProducts' => $topProducts,
        ]);
    }

    public function storeCartItem(Request $request)
    {
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
        $unitPrice = (float) $menu->price
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

        if ($existingIndex !== false) {
            $existingItem = $cart->get($existingIndex);
            $payload['quantity'] = (int) $existingItem['quantity'] + $quantity;
            $payload['line_total'] = $payload['unit_price'] * $payload['quantity'];
            $cart->put($existingIndex, $payload);
        } else {
            $cart->push($payload);
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

        if ($cart->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Cart masih kosong.',
            ]);
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'service_type' => ['required', Rule::in(['dine_in', 'take_away'])],
            'pager_number' => ['nullable', 'string', 'max:30'],
            'table_number' => ['nullable', 'string', 'max:30'],
            'payment_method' => ['required', Rule::in(['cash', 'qris'])],
            'cash_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $hasPastry = $cart->contains(fn (array $item) => ($item['category_slug'] ?? null) === 'pastry');
        $hasBeverage = $cart->contains(fn (array $item) => in_array($item['category_slug'] ?? null, ['matcha', 'non-matcha'], true));
        $requiresTableDelivery = $hasPastry && $validated['service_type'] === 'dine_in';
        $requiresPager = true;

        if ($requiresTableDelivery && empty($validated['table_number'])) {
            throw ValidationException::withMessages([
                'table_number' => 'Nomor meja wajib diisi untuk pesanan pastry dine-in.',
            ]);
        }

        if ($requiresPager && empty($validated['pager_number'])) {
            throw ValidationException::withMessages([
                'pager_number' => 'Nomor pager wajib diisi untuk pesanan self service.',
            ]);
        }

        $subtotal = (float) $cart->sum('line_total');
        $amountPaid = $validated['payment_method'] === 'cash'
            ? (float) ($validated['cash_amount'] ?? 0)
            : $subtotal;

        if ($validated['payment_method'] === 'cash' && $amountPaid < $subtotal) {
            throw ValidationException::withMessages([
                'cash_amount' => 'Nominal uang customer belum cukup.',
            ]);
        }

        $changeAmount = max(0, $amountPaid - $subtotal);

        $transaction = DB::transaction(function () use ($cart, $subtotal, $validated, $amountPaid, $changeAmount, $requiresTableDelivery, $requiresPager) {
            $transactionNumber = 'MH-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);

            $transaction = Transaction::create([
                'transaction_number' => $transactionNumber,
                'cashier_id' => auth()->id(),
                'customer_name' => $validated['customer_name'],
                'service_type' => $validated['service_type'],
                'fulfillment_type' => $requiresTableDelivery ? 'table_delivery' : 'self_service',
                'pager_number' => $requiresPager ? $validated['pager_number'] : null,
                'table_number' => $requiresTableDelivery ? $validated['table_number'] : null,
                'subtotal' => $subtotal,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => $subtotal,
                'amount_paid' => $amountPaid,
                'change_amount' => $changeAmount,
                'payment_method' => $validated['payment_method'],
                'status' => 'completed',
                'sold_at' => now(),
            ]);

            foreach ($cart as $item) {
                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'menu_id' => $item['menu_id'],
                    'matcha_type_id' => $item['matcha_type_id'] ?? null,
                    'size_id' => null,
                    'topping_id' => $item['topping_ids'][0] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'item_options' => $item['item_options'] ?? [],
                ]);
            }

            return $transaction;
        });

        session()->forget('pos_cart');
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
        $categories = Category::query()
            ->where('is_active', true)
            ->whereIn('slug', ['matcha', 'non-matcha', 'pastry'])
            ->with([
                'menus' => function ($query) {
                    $query->where('is_active', true)->with(['variants' => fn ($variantQuery) => $variantQuery->where('is_active', true)->orderBy('name')])->orderBy('name');
                },
            ])
            ->orderBy('sort_order')
            ->get();

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
            'activeMenuCount' => $categories->sum(fn (Category $category) => $category->menus->count()),
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

    private function historyData(): array
    {
        $transactions = Transaction::query()
            ->with(['items.menu', 'cashier'])
            ->withCount('items')
            ->orderByDesc('sold_at')
            ->paginate(10);

        return compact('transactions');
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
