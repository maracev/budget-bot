<?php

namespace Tests\Unit;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_categories()
    {
        $this->seed(CategorySeeder::class);

        $this->assertDatabaseCount('categories', 129);
    }

    public function test_seeder_creates_income_categories()
    {
        $this->seed(CategorySeeder::class);

        $incomeCategories = Category::forType('income')->get();

        $this->assertGreaterThan(0, $incomeCategories->count());

        $incomeNames = $incomeCategories->pluck('name')->map(fn ($n) => strtolower($n));
        $this->assertTrue($incomeNames->contains('sueldo'));
    }

    public function test_seeder_creates_outgo_categories()
    {
        $this->seed(CategorySeeder::class);

        $outgoCategories = Category::forType('outgo')->get();

        $this->assertGreaterThan(0, $outgoCategories->count());

        $outgoNames = $outgoCategories->pluck('name')->map(fn ($n) => strtolower($n));
        $this->assertTrue($outgoNames->contains('supermercado'));
    }

    public function test_seeder_creates_no_both_type_categories()
    {
        $this->seed(CategorySeeder::class);

        $both = Category::where('type', 'both')->get();

        $this->assertCount(0, $both);
    }

    public function test_seeder_sets_sort_order()
    {
        $this->seed(CategorySeeder::class);

        $categories = Category::orderBy('sort_order')->get();
        $sorted = $categories->sortBy('sort_order')->values();

        $this->assertEquals($sorted->pluck('id'), $categories->pluck('id'));
    }
}
