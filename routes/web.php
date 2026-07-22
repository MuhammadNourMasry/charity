<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Charity API is running',
        'status' => 'ok',
        'documentation' => url('/api'),
    ]);
});
Route::get('/payment/success', function () {
    return view('payment.success');
})->name('payment.success');

Route::get('/payment/cancel', function () {
    return view('payment.cancel');
})->name('payment.cancel');