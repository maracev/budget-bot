<?php

namespace Tests\Unit;

use App\Models\Transaction;
use App\Services\TelegramCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Telegram\Bot\Api;
use Tests\TestCase;

class TelegramCommandServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        \Mockery::close();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_execute_records_transaction_with_category_and_subcategory_and_replies()
    {
        $service = app(TelegramCommandService::class);

        // Mock Telegram API to assert reply message
        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertEquals('123', (string) $payload['chat_id']);
                $this->assertStringContainsString('Registrado: gasto de $500 en servicios / metrogas', $payload['text']);

                return true;
            });

        $service->execute($api, '123', 'maria', 'gasto 500 servicios metrogas');

        $this->assertDatabaseHas('transactions', [
            'owner_id' => 123,
            'type' => 'outgo',
            'amount' => -500,
            'category' => 'servicios',
            'subcategory' => 'metrogas',
        ]);
    }

    public function test_execute_records_transaction_without_subcategory_and_replies()
    {
        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertEquals('123', (string) $payload['chat_id']);
                $this->assertStringContainsString('Registrado: gasto de $800 en supermercado', $payload['text']);
                $this->assertStringNotContainsString('/', $payload['text']);

                return true;
            });

        $service->execute($api, '123', 'maria', 'gasto 800 supermercado');

        $this->assertDatabaseHas('transactions', [
            'owner_id' => 123,
            'type' => 'outgo',
            'amount' => -800,
            'category' => 'supermercado',
            'subcategory' => null,
        ]);
    }

    public function test_filtro_balance_accepts_month_and_year()
    {
        Carbon::setTestNow(Carbon::create(2024, 6, 15, 12, 0, 0));

        Transaction::create([
            'type' => 'income',
            'amount' => 1000,
            'category' => 'sueldo',
            'owner_id' => 1,
            'created_at' => Carbon::create(2023, 3, 10, 10),
        ]);

        Transaction::create([
            'type' => 'outgo',
            'amount' => -200,
            'category' => 'supermercado',
            'owner_id' => 1,
            'created_at' => Carbon::create(2023, 3, 12, 12),
        ]);

        // Extra data from another year that should not be included
        Transaction::create([
            'type' => 'income',
            'amount' => 999,
            'category' => 'extra',
            'owner_id' => 1,
            'created_at' => Carbon::create(2024, 3, 1, 9),
        ]);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertSame('123', (string) $payload['chat_id']);
                $this->assertStringContainsString('Balance:', $payload['text']);
                $this->assertStringContainsString('Resumen de marzo 2023', $payload['text']);
                $this->assertStringContainsString('<b>income</b> - sueldo: 1000', $payload['text']);
                $this->assertStringContainsString('<b>outgo</b> - supermercado: -200', $payload['text']);
                $this->assertStringNotContainsString('999', $payload['text']);

                return true;
            });

        $service = app(TelegramCommandService::class);
        $service->execute($api, '123', null, 'filtro_balance marzo 2023');
    }

    public function test_filtro_balance_defaults_to_current_year_when_not_provided()
    {
        Carbon::setTestNow(Carbon::create(2024, 5, 10, 9, 0, 0));

        Transaction::create([
            'type' => 'income',
            'amount' => 500,
            'category' => 'sueldo',
            'owner_id' => 1,
            'created_at' => Carbon::create(2024, 3, 5, 8),
        ]);

        Transaction::create([
            'type' => 'income',
            'amount' => 777,
            'category' => 'otro',
            'owner_id' => 1,
            'created_at' => Carbon::create(2023, 3, 5, 8),
        ]);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('Resumen de marzo 2024', $payload['text']);
                $this->assertStringContainsString('500', $payload['text']);
                $this->assertStringNotContainsString('777', $payload['text']);

                return true;
            });

        $service = app(TelegramCommandService::class);
        $service->execute($api, '321', null, 'filtro_balance marzo');
    }

    public function test_filtro_balance_rejects_invalid_month_and_year()
    {
        Carbon::setTestNow(Carbon::create(2024, 5, 10, 9, 0, 0));

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('Mes inválido', $payload['text']);

                return true;
            });

        $service = app(TelegramCommandService::class);
        $service->execute($api, '999', null, 'filtro_balance noesmes');

        $apiYear = \Mockery::mock(Api::class);
        $apiYear->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('Año inválido', $payload['text']);

                return true;
            });

        $service->execute($apiYear, '999', null, 'filtro_balance marzo 20a4');
    }
}
