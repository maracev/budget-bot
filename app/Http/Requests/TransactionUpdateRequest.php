<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:income,outgo'],
            'category' => ['required', 'string', 'max:255'],
            'subcategory' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:-9999999', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');

        if (! is_numeric($amount)) {
            return;
        }

        // Non integral values are left untouched so the integer rule rejects them.
        if ((int) $amount != $amount) {
            return;
        }

        // The rest of the app assumes income amounts are positive and outgo amounts negative.
        $sign = $this->input('type') === 'outgo' ? -1 : 1;

        $this->merge(['amount' => $sign * abs((int) $amount)]);
    }
}
