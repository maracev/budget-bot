<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class DateRangeFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Shared rules for the date range filters so both listings stay in sync.
     */
    protected function dateRangeRules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
