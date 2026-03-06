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
            $table->decimal('senior_citizen_discount', 12, 2)->default(0)->after('payment_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumer_payments', function (Blueprint $table) {
            $table->dropColumn('senior_citizen_discount');
        });
    }
};
