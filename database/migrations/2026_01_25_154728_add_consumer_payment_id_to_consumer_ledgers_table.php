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
        Schema::table('consumer_ledgers', function (Blueprint $table) {
            $table->unsignedBigInteger('consumer_payment_id')->nullable()->after('consumer_zone_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumer_ledgers', function (Blueprint $table) {
            $table->dropColumn('consumer_payment_id');
        });
    }
};
