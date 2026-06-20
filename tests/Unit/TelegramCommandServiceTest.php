<?php

namespace Tests\Unit;

use App\Models\Transaction;
use App\Services\TelegramCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

        $tx1 = Transaction::create([
            'type' => 'income',
            'amount' => 1000,
            'category' => 'sueldo',
            'owner_id' => 1,
        ]);
        $tx1->created_at = Carbon::create(2023, 3, 10, 10);
        $tx1->save();

        $tx2 = Transaction::create([
            'type' => 'outgo',
            'amount' => -200,
            'category' => 'supermercado',
            'owner_id' => 1,
        ]);
        $tx2->created_at = Carbon::create(2023, 3, 12, 12);
        $tx2->save();

        // Extra data from another year that should not be included
        $tx3 = Transaction::create([
            'type' => 'income',
            'amount' => 999,
            'category' => 'extra',
            'owner_id' => 1,
        ]);
        $tx3->created_at = Carbon::create(2024, 3, 1, 9);
        $tx3->save();

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

        $tx1 = Transaction::create([
            'type' => 'income',
            'amount' => 500,
            'category' => 'sueldo',
            'owner_id' => 1,
        ]);
        $tx1->created_at = Carbon::create(2024, 3, 5, 8);
        $tx1->save();

        $tx2 = Transaction::create([
            'type' => 'income',
            'amount' => 777,
            'category' => 'otro',
            'owner_id' => 1,
        ]);
        $tx2->created_at = Carbon::create(2023, 3, 5, 8);
        $tx2->save();

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

    public function test_slash_gasto_starts_conversation()
    {
        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertSame('123', (string) $payload['chat_id']);
                $this->assertSame('¿Cuál es el monto?', $payload['text']);

                return true;
            });

        $service->execute($api, '123', 'maria', 'gasto');

        $state = Cache::get('telegram_conversation_123');
        $this->assertNotNull($state);
        $this->assertSame('amount', $state['step']);
        $this->assertSame('gasto', $state['type']);
    }

    public function test_valid_amount_advances_to_category_step()
    {
        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === '¿Cuál es el monto?');
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === '¿En qué categoría?');

        $service->execute($api, '123', 'maria', 'gasto');
        $service->execute($api, '123', 'maria', '500');

        $state = Cache::get('telegram_conversation_123');
        $this->assertSame('category', $state['step']);
        $this->assertSame(500, $state['amount']);
    }

    public function test_invalid_amount_asks_to_retry()
    {
        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($p) => $p['text'] === '¿Cuál es el monto?');

        $service->execute($api, '123', 'maria', 'gasto');

        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('Monto inválido', $payload['text']);

                return true;
            });

        $service->execute($api, '123', 'maria', 'abc');

        $state = Cache::get('telegram_conversation_123');
        $this->assertSame('amount', $state['step']);
    }

    public function test_expense_is_registered_when_category_is_chosen()
    {
        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($p) => $p['text'] === '¿Cuál es el monto?');
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn ($p) => $p['text'] === '¿En qué categoría?');
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('Registrado: gasto de $500 en supermercado', $payload['text']);

                return true;
            });

        $service->execute($api, '123', 'maria', 'gasto');
        $service->execute($api, '123', 'maria', '500');
        $service->execute($api, '123', 'maria', 'supermercado');

        $this->assertDatabaseHas('transactions', [
            'owner_id' => 123,
            'type' => 'outgo',
            'amount' => -500,
            'category' => 'supermercado',
            'owner_name' => 'maria',
        ]);

        $this->assertNull(Cache::get('telegram_conversation_123'));
    }

    public function test_linear_gasto_still_works()
    {
        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
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

        $state = Cache::get('telegram_conversation_123');
        $this->assertNull($state);
    }

    public function test_cancelar_clears_conversation()
    {
        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === '¿Cuál es el monto?');
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === 'Operación cancelada.');

        $service->execute($api, '123', 'maria', 'gasto');
        $service->execute($api, '123', 'maria', 'cancelar');

        $this->assertNull(Cache::get('telegram_conversation_123'));
    }
}
