<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add zone_code to disconnection_orders if the table exists but column is missing.
     */
    public function up(): void
    {
        if (Schema::hasTable('disconnection_orders') && ! Schema::hasColumn('disconnection_orders', 'zone_code')) {
            Schema::table('disconnection_orders', function (Blueprint $table) {
                $table->string('zone_code')->nullable()->after('address');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('disconnection_orders') && Schema::hasColumn('disconnection_orders', 'zone_code')) {
            Schema::table('disconnection_orders', function (Blueprint $table) {
                $table->dropColumn('zone_code');
            });
        }
    }
};
