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
        $expectedToken = config('app.telegram_secret_token');

        if (! $expectedToken) {
            Log::error('Telegram webhook secret token is not configured.');
            return response('Service misconfigured', 500);
        }

        $providedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! is_string($providedToken) || ! hash_equals($expectedToken, $providedToken)) {
            Log::warning('Unauthorized Telegram webhook attempt', [
                'ip' => $request->ip(),
                'has_token' => (bool) $providedToken,
            ]);

            return response('Unauthorized', 401);
        }

        $telegram = new Api(config('app.telegram_bot_token'));
        $update   = $telegram->getWebhookUpdate();
        $message  = $update->getMessage();

        $chat     = $message ? $message->getChat() : null;
        $textRaw  = $message?->getText() ?? '';
        $text     = strtolower(ltrim(trim($textRaw), '/'));
        $chatId   = $chat?->getId();
        $username = $chat?->getUsername();

        Log::info('Telegram webhook received', [
            'chat_id' => $chatId,
            'update_id' => $update->getUpdateId(),
            'message_length' => mb_strlen($textRaw, 'UTF-8'),
        ]);

        if ($chatId != config('app.my_telegram_chat_id')) {
            Log::warning('Acceso no autorizado al bot', [
                'chat_id' => $chatId,
                'update_id' => $update->getUpdateId(),
            ]);

            return response('OK', 200);
        }

        $commandParts = explode(' ', $text, 2);
        $command = ($commandParts[0] ?? null) ?: null;

        Log::info('Procesando comando de Telegram', [
            'chat_id' => $chatId,
            'command' => $command,
        ]);

        $this->commandService->execute($telegram, $chatId ?? '', $username, $text);

        return response('OK', 200);
    }

}
