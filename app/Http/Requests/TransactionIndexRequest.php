<?php

namespace App\Http\Requests;

class TransactionIndexRequest extends DateRangeFormRequest
{
    public function rules(): array
    {
        return array_merge($this->dateRangeRules(), [
            'type' => ['nullable', 'in:income,outgo'],
            'category' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
