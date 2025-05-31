<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramBotController;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::post('/telegram/webhook', [TelegramBotController::class, 'webhook']);
Route::get('/telegram/webhook', function () {
    Log::info('Telegram webhook');
    return response('OK', 200); 
});
Route::get('/', function () {
    return view('welcome');
});
