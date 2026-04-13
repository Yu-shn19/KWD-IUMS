<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Fix the foreign key constraint to use CASCADE delete instead of SET NULL
     */
    public function up(): void
    {
        // Check if the old foreign key exists and drop it
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'consumer_ledgers' 
            AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            AND CONSTRAINT_NAME = 'consumer_ledgers_schedule_id_foreign'
        ");

        if (!empty($foreignKeys)) {
            // Drop the existing foreign key
            Schema::table('consumer_ledgers', function (Blueprint $table) {
                $table->dropForeign(['schedule_id']);
            });
        }

        // Add the new foreign key with CASCADE delete
        Schema::table('consumer_ledgers', function (Blueprint $table) {
            $table->foreign('schedule_id')
                ->references('id')
                ->on('meter_reading_schedules')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the cascading foreign key
        Schema::table('consumer_ledgers', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
        });

        // Restore the original SET NULL behavior
        Schema::table('consumer_ledgers', function (Blueprint $table) {
            $table->foreign('schedule_id')
                ->references('id')
                ->on('meter_reading_schedules')
                ->onDelete('set null');
        });
    }
};
