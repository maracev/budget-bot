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

    private CreditCardService $creditCardService;

    public function __construct( 
        TransactionService $transactionService, 
        MonthlyClosureService $closureService,
        CreditCardService $creditCardService
    ) {
        $this->transactionService = $transactionService;
        $this->closureService = $closureService;
        $this->creditCardService = $creditCardService;
    }

    /**
     * Process the command received from Telegram.
     *
     * @param Api $telegram
     * @param string $chatId
     * @param ?string $username
     * @param string $text
     */
    public function execute(Api $telegram, string $chatId, ?string $username, string $text): void
    {
        [$command, $args] = $this->parseCommand($text);

        match ($command) {
            'ingreso', 'gasto'       => $this->handleTransaction($telegram, $chatId, $username, $command, $args),
            'balance'                => $this->handleBalance($telegram, $chatId),
            'filtro_balance'         => $this->handleFilteredBalance($telegram, $chatId),
            'cierre'                 => $this->handleClosure($telegram, $chatId, $args),
            'tarjeta'                => $this->handleCreditCard($telegram, $chatId, $username, $args),
            'tarjeta_balance'        => $this->handleCreditCardBalance($telegram, $chatId, $args),
            default                  => $this->sendUnknownCommand($telegram, $chatId),
        };
    }

    private function parseCommand(string $text): array
    {
        $parts   = preg_split('/\s+/', $text, 2, PREG_SPLIT_NO_EMPTY);
        $command = $parts[0] ?? '';
        $args    = $parts[1] ?? '';
        return [$command, $args];
    }

    /**
     * Handles Income or Expense transactions.
     * @param \Telegram\Bot\Api $telegram
     * @param string $chatId
     * @param mixed $username
     * @param string $rawType
     * @param string $args
     * @return void
     */
    private function handleTransaction(Api $telegram, string $chatId, ?string $username, string $rawType, string $args): void
    {
        if (! $this->transactionService->register($rawType, $args, $chatId, $username, $errorMessage)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => $errorMessage,
            ]);
            return;
        }

        $amount      = $this->transactionService->getLastAmount();
        $category    = $this->transactionService->getLastCategory();
        $subcategory = $this->transactionService->getLastSubcategory();

        $where = $subcategory ? "{$category} / {$subcategory}" : $category;

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => "Registrado: {$rawType} de \${$amount} en {$where}",
        ]);
    }

    /**
     * Shows the general transaction balance.
     * @param \Telegram\Bot\Api $telegram
     * @param string $chatId
     * @return void
     */
    private function handleBalance(Api $telegram, string $chatId): void
    {
        $balance = $this->transactionService->getBalance();
        $textLines = [
            "Balance actual:",
            "Ingresos: \${$balance['ingresos']}",
            "Gastos: \${$balance['gastos']}",
            "Saldo: \${$balance['saldo']}",
        ];

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => implode("\n", $textLines),
        ]);
    }

    private function handleFilteredBalance(Api $telegram, string $chatId): void
    {
        $balance = $this->transactionService->getBalancePerCategory();
        Log::debug('balance', ['balance', $balance]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => implode("\n", ['balance' => $balance]),
        ]);
    }

    /**
     * Handles monthly closure commands.
     * @param \Telegram\Bot\Api $telegram
     * @param string $chatId
     * @param string $args
     * @return void
     */
    private function handleClosure(Api $telegram, string $chatId, string $args): void
    {
        $monthMap = config('month_map');
        $year     = Carbon::now()->year;

        if (empty($args)) {
            // Cierra mes actual
            $month     = Carbon::now()->month;
            $monthName = Carbon::createFromDate($year, $month, 1)->locale('es')->monthName;
        } else {
            $key       = trim($args);
            $month     = $monthMap[$key] ?? null;
            $monthName = $key;
        }

        if (! $month) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "Mes inválido. Usar: “cierre mayo” o simplemente “cierre” para mes actual.",
            ]);
            return;
        }

        $closure = $this->closureService->closeMonth($month, $year);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => "Cierre de {$monthName} {$year}:\n" .
                         "Ingresos: \$" . strval($closure->income) . "\n" .
                         "Gastos: \$" . strval($closure->outgo) . "\n" .
                         "Saldo: \$" . strval($closure->balance),
        ]);
    }

    /**
     * Handles credit card purchases.
     * @param \Telegram\Bot\Api $telegram
     * @param string $chatId
     * @param mixed $username
     * @param string $args
     * @return void
     */
    private function handleCreditCard(Api $telegram, string $chatId, ?string $username, string $args): void
    {
        if (! $this->creditCardService->registerPurchase($args, $chatId, $username, $errorMessage)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => $errorMessage,
            ]);
            return;
        }

        // Volver a extraer monto y vendor para mensaje
        preg_match('/^(\d+(\.\d{1,2})?)\s+([\pL\pN\s]+?)(?:\s+(\S+))?(?:\s+(\d+))?$/u', $args, $m);
        $monto  = $m[1];
        $vendor = trim($m[3]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => "Compra con tarjeta registrada: \${$monto} en {$vendor}",
        ]);
    }

    /**
     * Handles credit card balance inquiries.
     * @param \Telegram\Bot\Api $telegram
     * @param string $chatId
     * @param string $args
     * @return void
     */
    private function handleCreditCardBalance(Api $telegram, string $chatId, string $args): void
    {
        $monthMap = config('month_map');
        $year     = Carbon::now()->year;

        if (empty($args)) {
            $month     = Carbon::now()->month;
            $monthName = Carbon::createFromDate($year, $month, 1)->locale('es')->monthName;
        } else {
            $key       = trim($args);
            $month     = $monthMap[$key] ?? null;
            $monthName = $key;
        }

        if (! $month) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "Mes inválido. Usar “tarjeta_balance mayo” o “tarjeta_balance” para mes actual.",
            ]);
            return;
        }

        $balance = $this->creditCardService->getMonthlyBalance($month, $year);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => "Balance tarjeta para {$monthName} {$year}: \${$balance}",
        ]);
    }

    private function sendUnknownCommand(Api $telegram, string $chatId): void
    {
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text'    => "Comando desconocido. Opciones:\n" .
                         "• ingreso <monto> <categoría> [<rubro>]\n" .
                         "• gasto <monto> <categoría> [<rubro>]\n" .
                         "• balance\n" .
                         "• filtro_balance\n".
                         "• cierre [<mes>]\n" .
                         "• tarjeta <monto> <vendor> [<card_name>] [<n_cuotas>]\n" .
                         "• tarjeta_balance [<mes>]",
        ]);
    }
}
