<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('consumer_zone', 'bill_disc_percent')) {
            return;
        }

        // Avoid doctrine/dbal dependency by using raw SQL.
        DB::statement("ALTER TABLE `consumer_zone` MODIFY `bill_disc_percent` VARCHAR(50) NULL");

        // Convert legacy numeric values (5 / 5.00) to the new string code.
        DB::statement("UPDATE `consumer_zone` SET `bill_disc_percent` = 'SC DISCOUNT' WHERE TRIM(`bill_disc_percent`) IN ('5', '5.0', '5.00')");
    }

    public function down(): void
    {
        if (! Schema::hasColumn('consumer_zone', 'bill_disc_percent')) {
            return;
        }

        // Convert back to numeric value for SC discount.
        DB::statement("UPDATE `consumer_zone` SET `bill_disc_percent` = '5.00' WHERE TRIM(`bill_disc_percent`) = 'SC DISCOUNT'");
        DB::statement("ALTER TABLE `consumer_zone` MODIFY `bill_disc_percent` DECIMAL(8,2) NULL");
    }
};

