<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionManagementTest extends TestCase
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

    private function transaction(array $attributes = []): Transaction
    {
        return Transaction::create(array_merge([
            'type' => 'outgo',
            'amount' => -500,
            'category' => 'supermercado',
            'subcategory' => 'alimentos',
            'notes' => 'compra semanal',
        ], $attributes));
    }

    /** @test */
    public function it_renders_the_movement_pages()
    {
        $this->login();
        $transaction = $this->transaction();

        $this->get('/transactions')
            ->assertOk()
            ->assertSee('supermercado');

        $this->get("/transactions/{$transaction->id}")
            ->assertOk()
            ->assertSee('compra semanal');

        $this->get("/transactions/{$transaction->id}/edit")
            ->assertOk()
            ->assertSee('Guardar cambios');
    }

    /** @test */
    public function it_updates_a_movement()
    {
        $this->login();
        $transaction = $this->transaction();

        $response = $this->put("/transactions/{$transaction->id}", [
            'type' => 'outgo',
            'amount' => -750,
            'category' => 'supermercado',
            'subcategory' => 'alimentos',
            'notes' => 'compra grande',
        ]);

        $response->assertRedirect("/transactions/{$transaction->id}");

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => -750,
            'notes' => 'compra grande',
        ]);
    }

    /** @test */
    public function it_deletes_a_movement()
    {
        $this->login();
        $transaction = $this->transaction();

        $this->delete("/transactions/{$transaction->id}")->assertRedirect('/transactions');

        $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
    }

    /** @test */
    public function it_stores_an_expense_as_a_negative_amount()
    {
        $this->login();
        $transaction = $this->transaction();

        $this->put("/transactions/{$transaction->id}", [
            'type' => 'outgo',
            'amount' => 900,
            'category' => 'supermercado',
        ]);

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'amount' => -900]);
    }

    /** @test */
    public function it_stores_an_income_as_a_positive_amount()
    {
        $this->login();
        $transaction = $this->transaction(['type' => 'income', 'amount' => 1000, 'category' => 'sueldo']);

        $this->put("/transactions/{$transaction->id}", [
            'type' => 'income',
            'amount' => -1200,
            'category' => 'sueldo',
        ]);

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'amount' => 1200]);
    }

    /** @test */
    public function it_rejects_a_fractional_amount_instead_of_truncating_it()
    {
        $this->login();
        $transaction = $this->transaction();

        $response = $this->from("/transactions/{$transaction->id}/edit")->put("/transactions/{$transaction->id}", [
            'type' => 'outgo',
            'amount' => '1000.5',
            'category' => 'supermercado',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id, 'amount' => -500]);
    }
}
