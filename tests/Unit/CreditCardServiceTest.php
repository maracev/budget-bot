<?php

namespace Tests\Unit;

use App\Models\CreditCardPurchase;
use App\Services\CreditCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CreditCardServiceTest extends TestCase
{

    protected CreditCardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CreditCardService();
        // Fijar fecha de “hoy” para pruebas deterministas
        Carbon::setTestNow(Carbon::create(2025, 5, 5, 12, 0, 0)); 
    }

    /** @test */
    public function it_registers_single_payment_when_no_installments_are_provided()
    {
        $args = '1500 Amazon';
        $ownerId = '200213027';
        $ownerName = 'usuario_test';

        $result = $this->service->registerPurchase($args, $ownerId, $ownerName, $errorMessage);

        $this->assertTrue($result);
        $this->assertEmpty($errorMessage);

        $this->assertDatabaseCount('credit_card_purchases', 1);

        $purchase = CreditCardPurchase::first();
        $this->assertEquals(1500.00, $purchase->amount);
        $this->assertEquals('Amazon', $purchase->vendor);
        $this->assertEquals('usuario_test', $purchase->owner_name);
        // Hoy 05/05 es antes del cut-off (día 10), ciclo = “2025-05”
        $this->assertEquals('2025-05', $purchase->billing_cycle);
    }

    /** @test */
    public function it_registers_multiple_installments_and_splits_amount_correctly()
    {
        $args = '3000 TiendaX 3';
        $ownerId = '200213027';
        $ownerName = 'usuario_test';

        $result = $this->service->registerPurchase($args, $ownerId, $ownerName, $errorMessage);

        $this->assertTrue($result);
        $this->assertEmpty($errorMessage);

        // Debe crear 3 registros
        $this->assertDatabaseCount('credit_card_purchases', 3);

        $purchases = CreditCardPurchase::orderBy('id')->get();
        // Monto base sería floor(1000/3*100)/100 = 333.33, remanente = 1000 - 333.33*3 = 0.01
        $this->assertEquals(333.33, $purchases[0]->amount);
        $this->assertEquals(333.33, $purchases[1]->amount);
        // Última cuota: 333.33 + 0.01 = 333.34
        $this->assertEquals(333.34, $purchases[2]->amount);

        // Vendor y owner se repiten
        foreach ($purchases as $purchase) {
            $this->assertEquals('TiendaX', $purchase->vendor);
            $this->assertEquals('usuario_test', $purchase->owner_name);
        }

        // Primer ciclo: 2025-05 (hoy = 05/05, antes del corte 10)
        $this->assertEquals('2025-05', $purchases[0]->billing_cycle);
        // Segundo ciclo: junio
        $this->assertEquals('2025-06', $purchases[1]->billing_cycle);
        // Tercer ciclo: julio
        $this->assertEquals('2025-07', $purchases[2]->billing_cycle);
    }

    /** @test */
    public function it_assigns_first_cycle_to_next_month_if_purchase_after_cutoff()
    {
        // Fijar “hoy” al día 11 de mayo (después del corte 10)
        Carbon::setTestNow(Carbon::create(2025, 5, 11, 12, 0, 0));

        $args = '500 PagoWeb 2';
        $ownerId = '200213027';
        $ownerName = 'usuario_test';

        $result = $this->service->registerPurchase($args, $ownerId, $ownerName, $errorMessage);

        $this->assertTrue($result);
        $this->assertEmpty($errorMessage);

        $purchases = CreditCardPurchase::orderBy('id')->get();
        // Hoy = 11/05 => primer ciclo = 2025-06
        $this->assertEquals('2025-06', $purchases[0]->billing_cycle);
        // Segunda cuota = 2025-07
        $this->assertEquals('2025-07', $purchases[1]->billing_cycle);
    }

    /** @test */
    public function it_returns_error_message_for_invalid_format()
    {
        $invalidArgs = 'invalid_format_string';
        $ownerId = '200213027';

        $result = $this->service->registerPurchase($invalidArgs, $ownerId, null, $errorMessage);

        $this->assertFalse($result);
        $this->assertStringContainsString('Formato inválido', $errorMessage);
        $this->assertDatabaseCount('credit_card_purchases', 0);
    }

    /** @test */
    public function it_calculates_monthly_balance_correctly()
    {
        // Crear manualmente registros para tres ciclos distintos
        CreditCardPurchase::factory()->create([
            'amount'       => 200.00,
            'billing_cycle'=> '2025-05',
        ]);
        CreditCardPurchase::factory()->create([
            'amount'       => 300.00,
            'billing_cycle'=> '2025-05',
        ]);
        CreditCardPurchase::factory()->create([
            'amount'       => 150.00,
            'billing_cycle'=> '2025-06',
        ]);

        // Balance de mayo: 200 + 300 = 500
        $balanceMay = $this->service->getMonthlyBalance(5, 2025);
        $this->assertEquals(500.00, $balanceMay);

        // Balance de junio: 150
        $balanceJun = $this->service->getMonthlyBalance(6, 2025);
        $this->assertEquals(150.00, $balanceJun);

        // Sin registros en julio: debe retornar 0
        $balanceJul = $this->service->getMonthlyBalance(7, 2025);
        $this->assertEquals(0.00, $balanceJul);
    }
}
