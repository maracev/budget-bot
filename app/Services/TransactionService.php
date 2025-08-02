<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Movimiento;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class TransactionService
{
    private int $lastAmount = 0;
    private string $lastCategory = '';

    /**
     * Summary of register
     * @param string $rawType
     * @param string $args
     * @param string $ownerId
     * @param mixed $ownerName
     * @param mixed $errorMessage
     * @return bool
     */
    public function register(string $rawType, string $args, string $ownerId, ?string $ownerName, ?string &$errorMessage = null): bool
    {
        $typeMap = config('type_mapper');
        $type    = $typeMap[$rawType] ?? null;

        if (! $type) {
            $errorMessage = 'Tipo inválido. Usá "ingreso" o "gasto".';
            return false;
        }

        if (! preg_match('/^(\d+)\s+(.*)$/', $args, $matches)) {
            $errorMessage = "Formato inválido. Usá: \"{$rawType} 1000 sueldo\".";
            return false;
        }

        $amount   = (int) $matches[1];
        $category = trim($matches[2]);

        try {
            Transaction::create([
                'type'       => $type,
                'amount'      => $amount,
                'category'  => $category,
                'owner_id'   => $ownerId,
                'owner_name' => $ownerName,
            ]);

            $this->lastAmount   = $amount;
            $this->lastCategory = $category;
            return true;
        } catch (\Throwable $e) {
            Log::error('Error al crear transacción: ' . $e->getMessage());
            $errorMessage = 'Ocurrió un error al registrar la transacción.';
            return false;
        }
    }

    /**
     * Summary of getBalance
     * @return array{gastos: mixed, ingresos: mixed, saldo: float|int}
     */
    public function getBalance(): array
    {
        $incomes = Transaction::where('type', config('type_mapper.ingreso'))
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('amount');
        $outgoes   = Transaction::where('type', config('type_mapper.gasto'))
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('amount');

        $balance    = $incomes - $outgoes;

        return [
            'ingresos' => $incomes,
            'gastos'   => $outgoes,
            'saldo'    => $balance,
        ];
    }

    public function getLastAmount(): int
    {
        return $this->lastAmount;
    }

    public function getLastCategory(): string
    {
        return $this->lastCategory;
    }
}
