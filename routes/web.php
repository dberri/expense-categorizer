<?php

use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('receipts.index');
});

Route::resource('receipts', ReceiptController::class)->only(['index', 'store', 'show', 'destroy']);
Route::post('receipts/change-item-category', [ReceiptController::class, 'changeItemCategory'])->name('receipts.change-item-category');
Route::post('receipts/bundle-items', [ReceiptController::class, 'bundleItems'])->name('receipts.bundle-items');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
