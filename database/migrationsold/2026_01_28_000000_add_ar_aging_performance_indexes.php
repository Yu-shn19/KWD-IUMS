<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These indexes are focused on speeding up the AR Aging Summary
     * and related reports that query large portions of consumer_ledgers,
     * consumer_zone, meter_reading_schedules, and downloaded_readings.
     */
    public function up(): void
    {
        // consumer_ledgers: speed lookups by consumer_zone_id, date, and trans
        Schema::table('consumer_ledgers', function (Blueprint $table) {
            // Composite index for ledger scans by consumer and date
            $table->index(['consumer_zone_id', 'date'], 'idx_ledgers_consumer_zone_date');

            // Composite index for filtering by transaction type and date
            $table->index(['trans', 'date'], 'idx_ledgers_trans_date');
        });

        // consumer_zone: account_no and zone_code are heavily used in joins and filters
        Schema::table('consumer_zone', function (Blueprint $table) {
            // Account number is used in many joins; make sure it is indexed
            $table->index('account_no', 'idx_consumer_zone_account_no');

            // Zone/route filter
            $table->index('zone_code', 'idx_consumer_zone_zone_code');
        });

        // meter_reading_schedules: zone + bill_month and account_number
        Schema::table('meter_reading_schedules', function (Blueprint $table) {
            $table->index(['zone', 'bill_month'], 'idx_mrs_zone_bill_month');
            $table->index('account_number', 'idx_mrs_account_number');
        });

        // downloaded_readings: account_number and status/current_bill used in AR queries
        Schema::table('downloaded_readings', function (Blueprint $table) {
            $table->index('account_number', 'idx_dr_account_number');
            $table->index(['status', 'current_bill'], 'idx_dr_status_current_bill');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumer_ledgers', function (Blueprint $table) {
            $table->dropIndex('idx_ledgers_consumer_zone_date');
            $table->dropIndex('idx_ledgers_trans_date');
        });

        Schema::table('consumer_zone', function (Blueprint $table) {
            $table->dropIndex('idx_consumer_zone_account_no');
            $table->dropIndex('idx_consumer_zone_zone_code');
        });

        Schema::table('meter_reading_schedules', function (Blueprint $table) {
            $table->dropIndex('idx_mrs_zone_bill_month');
            $table->dropIndex('idx_mrs_account_number');
        });

        Schema::table('downloaded_readings', function (Blueprint $table) {
            $table->dropIndex('idx_dr_account_number');
            $table->dropIndex('idx_dr_status_current_bill');
        });
    }
};

