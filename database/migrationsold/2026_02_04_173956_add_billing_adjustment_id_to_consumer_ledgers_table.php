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
        if (!Schema::hasColumn('consumer_ledgers', 'billing_adjustment_id')) {
            Schema::table('consumer_ledgers', function (Blueprint $table) {
                $table->unsignedBigInteger('billing_adjustment_id')->nullable()->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('consumer_ledgers', 'billing_adjustment_id')) {
            Schema::table('consumer_ledgers', function (Blueprint $table) {
                $table->dropColumn('billing_adjustment_id');
            });
        }
    }
};
