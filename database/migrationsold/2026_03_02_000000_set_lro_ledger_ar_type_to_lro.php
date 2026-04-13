<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * lro_ledger rows are LRO type; set default and fix existing AR/null to LRO.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lro_ledger')) {
            return;
        }

        // Update existing rows: ar_type = 'AR' or NULL → 'LRO'
        DB::table('lro_ledger')
            ->where(function ($q) {
                $q->where('ar_type', 'AR')->orWhereNull('ar_type');
            })
            ->update(['ar_type' => 'LRO']);

        // Change column default so new inserts get LRO (raw SQL to avoid doctrine/dbal)
        if (Schema::hasColumn('lro_ledger', 'ar_type')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE lro_ledger MODIFY ar_type VARCHAR(10) DEFAULT 'LRO'");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('lro_ledger') || !Schema::hasColumn('lro_ledger', 'ar_type')) {
            return;
        }
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE lro_ledger MODIFY ar_type VARCHAR(10) DEFAULT 'AR'");
        }
    }
};
