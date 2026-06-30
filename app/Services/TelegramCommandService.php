<?php

namespace App\Services;

use App\Helpers\CategoryEmoji;
use App\Models\Category;
use App\Validators\FiltroTxValidator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;

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
        $conversation = Cache::get("telegram_conversation_{$chatId}");

        if ($conversation) {
            $this->handleConversation($telegram, $chatId, $username, $text, $conversation);

            return;
        }

        [$command, $args] = $this->parseCommand($text);

        if (in_array($command, ['gasto', 'ingreso']) && $args === '') {
            $this->startConversation($telegram, $chatId, $command);

            return;
        }

        match ($command) {
            'ingreso', 'gasto' => $this->handleTransaction($telegram, $chatId, $username, $command, $args),
            'balance' => $this->handleBalance($telegram, $chatId),
            'filtro_balance' => $this->handleFilteredBalance($telegram, $chatId, $args),
            'filtro_tx' => $this->handleFilteredTransactions($telegram, $chatId, $args),
            'cierre' => $this->handleClosure($telegram, $chatId, $args),
            'resumen' => $this->handleCategorySummary($telegram, $chatId, $args),
            'tarjeta' => $this->handleCreditCard($telegram, $chatId, $username, $args),
            'tarjeta_balance' => $this->handleCreditCardBalance($telegram, $chatId, $args),
            'categorias' => $this->handleListCategories($telegram, $chatId, $args),
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

        $args = trim($args);

        if ($args !== '') {
            $parts = preg_split('/\\s+/', $args, -1, PREG_SPLIT_NO_EMPTY);
            $monthKey = $parts[0] ?? null;
            $maybeYear = $parts[1] ?? null;

            if ($monthKey) {
                $normalized = strtolower($monthKey);
                if (isset($monthMap[$normalized])) {
                    $month = $monthMap[$normalized];
                } elseif (ctype_digit($normalized) && (int) $normalized >= 1 && (int) $normalized <= 12) {
                    $month = (int) $normalized;
                } else {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Mes inválido. Usar “filtro_balance mayo” o “filtro_balance mayo 2024”.',
                    ]);

                    return;
                }
            }

            if ($maybeYear !== null) {
                if (ctype_digit($maybeYear) && strlen($maybeYear) === 4) {
                    $year = (int) $maybeYear;
                } else {
                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Año inválido. Usar un año con 4 dígitos. Ej: “filtro_balance mayo 2024”.',
                    ]);

                    return;
                }
            }
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
        $currentDate = $this->getCurrentDate();
        $year = $currentDate['year'];
        $month = null;
        $monthName = null;

        $args = trim($args);

        if ($args === '') {
            // Cierra mes actual por defecto
            $month = $currentDate['month'];
            $monthName = Carbon::createFromDate($year, $month, 1)->locale('es')->monthName;
        } else {
            $parts = preg_split('/\s+/', $args, -1, PREG_SPLIT_NO_EMPTY);
            $monthKey = $parts[0] ?? null;
            $month = $monthKey ? $monthMap[$monthKey] ?? null : null;
            $monthName = $monthKey;

            $maybeYear = $parts[1] ?? null;
            if ($maybeYear && ctype_digit($maybeYear) && strlen($maybeYear) === 4) {
                $year = (int) $maybeYear;
            }
        }

        if (! $month) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Mes inválido. Usar: “cierre mayo”, “cierre diciembre 2025” o simplemente “cierre” para el mes actual.',
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
    private function handleListCategories(Api $telegram, string $chatId, string $args): void
    {
        $query = Category::active()->orderBy('sort_order')->orderBy('name');

        $typeFilter = trim($args);
        if ($typeFilter === 'gasto') {
            $query->forType('outgo');
        } elseif ($typeFilter === 'ingreso') {
            $query->forType('income');
        }

        $categories = $query->get(['name', 'type']);

        $lines = ['Categorías disponibles:'];
        foreach ($categories as $cat) {
            $label = $cat->type === 'both' ? ($typeFilter ? $cat->name : "{$cat->name} (ambos)") : $cat->name;
            $lines[] = "• {$label}";
        }

        if ($categories->isEmpty()) {
            $lines[] = '(sin categorías configuradas)';
        }

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => implode("\n", $lines),
        ]);
    }

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

    private function startConversation(Api $telegram, string $chatId, string $type): void
    {
        Cache::put("telegram_conversation_{$chatId}", [
            'step' => 'amount',
            'type' => $type,
        ], now()->addHour());

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => '¿Cuál es el monto?',
        ]);
    }

    private function handleConversation(Api $telegram, string $chatId, ?string $username, string $text, array $conversation): void
    {
        if (strtolower(trim($text)) === 'cancelar') {
            Cache::forget("telegram_conversation_{$chatId}");

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Operación cancelada.',
            ]);

            return;
        }

        $step = $conversation['step'];

        if ($step === 'amount') {
            $this->handleAmountStep($telegram, $chatId, $username, $text, $conversation['type']);

            return;
        }

        if ($step === 'category_selection') {
            $this->handleCategorySelectionStep($telegram, $chatId, $username, $text, $conversation);

            return;
        }

        if ($step === 'subcategory_selection') {
            $this->handleSubcategorySelectionStep($telegram, $chatId, $username, $text, $conversation);

            return;
        }

        if ($step === 'note') {
            $this->handleNoteStep($telegram, $chatId, $username, $text, $conversation);
        }

    }

    private function handleAmountStep(Api $telegram, string $chatId, ?string $username, string $text, string $type): void
    {
        if (! ctype_digit($text) || (int) $text <= 0) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Monto inválido. Ingresá un número positivo.',
            ]);

            return;
        }

        $amount = (int) $text;

        Cache::put("telegram_conversation_{$chatId}", [
            'step' => 'category_selection',
            'type' => $type,
            'amount' => $amount,
        ], now()->addHour());

        $this->sendCategoryKeyboard($telegram, $chatId, $type);
    }

    private function handleCategorySelectionStep(Api $telegram, string $chatId, ?string $username, string $text, array $conversation): void
    {
        $type = $conversation['type'];
        $amount = $conversation['amount'];
        $categoryName = $this->extractCategoryName($text);

        if ($categoryName === '') {
            $this->sendCategoryKeyboard($telegram, $chatId, $type, 'Categoría inválida. Seleccioná una categoría:');

            return;
        }

        $category = Category::active()->forType($type === self::OUTGO ? 'outgo' : 'income')
            ->where('name', $categoryName)
            ->first();

        if (! $category) {
            $this->sendCategoryKeyboard($telegram, $chatId, $type, 'Categoría no encontrada. Seleccioná una categoría:');

            return;
        }

        $subcategories = Category::active()
            ->where('parent_id', $category->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($subcategories->isNotEmpty()) {
            Cache::put("telegram_conversation_{$chatId}", [
                'step' => 'subcategory_selection',
                'type' => $type,
                'amount' => $amount,
                'category' => $category->name,
                'category_id' => $category->id,
            ], now()->addHour());

            $this->sendSubcategoryKeyboard($telegram, $chatId, $category, $subcategories);

            return;
        }

        $this->proceedToNoteStep($telegram, $chatId, $username, $type, $amount, $category->name, null);

        return;
    }

    private function handleSubcategorySelectionStep(Api $telegram, string $chatId, ?string $username, string $text, array $conversation): void
    {
        $type = $conversation['type'];
        $amount = $conversation['amount'];
        $mainCategoryName = $conversation['category'];

        if (str_contains($text, '✅') || str_starts_with($text, '✓') || trim(strtolower($text)) === $mainCategoryName) {
            $this->proceedToNoteStep($telegram, $chatId, $username, $type, $amount, $mainCategoryName, null);

            return;
        }

        $subcategoryName = $this->extractCategoryName($text);

        if ($subcategoryName === '') {
            $category = Category::find($conversation['category_id']);
            $subcategories = Category::active()->where('parent_id', $category->id)
                ->orderBy('sort_order')->orderBy('name')->get();
            $this->sendSubcategoryKeyboard($telegram, $chatId, $category, $subcategories, 'Subcategoría inválida. Seleccioná una subcategoría:');

            return;
        }

        $subcategory = Category::active()
            ->where('parent_id', $conversation['category_id'])
            ->where('name', $subcategoryName)
            ->first();

        if (! $subcategory) {
            $category = Category::find($conversation['category_id']);
            $subcategories = Category::active()->where('parent_id', $category->id)
                ->orderBy('sort_order')->orderBy('name')->get();
            $this->sendSubcategoryKeyboard($telegram, $chatId, $category, $subcategories, 'Subcategoría no encontrada. Seleccioná una subcategoría:');

            return;
        }

        $this->proceedToNoteStep($telegram, $chatId, $username, $type, $amount, $mainCategoryName, $subcategory->name);

        return;
    }

    private function proceedToNoteStep(Api $telegram, string $chatId, ?string $username, string $type, int $amount, string $category, ?string $subcategory): void
    {
        Cache::put("telegram_conversation_{$chatId}", [
            'step' => 'note',
            'type' => $type,
            'amount' => $amount,
            'category' => $category,
            'subcategory' => $subcategory,
        ], now()->addHour());

        $keyboard = Keyboard::make([
            'keyboard' => [[Keyboard::button(['text' => '⏭ Saltar'])]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Agregá una nota (opcional) o presioná "Saltar":',
            'reply_markup' => $keyboard,
        ]);
    }

    private function handleNoteStep(Api $telegram, string $chatId, ?string $username, string $text, array $conversation): void
    {
        $type = $conversation['type'];
        $amount = $conversation['amount'];
        $category = $conversation['category'];
        $subcategory = $conversation['subcategory'] ?? null;

        $trimmed = trim($text);
        $notes = null;

        if (strtolower($trimmed) !== 'saltar' && ! str_contains($trimmed, '⏭')) {
            $notes = $trimmed;
        }

        $args = $subcategory ? "{$amount} {$category} {$subcategory}" : "{$amount} {$category}";

        if (! $this->transactionService->register($type, $args, $chatId, $username, $errorMessage, $notes)) {
            Cache::forget("telegram_conversation_{$chatId}");

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $errorMessage,
            ]);

            return;
        }

        Cache::forget("telegram_conversation_{$chatId}");

        $displayAmount = $type === self::OUTGO ? $amount : $amount;
        $where = $subcategory ? "{$category} / {$subcategory}" : $category;
        $noteSuffix = $notes ? " — {$notes}" : '';

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Registrado: {$type} de \${$displayAmount} en {$where}{$noteSuffix}",
        ]);
    }

    private function sendCategoryKeyboard(Api $telegram, string $chatId, string $type, ?string $message = null): void
    {
        $categories = Category::active()
            ->whereNull('parent_id')
            ->forType($type === self::OUTGO ? 'outgo' : 'income')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $keyboard = $this->buildKeyboard($categories);
        $oneTimeKeyboard = Keyboard::make([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message ?? 'Seleccioná una categoría:',
            'reply_markup' => $oneTimeKeyboard,
        ]);
    }

    private function sendSubcategoryKeyboard(Api $telegram, string $chatId, Category $category, $subcategories, ?string $message = null): void
    {
        $buttons = $subcategories->map(fn ($cat) => [
            Keyboard::button(['text' => CategoryEmoji::forCategory($cat->name) . ' ' . $cat->name]),
        ])->all();

        $buttons[] = [
            Keyboard::button(['text' => '✅ ' . $category->name]),
        ];

        $keyboard = Keyboard::make([
            'keyboard' => $buttons,
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message ?? 'Seleccioná una subcategoría:',
            'reply_markup' => $keyboard,
        ]);
    }

    private function buildKeyboard($categories): array
    {
        $buttons = $categories->map(fn ($cat) =>
            Keyboard::button(['text' => CategoryEmoji::forCategory($cat->name) . ' ' . $cat->name])
        )->values()->all();

        $rows = [];
        for ($i = 0; $i < count($buttons); $i += 2) {
            $rows[] = array_slice($buttons, $i, 2);
        }

        return $rows;
    }

    private function extractCategoryName(string $text): string
    {
        $text = trim($text);

        $text = preg_replace('/^[^\p{L}\p{N}\s]+/u', '', $text);

        return trim(strtolower($text));
    }

    private function sendUnknownCommand(Api $telegram, string $chatId): void
    {
        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Comando desconocido. Opciones:\n".
                         "• ingreso <monto> <categoría> [<rubro>]\n".
                         "• gasto <monto> <categoría> [<rubro>]\n".
                         "• balance\n".
                         "• filtro_balance [<mes> [<anio>]]\n".
                         "• filtro_tx [tipo] [categoria] [mes] [anio]\n".
                         "• resumen [<mes> [<anio> [<categoría> [<rubro>]]]]\n".
                         "• cierre [<mes>]\n".
                         "• tarjeta <monto> <vendor> [<card_name>] [<n_cuotas>]\n".
                         "• tarjeta_balance [<mes>]\n".
                         '• categorias [gasto|ingreso]',
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

    private function handleCategorySummary(Api $telegram, string $chatId, string $args): void
    {
        $monthMap = config('month_map');
        $currentDate = $this->getCurrentDate();

        $year = $currentDate['year'];
        $month = $currentDate['month'];

        $category = null;
        $subcategory = null;

        $args = trim($args);

        if ($args !== '') {

            $parts = preg_split('/\\s+/', $args, -1, PREG_SPLIT_NO_EMPTY);

            $monthKey = $parts[0] ?? null;
            $maybeYear = $parts[1] ?? null;
            $maybeCategory = $parts[2] ?? null;
            $maybeSubcategory = $parts[3] ?? null;

            /**
             * MES
             */
            if ($monthKey) {

                $normalized = strtolower($monthKey);

                if (isset($monthMap[$normalized])) {
                    $month = $monthMap[$normalized];
                } elseif (ctype_digit($normalized) && (int)$normalized >= 1 && (int)$normalized <= 12) {
                    $month = (int)$normalized;
                } else {

                    $telegram->sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Mes inválido. Ej: resumen mayo o resumen mayo 2024 supermercado',
                    ]);

                    return;
                }
            }

            /**
             * AÑO
             */
            if ($maybeYear !== null && ctype_digit($maybeYear) && strlen($maybeYear) === 4) {

                $year = (int)$maybeYear;
            } elseif ($maybeYear !== null && !ctype_alpha($maybeYear)) {

                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Año inválido. Ej: resumen mayo 2024',
                ]);

                return;
            }

            /**
             * CATEGORY
             */
            if ($maybeCategory !== null) {
                $category = strtolower($maybeCategory);
            }

            /**
             * SUBCATEGORY
             */
            if ($maybeSubcategory !== null) {
                $subcategory = strtolower($maybeSubcategory);
            }
        }

        $summary = $this->transactionService->getCategorySummary(
            month: $month,
            year: $year,
            category: $category,
            subcategory: $subcategory
        );

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Resumen:\n{$summary}",
            'parse_mode' => 'HTML',
        ]);
    }
}
