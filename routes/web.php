<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::post('/dashboard/cart', [DashboardController::class, 'storeCartItem'])->name('dashboard.cart.store');
Route::patch('/dashboard/cart/{index}/increase', [DashboardController::class, 'increaseCartItem'])->name('dashboard.cart.increase');
Route::patch('/dashboard/cart/{index}/decrease', [DashboardController::class, 'decreaseCartItem'])->name('dashboard.cart.decrease');
Route::delete('/dashboard/cart/{index}', [DashboardController::class, 'removeCartItem'])->name('dashboard.cart.remove');
Route::delete('/dashboard/cart', [DashboardController::class, 'clearCart'])->name('dashboard.cart.clear');

Route::get('/checkout', [DashboardController::class, 'showCheckout'])->name('checkout');
Route::post('/checkout', [DashboardController::class, 'checkout'])->name('checkout.store');

Route::get('/receipt', [DashboardController::class, 'latestReceipt'])->name('receipt');
Route::get('/receipt/{transaction}', [DashboardController::class, 'receipt'])->name('receipt.show');

Route::get('/history', [DashboardController::class, 'history'])->name('history');
Route::get('/history/{transaction}', [DashboardController::class, 'historyShow'])->name('history.show');

Route::get('/reports', [DashboardController::class, 'salesReport'])->name('reports.sales');
