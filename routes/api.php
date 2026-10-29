<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::post('/quotes', [QuoteController::class, 'store']);
Route::post('/quotes/batch', [QuoteController::class, 'batch']);
Route::get('/quotes', [QuoteController::class, 'index']);
Route::get('/quotes/{id}', [QuoteController::class, 'show'])->whereNumber('id');
Route::post('/imports', [ImportController::class, 'store']);
Route::get('/imports/{id}', [ImportController::class, 'show'])->whereNumber('id');
