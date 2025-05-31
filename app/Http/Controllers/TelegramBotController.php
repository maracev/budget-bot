<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Telegram\Bot\Api;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\MonthlyClosureService;

class TelegramBotController extends Controller
{
    public function webhook(Request $request)
    {
        Log::info("Received webhook: " . $request->getContent());

        $telegram = new Api(config('app.telegram_bot_token'));
        $update = $telegram->getWebhookUpdate();
        $message = $update->getMessage();
        $text = strtolower($message->getText());
        $chatId = $message->getChat()->getId();

        if ($chatId != '200213027') {
            Log::error('Someone different from me is trying to use the bot: ' . $chatId . ' received text: ' . $text);
            return response('OK', 200);
        }

        Log::info('Received message: ' . $text);

        if (str_starts_with($text, 'gasto') || str_starts_with($text, 'ingreso')) {

            preg_match('/(gasto|ingreso)\s+(\d+)[^\w]+(.+)/', $text, $matches);
            
            if ($matches) {
                $type = $matches[1];
                $amount = $matches[2];
                $category = $matches[3];

                $type = translateType($matches[1]);

                if (!$type) {
                    Log::warning("Tipo inválido recibido: $matches[1]");
                    return response()->json(['error' => 'Tipo inválido'], 422);
                }

                Transaction::create([
                    'type' => $type,
                    'amount' => $amount,
                    'category' => $category,
                    'owner_id' => $chatId,
                    'owner_name' => $message->getChat()->getUsername(),
                ]);

                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Registrado: $type de $$amount en $category"
                ]);
            } elseif (str_starts_with($text, '/cierre')) {
                $mesTexto = trim(str_replace('/cierre', '', $text));

                $meses = [
                    'enero' => 1,
                    'febrero' => 2,
                    'marzo' => 3,
                    'abril' => 4,
                    'mayo' => 5,
                    'junio' => 6,
                    'julio' => 7,
                    'agosto' => 8,
                    'septiembre' => 9,
                    'octubre' => 10,
                    'noviembre' => 11,
                    'diciembre' => 12,
                ];

                $mes = $meses[$mesTexto] ?? null;
                $año = Carbon::now()->year;

                if ($mes) {
                    $closureService = app(MonthlyClosureService::class);
                    $closure = $closureService->closeMonth($mes, $año);

                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "Cierre registrado para $mesTexto $año:\nIngresos: \${$closure->ingresos}\nGastos: \${$closure->gastos}\nSaldo: \${$closure->saldo}"
                    ]);
                } else {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => "No entendí el mes. Usá: /cierre mayo, /cierre junio, etc."
                    ]);
                }
            }
        }
    }
}
