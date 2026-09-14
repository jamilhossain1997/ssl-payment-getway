<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// --- SSLCommerz payment routes ---
Route::post('/payment/initiate', [PaymentController::class, 'initiate'])->name('payment.initiate');

// SSLCommerz posts to these as application/x-www-form-urlencoded — exempted
// from CSRF in bootstrap/app.php.
Route::post('/payment/success', [PaymentController::class, 'success'])->name('payment.success');
Route::post('/payment/fail', [PaymentController::class, 'fail'])->name('payment.fail');
Route::post('/payment/cancel', [PaymentController::class, 'cancel'])->name('payment.cancel');
Route::post('/payment/ipn', [PaymentController::class, 'ipn'])->name('payment.ipn');
Route::get('/api/payment-status/{tranId}', [PaymentController::class, 'status'])
    ->name('payment.status');

Route::view('/payment/thank-you', 'payment.thank-you');
Route::view('/payment/failed', 'payment.failed');
Route::view('/payment/cancelled', 'payment.cancelled');
Route::view('/payment', 'payment.index')->name('payment.page');
