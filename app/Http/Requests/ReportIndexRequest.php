<?php

namespace App\Http\Requests;

class ReportIndexRequest extends DateRangeFormRequest
{
    public function rules(): array
    {
        return $this->dateRangeRules();
    }
}
