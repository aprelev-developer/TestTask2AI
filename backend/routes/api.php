<?php

use App\Http\Controllers\CheckController;
use Illuminate\Support\Facades\Route;

Route::post('/checks', [CheckController::class, 'store'])->name('checks.store');
