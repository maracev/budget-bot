<?php

namespace App\Services;

use App\Services\TransactionService;
use App\Services\MonthlyClosureService;
use Illuminate\Support\Carbon;
use Telegram\Bot\Api;
use Illuminate\Support\Facades\Log;

class TelegramCommandService
{
    private TransactionService $transactionService;
    private MonthlyClosureService $closureService;

    public function __construct( TransactionService $transactionService, MonthlyClosureService $closureService ) {
        $this->transactionService = $transactionService;
        $this->closureService = $closureService;
    }

    public function execute(Api $telegram, string $chatId, ?string $username, string $text): void
    {
        [$command, $args] = $this->parseCommand($text);

        match ($command) {
            'ingreso', 'gasto' => $this->handleTransaction($telegram, $chatId, $username, $command, $args),
            'balance'           => $this->handleBalance($telegram, $chatId),
            'cierre'            => $this->handleClosure($telegram, $chatId, $args),
            default             => $this->sendUnknownCommand($telegram, $chatId),
        };
    }

    private function parseCommand(string $text): array
    {
        $parts   = preg_split('/\s+/', $text, 2, PREG_SPLIT_NO_EMPTY);
        $command = $parts[0] ?? '';
        $args    = $parts[1] ?? '';
        return [$command, $args];
    }

    private function handleTransaction(Api $telegram, string $chatId, ?string $username, string $rawType, string $args): void
    {
        // Use el servicio de transacciones para persistir la lógica
        if (! $this->transactionService->register($rawType, $args, $chatId, $username, $errorMessage)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => $errorMessage,
            ]);
            return;
        }

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => "Registrado: {$rawType} de \${$this->transactionService->getLastAmount()} en {$this->transactionService->getLastCategory()}",
        ]);
    }

    private function handleBalance(Api $telegram, string $chatId): void
    {
        $balanceData = $this->transactionService->getBalance();
        $texto = [
            "Balance actual:",
            "Ingresos: \${$balanceData['ingresos']}",
            "Gastos: \${$balanceData['gastos']}",
            "Saldo: \${$balanceData['saldo']}",
        ];

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => implode("\n", $texto),
        ]);
    }

    private function handleClosure(Api $telegram, string $chatId, string $args): void
    {
        $monthMap = config('month_map');
        $year     = Carbon::now()->year;

        if (empty($args)) {
            // Cerrar mes actual
            $month     = Carbon::now()->month;
            $monthText = Carbon::createFromDate($year, $month, 1)->locale('es')->monthName;
        } else {
            $key       = trim($args);
            $month     = $monthMap[$key] ?? null;
            $monthText = $key;
        }

        if (! $month) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "Mes inválido. Usá: \"cierre mayo\" u \"cierre\" para mes actual.",
            ]);
            return;
        }

        $closure = $this->closureService->closeMonth($month, $year);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => "Cierre de {$monthText} {$year}:\n" .
                         "Ingresos: \${$closure->ingresos}\n" .
                         "Gastos: \${$closure->gastos}\n" .
                         "Saldo: \${$closure->saldo}",
        ]);
    }

    private function sendUnknownCommand(Api $telegram, string $chatId): void
    {
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => "Comando desconocido. Usá:\n" .
                         "\"ingreso 1000 sueldo\"\n" .
                         "\"gasto 500 comida\"\n" .
                         "\"balance\"\n" .
                         "\"cierre\" o \"cierre junio\"",
        ]);
    }
}
