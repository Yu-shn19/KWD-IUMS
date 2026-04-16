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
        // Force drop the index if it exists (in case it's corrupted)
        try {
            DB::statement("DROP INDEX IF EXISTS idx_consumer_ledgers_consumption ON consumer_ledgers");
        } catch (\Exception $e) {
            // Ignore if doesn't exist
        }
        
        // Create the composite index with proper syntax
        // Using prefix for string columns if needed
        try {
            // First, try with prefix for string columns
            DB::statement("
                CREATE INDEX idx_consumer_ledgers_consumption 
                ON consumer_ledgers (consumer_zone_id, trans(20), date(10))
            ");
        } catch (\Exception $e) {
            // If that fails, try without prefix (if columns are not strings)
            try {
                DB::statement("
                    CREATE INDEX idx_consumer_ledgers_consumption 
                    ON consumer_ledgers (consumer_zone_id, trans, date)
                ");
            } catch (\Exception $e2) {
                // Last resort: try with just the essential columns
                DB::statement("
                    CREATE INDEX idx_consumer_ledgers_consumption 
                    ON consumer_ledgers (consumer_zone_id, date)
                ");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement("DROP INDEX idx_consumer_ledgers_consumption ON consumer_ledgers");
        } catch (\Exception $e) {
            // Index might not exist, ignore
        }
    }
};
