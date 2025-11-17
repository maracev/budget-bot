<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditCardPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'owner_name',
        'amount',
        'vendor',
        'card_name',
        'billing_cycle',
        'purchased_at',
    ];
}
