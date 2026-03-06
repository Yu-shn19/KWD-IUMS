<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Consumer extends Model
{
    use HasFactory;

    protected $fillable = [
        'installation_date',
        'account_number',
        'meter_number',
        'address',
        'category',
        'status',
        'full_name',
        'first_name',
        'last_name',
        'middle_name',
        'extension',
        'contact_number',
        'meter_brand',
        'zone',
        'card_number',
    ];

    protected $casts = [
        'installation_date' => 'date',
    ];
}

