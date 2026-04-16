<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('consumer_payments', function (Blueprint $table) {
            $table->decimal('current_bill', 12, 2)->default(0)->after('senior_citizen_discount');
            $table->decimal('penalty', 12, 2)->default(0)->after('current_bill');
            $table->decimal('meter_maintenance', 12, 2)->default(0)->after('penalty');
            $table->decimal('arrears_cy', 12, 2)->default(0)->after('meter_maintenance');
            $table->decimal('arrears_py', 12, 2)->default(0)->after('arrears_cy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumer_payments', function (Blueprint $table) {
            $table->dropColumn(['current_bill', 'penalty', 'meter_maintenance', 'arrears_cy', 'arrears_py']);
        });
    }
};
