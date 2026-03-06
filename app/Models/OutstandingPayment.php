<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OutstandingPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'consumer_zone_id',
        'account_no',
        'amount',
        'previous_balance',
        'new_balance',
        'reference',
        'remarks',
        'paid_at',
        'created_by',
    ];
}

