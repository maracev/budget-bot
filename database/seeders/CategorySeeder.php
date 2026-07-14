<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Supermercado',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Carnicería'],
                    ['name' => 'Verdulería'],
                    ['name' => 'Huevos'],
                    ['name' => 'Lácteos'],
                    ['name' => 'Almacén'],
                    ['name' => 'Congelados'],
                    ['name' => 'Dietética'],
                    ['name' => 'Bebidas'],
                    ['name' => 'Prod. Limpieza'],
                    ['name' => 'Higiene personal'],
                    ['name' => 'Otros'],
                ],
            ],
            [
                'name' => 'Vivienda',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Limpieza'],
                    ['name' => 'Expensas'],
                    ['name' => 'Alquiler'],
                    ['name' => 'Reparaciones'],
                    ['name' => 'Muebles'],
                    ['name' => 'Decoración'],
                    ['name' => 'Electrodomésticos'],
                    ['name' => 'Ferretería'],
                ],
            ],
            [
                'name' => 'Auto',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Combustible'],
                    ['name' => 'Seguro'],
                    ['name' => 'Patente'],
                    ['name' => 'Service'],
                    ['name' => 'Mecánico'],
                    ['name' => 'Lavado'],
                    ['name' => 'Estacionamiento'],
                    ['name' => 'Peajes'],
                    ['name' => 'Matafuego'],
                    ['name' => 'VTV'],
                ],
            ],
            [
                'name' => 'Salud',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Terapia'],
                    ['name' => 'Pilates'],
                    ['name' => 'Suplementos'],
                    ['name' => 'Medicinas'],
                    ['name' => 'Consultas médicas'],
                    ['name' => 'Laboratorios'],
                    ['name' => 'Odontología'],
                    ['name' => 'Oftalmología'],
                    ['name' => 'Farmacia'],
                ],
            ],
            [
                'name' => 'Mascotas',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Veterinaria'],
                    ['name' => 'Medicamentos'],
                    ['name' => 'Alimentos'],
                    ['name' => 'Estudios'],
                    ['name' => 'Vacunas'],
                    ['name' => 'Piedras'],
                ],
            ],
            [
                'name' => 'Belleza',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Cosmética'],
                    ['name' => 'Skincare'],
                    ['name' => 'Perfumería'],
                    ['name' => 'Peluquería'],
                    ['name' => 'Manicura'],
                ],
            ],
            [
                'name' => 'Indumentaria',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Ropa'],
                    ['name' => 'Calzado'],
                    ['name' => 'Accesorios'],
                ],
            ],
            [
                'name' => 'Educación',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Universidad'],
                    ['name' => 'Cursos'],
                    ['name' => 'Libros'],
                    ['name' => 'Certificaciones'],
                    ['name' => 'Idiomas'],
                ],
            ],
            [
                'name' => 'Tecnología',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Hardware'],
                    ['name' => 'Software'],
                    ['name' => 'Hosting'],
                    ['name' => 'Dominios'],
                    ['name' => 'Licencias'],
                ],
            ],
            [
                'name' => 'Suscripciones',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'ChatGPT'],
                    ['name' => 'Netflix'],
                    ['name' => 'Spotify'],
                    ['name' => 'PHP Architect'],
                    ['name' => 'Google One'],
                    ['name' => 'iCloud'],
                    ['name' => 'Amazon Prime'],
                    ['name' => 'Disney+'],
                    ['name' => 'YouTube Premium'],
                    ['name' => 'HBO Max'],
                ],
            ],
            [
                'name' => 'Seguros',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Hogar'],
                    ['name' => 'Vida'],
                    ['name' => 'Celular'],
                ],
            ],
            [
                'name' => 'Servicios',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Internet'],
                    ['name' => 'Luz'],
                    ['name' => 'Gas'],
                    ['name' => 'Agua'],
                    ['name' => 'Municipal'],
                    ['name' => 'ARBA'],
                    ['name' => 'ABL'],
                ],
            ],
            [
                'name' => 'Tarjeta de crédito',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Visa'],
                    ['name' => 'Mastercard'],
                    ['name' => 'Amex'],
                ],
            ],
            [
                'name' => 'Salidas',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Restaurantes'],
                    ['name' => 'Café'],
                    ['name' => 'Amigos'],
                    ['name' => 'Cine'],
                    ['name' => 'Teatro'],
                    ['name' => 'Viajes'],
                ],
            ],
            [
                'name' => 'Finanzas',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Ahorros'],
                    ['name' => 'Fondo de emergencia'],
                    ['name' => 'USD'],
                    ['name' => 'Inversiones'],
                    ['name' => 'Comisiones bancarias'],
                ],
            ],
            [
                'name' => 'Préstamos',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Familia'],
                    ['name' => 'Banco'],
                    ['name' => 'Otro'],
                ],
            ],
            [
                'name' => 'Regalos',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Cumpleaños'],
                    ['name' => 'Navidad'],
                    ['name' => 'Donaciones'],
                ],
            ],
            [
                'name' => 'Extras',
                'type' => 'outgo',
                'children' => [
                    ['name' => 'Imprevistos'],
                    ['name' => 'Varios'],
                ],
            ],
            [
                'name' => 'Sueldo',
                'type' => 'income',
                'children' => [],
            ],
            [
                'name' => 'Bono',
                'type' => 'income',
                'children' => [],
            ],
            [
                'name' => 'Aporte',
                'type' => 'income',
                'children' => [],
            ],
            [
                'name' => 'Reintegros',
                'type' => 'income',
                'children' => [],
            ],
            [
                'name' => 'Intereses',
                'type' => 'income',
                'children' => [],
            ],
            [
                'name' => 'Venta',
                'type' => 'income',
                'children' => [],
            ],
            [
                'name' => 'Regalos platita',
                'type' => 'income',
                'children' => [],
            ],
        ];

        foreach ($categories as $sortOrder => $categoryData) {
            $children = $categoryData['children'];
            unset($categoryData['children']);

            $parent = Category::create([
                ...$categoryData,
                'sort_order' => $sortOrder + 1,
            ]);

            foreach ($children as $childSortOrder => $child) {
                Category::create([
                    ...$child,
                    'type' => $parent->type,
                    'parent_id' => $parent->id,
                    'sort_order' => $childSortOrder + 1,
                ]);
            }
        }
    }
}
