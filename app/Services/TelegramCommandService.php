<?php

namespace App\Services;

use App\Validators\FiltroTxValidator;
use Illuminate\Support\Carbon;
use Telegram\Bot\Api;

class TelegramCommandService
{
    private TransactionService $transactionService;

    private MonthlyClosureService $closureService;

    private CreditCardService $creditCardService;

    private FiltroTxValidator $filtroTxValidator;

    private const OUTGO = 'gasto';

    public function __construct(
        TransactionService $transactionService,
        MonthlyClosureService $closureService,
        CreditCardService $creditCardService,
        FiltroTxValidator $filtroTxValidator
    ) {
        $this->transactionService = $transactionService;
        $this->closureService = $closureService;
        $this->creditCardService = $creditCardService;
        $this->filtroTxValidator = $filtroTxValidator;
    }

    /**
     * Process the command received from Telegram.
     */
    public function execute(Api $telegram, string $chatId, ?string $username, string $text): void
    {
        [$command, $args] = $this->parseCommand($text);

        match ($command) {
            'ingreso', 'gasto' => $this->handleTransaction($telegram, $chatId, $username, $command, $args),
            'balance' => $this->handleBalance($telegram, $chatId),
            'filtro_balance' => $this->handleFilteredBalance($telegram, $chatId, $args),
            'filtro_tx' => $this->handleFilteredTransactions($telegram, $chatId, $args),
            'cierre' => $this->handleClosure($telegram, $chatId, $args),
            'tarjeta' => $this->handleCreditCard($telegram, $chatId, $username, $args),
            'tarjeta_balance' => $this->handleCreditCardBalance($telegram, $chatId, $args),
            default => $this->sendUnknownCommand($telegram, $chatId),
        };
    }

    private function parseCommand(string $text): array
    {
        $parts = preg_split('/\s+/', $text, 2, PREG_SPLIT_NO_EMPTY);
        $command = $parts[0] ?? '';
        $args = $parts[1] ?? '';

        return [$command, $args];
    }

    /**
     * Handles Income or Expense transactions.
     *
     * @param  mixed  $username
     */
    private function handleTransaction(Api $telegram, string $chatId, ?string $username, string $rawType, string $args): void
    {
        if (! $this->transactionService->register($rawType, $args, $chatId, $username, $errorMessage)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $errorMessage,
            ]);

