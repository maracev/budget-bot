<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFilterTest extends TestCase
{
    use RefreshDatabase;

    private function login(): User
    {
        $user = User::create([
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $this->actingAs($user);

        return $user;
    }

    /** @test */
    public function it_rejects_invalid_date_filters_instead_of_crashing()
    {
        $this->login();

        $this->get('/reportes?date_from=abc&date_to=xyz')
            ->assertSessionHasErrors('date_from');

        $this->get('/reportes?date_from=2026-01-01&date_to=not-a-date')
            ->assertSessionHasErrors('date_to');

        $this->get('/reportes?date_from=2026-02-01&date_to=2026-01-01')
            ->assertSessionHasErrors('date_to');
    }

    /** @test */
    public function it_rejects_invalid_date_filters_on_movements()
    {
        $this->login();

        $this->get('/transactions?date_from=abc')
            ->assertSessionHasErrors('date_from');

        $this->get('/transactions?type=invalid')
            ->assertSessionHasErrors('type');
    }

    /** @test */
    public function it_returns_totals_for_the_selected_period()
    {
        $this->login();

        Transaction::create(['type' => 'income', 'amount' => 1000, 'category' => 'sueldo']);
        Transaction::create(['type' => 'outgo', 'amount' => -400, 'category' => 'supermercado']);

        $response = $this->get('/reportes?date_from='.now()->startOfMonth()->toDateString().'&date_to='.now()->endOfMonth()->toDateString());

        $response->assertOk();
        $response->assertViewHas('incomeTotal', 1000);
        $response->assertViewHas('expenseTotal', -400);
        $response->assertViewHas('balance', 600);
    }

    /** @test */
    public function it_excludes_transactions_outside_the_period()
    {
        $this->login();

        $outside = Transaction::create(['type' => 'outgo', 'amount' => -999, 'category' => 'viejo']);
        $outside->forceFill(['created_at' => now()->subYear()])->save();

        $response = $this->get('/reportes?date_from='.now()->startOfMonth()->toDateString().'&date_to='.now()->endOfMonth()->toDateString());

        $response->assertViewHas('expenseTotal', 0);
        $response->assertDontSee('viejo');
    }
}