<?php

namespace App\Validators;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FiltroTxValidator
{
    /**
     * Parse and validate args for filtro_tx.
     *
     * @return array{type: ?string, category: ?string, month: int, year: int, month_provided: bool, year_provided: bool, inverse_type_map: array<string, string>}|array{error: string}
     */
    public function parse(string $args): array
    {
        $tokens = preg_split('/\s+/', $args, -1, PREG_SPLIT_NO_EMPTY);

        $typeMap = config('type_mapper');
        $inverseTypeMap = array_flip($typeMap);
        $monthMap = config('month_map');

        $typeRaw = null;
        $category = null;
        $month = null;
        $year = null;
        $monthProvided = false;
        $yearProvided = false;

        foreach ($tokens as $token) {
            $normalized = strtolower($token);

            if (! $typeRaw && (isset($typeMap[$normalized]) || isset($inverseTypeMap[$normalized]))) {
                $typeRaw = $normalized;

                continue;
            }

            if (! $month && (isset($monthMap[$normalized]) || (is_numeric($normalized) && (int) $normalized >= 1 && (int) $normalized <= 12))) {
                $month = isset($monthMap[$normalized]) ? $monthMap[$normalized] : (int) $normalized;
                $monthProvided = true;

                continue;
            }

            if (! $year && is_numeric($normalized) && strlen($normalized) === 4) {
                $year = (int) $normalized;
                $yearProvided = true;

                continue;
            }

            if (! $category) {
                $category = $token;
            }
        }

        $validator = Validator::make(
            [
                'type_raw' => $typeRaw,
                'category' => $category,
                'month' => $month,
                'year' => $year,
            ],
            [
                'type_raw' => ['nullable', Rule::in(array_unique(array_merge(array_keys($typeMap), array_values($typeMap))))],
                'category' => ['nullable', 'string'],
                'month' => ['nullable', 'integer', 'between:1,12'],
                'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            ],
            [
                'type_raw.in' => 'Tipo inválido. Usá ingreso/gasto.',
                'month.between' => 'Mes inválido. Usá nombre (mayo) o número (5).',
                'year.min' => 'Año inválido. Usá un año con 4 dígitos (ej: 2024).',
                'year.max' => 'Año inválido. Usá un año con 4 dígitos (ej: 2024).',
            ]
        );

        if ($validator->fails()) {
            return ['error' => $validator->errors()->first()];
        }

        $type = $typeRaw ? ($typeMap[$typeRaw] ?? $typeRaw) : null;
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        return [
            'type' => $type,
            'category' => $category,
            'month' => $month ?? $currentMonth,
            'year' => $year ?? $currentYear,
            'month_provided' => $monthProvided,
            'year_provided' => $yearProvided,
            'inverse_type_map' => $inverseTypeMap,
        ];
    }
}
