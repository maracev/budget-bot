<?php

namespace Tests\Unit;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_category_with_fillable_fields()
    {
        $category = Category::create([
            'name' => 'supermercado',
            'type' => 'outgo',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'supermercado',
            'type' => 'outgo',
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    public function test_it_casts_is_active_as_boolean()
    {
        $category = Category::create([
            'name' => 'test',
            'type' => 'both',
        ]);

        $this->assertTrue($category->is_active);
        $this->assertIsBool($category->is_active);
    }

    public function test_it_casts_sort_order_as_integer()
    {
        $category = Category::create([
            'name' => 'test',
            'type' => 'both',
            'sort_order' => 5,
        ]);

        $this->assertIsInt($category->sort_order);
        $this->assertSame(5, $category->sort_order);
    }

    public function test_scope_active_returns_only_active_categories()
    {
        Category::create(['name' => 'activa', 'type' => 'outgo', 'is_active' => true]);
        Category::create(['name' => 'inactiva', 'type' => 'outgo', 'is_active' => false]);

        $active = Category::active()->get();

        $this->assertCount(1, $active);
        $this->assertSame('activa', $active->first()->name);
    }

    public function test_scope_for_type_returns_matching_and_both()
    {
        Category::create(['name' => 'sueldo', 'type' => 'income']);
        Category::create(['name' => 'supermercado', 'type' => 'outgo']);
        Category::create(['name' => 'transferencias', 'type' => 'both']);

        $income = Category::forType('income')->get();
        $outgo = Category::forType('outgo')->get();

        $this->assertCount(2, $income);
        $this->assertTrue($income->pluck('name')->contains('sueldo'));
        $this->assertTrue($income->pluck('name')->contains('transferencias'));

        $this->assertCount(2, $outgo);
        $this->assertTrue($outgo->pluck('name')->contains('supermercado'));
        $this->assertTrue($outgo->pluck('name')->contains('transferencias'));
    }

    public function test_scope_for_type_excludes_non_matching()
    {
        Category::create(['name' => 'sueldo', 'type' => 'income']);
        Category::create(['name' => 'supermercado', 'type' => 'outgo']);

        $income = Category::forType('income')->get();

        $this->assertCount(1, $income);
        $this->assertSame('sueldo', $income->first()->name);
    }

    public function test_default_type_is_both()
    {
        $category = Category::create(['name' => 'test']);

        $this->assertSame('both', $category->type);
    }

    public function test_default_is_active_is_true()
    {
        $category = Category::create(['name' => 'test', 'type' => 'outgo']);

        $this->assertTrue($category->is_active);
    }

    public function test_default_sort_order_is_zero()
    {
        $category = Category::create(['name' => 'test', 'type' => 'outgo']);

        $this->assertSame(0, $category->sort_order);
    }
}
