<?php

namespace App\Http\Controllers;

use Telegram\Bot\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\TelegramCommandService;

class TelegramBotController extends Controller
{

    private TelegramCommandService $commandService;

    public function __construct(TelegramCommandService $commandService)
    {
        $this->commandService = $commandService;
    }


    public function webhook(Request $request)
    {
        Log::info('Received webhook: ' . $request->getContent());

        $telegram = new Api(config('app.telegram_bot_token'));
        $update   = $telegram->getWebhookUpdate();
        $message  = $update->getMessage();

        $text = strtolower(ltrim(trim($message->getText()), '/'));
        $chatId   = $message->getChat()->getId();
        $username = $message->getChat()->getUsername();

        if ($chatId != config('app.my_telegram_chat_id')) {
            Log::warning("Acceso no autorizado: {$chatId} intentó usar el bot con texto: {$text}");
            return response('OK', 200);
        }

        Log::info("Procesando mensaje: {$text}");

        $this->commandService->execute($telegram, $chatId, $username, $text);

        return response('OK', 200);
    }

}
