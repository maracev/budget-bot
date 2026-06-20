<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {

        $supermercado = Category::create([
            'name' => 'supermercado',
            'type' => 'outgo',
            'sort_order' => 22
        ]);

        Category::insert([
            [
            'name' => 'alimentos',
            'type' => 'outgo',
            'sort_order' => 23,
            'parent_id' => $supermercado->id,
            ],
            [
                'name' => 'limpieza',
                'type' => 'outgo',
                'sort_order' => 24,
                'parent_id' => $supermercado->id,
            ],
            [
                'name' => 'dietética',
                'type' => 'outgo',
                'sort_order' => 25,
                'parent_id' => $supermercado->id,
            ],
            [
                'name' => 'perfumería',
                'type' => 'outgo',
                'sort_order' => 26,
                'parent_id' => $supermercado->id,
            ],
        ]);

        $categories = [
            ['name' => 'sueldo', 'type' => 'income', 'sort_order' => 1],
            ['name' => 'bono', 'type' => 'income', 'sort_order' => 2],
            ['name' => 'caución', 'type' => 'income', 'sort_order' => 3],
            ['name' => 'fondo', 'type' => 'income', 'sort_order' => 4],
            ['name' => 'transferencias', 'type' => 'both', 'sort_order' => 5],
            ['name' => 'ahorros', 'type' => 'outgo', 'sort_order' => 6],
            ['name' => 'auto', 'type' => 'outgo', 'sort_order' => 8],
            ['name' => 'belleza', 'type' => 'outgo', 'sort_order' => 9],
            ['name' => 'educación', 'type' => 'outgo', 'sort_order' => 10],
            ['name' => 'extras', 'type' => 'outgo', 'sort_order' => 11],
            ['name' => 'gastos', 'type' => 'outgo', 'sort_order' => 13],
            ['name' => 'impuestos', 'type' => 'outgo', 'sort_order' => 14],
            ['name' => 'indumentaria', 'type' => 'outgo', 'sort_order' => 15],
            ['name' => 'mascotas', 'type' => 'outgo', 'sort_order' => 17],
            ['name' => 'préstamos', 'type' => 'outgo', 'sort_order' => 18],
            ['name' => 'salidas', 'type' => 'outgo', 'sort_order' => 19],
            ['name' => 'salud', 'type' => 'outgo', 'sort_order' => 20],
            ['name' => 'servicios', 'type' => 'outgo', 'sort_order' => 21],
            ['name' => 'tarjetas', 'type' => 'outgo', 'sort_order' => 23],
            ['name' => 'transporte', 'type' => 'outgo', 'sort_order' => 25],
            ['name' => 'vivienda', 'type' => 'outgo', 'sort_order' => 26],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
