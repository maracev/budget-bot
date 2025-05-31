<?php 

if (!function_exists('getMonthNumber')) {
    /**
     * Devuelve el número de mes (1-12) para un nombre de mes en español.
     */
    function getMonthNumber(string $mes): ?int
    {
        $mapa = config('month_map');
        return $mapa[strtolower($mes)] ?? null;
    }
}

if (!function_exists('translateType')) {
    function translateType(string $input): ?string
    {
        $mapper = config('type_mapper');
        return $mapper[strtolower(trim($input))] ?? null;
    }
}
