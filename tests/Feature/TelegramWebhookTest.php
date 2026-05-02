<?php

namespace Tests\Feature;

use App\Http\Controllers\TelegramBotController;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_stores_expense_from_telegram_message()
    {
        // Fake Telegram SDK
        $fakeUpdate = new \Telegram\Bot\Objects\Update([
            'message' => [
                'text' => 'gasto 500 comida',
                'chat' => [
                    'id' => 123456,
                ],
            ],
        ]);

        $telegram = Mockery::mock(\Telegram\Bot\Api::class);
        $telegram->shouldReceive('getWebhookUpdate')
            ->once()
            ->andReturn($fakeUpdate);
        $telegram->shouldReceive('sendMessage')
            ->once()
            ->andReturn(new \Telegram\Bot\Objects\Message([]));

        // Reemplaza la instancia que usa el controlador por la fake
        $this->app->instance(\Telegram\Bot\Api::class, $telegram);

        config([
            'app.telegram_secret_token' => 'secret-token',
            'app.telegram_bot_token' => 'fake-token',
            'app.my_telegram_chat_id' => 123456,
        ]);

        $request = Request::create('/telegram/webhook', 'POST');
        $request->headers->set('X-Telegram-Bot-Api-Secret-Token', 'secret-token');

        $response = $this->app->make(TelegramBotController::class)->webhook($request);

        $this->assertEquals(200, $response->getStatusCode());

        // Verifica que se haya creado la transacción
        $this->assertDatabaseHas('transactions', [
            'owner_id' => 123456,
            'type' => 'outgo',
            'amount' => -500,
            'category' => 'comida',
        ]);
    }

    public function test_it_returns_category_summary_from_telegram_message()
    {
        Carbon::setTestNow('2026-05-10');

        // Fake Telegram update
        $fakeUpdate = new \Telegram\Bot\Objects\Update([
            'message' => [
                'text' => 'resumen mayo supermercado pescaderia',
                'chat' => [
                    'id' => 123456,
                ],
            ],
        ]);

        $telegram = Mockery::mock(\Telegram\Bot\Api::class);

        $telegram->shouldReceive('getWebhookUpdate')
            ->once()
            ->andReturn($fakeUpdate);

        $telegram->shouldReceive('sendMessage')
            ->once()
            ->with(Mockery::on(function ($payload) {

                return str_contains($payload['text'], 'Resumen');
            }))
            ->andReturn(new \Telegram\Bot\Objects\Message([]));


        // Bind fake Telegram instance
        $this->app->instance(\Telegram\Bot\Api::class, $telegram);


        config([
            'app.telegram_secret_token' => 'secret-token',
            'app.telegram_bot_token' => 'fake-token',
            'app.my_telegram_chat_id' => 123456,
        ]);


        // Seed test data
        DB::table('transactions')->insert([
            [
                'owner_id' => 123456,
                'owner_name' => 'test',
                'type' => 'outgo',
                'amount' => -50000,
                'category' => 'supermercado',
                'subcategory' => 'pescaderia',
                'created_at' => '2026-05-05',
                'updated_at' => now(),
            ],
        ]);


        $request = Request::create('/telegram/webhook', 'POST');

        $request->headers->set(
            'X-Telegram-Bot-Api-Secret-Token',
            'secret-token'
        );


        $response = $this->app
            ->make(TelegramBotController::class)
            ->webhook($request);


        $this->assertEquals(200, $response->getStatusCode());
    }
}
