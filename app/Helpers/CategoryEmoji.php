<?php

namespace App\Helpers;

class CategoryEmoji
{
    private static array $emojiMap = [
        'supermercado' => '🛒',
        'sueldo' => '💰',
        'bono' => '🎁',
        'caución' => '🏦',
        'fondo' => '📦',
        'transferencias' => '🔄',
        'ahorros' => '🐷',
        'auto' => '🚗',
        'belleza' => '💅',
        'educación' => '📚',
        'extras' => '📎',
        'gastos' => '📋',
        'impuestos' => '🧾',
        'indumentaria' => '👕',
        'mascotas' => '🐾',
        'préstamos' => '🤝',
        'salidas' => '🍕',
        'salud' => '🏥',
        'servicios' => '🔧',
        'tarjetas' => '💳',
        'transporte' => '🚌',
        'vivienda' => '🏠',
        'alimentos' => '🥩',
        'limpieza' => '🧹',
        'dietética' => '🥗',
        'perfumería' => '🧴',
    ];

    public static function forCategory(string $categoryName): string
    {
        return self::$emojiMap[strtolower($categoryName)] ?? '📁';
    }
}
