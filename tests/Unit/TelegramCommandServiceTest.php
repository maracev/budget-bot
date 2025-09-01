<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\TelegramCommandService;
use App\Services\TransactionService;
use Telegram\Bot\Api;

class TelegramCommandServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function execute_records_transaction_with_category_and_subcategory_and_replies()
    {
        $service = app(TelegramCommandService::class);

        // Mock Telegram API to assert reply message
        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertEquals('123', (string)$payload['chat_id']);
                $this->assertStringContainsString('Registrado: gasto de $500 en servicios / metrogas', $payload['text']);
                return true;
            });

        $service->execute($api, '123', 'maria', 'gasto 500 servicios metrogas');

        $this->assertDatabaseHas('transactions', [
            'owner_id' => 123,
            'type' => 'outgo',
            'amount' => 500,
            'category' => 'servicios',
            'subcategory' => 'metrogas',
        ]);
    }

    /** @test */
    public function execute_records_transaction_without_subcategory_and_replies()
    {
        $service = app(TelegramCommandService::class);

        $api = \Mockery::mock(Api::class);
        $api->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($payload) {
                $this->assertEquals('123', (string)$payload['chat_id']);
                $this->assertStringContainsString('Registrado: gasto de $800 en supermercado', $payload['text']);
                $this->assertStringNotContainsString('/', $payload['text']);
                return true;
            });

        $service->execute($api, '123', 'maria', 'gasto 800 supermercado');

        $this->assertDatabaseHas('transactions', [
            'owner_id' => 123,
            'type' => 'outgo',
            'amount' => 800,
            'category' => 'supermercado',
            'subcategory' => null,
        ]);
    }
}

