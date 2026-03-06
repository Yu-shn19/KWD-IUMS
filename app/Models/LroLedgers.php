<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model for LRO ledger entries in the plural table (imported/CBA data).
 * Table: lro_ledgers — account_no, cba_date, ar_dm, lro_dm, ar_cm, lro_cm, acct_group, cba_type, cba_no, etc.
 */
class LroLedgers extends Model
{
    protected $table = 'lro_ledgers';

    protected $fillable = [
        'billmonth',
        'cba_date',
        'cba_type',
        'cba_no',
        'zone_code',
        'account_no',
        'account_name',
        'cba_remarks',
        'ar_dm',
        'ar_cm',
        'lro_dm',
        'lro_cm',
        'cba_amount',
        'acct_group',
        'consumer_zone_id',
    ];

    protected $casts = [
        'cba_date' => 'date',
        'billmonth' => 'date',
        'ar_dm' => 'decimal:2',
        'ar_cm' => 'decimal:2',
        'lro_dm' => 'decimal:2',
        'lro_cm' => 'decimal:2',
        'cba_amount' => 'decimal:2',
    ];
}
