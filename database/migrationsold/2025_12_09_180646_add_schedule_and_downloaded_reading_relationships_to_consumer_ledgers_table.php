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
            // Add schedule_id column if it doesn't exist
            if (!Schema::hasColumn('consumer_ledgers', 'schedule_id')) {
                $table->unsignedBigInteger('schedule_id')->nullable()->after('consumer_zone_id');
            }
            
            // Add downloaded_reading_id column if it doesn't exist
            if (!Schema::hasColumn('consumer_ledgers', 'downloaded_reading_id')) {
                $table->unsignedBigInteger('downloaded_reading_id')->nullable()->after('schedule_id');
            }
        });

        // Add foreign key constraints if they don't exist
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'consumer_ledgers' 
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");
        
        $existingConstraintNames = array_column($foreignKeys, 'CONSTRAINT_NAME');
        
        // Add foreign key for schedule_id
        if (!in_array('consumer_ledgers_schedule_id_foreign', $existingConstraintNames)) {
            Schema::table('consumer_ledgers', function (Blueprint $table) {
                $table->foreign('schedule_id')
                    ->references('id')
                    ->on('meter_reading_schedules')
                    ->onDelete('set null');
            });
        }
        
        // Add foreign key for downloaded_reading_id
        if (!in_array('consumer_ledgers_downloaded_reading_id_foreign', $existingConstraintNames)) {
            Schema::table('consumer_ledgers', function (Blueprint $table) {
                $table->foreign('downloaded_reading_id')
                    ->references('id')
                    ->on('downloaded_readings')
                    ->onDelete('set null');
            });
        }
        
        // Add indexes if they don't exist
        $indexes = DB::select("
            SELECT INDEX_NAME 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'consumer_ledgers'
            AND COLUMN_NAME IN ('schedule_id', 'downloaded_reading_id')
            AND INDEX_NAME != 'PRIMARY'
        ");
        
        $existingIndexNames = array_column($indexes, 'INDEX_NAME');
        
        if (!in_array('consumer_ledgers_schedule_id_index', $existingIndexNames) && Schema::hasColumn('consumer_ledgers', 'schedule_id')) {
            Schema::table('consumer_ledgers', function (Blueprint $table) {
                $table->index('schedule_id');
            });
        }
        
        if (!in_array('consumer_ledgers_downloaded_reading_id_index', $existingIndexNames) && Schema::hasColumn('consumer_ledgers', 'downloaded_reading_id')) {
            Schema::table('consumer_ledgers', function (Blueprint $table) {
                $table->index('downloaded_reading_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumer_ledgers', function (Blueprint $table) {
            // Drop foreign keys
            if (DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'consumer_ledgers' 
                AND CONSTRAINT_NAME = 'consumer_ledgers_schedule_id_foreign'
            ")) {
                $table->dropForeign(['schedule_id']);
            }
            
            if (DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'consumer_ledgers' 
                AND CONSTRAINT_NAME = 'consumer_ledgers_downloaded_reading_id_foreign'
            ")) {
                $table->dropForeign(['downloaded_reading_id']);
            }
            
            // Drop indexes
            $table->dropIndex(['schedule_id']);
            $table->dropIndex(['downloaded_reading_id']);
            
            // Drop columns
            if (Schema::hasColumn('consumer_ledgers', 'schedule_id')) {
                $table->dropColumn('schedule_id');
            }
            
            if (Schema::hasColumn('consumer_ledgers', 'downloaded_reading_id')) {
                $table->dropColumn('downloaded_reading_id');
            }
        });
    }
};
