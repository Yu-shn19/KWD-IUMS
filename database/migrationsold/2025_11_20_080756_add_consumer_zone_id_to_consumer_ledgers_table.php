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
        // Check if column exists and needs type modification
        if (Schema::hasColumn('consumer_ledgers', 'consumer_zone_id')) {
            // Modify existing column to match consumer_zone.id type exactly (int(11) - signed)
            DB::statement('ALTER TABLE consumer_ledgers MODIFY consumer_zone_id INT(11) NULL');
        } else {
            // Add consumer_zone_id column if it doesn't exist - using integer() to match int(11)
            Schema::table('consumer_ledgers', function (Blueprint $table) {
                $table->integer('consumer_zone_id')->nullable()->after('id');
            });
        }
        
        // Check if foreign key already exists before adding
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'consumer_ledgers' 
            AND CONSTRAINT_TYPE = 'FOREIGN KEY' 
            AND CONSTRAINT_NAME = 'consumer_ledgers_consumer_zone_id_foreign'
        ");
        
        if (empty($foreignKeys)) {
            Schema::table('consumer_ledgers', function (Blueprint $table) {
                $table->foreign('consumer_zone_id')
                    ->references('id')
                    ->on('consumer_zone')
                    ->onDelete('set null');
            });
        }
        
        // Add index if it doesn't exist
        $indexes = DB::select("
            SELECT INDEX_NAME 
            FROM information_schema.STATISTICS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'consumer_ledgers' 
            AND COLUMN_NAME = 'consumer_zone_id'
            AND INDEX_NAME != 'PRIMARY'
        ");
        
        if (empty($indexes) && Schema::hasColumn('consumer_ledgers', 'consumer_zone_id')) {
            Schema::table('consumer_ledgers', function (Blueprint $table) {
                $table->index('consumer_zone_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumer_ledgers', function (Blueprint $table) {
            $table->dropForeign(['consumer_zone_id']);
            $table->dropIndex(['consumer_zone_id']);
            $table->dropColumn('consumer_zone_id');
        });
    }
};
