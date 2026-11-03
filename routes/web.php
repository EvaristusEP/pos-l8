<?php

use App\Http\Controllers\ActiveBranchController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleDashboardController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\MenuManagementController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\AddonController;
use App\Http\Controllers\OperationalReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\DiningTableController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\InvoiceEmailController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('active.user')->group(function () {

    Route::middleware(['role:owner,manager,cashier', 'active.branch'])->prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/{transaction}', [OrderController::class, 'show'])->name('show');
        Route::post('/{transaction}/invoice-email', [InvoiceEmailController::class, 'send'])->name('invoice-email');
    });

    Route::middleware('role:owner')->group(function () {
        Route::get('/coupons', [CouponController::class, 'index'])->name('coupons.index');
        Route::post('/coupons', [CouponController::class, 'store'])->name('coupons.store');
        Route::put('/coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');
        Route::patch('/coupons/{coupon}/toggle-status', [CouponController::class, 'toggleStatus'])->name('coupons.toggle-status');
        Route::delete('/coupons/{coupon}', [CouponController::class, 'destroy'])->name('coupons.destroy');
        Route::get('/settings/system', [SystemSettingsController::class, 'show'])->name('settings.system');
        Route::post('/settings/system/locale', [SystemSettingsController::class, 'updateLocale'])->name('settings.system.locale');
    });

    Route::middleware('role:owner,admin,manager,cashier,inventory')->get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::middleware('role:owner,manager,cashier,inventory')->prefix('attendance')->name('attendance.')->group(function () {
        Route::post('/check-in', [AttendanceController::class, 'checkIn'])->name('check-in');
        Route::post('/check-out', [AttendanceController::class, 'checkOut'])->name('check-out');
    });
    Route::middleware('role:owner,admin,manager')->get('/reports/attendance', [AttendanceController::class, 'report'])->name('reports.attendance');

    Route::middleware('role:owner,manager')->prefix('transactions/{transaction}/refunds')->name('transactions.refunds.')->group(function () {
        Route::get('/', [RefundController::class, 'index'])->name('index');
        Route::post('/', [RefundController::class, 'store'])->name('store');
        Route::patch('/{refund}/confirm-manual', [RefundController::class, 'confirmManual'])->name('confirm-manual');
    });

    Route::get('/branches/select', [ActiveBranchController::class, 'select'])->name('branches.select');
    Route::post('/branches/select', [ActiveBranchController::class, 'update'])->name('branches.select.update');

    Route::get('/dashboard', [RoleDashboardController::class, 'index'])->name('dashboard');

    Route::middleware('role:owner')->group(function () {
        Route::get('/dashboard/owner', [DashboardController::class, 'ownerDashboard'])->name('dashboard.owner');
    });

    Route::get('/dashboard/manager', [RoleDashboardController::class, 'manager'])
        ->middleware(['role:manager', 'active.branch'])
        ->name('dashboard.manager');

    Route::get('/dashboard/cashier', [RoleDashboardController::class, 'cashier'])
        ->middleware(['role:cashier', 'active.branch'])
        ->name('dashboard.cashier');

    Route::middleware('role:owner')->prefix('branches')->name('branches.')->group(function () {
        Route::get('/', [BranchController::class, 'index'])->name('index');
        Route::post('/', [BranchController::class, 'store'])->name('store');
        Route::get('/{branch}/edit', [BranchController::class, 'edit'])->name('edit');
        Route::put('/{branch}', [BranchController::class, 'update'])->name('update');
        Route::patch('/{branch}/toggle-status', [BranchController::class, 'toggleStatus'])->name('toggle-status');
    });

    Route::middleware(['role:owner,manager,cashier', 'active.branch'])->group(function () {
        Route::get('/dashboard/pos', [DashboardController::class, 'index'])->name('dashboard.pos');

        Route::post('/dashboard/cart', [DashboardController::class, 'storeCartItem'])->name('dashboard.cart.store');
        Route::patch('/dashboard/cart/{index}/increase', [DashboardController::class, 'increaseCartItem'])->name('dashboard.cart.increase');
        Route::patch('/dashboard/cart/{index}/decrease', [DashboardController::class, 'decreaseCartItem'])->name('dashboard.cart.decrease');
        Route::delete('/dashboard/cart/{index}', [DashboardController::class, 'removeCartItem'])->name('dashboard.cart.remove');
        Route::delete('/dashboard/cart', [DashboardController::class, 'clearCart'])->name('dashboard.cart.clear');

        Route::get('/checkout', [DashboardController::class, 'showCheckout'])->name('checkout');
        Route::post('/checkout', [DashboardController::class, 'checkout'])->name('checkout.store');
    });

    Route::middleware(['role:owner,manager,cashier', 'active.branch'])->group(function () {
        Route::get('/receipt', [DashboardController::class, 'latestReceipt'])->name('receipt');
        Route::get('/receipt/{transaction}', [DashboardController::class, 'receipt'])->name('receipt.show');

        Route::get('/history', [DashboardController::class, 'history'])->name('history');
        Route::get('/history/{transaction}', [DashboardController::class, 'historyShow'])->name('history.show');
    });

    Route::middleware(['role:owner,manager', 'active.branch'])->group(function () {
        Route::get('/reports', [DashboardController::class, 'salesReport'])->name('reports.sales');
        Route::get('/reports/payment-methods', [OperationalReportController::class, 'paymentMethods'])->name('reports.payment-methods');
        Route::get('/reports/refunds', [OperationalReportController::class, 'refunds'])->name('reports.refunds');
    });

    Route::get('/dashboard/inventory', [InventoryController::class, 'index'])
        ->middleware('role:owner,manager,inventory')
        ->name('dashboard.inventory');

    Route::middleware('role:owner,manager,inventory')->prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::get('/stock-in', [InventoryController::class, 'stockInPage'])->name('stock-in.index');
        Route::get('/branches', [InventoryController::class, 'branchStocks'])->name('stocks.index');
        Route::get('/movements', [InventoryController::class, 'movementHistory'])->name('movements.index');
        Route::get('/receipts/{receipt}', [InventoryController::class, 'receipt'])->name('receipts.show');
        Route::post('/stock-in', [InventoryController::class, 'stockIn'])->name('stock-in');
        Route::post('/adjust', [InventoryController::class, 'adjust'])->name('adjust');
    });

    Route::middleware('role:owner')->prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/materials', [InventoryController::class, 'materials'])->name('materials.index');
        Route::post('/ingredients', [InventoryController::class, 'storeIngredient'])->name('ingredients.store');
        Route::get('/ingredients/{ingredient}/edit', [InventoryController::class, 'editIngredient'])->name('ingredients.edit');
        Route::put('/ingredients/{ingredient}', [InventoryController::class, 'updateIngredient'])->name('ingredients.update');
        Route::patch('/ingredients/{ingredient}/toggle-status', [InventoryController::class, 'toggleIngredient'])->name('ingredients.toggle-status');
    });

    Route::middleware('role:owner,manager,inventory')->get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::middleware('role:owner')->prefix('suppliers')->name('suppliers.')->group(function () {
        Route::post('/', [SupplierController::class, 'store'])->name('store');
        Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->name('edit');
        Route::put('/{supplier}', [SupplierController::class, 'update'])->name('update');
        Route::patch('/{supplier}/toggle-status', [SupplierController::class, 'toggleStatus'])->name('toggle-status');
    });

    Route::middleware('role:owner')->prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::patch('/{user}/toggle-status', [UserManagementController::class, 'toggleStatus'])->name('toggle-status');
    });

    Route::middleware('role:owner')->prefix('master-menu')->name('master-menu.')->group(function () {
        Route::get('/', [MenuManagementController::class, 'index'])->name('index');
        Route::post('/', [MenuManagementController::class, 'store'])->name('store');
        Route::get('/{menu}/edit', [MenuManagementController::class, 'edit'])->name('edit');
        Route::put('/{menu}', [MenuManagementController::class, 'update'])->name('update');
        Route::patch('/{menu}/toggle-status', [MenuManagementController::class, 'toggleStatus'])->name('toggle-status');
    });

    Route::middleware('role:owner')->prefix('addons')->name('addons.')->group(function () {
        Route::get('/', [AddonController::class, 'index'])->name('index');
        Route::post('/', [AddonController::class, 'store'])->name('store');
        Route::get('/{topping}/edit', [AddonController::class, 'edit'])->name('edit');
        Route::put('/{topping}', [AddonController::class, 'update'])->name('update');
        Route::patch('/{topping}/toggle-status', [AddonController::class, 'toggleStatus'])->name('toggle-status');
    });

    Route::middleware('role:owner,manager')->prefix('tables')->name('tables.')->group(function () {
        Route::get('/', [DiningTableController::class, 'index'])->name('index');
        Route::post('/', [DiningTableController::class, 'store'])->name('store');
        Route::put('/{table}', [DiningTableController::class, 'update'])->name('update');
        Route::patch('/{table}/toggle-status', [DiningTableController::class, 'toggleStatus'])->name('toggle-status');
    });

    Route::middleware('role:owner,manager')->prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
        Route::get('/{customer}/edit', [CustomerController::class, 'edit'])->name('edit');
        Route::put('/{customer}', [CustomerController::class, 'update'])->name('update');
        Route::patch('/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('toggle-status');
    });

    Route::middleware('role:owner')->prefix('tags')->name('tags.')->group(function () {
        Route::get('/', [TagController::class, 'index'])->name('index');
        Route::post('/', [TagController::class, 'store'])->name('store');
        Route::put('/{tag}', [TagController::class, 'update'])->name('update');
        Route::patch('/{tag}/toggle-status', [TagController::class, 'toggleStatus'])->name('toggle-status');
    });
    });

    Route::middleware('role:owner')->prefix('recipes')->name('recipes.')->group(function () {
        Route::get('/', [RecipeController::class, 'index'])->name('index');
        Route::post('/', [RecipeController::class, 'store'])->name('store');
        Route::put('/{recipeItem}', [RecipeController::class, 'update'])->name('update');
        Route::patch('/{recipeItem}/toggle-status', [RecipeController::class, 'toggleStatus'])->name('toggle-status');
    });
});
