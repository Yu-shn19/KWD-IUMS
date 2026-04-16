<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('consumer_ledgers', function (Blueprint $table) {
            // Add composite index for AR Aging query performance
            // This index helps with: WHERE balance > 0 AND trans LIKE '%BILL%' AND date >= ?
            try {
                DB::statement('CREATE INDEX idx_ar_aging_query ON consumer_ledgers (balance, trans, date) WHERE balance > 0');
            } catch (\Exception $e) {
                // Fallback for databases that don't support partial indexes
                $table->index(['balance', 'trans', 'date'], 'idx_ar_aging_query');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumer_ledgers', function (Blueprint $table) {
            $table->dropIndex('idx_ar_aging_query');
        });
    }
};
