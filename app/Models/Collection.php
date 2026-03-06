<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    protected $table = 'collection';

    protected $fillable = [
        'zone_code',
        'sequence',
        'account_no',
        'account_name',
        'coll_date',
        'coll_time',
        'pay_mode',
        'or_number',
        'pay_amount',
        'current_bill',
        'meter_rental',
        'arrears',
        'penalty',
        'materials',
        'others',
        'advances',
        'sc_discount',
        'fees_charges',
        'materials_loan',
        'prev_yr',
        'cancel',
        'username',
        'sund1_amt',
        'sund2_amt',
        'sund3_amt',
        'sund4_amt',
        'sund5_amt',
        'sund1_code',
        'sund2_code',
        'sund3_code',
        'sund4_code',
        'sund5_code',
    ];

    protected $casts = [
        'coll_date' => 'date',
    ];

    /**
     * Get the consumer zone associated with this collection
     */
    public function consumerZone()
    {
        return $this->belongsTo(ConsumerZoneOne::class, 'account_no', 'account_no');
    }

    /**
     * Get related consumer ledger entries based on account_no and date matching
     * Joins through consumer_zone table
     */
    public function consumerLedgers()
    {
        $accountNo = $this->account_no;
        $collDate = $this->coll_date ? $this->coll_date->format('Y-m-d') : null;
        
        if (!$accountNo || !$collDate) {
            return collect([]);
        }

        return ConsumerLedger::whereHas('consumerZone', function($query) use ($accountNo) {
            $query->where('account_no', $accountNo);
        })->whereDate('txtime', $collDate)->get();
    }
}