            return;
        }

        $amount = $this->transactionService->getLastAmount();
        $category = $this->transactionService->getLastCategory();
        $subcategory = $this->transactionService->getLastSubcategory();

        $where = $subcategory ? "{$category} / {$subcategory}" : $category;
        $displayAmount = $rawType === self::OUTGO ? abs($amount) : $amount;

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Registrado: {$rawType} de \${$displayAmount} en {$where}",
        ]);
    }

    /**
     * Shows the general transaction balance.
     */
    private function handleBalance(Api $telegram, string $chatId): void
    {
        $balance = $this->transactionService->getBalance();
        $textLines = [
            'Balance actual:',
            "Ingresos: \${$balance['ingresos']}",
            "Gastos: \${$balance['gastos']}",
            "Saldo: \${$balance['saldo']}",
        ];

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => implode("\n", $textLines),
        ]);
    }

    private function handleFilteredBalance(Api $telegram, string $chatId, string $args): void
    {
        $monthMap = config('month_map');

        $currentDate = $this->getCurrentDate();
        $year = $currentDate['year'];
        $month = $currentDate['month'];

        if (! empty($args)) {
            $key = trim($args);
            $month = $monthMap[$key] ?? null;
        }

        $balanceSummary = $this->transactionService->getBalancePerCategory($month, $year);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Balance:\n{$balanceSummary}",
            'parse_mode' => 'HTML',
        ]);
    }

    /**
     * Handles monthly closure commands.
     */
    private function handleClosure(Api $telegram, string $chatId, string $args): void
    {
        $monthMap = config('month_map');
        $year = $this->getCurrentDate()['year'];

        if (empty($args)) {
            // Cierra mes actual
            $month = Carbon::now()->month;
            $monthName = Carbon::createFromDate($year, $month, 1)->locale('es')->monthName;
        } else {
            $key = trim($args);
            $month = $monthMap[$key] ?? null;
            $monthName = $key;
        }

        if (! $month) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Mes inválido. Usar: “cierre mayo” o simplemente “cierre” para mes actual.',
            ]);

            return;
        }

        $closure = $this->closureService->closeMonth($month, $year);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Cierre de {$monthName} {$year}:\n".
                         'Ingresos: $'.strval($closure->income)."\n".
                         'Gastos: $'.strval($closure->outgo)."\n".
                         'Saldo: $'.strval($closure->balance),
        ]);
    }

    /**
     * Handles credit card purchases.
     *
     * @param  mixed  $username
     */
    private function handleCreditCard(Api $telegram, string $chatId, ?string $username, string $args): void
    {
        if (! $this->creditCardService->registerPurchase($args, $chatId, $username, $errorMessage)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $errorMessage,
            ]);

            return;
        }

        // Volver a extraer monto y vendor para mensaje
        preg_match('/^(\d+(\.\d{1,2})?)\s+([\pL\pN\s]+?)(?:\s+(\S+))?(?:\s+(\d+))?$/u', $args, $m);
        $monto = $m[1];
        $vendor = trim($m[3]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Compra con tarjeta registrada: \${$monto} en {$vendor}",
        ]);
    }

    /**
     * Handles credit card balance inquiries.
     */
    private function handleCreditCardBalance(Api $telegram, string $chatId, string $args): void
    {
        $monthMap = config('month_map');
        $year = $this->getCurrentDate()['year'];

        if (empty($args)) {
            $month = Carbon::now()->month;
            $monthName = Carbon::createFromDate($year, $month, 1)->locale('es')->monthName;
        } else {
            $key = trim($args);
            $month = $monthMap[$key] ?? null;
            $monthName = $key;
        }

        if (! $month) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Mes inválido. Usar “tarjeta_balance mayo” o “tarjeta_balance” para mes actual.',
            ]);

            return;
        }

        $balance = $this->creditCardService->getMonthlyBalance($month, $year);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Balance tarjeta para {$monthName} {$year}: \${$balance}",
        ]);
    }

    private function sendUnknownCommand(Api $telegram, string $chatId): void
    {
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Comando desconocido. Opciones:\n".
                         "• ingreso <monto> <categoría> [<rubro>]\n".
                         "• gasto <monto> <categoría> [<rubro>]\n".
                         "• balance\n".
                         "• filtro_balance[<mes>]\n".
                         "• filtro_tx [tipo] [categoria] [mes] [anio]\n".
                         "• cierre [<mes>]\n".
                         "• tarjeta <monto> <vendor> [<card_name>] [<n_cuotas>]\n".
                         '• tarjeta_balance [<mes>]',
        ]);
    }

    /**
     * Summary of getCurrentDate
     *
     * @return array{month: int, monthName: string, year: int}
     */
    protected function getCurrentDate(): array
    {
        $month = Carbon::now()->month;
        $year = Carbon::now()->year;

        return [
            'month' => $month,
            'year' => $year,
            'monthName' => Carbon::createFromDate($year, $month, 1)->locale('es')->monthName,
        ];
    }

    private function handleFilteredTransactions(Api $telegram, string $chatId, string $args): void
    {
        $args = trim($args);

        if ($args === '') {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Indicá al menos un filtro: tipo, categoría o período. Ej: "filtro_tx gasto mascotas mayo 2024".',
            ]);

            return;
        }

        $parsed = $this->filtroTxValidator->parse($args);

        if (isset($parsed['error'])) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $parsed['error'],
            ]);

            return;
        }

        $type = $parsed['type'];
        $category = $parsed['category'];
        $month = $parsed['month'];
        $year = $parsed['year'];
        $monthProvided = $parsed['month_provided'];
        $yearProvided = $parsed['year_provided'];
        $inverseTypeMap = $parsed['inverse_type_map'];

        if ($category === null && $type === null && ! $monthProvided && ! $yearProvided) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Indicá al menos un filtro: tipo, categoría o período.',
            ]);

            return;
        }

        $transactions = $this->transactionService->getFilteredTransactions($type, $category, (int) $month, (int) $year);

        if ($transactions->isEmpty()) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Sin transacciones para esos filtros.',
            ]);

            return;
        }

        $monthName = Carbon::createFromDate($year, $month, 1)->locale('es')->monthName;

        $titleParts = ["Transacciones {$monthName} {$year}"];
        if ($type) {
            $titleParts[] = $inverseTypeMap[$type] ?? $type;
        }
        if ($category) {
            $titleParts[] = $category;
        }

        $lines = [implode(' / ', $titleParts)];

        $total = 0;
        foreach ($transactions as $tx) {
            $sign = $tx->amount >= 0 ? '+' : '-';
            $formattedAmount = "{$sign}$".abs($tx->amount);
            $label = $tx->subcategory ? "{$tx->category}/{$tx->subcategory}" : $tx->category;
            $lines[] = "{$tx->created_at->format('Y-m-d')}  {$label}  {$formattedAmount}";

            $total += $tx->amount;
        }

        $lines[] = "Total: {$total} (".count($transactions).' tx)';

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => implode("\n", $lines),
        ]);
    }
}
