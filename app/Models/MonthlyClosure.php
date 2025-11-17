<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyClosure extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'year',
        'income',
        'outgo',
        'balance',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'income' => 'decimal:2',
        'outgo' => 'decimal:2',
        'balance' => 'decimal:2',
    ];
}
