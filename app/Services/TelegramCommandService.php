<?php

namespace App\Services;

use App\Helpers\CategoryEmoji;
use App\Models\Category;
use App\Validators\FiltroTxValidator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;
use App\Contants\TransactionConstants;

class TelegramCommandService
{
    private TransactionService $transactionService;

    private MonthlyClosureService $closureService;

    private CreditCardService $creditCardService;

    private FiltroTxValidator $filtroTxValidator;

   
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

    public function execute(Api $telegram, string $chatId, ?string $username, string $text): void
    {
        $conversation = Cache::get(TransactionConstants::CACHE_KEY_PREFIX . $chatId);

        if ($conversation) {
            $this->handleConversation($telegram, $chatId, $username, $text, $conversation);

            return;
        }

        [$command, $args] = $this->parseCommand($text);

        if (in_array($command, [TransactionConstants::CMD_GASTO, TransactionConstants::CMD_INGRESO]) && $args === '') {
            $this->startConversation($telegram, $chatId, $command);

            return;
        }

        match ($command) {
            TransactionConstants::CMD_INGRESO, TransactionConstants::CMD_GASTO => $this->handleTransaction($telegram, $chatId, $username, $command, $args),
            TransactionConstants::CMD_BALANCE => $this->handleBalance($telegram, $chatId),
            TransactionConstants::CMD_FILTRO_BALANCE => $this->handleFilteredBalance($telegram, $chatId, $args),
            TransactionConstants::CMD_FILTRO_TX => $this->handleFilteredTransactions($telegram, $chatId, $args),
            TransactionConstants::CMD_CIERRE => $this->handleClosure($telegram, $chatId, $args),
            TransactionConstants::CMD_RESUMEN => $this->handleCategorySummary($telegram, $chatId, $args),
            TransactionConstants::CMD_TARJETA => $this->handleCreditCard($telegram, $chatId, $username, $args),
            TransactionConstants::CMD_TARJETA_BALANCE => $this->handleCreditCardBalance($telegram, $chatId, $args),
            TransactionConstants::CMD_CATEGORIAS => $this->handleListCategories($telegram, $chatId, $args),
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
        $displayAmount = $rawType === TransactionConstants::OUTGO ? abs($amount) : $amount;

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Registrado: {$rawType} de \${$displayAmount} en {$where}",
        ]);
    }

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
                        'text' => 'Mes inválido. Usar "filtro_balance mayo" o "filtro_balance mayo 2024".',
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
                        'text' => 'Año inválido. Usar un año con 4 dígitos. Ej: "filtro_balance mayo 2024".',
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

    private function handleClosure(Api $telegram, string $chatId, string $args): void
    {
        $monthMap = config('month_map');
        $currentDate = $this->getCurrentDate();
        $year = $currentDate['year'];
        $month = null;
        $monthName = null;

        $args = trim($args);

        if ($args === '') {
            $month = $currentDate['month'];
            $monthName = Carbon::createFromDate($year, $month, 1)->locale(TransactionConstants::LOCALE)->monthName;
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
                'text' => 'Mes inválido. Usar: "cierre mayo", "cierre diciembre 2025" o simplemente "cierre" para el mes actual.',
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

    private function handleCreditCard(Api $telegram, string $chatId, ?string $username, string $args): void
    {
        if (! $this->creditCardService->registerPurchase($args, $chatId, $username, $errorMessage)) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $errorMessage,
            ]);

            return;
        }

        preg_match('/^(\d+(\.\d{1,2})?)\s+([\pL\pN\s]+?)(?:\s+(\S+))?(?:\s+(\d+))?$/u', $args, $m);
        $monto = $m[1];
        $vendor = trim($m[3]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Compra con tarjeta registrada: \${$monto} en {$vendor}",
        ]);
    }

    private function handleListCategories(Api $telegram, string $chatId, string $args): void
    {
        $query = Category::active()->orderBy('sort_order')->orderBy('name');

        $typeFilter = trim($args);
        if ($typeFilter === TransactionConstants::CMD_GASTO) {
            $query->forType(TransactionConstants::TYPE_OUTGO);
        } elseif ($typeFilter === TransactionConstants::CMD_INGRESO) {
            $query->forType(TransactionConstants::TYPE_INCOME);
        }

        $categories = $query->get(['name', 'type']);

        $lines = ['Categorías disponibles:'];
        foreach ($categories as $cat) {
            $label = $cat->type === Category::TYPE_BOTH ? ($typeFilter ? $cat->name : "{$cat->name} (ambos)") : $cat->name;
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
            $monthName = Carbon::createFromDate($year, $month, 1)->locale(TransactionConstants::LOCALE)->monthName;
        } else {
            $key = trim($args);
            $month = $monthMap[$key] ?? null;
            $monthName = $key;
        }

        if (! $month) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Mes inválido. Usar "tarjeta_balance mayo" o "tarjeta_balance" para mes actual.',
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
        Cache::put(TransactionConstants::CACHE_KEY_PREFIX . $chatId, [
            TransactionConstants::KEY_STEP => TransactionConstants::STEP_AMOUNT,
            TransactionConstants::KEY_TYPE => $type,
        ], now()->addHour());

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => TransactionConstants::PROMPT_AMOUNT,
        ]);
    }

    private function handleConversation(Api $telegram, string $chatId, ?string $username, string $text, array $conversation): void
    {
        if (strtolower(trim($text)) === 'cancelar') {
            Cache::forget(TransactionConstants::CACHE_KEY_PREFIX . $chatId);

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => TransactionConstants::MSG_CANCELLED,
            ]);

            return;
        }

        $step = $conversation[TransactionConstants::KEY_STEP];

        if ($step === TransactionConstants::STEP_AMOUNT) {
            $this->handleAmountStep($telegram, $chatId, $username, $text, $conversation[TransactionConstants::KEY_TYPE]);

            return;
        }

        if ($step === TransactionConstants::STEP_CATEGORY_SELECTION) {
            $this->handleCategorySelectionStep($telegram, $chatId, $username, $text, $conversation);

            return;
        }

        if ($step === TransactionConstants::STEP_SUBCATEGORY_SELECTION) {
            $this->handleSubcategorySelectionStep($telegram, $chatId, $username, $text, $conversation);

            return;
        }

        if ($step === TransactionConstants::STEP_NOTE) {
            $this->handleNoteStep($telegram, $chatId, $username, $text, $conversation);
        }
    }

    private function handleAmountStep(Api $telegram, string $chatId, ?string $username, string $text, string $type): void
    {
        if (! ctype_digit($text) || (int) $text <= 0) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => TransactionConstants::MSG_INVALID_AMOUNT,
            ]);

            return;
        }

        $amount = (int) $text;

        Cache::put(TransactionConstants::CACHE_KEY_PREFIX . $chatId, [
            TransactionConstants::KEY_STEP => TransactionConstants::STEP_CATEGORY_SELECTION,
            TransactionConstants::KEY_TYPE => $type,
            TransactionConstants::KEY_AMOUNT => $amount,
        ], now()->addHour());

        $this->sendCategoryKeyboard($telegram, $chatId, $type);
    }

    private function handleCategorySelectionStep(Api $telegram, string $chatId, ?string $username, string $text, array $conversation): void
    {
        $type = $conversation[TransactionConstants::KEY_TYPE];
        $amount = $conversation[TransactionConstants::KEY_AMOUNT];
        $categoryName = $this->extractCategoryName($text);

        if ($categoryName === '') {
            $this->sendCategoryKeyboard($telegram, $chatId, $type, TransactionConstants::MSG_INVALID_CATEGORY);

            return;
        }

        $category = Category::active()->forType($type === TransactionConstants::OUTGO ? TransactionConstants::TYPE_OUTGO : TransactionConstants::TYPE_INCOME)
            ->where('name', $categoryName)
            ->first();

        if (! $category) {
            $this->sendCategoryKeyboard($telegram, $chatId, $type, TransactionConstants::MSG_CATEGORY_NOT_FOUND);

            return;
        }

        $subcategories = Category::active()
            ->where('parent_id', $category->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($subcategories->isNotEmpty()) {
            Cache::put(TransactionConstants::CACHE_KEY_PREFIX . $chatId, [
                TransactionConstants::KEY_STEP => TransactionConstants::STEP_SUBCATEGORY_SELECTION,
                TransactionConstants::KEY_TYPE => $type,
                TransactionConstants::KEY_AMOUNT => $amount,
                TransactionConstants::KEY_CATEGORY => $category->name,
                TransactionConstants::KEY_CATEGORY_ID => $category->id,
            ], now()->addHour());

            $this->sendSubcategoryKeyboard($telegram, $chatId, $category, $subcategories);

            return;
        }

        $this->proceedToNoteStep($telegram, $chatId, $username, $type, $amount, $category->name, null);
    }

    private function handleSubcategorySelectionStep(Api $telegram, string $chatId, ?string $username, string $text, array $conversation): void
    {
        $type = $conversation[TransactionConstants::KEY_TYPE];
        $amount = $conversation[TransactionConstants::KEY_AMOUNT];
        $mainCategoryName = $conversation[TransactionConstants::KEY_CATEGORY];

        if (str_contains($text, TransactionConstants::CHECK_PREFIX) || str_starts_with($text, TransactionConstants::CHECK_ALT) || trim(strtolower($text)) === $mainCategoryName) {
            $this->proceedToNoteStep($telegram, $chatId, $username, $type, $amount, $mainCategoryName, null);

            return;
        }

        $subcategoryName = $this->extractCategoryName($text);

        if ($subcategoryName === '') {
            $category = Category::find($conversation[TransactionConstants::KEY_CATEGORY_ID]);
            $subcategories = Category::active()->where('parent_id', $category->id)
                ->orderBy('sort_order')->orderBy('name')->get();
            $this->sendSubcategoryKeyboard($telegram, $chatId, $category, $subcategories, TransactionConstants::MSG_INVALID_SUBCATEGORY);

            return;
        }

        $subcategory = Category::active()
            ->where('parent_id', $conversation[TransactionConstants::KEY_CATEGORY_ID])
            ->where('name', $subcategoryName)
            ->first();

        if (! $subcategory) {
            $category = Category::find($conversation[TransactionConstants::KEY_CATEGORY_ID]);
            $subcategories = Category::active()->where('parent_id', $category->id)
                ->orderBy('sort_order')->orderBy('name')->get();
            $this->sendSubcategoryKeyboard($telegram, $chatId, $category, $subcategories, TransactionConstants::MSG_SUBCATEGORY_NOT_FOUND);

            return;
        }

        $this->proceedToNoteStep($telegram, $chatId, $username, $type, $amount, $mainCategoryName, $subcategory->name);
    }

    private function proceedToNoteStep(Api $telegram, string $chatId, ?string $username, string $type, int $amount, string $category, ?string $subcategory): void
    {
        Cache::put(TransactionConstants::CACHE_KEY_PREFIX . $chatId, [
            TransactionConstants::KEY_STEP => TransactionConstants::STEP_NOTE,
            TransactionConstants::KEY_TYPE => $type,
            TransactionConstants::KEY_AMOUNT => $amount,
            TransactionConstants::KEY_CATEGORY => $category,
            TransactionConstants::KEY_SUBCATEGORY => $subcategory,
        ], now()->addHour());

        $keyboard = Keyboard::make([
            'keyboard' => [[Keyboard::button(['text' => TransactionConstants::SKIP_TEXT])]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => TransactionConstants::PROMPT_NOTE,
            'reply_markup' => $keyboard,
        ]);
    }

    private function handleNoteStep(Api $telegram, string $chatId, ?string $username, string $text, array $conversation): void
    {
        $type = $conversation[TransactionConstants::KEY_TYPE];
        $amount = $conversation[TransactionConstants::KEY_AMOUNT];
        $category = $conversation[TransactionConstants::KEY_CATEGORY];
        $subcategory = $conversation[TransactionConstants::KEY_SUBCATEGORY] ?? null;

        $trimmed = trim($text);
        $notes = null;

        if (strtolower($trimmed) !== 'saltar' && ! str_contains($trimmed, TransactionConstants::SKIP_INDICATOR)) {
            $notes = $trimmed;
        }

        $args = $subcategory ? "{$amount} {$category} {$subcategory}" : "{$amount} {$category}";

        if (! $this->transactionService->register($type, $args, $chatId, $username, $errorMessage, $notes)) {
            Cache::forget(TransactionConstants::CACHE_KEY_PREFIX . $chatId);

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $errorMessage,
            ]);

            return;
        }

        Cache::forget(TransactionConstants::CACHE_KEY_PREFIX . $chatId);

        $displayAmount = $type === TransactionConstants::OUTGO ? $amount : $amount;
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
            ->forType($type === TransactionConstants::OUTGO ? TransactionConstants::TYPE_OUTGO : TransactionConstants::TYPE_INCOME)
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
            'text' => $message ?? TransactionConstants::PROMPT_CATEGORY,
            'reply_markup' => $oneTimeKeyboard,
        ]);
    }

    private function sendSubcategoryKeyboard(Api $telegram, string $chatId, Category $category, $subcategories, ?string $message = null): void
    {
        $buttons = $subcategories->map(fn ($cat) => [
            Keyboard::button(['text' => CategoryEmoji::forCategory($cat->name) . ' ' . $cat->name]),
        ])->all();

        $buttons[] = [
            Keyboard::button(['text' => TransactionConstants::CHECK_PREFIX . $category->name]),
        ];

        $keyboard = Keyboard::make([
            'keyboard' => $buttons,
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message ?? TransactionConstants::PROMPT_SUBCATEGORY,
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

    protected function getCurrentDate(): array
    {
        $month = Carbon::now()->month;
        $year = Carbon::now()->year;

        return [
            'month' => $month,
            'year' => $year,
            'monthName' => Carbon::createFromDate($year, $month, 1)->locale(TransactionConstants::LOCALE)->monthName,
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

        $monthName = Carbon::createFromDate($year, $month, 1)->locale(TransactionConstants::LOCALE)->monthName;

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

            if ($maybeYear !== null && ctype_digit($maybeYear) && strlen($maybeYear) === 4) {

                $year = (int)$maybeYear;
            } elseif ($maybeYear !== null && !ctype_alpha($maybeYear)) {

                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Año inválido. Ej: resumen mayo 2024',
                ]);

                return;
            }

            if ($maybeCategory !== null) {
                $category = strtolower($maybeCategory);
            }

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
