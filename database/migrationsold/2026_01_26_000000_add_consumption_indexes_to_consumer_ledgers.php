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
        // Add composite index for consumption queries
        // This index optimizes: WHERE consumer_zone_id = ? AND trans = 'BILLING' AND date >= ? AND date < ?
        
        // First, drop if exists to ensure clean creation
        try {
            DB::statement("DROP INDEX IF EXISTS idx_consumer_ledgers_consumption ON consumer_ledgers");
        } catch (\Exception $e) {
            // Ignore if doesn't exist
        }
        
        // Create the index - try with prefix first, fallback to without prefix
        try {
            // Try with prefix for string columns
            DB::statement("
                CREATE INDEX idx_consumer_ledgers_consumption 
                ON consumer_ledgers (consumer_zone_id, trans(20), date(10))
            ");
        } catch (\Exception $e) {
            // Fallback: without prefix (if columns are not strings or prefix not supported)
            DB::statement("
                CREATE INDEX idx_consumer_ledgers_consumption 
                ON consumer_ledgers (consumer_zone_id, trans, date)
            ");
        }

        // Note: volume index not needed as it's used in WHERE with CAST, 
        // and the composite index above is sufficient for the query pattern
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS idx_consumer_ledgers_consumption ON consumer_ledgers");
        DB::statement("DROP INDEX IF EXISTS idx_consumer_ledgers_volume ON consumer_ledgers");
    }
};
