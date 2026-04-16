<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Allows payment without a downloaded reading (e.g. accounts with no billing schedule).
     */
    public function up(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE consumer_payments MODIFY reading_id BIGINT UNSIGNED NULL');
        } else {
            DB::statement('ALTER TABLE consumer_payments ALTER COLUMN reading_id DROP NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE consumer_payments MODIFY reading_id BIGINT UNSIGNED NOT NULL');
        } else {
            DB::statement('ALTER TABLE consumer_payments ALTER COLUMN reading_id SET NOT NULL');
        }
    }
};
