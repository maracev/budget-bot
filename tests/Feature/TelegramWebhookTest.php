<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use App\Models\Movimiento;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate');
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

        $telegram = new \Telegram\Bot\Api('fake-token');
        $telegram->setWebhookUpdate($fakeUpdate);

        // Reemplaza la instancia que usa el controlador por la fake
        $this->app->instance(\Telegram\Bot\Api::class, $telegram);

        config([
            'app.telegram_secret_token' => 'secret-token',
            'app.telegram_bot_token' => 'fake-token',
            'app.my_telegram_chat_id' => 123456,
        ]);

        // Llama al endpoint (no importa el payload real, ya inyectamos el update)
        $this->post('/telegram/webhook', [], [
            'X-Telegram-Bot-Api-Secret-Token' => 'secret-token',
        ])->assertOk();

        // Verifica que se haya creado la transacción
        $this->assertDatabaseHas('movimientos', [
            'chat_id' => 123456,
            'type' => 'gasto',
            'amount' => 500,
            'category' => 'comida',
        ]);
    }
}
