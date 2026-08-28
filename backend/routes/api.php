<?php

use App\Http\Controllers\CheckController;
use App\Http\Controllers\ReferencePaymentController;
use Illuminate\Support\Facades\Route;

Route::post('/checks', [CheckController::class, 'store'])->name('checks.store');

Route::post('/reference-payments', [ReferencePaymentController::class, 'store'])->name('reference-payments.store');
Route::get('/reference-payments/{run_id}', [ReferencePaymentController::class, 'show'])->name('reference-payments.show');
