<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Transaction;
use App\Services\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleDashboardController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        if ($request->user()->isOwner()) {
            return redirect()->route('dashboard.owner');
        }

        if ($request->user()->isInventory()) {
            return redirect()->route('inventory.stocks.index');
        }

        if ($request->user()->isManager() || $request->user()->isCashier()) {
            if (! app(BranchContext::class)->activeFor($request->user())) {
                return redirect()->route('branches.select');
            }

            return redirect()->route($request->user()->isManager() ? 'dashboard.manager' : 'dashboard.cashier');
        }

        abort(403);
    }

    public function manager(Request $request): View
    {
        $branch = $request->attributes->get('active_branch');
        abort_unless($branch instanceof Branch, 409, 'Cabang aktif belum dipilih.');

        $sales = Transaction::query()
            ->where('branch_id', $branch->getKey())
            ->where('status', 'completed')
            ->selectRaw('COUNT(*) as total_transactions, COALESCE(SUM(grand_total), 0) as total_sales')
            ->first();
        $lowStockCount = BranchStock::query()
            ->where('branch_id', $branch->getKey())
            ->with('ingredient')
            ->get()
            ->filter(fn (BranchStock $stock) => $stock->ingredient && $stock->ingredient->is_active && (float) $stock->quantity <= (float) $stock->ingredient->minimum_stock)
            ->count();

        return view('dashboard.manager', [
            'branch' => $branch,
            'totalTransactions' => (int) $sales->total_transactions,
            'totalSales' => (float) $sales->total_sales,
            'lowStockCount' => $lowStockCount,
        ]);
    }

    public function cashier(Request $request): View
    {
        $branch = $request->attributes->get('active_branch');
        abort_unless($branch instanceof Branch, 409, 'Cabang aktif belum dipilih.');

        return view('dashboard.cashier', compact('branch'));
    }
}
