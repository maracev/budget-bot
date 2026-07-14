<?php

namespace Tests\Unit;

use App\Helpers\CategoryEmoji;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\TelegramCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;
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

    public function test_valid_amount_advances_to_category_selection()
    {
        Category::create(['name' => 'supermercado', 'type' => 'outgo', 'sort_order' => 1]);

        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === '¿Cuál es el monto?');
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertSame('Seleccioná una categoría:', $payload['text']);
                $this->assertArrayHasKey('reply_markup', $payload);

                return true;
            });

        $service->execute($api, '123', 'maria', 'gasto');
        $service->execute($api, '123', 'maria', '500');

        $state = Cache::get('telegram_conversation_123');
        $this->assertSame('category_selection', $state['step']);
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
        Category::create(['name' => 'supermercado', 'type' => 'outgo', 'sort_order' => 1]);

        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === '¿Cuál es el monto?');
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertSame('Seleccioná una categoría:', $payload['text']);
                $this->assertArrayHasKey('reply_markup', $payload);

                return true;
            });
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('Agregá una nota', $payload['text']);
                $this->assertArrayHasKey('reply_markup', $payload);

                return true;
            });
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('Registrado: gasto de $500 en supermercado', $payload['text']);

                return true;
            });

        $service->execute($api, '123', 'maria', 'gasto');
        $service->execute($api, '123', 'maria', '500');
        $service->execute($api, '123', 'maria', 'supermercado');
        $service->execute($api, '123', 'maria', 'Saltar');

        $this->assertDatabaseHas('transactions', [
            'owner_id' => 123,
            'type' => 'outgo',
            'amount' => -500,
            'category' => 'supermercado',
            'owner_name' => 'maria',
            'notes' => 'Saltar',
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

    public function test_category_keyboard_shows_only_main_categories()
    {
        $main = Category::create(['name' => 'supermercado', 'type' => 'outgo', 'sort_order' => 1]);
        Category::insert(['name' => 'alimentos', 'type' => 'outgo', 'sort_order' => 2, 'parent_id' => $main->id]);

        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === '¿Cuál es el monto?');
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertSame('Seleccioná una categoría:', $payload['text']);
                $keyboard = $payload['reply_markup'];
                $this->assertInstanceOf(Keyboard::class, $keyboard);
                $buttons = array_merge(...$keyboard['keyboard']);
                $buttonTexts = array_map(fn ($b) => $b['text'], $buttons);
                $this->assertContains('🛒 supermercado', $buttonTexts);
                $this->assertNotContains('🥩 alimentos', $buttonTexts);

                return true;
            });

        $service->execute($api, '123', 'maria', 'gasto');
        $service->execute($api, '123', 'maria', '500');
    }

    public function test_category_keyboard_filters_by_transaction_type()
    {
        Category::create(['name' => 'sueldo', 'type' => 'income', 'sort_order' => 1]);
        Category::create(['name' => 'supermercado', 'type' => 'outgo', 'sort_order' => 2]);

        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === '¿Cuál es el monto?');
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $keyboard = $payload['reply_markup'];
                $buttons = array_merge(...$keyboard['keyboard']);
                $buttonTexts = array_map(fn ($b) => $b['text'], $buttons);
                $this->assertContains('💰 sueldo', $buttonTexts);
                $this->assertNotContains('🛒 supermercado', $buttonTexts);

                return true;
            });

        $service->execute($api, '123', 'maria', 'ingreso');
        $service->execute($api, '123', 'maria', '1000');
    }

    public function test_category_keyboard_is_ordered_by_sort_order()
    {
        Category::create(['name' => 'b', 'type' => 'outgo', 'sort_order' => 2]);
        Category::create(['name' => 'a', 'type' => 'outgo', 'sort_order' => 1]);

        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === '¿Cuál es el monto?');
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $keyboard = $payload['reply_markup'];
                $buttons = array_merge(...$keyboard['keyboard']);
                $buttonTexts = array_map(fn ($b) => $b['text'], $buttons);
                $emojiA = CategoryEmoji::forCategory('a') . ' a';
                $emojiB = CategoryEmoji::forCategory('b') . ' b';
                $this->assertSame($emojiA, $buttonTexts[0]);
                $this->assertSame($emojiB, $buttonTexts[1]);

                return true;
            });

        $service->execute($api, '123', 'maria', 'gasto');
        $service->execute($api, '123', 'maria', '500');
    }

    public function test_guided_flow_continues_to_subcategory_selection()
    {
        $supermercado = Category::create(['name' => 'supermercado', 'type' => 'outgo', 'sort_order' => 1]);
        Category::insert([
            ['name' => 'alimentos', 'type' => 'outgo', 'sort_order' => 2, 'parent_id' => $supermercado->id],
            ['name' => 'limpieza', 'type' => 'outgo', 'sort_order' => 3, 'parent_id' => $supermercado->id],
        ]);

        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === '¿Cuál es el monto?');
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertSame('Seleccioná una categoría:', $payload['text']);

                return true;
            });
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertSame('Seleccioná una subcategoría:', $payload['text']);
                $keyboard = $payload['reply_markup'];
                $this->assertInstanceOf(Keyboard::class, $keyboard);
                $buttons = array_merge(...$keyboard['keyboard']);
                $buttonTexts = array_map(fn ($b) => $b['text'], $buttons);
                $this->assertContains('🥩 alimentos', $buttonTexts);
                $this->assertContains('🧹 limpieza', $buttonTexts);
                $this->assertContains('✅ supermercado', $buttonTexts);

                return true;
            });

        $service->execute($api, '123', 'maria', 'gasto');
        $service->execute($api, '123', 'maria', '500');
        $service->execute($api, '123', 'maria', 'supermercado');

        $state = Cache::get('telegram_conversation_123');
        $this->assertSame('subcategory_selection', $state['step']);
        $this->assertSame('supermercado', $state['category']);
    }

    public function test_guided_flow_registers_with_subcategory()
    {
        $supermercado = Category::create(['name' => 'supermercado', 'type' => 'outgo', 'sort_order' => 1]);
        Category::insert(['name' => 'alimentos', 'type' => 'outgo', 'sort_order' => 2, 'parent_id' => $supermercado->id]);

        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === '¿Cuál es el monto?');
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertSame('Seleccioná una categoría:', $payload['text']);

                return true;
            });
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertSame('Seleccioná una subcategoría:', $payload['text']);

                return true;
            });
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('Agregá una nota', $payload['text']);

                return true;
            });
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('Registrado: gasto de $500 en supermercado / alimentos', $payload['text']);

                return true;
            });

        $service->execute($api, '123', 'maria', 'gasto');
        $service->execute($api, '123', 'maria', '500');
        $service->execute($api, '123', 'maria', 'supermercado');
        $service->execute($api, '123', 'maria', 'alimentos');
        $service->execute($api, '123', 'maria', '⏭ Saltar');

        $this->assertDatabaseHas('transactions', [
            'owner_id' => 123,
            'type' => 'outgo',
            'amount' => -500,
            'category' => 'supermercado',
            'subcategory' => 'alimentos',
        ]);
    }

    public function test_guided_flow_registers_directly_under_main_category()
    {
        $supermercado = Category::create(['name' => 'supermercado', 'type' => 'outgo', 'sort_order' => 1]);
        Category::insert(['name' => 'alimentos', 'type' => 'outgo', 'sort_order' => 2, 'parent_id' => $supermercado->id]);

        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === '¿Cuál es el monto?');
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertSame('Seleccioná una categoría:', $payload['text']);

                return true;
            });
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertSame('Seleccioná una subcategoría:', $payload['text']);

                return true;
            });
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('Agregá una nota', $payload['text']);

                return true;
            });
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('Registrado: gasto de $500 en supermercado', $payload['text']);
                $this->assertStringNotContainsString('/', $payload['text']);

                return true;
            });

        $service->execute($api, '123', 'maria', 'gasto');
        $service->execute($api, '123', 'maria', '500');
        $service->execute($api, '123', 'maria', 'supermercado');
        $service->execute($api, '123', 'maria', '✅ supermercado');
        $service->execute($api, '123', 'maria', 'Saltar');

        $this->assertDatabaseHas('transactions', [
            'owner_id' => 123,
            'type' => 'outgo',
            'amount' => -500,
            'category' => 'supermercado',
            'subcategory' => null,
        ]);
    }

    public function test_invalid_category_shows_error_and_redisplays_keyboard()
    {
        Category::create(['name' => 'supermercado', 'type' => 'outgo', 'sort_order' => 1]);

        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === '¿Cuál es el monto?');
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertSame('Seleccioná una categoría:', $payload['text']);

                return true;
            });
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('Categoría no encontrada', $payload['text']);
                $this->assertArrayHasKey('reply_markup', $payload);

                return true;
            });

        $service->execute($api, '123', 'maria', 'gasto');
        $service->execute($api, '123', 'maria', '500');
        $service->execute($api, '123', 'maria', 'categoria_invalida');
    }

    public function test_guided_flow_accepts_note_and_shows_it_in_confirmation()
    {
        Category::create(['name' => 'supermercado', 'type' => 'outgo', 'sort_order' => 1]);

        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === '¿Cuál es el monto?');
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === 'Seleccioná una categoría:');
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => str_contains($p['text'], 'Agregá una nota'));
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(function ($payload) {
                $this->assertStringContainsString('Registrado: gasto de $500 en supermercado — compra semanal', $payload['text']);

                return true;
            });

        $service->execute($api, '123', 'maria', 'gasto');
        $service->execute($api, '123', 'maria', '500');
        $service->execute($api, '123', 'maria', 'supermercado');
        $service->execute($api, '123', 'maria', 'compra semanal');

        $this->assertDatabaseHas('transactions', [
            'owner_id' => 123,
            'type' => 'outgo',
            'amount' => -500,
            'category' => 'supermercado',
            'notes' => 'compra semanal',
        ]);
    }

    public function test_cancelar_from_note_step_clears_conversation()
    {
        Category::create(['name' => 'supermercado', 'type' => 'outgo', 'sort_order' => 1]);

        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === '¿Cuál es el monto?');
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === 'Seleccioná una categoría:');
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => str_contains($p['text'], 'Agregá una nota'));
        $api->shouldReceive('sendMessage')
            ->once()
            ->ordered()
            ->withArgs(fn ($p) => $p['text'] === 'Operación cancelada.');

        $service->execute($api, '123', 'maria', 'gasto');
        $service->execute($api, '123', 'maria', '500');
        $service->execute($api, '123', 'maria', 'supermercado');
        $service->execute($api, '123', 'maria', 'cancelar');

        $this->assertNull(Cache::get('telegram_conversation_123'));
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_emoji_mapper_returns_emoji_for_known_category()
    {
        $this->assertSame('🛒', CategoryEmoji::forCategory('supermercado'));
        $this->assertSame('💰', CategoryEmoji::forCategory('sueldo'));
        $this->assertSame('🚗', CategoryEmoji::forCategory('auto'));
    }

    public function test_emoji_mapper_is_case_insensitive()
    {
        $this->assertSame('🛒', CategoryEmoji::forCategory('Supermercado'));
        $this->assertSame('🛒', CategoryEmoji::forCategory('SUPERMERCADO'));
    }

    public function test_emoji_mapper_returns_fallback_for_unknown_category()
    {
        $this->assertSame('📁', CategoryEmoji::forCategory('categoria_inexistente'));
    }
}
