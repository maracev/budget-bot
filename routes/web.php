<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TelegramBotController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', [TelegramBotController::class, 'webhook']);
Route::get('/telegram/webhook', function () {
    Log::info('Telegram webhook');
    return response('OK', 200);
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', function () {
        return redirect()->route('transactions.index');
    });

    Route::resource('transactions', TransactionController::class)->only([
        'index', 'show', 'edit', 'update', 'destroy',
    ]);

    Route::get('/reportes', [ReportController::class, 'index'])->name('reports.index');
});
