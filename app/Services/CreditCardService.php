<?php

namespace App\Services;

use App\Models\CreditCardPurchase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class CreditCardService
{
    /**
     * Responsibility: Register credit card purchases dividing them into installments when applicable.
     * Calculates the billing balance for a given month based on the registered purchases.
     * List monthly installments with details.
     */


    /**
     * Registers a credit card purchase.
     * @param string $args
     * @param string $ownerId
     * @param mixed $ownerName
     * @param mixed $errorMessage
     * @return bool
     */
    public function registerPurchase(string $args, string $ownerId, ?string $ownerName, ?string &$errorMessage = null): bool
    {
        // Regex pattern to match the input format:
        // "tarjeta 1000 Supermercado [Visa] [n_cuotas]"
        // - Amount: 1–9 digits, optional decimal with 1–2 digits
        $pattern = '/^(\d+(\.\d{1,2})?)\s+([\pL\pN\s]+?)(?:\s+(\S+))?(?:\s+(\d+))?$/u';

        if (! preg_match($pattern, $args, $matches)) {
            $errorMessage = 'Formato inválido. Usar: "tarjeta 1000 Supermercado [Visa] [n_cuotas]".';
            return false;
        }

        $amount    = (float) $matches[1];
        $vendor    = trim($matches[3]);
        $cardName  = $matches[4] ?? null;
        $installments = isset($matches[5]) ? (int)$matches[5] : 1;

        if ($installments < 1) {
            $installments = 1;
        }

        // The cutoff day is the first Thursday of the month for Visa and Amex,
        // TODO: make this configurable
        $cutoffDay = 10;
        
        if ($cardName && in_array(strtolower($cardName), ['visa', 'amex'])) {
            $cutoffDay = Carbon::now()->startOfMonth()->next(Carbon::THURSDAY)->day;
        }
        $today = Carbon::now();

        // Determine the first billing cycle based on the cutoff day
        if ($today->day <= $cutoffDay) {
            $firstCycle = $today->copy();
        } else {
            $firstCycle = $today->copy()->addMonthNoOverflow();
        }

        $cycles = [];
        for ($i = 0; $i < $installments; $i++) {
            $cycleDate = $firstCycle->copy()->addMonthsNoOverflow($i);
            $cycles[] = $cycleDate->format('Y-m');
        }

        // Calculate the base amount per installment
        $baseAmount = floor(($amount / $installments) * 100) / 100;
        $remaining = $amount - ($baseAmount * $installments);

        try {
            foreach ($cycles as $index => $cycle) {
                $cuota = $baseAmount;
                // Ajustar la última cuota con el remanente
                if ($index === $installments - 1) {
                    $cuota += $remaining;
                }

                CreditCardPurchase::create([
                    'owner_id'     => $ownerId,
                    'owner_name'   => $ownerName,
                    'amount'       => $cuota,
                    'vendor'       => $vendor,
                    'card_name'    => $cardName,
                    'billing_cycle'=> $cycle,
                    'purchased_at' => Carbon::now(),
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Error al registrar compra con tarjeta: ' . $e->getMessage());
            $errorMessage = 'Error interno al registrar la compra de tarjeta.';
            return false;
        }
    }

    /**
     * Summary of getMonthlyBalance
     * @param mixed $month
     * @param mixed $year
     * @return float
     */
    public function getMonthlyBalance(?int $month = null, ?int $year = null): float
    {
        $year  = $year  ?? Carbon::now()->year;
        $month = $month ?? Carbon::now()->month;
        $cycle = sprintf('%04d-%02d', $year, $month);

        return CreditCardPurchase::where('billing_cycle', $cycle)
            ->sum('amount');
    }

    /**
     * Summary of listMonthlyPurchases
     * @param mixed $month
     * @param mixed $year
     * @return CreditCardPurchase[]|\Illuminate\Database\Eloquent\Collection
     */
    public function listMonthlyPurchases(?int $month = null, ?int $year = null)
    {
        $year  = $year  ?? Carbon::now()->year;
        $month = $month ?? Carbon::now()->month;
        $cycle = sprintf('%04d-%02d', $year, $month);

        return CreditCardPurchase::where('billing_cycle', $cycle)
            ->orderBy('purchased_at', 'desc')
            ->get();
    }
}
