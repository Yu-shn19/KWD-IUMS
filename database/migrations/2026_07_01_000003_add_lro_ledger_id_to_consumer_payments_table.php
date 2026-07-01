<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('consumer_payments')) {
            return;
        }

        if (!Schema::hasColumn('consumer_payments', 'lro_ledger_id')) {
            Schema::table('consumer_payments', function (Blueprint $table) {
                $table->foreignId('lro_ledger_id')
                    ->nullable()
                    ->after('consumer_zone_id')
                    ->constrained('lro_ledger')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasColumn('consumer_payments', 'consumer_zone_id')) {
            Schema::table('consumer_payments', function (Blueprint $table) {
                $table->dropForeign(['consumer_zone_id']);
            });

            Schema::table('consumer_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('consumer_zone_id')->nullable()->change();
                $table->foreign('consumer_zone_id')
                    ->references('id')
                    ->on('consumer_zone')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('consumer_payments')) {
            return;
        }

        if (Schema::hasColumn('consumer_payments', 'lro_ledger_id')) {
            Schema::table('consumer_payments', function (Blueprint $table) {
                $table->dropForeign(['lro_ledger_id']);
                $table->dropColumn('lro_ledger_id');
            });
        }
    }
};
