<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Services\TelegramCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Telegram\Bot\Api;
use Tests\TestCase;

class TelegramCategoriasCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_categorias_lists_all_active_categories()
    {
        Category::create(['name' => 'sueldo', 'type' => 'income', 'sort_order' => 1]);
        Category::create(['name' => 'supermercado', 'type' => 'outgo', 'sort_order' => 2]);
        Category::create(['name' => 'transferencias', 'type' => 'both', 'sort_order' => 3]);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('Categorías disponibles:', $payload['text']);
                $this->assertStringContainsString('sueldo', $payload['text']);
                $this->assertStringContainsString('supermercado', $payload['text']);
                $this->assertStringContainsString('transferencias (ambos)', $payload['text']);

                return true;
            });

        $service = app(TelegramCommandService::class);
        $service->execute($api, '123', null, 'categorias');
    }

    public function test_categorias_gasto_filters_outgo_categories()
    {
        Category::create(['name' => 'sueldo', 'type' => 'income', 'sort_order' => 1]);
        Category::create(['name' => 'supermercado', 'type' => 'outgo', 'sort_order' => 2]);
        Category::create(['name' => 'transferencias', 'type' => 'both', 'sort_order' => 3]);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('supermercado', $payload['text']);
                $this->assertStringContainsString('transferencias', $payload['text']);
                $this->assertStringNotContainsString('sueldo', $payload['text']);
                $this->assertStringNotContainsString('(ambos)', $payload['text']);

                return true;
            });

        $service = app(TelegramCommandService::class);
        $service->execute($api, '123', null, 'categorias gasto');
    }

    public function test_categorias_ingreso_filters_income_categories()
    {
        Category::create(['name' => 'sueldo', 'type' => 'income', 'sort_order' => 1]);
        Category::create(['name' => 'supermercado', 'type' => 'outgo', 'sort_order' => 2]);
        Category::create(['name' => 'transferencias', 'type' => 'both', 'sort_order' => 3]);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('sueldo', $payload['text']);
                $this->assertStringContainsString('transferencias', $payload['text']);
                $this->assertStringNotContainsString('supermercado', $payload['text']);
                $this->assertStringNotContainsString('(ambos)', $payload['text']);

                return true;
            });

        $service = app(TelegramCommandService::class);
        $service->execute($api, '123', null, 'categorias ingreso');
    }

    public function test_categorias_shows_message_when_no_categories()
    {
        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('sin categorías configuradas', $payload['text']);

                return true;
            });

        $service = app(TelegramCommandService::class);
        $service->execute($api, '123', null, 'categorias');
    }

    public function test_categorias_respects_sort_order()
    {
        Category::create(['name' => 'z', 'type' => 'outgo', 'sort_order' => 2]);
        Category::create(['name' => 'a', 'type' => 'outgo', 'sort_order' => 1]);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $posA = strpos($payload['text'], 'a');
                $posZ = strpos($payload['text'], 'z');
                $this->assertNotFalse($posA);
                $this->assertNotFalse($posZ);
                $this->assertLessThan($posZ, $posA);

                return true;
            });

        $service = app(TelegramCommandService::class);
        $service->execute($api, '123', null, 'categorias');
    }

    public function test_categorias_excludes_inactive_categories()
    {
        Category::create(['name' => 'activa', 'type' => 'outgo', 'is_active' => true]);
        Category::create(['name' => 'inactiva', 'type' => 'outgo', 'is_active' => false]);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('activa', $payload['text']);
                $this->assertStringNotContainsString('inactiva', $payload['text']);

                return true;
            });

        $service = app(TelegramCommandService::class);
        $service->execute($api, '123', null, 'categorias');
    }
}
