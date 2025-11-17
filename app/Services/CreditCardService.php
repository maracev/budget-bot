<?php

namespace App\Services;

use App\Models\CreditCardPurchase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CreditCardService
{
    /**
     * Responsibility: Register credit card purchases dividing them into installments when applicable.
     * Calculates the billing balance for a given month based on the registered purchases.
     * List monthly installments with details.
     */

    /**
     * Registers a credit card purchase.
     *
     * @param  mixed  $ownerName
     * @param  mixed  $errorMessage
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

        $amount = (float) $matches[1];
        $vendor = trim($matches[3]);
        $cardName = $matches[4] ?? null;
        $installments = isset($matches[5]) ? (int) $matches[5] : 1;

        if ($installments < 1) {
            $installments = 1;
        }

        $today = Carbon::now();
        $firstCycle = $this->resolveNextCutoffDate($cardName, $today);

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
                    'owner_id' => $ownerId,
                    'owner_name' => $ownerName,
                    'amount' => $cuota,
                    'vendor' => $vendor,
                    'card_name' => $cardName,
                    'billing_cycle' => $cycle,
                    'purchased_at' => Carbon::now(),
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Error al registrar compra con tarjeta: '.$e->getMessage());
            $errorMessage = 'Error interno al registrar la compra de tarjeta.';

            return false;
        }
    }

    /**
     * Summary of getMonthlyBalance
     *
     * @param  mixed  $month
     * @param  mixed  $year
     */
    public function getMonthlyBalance(?int $month = null, ?int $year = null): float
    {
        $year = $year ?? Carbon::now()->year;
        $month = $month ?? Carbon::now()->month;
        $cycle = sprintf('%04d-%02d', $year, $month);

        return CreditCardPurchase::where('billing_cycle', $cycle)
            ->sum('amount');
    }

    /**
     * Summary of listMonthlyPurchases
     *
     * @param  mixed  $month
     * @param  mixed  $year
     * @return CreditCardPurchase[]|\Illuminate\Database\Eloquent\Collection
     */
    public function listMonthlyPurchases(?int $month = null, ?int $year = null)
    {
        $year = $year ?? Carbon::now()->year;
        $month = $month ?? Carbon::now()->month;
        $cycle = sprintf('%04d-%02d', $year, $month);

        return CreditCardPurchase::where('billing_cycle', $cycle)
            ->orderBy('purchased_at', 'desc')
            ->get();
    }

    /**
     * Return the next cutoff date strictly after the reference date.
     */
    protected function resolveNextCutoffDate(?string $cardName, Carbon $referenceDate): Carbon
    {
        $normalized = $cardName ? strtolower($cardName) : null;

        if ($normalized && in_array($normalized, ['visa', 'amex'], true)) {
            $candidate = $referenceDate->copy()
                ->firstOfMonth(Carbon::THURSDAY)
                ->startOfDay();

            if ($referenceDate->lt($candidate)) {
                return $candidate;
            }

            return $referenceDate->copy()
                ->addMonthNoOverflow()
                ->firstOfMonth(Carbon::THURSDAY)
                ->startOfDay();
        }

        $candidate = Carbon::create(
            $referenceDate->year,
            $referenceDate->month,
            10,
            0,
            0,
            0,
            $referenceDate->getTimezone()
        );

        if ($referenceDate->lt($candidate)) {
            return $candidate;
        }

        $nextMonth = $referenceDate->copy()->addMonthNoOverflow();

        return Carbon::create(
            $nextMonth->year,
            $nextMonth->month,
            10,
            0,
            0,
            0,
            $referenceDate->getTimezone()
        );
    }
}
