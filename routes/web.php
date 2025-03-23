<?php

use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return redirect()->route('receipts.index');
    })->name('home');

    Route::resource('receipts', ReceiptController::class);
    Route::post('receipts/change-item-category', [ReceiptController::class, 'changeItemCategory'])->name('receipts.change-item-category');
    Route::post('receipts/bundle-items', [ReceiptController::class, 'bundleItems'])->name('receipts.bundle-items');
});

