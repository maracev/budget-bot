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
        Log::info('Received webhook: ' . $request->getContent());

        $telegram = new Api(config('app.telegram_bot_token'));
        $update   = $telegram->getWebhookUpdate();
        $message  = $update->getMessage();
        $text     = strtolower(trim($message->getText()));
        $chatId   = $message->getChat()->getId();
        $username = $message->getChat()->getUsername();

        if ($chatId != config('app.my_telegram_chat_id')) {
            Log::warning("Acceso no autorizado: {$chatId} intentó usar el bot con texto: {$text}");
            return response('OK', 200);
        }

        Log::info("Procesando mensaje: {$text}");

        [$command, $args] = $this->parseCommand($text);

        return match ($command) {
            'ingreso', 'gasto' => $this->handleTransaction($telegram, $chatId, $username, $command, $args),
            'balance'           => $this->handleBalance($telegram, $chatId),
            'cierre'            => $this->handleClosure($telegram, $chatId, $args),
            default             => $this->sendUnknownCommand($telegram, $chatId),
        };
    }

    /**
     * Summary of handleTransaction
     * @param \Telegram\Bot\Api $telegram
     * @param string $chatId
     * @param ?string $username
     * @param string $rawType
     * @param string $args
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    private function handleTransaction(Api $telegram, string $chatId, ?string $username, string $rawType, string $args)
    {
        if (!preg_match('/^(\d+)\s+(.*)$/', $args, $matches)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "Formato inválido. Usá: \"{$rawType} 1000 sueldo\".",
            ]);
            return response('OK', 200);
        }

        $amount = (int) $matches[1];
        $category = trim($matches[2]);
        $typeMapper = config('type_mapper');

        $type = $typeMapper[$rawType] ?? null;

        if (!$type) {
            Log::warning("Tipo inválido recibido: {$rawType}");
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "Tipo inválido. Usá \"ingreso\" u \"gasto\".",
            ]);
            return response('OK', 200);
        }

        Transaction::create([
            'type'       => $type,
            'amount'      => $amount,
            'category'  => $category,
            'owner_id'   => $chatId,
            'owner_name' => $username,
        ]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => "Registrado: {$rawType} de \${$amount} en {$category}",
        ]);

        return response('OK', 200);
    }

    /**
     * Summary of handleBalance
     * @param \Telegram\Bot\Api $telegram
     * @param string $chatId
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    private function handleBalance(Api $telegram, string $chatId)
    {
        $incomes = Transaction::where('type', config('type_mapper.ingreso'))->sum('amount');
        $outgoes   = Transaction::where('type', config('type_mapper.outgo'))->sum('amount');
        $balance    = $incomes - $outgoes;

        $texto = [
            "Balance actual:",
            "ingresos: \${$incomes}",
            "egresos: \${$outgoes}",
            "balance: \${$balance}",
        ];

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => implode("\n", $texto),
        ]);

        return response('OK', 200);
    }

    /**
     * Summary of handleClosure
     * @param \Telegram\Bot\Api $telegram
     * @param string $chatId
     * @param string $args
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    private function handleClosure(Api $telegram, string $chatId, string $args)
    {
        $monthMap = config('month_map');
        $year     = Carbon::now()->year;

        if (empty($args)) {
            $month     = Carbon::now()->month;
            $monthText = Carbon::createFromDate($year, $month, 1)
                                ->locale('es')
                                ->monthName;
        } else {
            $key       = trim($args);
            $month     = $monthMap[$key] ?? null;
            $monthText = $key;
        }

        if (!$month) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "Mes inválido. Usá: \"cierre mayo\" u \"cierre\" para el mes actual.",
            ]);
            return response('OK', 200);
        }

        $closureService = app(MonthlyClosureService::class);
        $closure        = $closureService->closeMonth($month, $year);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => "Cierre de {$monthText} {$year}:\n" .
                         "Ingresos: \${$closure->income}\n" .
                         "Gastos: \${$closure->outcome}\n" .
                         "Saldo: \${$closure->balance}",
        ]);

        return response('OK', 200);
    }

    
    private function parseCommand(string $text): array
    {
        $parts   = preg_split('/\s+/', $text, 2, PREG_SPLIT_NO_EMPTY);
        $command = $parts[0] ?? '';
        $args    = $parts[1] ?? '';
        return [$command, $args];
    }

    private function sendUnknownCommand(Api $telegram, string $chatId)
    {
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => "Comando desconocido. Usá:\n" .
                         "\"ingreso 1000 sueldo\"\n" .
                         "\"gasto 500 comida\"\n" .
                         "\"balance\"\n" .
                         "\"cierre\" o \"cierre junio\"",
        ]);

        return response('OK', 200);
    }   
}
