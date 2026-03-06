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
        // Add penalty_id column to consumer_ledgers if it doesn't exist
        if (Schema::hasTable('consumer_ledgers')) {
            if (!Schema::hasColumn('consumer_ledgers', 'penalty_id')) {
                Schema::table('consumer_ledgers', function (Blueprint $table) {
                    $table->unsignedBigInteger('penalty_id')->nullable()->after('downloaded_reading_id');
                    $table->index('penalty_id');
                });
            }

            // Check if foreign key already exists
            $foreignKeyExists = false;
            try {
                $constraints = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'consumer_ledgers'
                    AND COLUMN_NAME = 'penalty_id'
                    AND REFERENCED_TABLE_NAME = 'penalties'
                ");
                $foreignKeyExists = !empty($constraints);
            } catch (\Exception $e) {
                // If query fails, assume foreign key doesn't exist
            }

            // Add foreign key with cascade delete if it doesn't exist
            if (!$foreignKeyExists && Schema::hasColumn('consumer_ledgers', 'penalty_id')) {
                Schema::table('consumer_ledgers', function (Blueprint $table) {
                    $table->foreign('penalty_id')
                        ->references('id')
                        ->on('penalties')
                        ->onDelete('cascade');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('consumer_ledgers')) {
            Schema::table('consumer_ledgers', function (Blueprint $table) {
                // Drop foreign key first
                $table->dropForeign(['penalty_id']);
                // Then drop column
                if (Schema::hasColumn('consumer_ledgers', 'penalty_id')) {
                    $table->dropColumn('penalty_id');
                }
            });
        }
    }
};
