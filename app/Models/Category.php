<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Categories support one level of nesting.
 *
 * The parent category represents the budgeting group.
 * Example:
 *
 * Supermercado
 * ├─ Alimentos
 * ├─ Limpieza
 * └─ Dietética
 *
 * Salud
 * ├─ Farmacia
 * └─ Médico
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'is_active', 'sort_order', 'parent_id', 'notes'];

    protected $attributes = [
        'type' => 'both',
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForType($query, string $type)
    {
        return $query->whereIn('type', [$type, 'both']);
    }

    public function isMainCategory(): bool
    {
        return $this->parent_id === null;
    }

    public function isSubcategory(): bool
    {
        return $this->parent_id !== null;
    }
}
