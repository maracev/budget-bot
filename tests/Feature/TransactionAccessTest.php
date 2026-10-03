<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);
    }

    private function transaction(): Transaction
    {
        return Transaction::create([
            'type' => 'outgo',
            'amount' => -500,
            'category' => 'supermercado',
            'subcategory' => 'alimentos',
            'notes' => 'compra semanal',
        ]);
    }

    /** @test */
    public function guest_cannot_read_financial_data()
    {
        $transaction = $this->transaction();

        $this->get('/transactions')->assertRedirect('/login');
        $this->get("/transactions/{$transaction->id}")->assertRedirect('/login');
        $this->get("/transactions/{$transaction->id}/edit")->assertRedirect('/login');
        $this->get('/reportes')->assertRedirect('/login');
    }

    /** @test */
    public function guest_cannot_update_a_movement()
    {
        $transaction = $this->transaction();

        $response = $this->put("/transactions/{$transaction->id}", [
            'type' => 'income',
            'amount' => 9999,
            'category' => 'manipulado',
        ]);

        $response->assertRedirect('/login');
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'amount' => -500,
            'category' => 'supermercado',
        ]);
    }

    /** @test */
    public function guest_cannot_delete_a_movement()
    {
        $transaction = $this->transaction();

        $response = $this->delete("/transactions/{$transaction->id}");

        $response->assertRedirect('/login');
        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }
}