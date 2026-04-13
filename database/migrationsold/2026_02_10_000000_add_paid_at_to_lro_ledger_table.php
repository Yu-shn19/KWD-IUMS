<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add paid_at to lro_ledger (singular) table used by LROLedger model.
     */
    public function up(): void
    {
        if (!Schema::hasTable('lro_ledger')) {
            return;
        }
        if (Schema::hasColumn('lro_ledger', 'paid_at')) {
            return;
        }
        Schema::table('lro_ledger', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('correct_reading');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('lro_ledger') && Schema::hasColumn('lro_ledger', 'paid_at')) {
            Schema::table('lro_ledger', function (Blueprint $table) {
                $table->dropColumn('paid_at');
            });
        }
    }
};
