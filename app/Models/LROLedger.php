<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model for Billing Adjustment Memo LRO ledger entries.
 * Uses the singular legacy table name `lro_ledger`.
 */
class LROLedger extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lro_ledger';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'date',
        'account',
        'name',
        'consumer_zone_id',
        'bam_no',
        'amount',
        'ar_type',
        'acct_code',
        'reference',
        'remarks',
        'status',
        'correct_reading',
    ];
}
