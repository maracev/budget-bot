<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\TransactionService;
use App\Models\Transaction;

class TransactionServiceParseTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_parses_category_and_subcategory_when_present()
    {
        $service = app(TransactionService::class);

        $ok = $service->register('gasto', '500 servicios metrogas', '123', 'maria', $error);

        $this->assertTrue($ok, $error ?? 'Expected to register successfully');

        $this->assertDatabaseHas('transactions', [
            'type' => 'outgo',
            'amount' => 500,
            'category' => 'servicios',
            'subcategory' => 'metrogas',
            'owner_id' => 123,
            'owner_name' => 'maria',
        ]);

        $this->assertSame(500, $service->getLastAmount());
        $this->assertSame('servicios', $service->getLastCategory());
        $this->assertSame('metrogas', $service->getLastSubcategory());
    }

    /** @test */
    public function it_parses_category_without_subcategory_when_missing()
    {
        $service = app(TransactionService::class);

        $ok = $service->register('gasto', '800 supermercado', '123', 'maria', $error);

        $this->assertTrue($ok, $error ?? 'Expected to register successfully');

        $this->assertDatabaseHas('transactions', [
            'type' => 'outgo',
            'amount' => 800,
            'category' => 'supermercado',
            'subcategory' => null,
            'owner_id' => 123,
            'owner_name' => 'maria',
        ]);

        $this->assertSame(800, $service->getLastAmount());
        $this->assertSame('supermercado', $service->getLastCategory());
        $this->assertNull($service->getLastSubcategory());
    }
}

