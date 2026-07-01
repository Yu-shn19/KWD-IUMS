<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('lro_ledger')) {
            return;
        }

        if (!Schema::hasColumn('lro_ledger', 'account_name')) {
            Schema::table('lro_ledger', function (Blueprint $table) {
                $table->string('account_name')->nullable()->after('consumer_zone_id');
            });
        }

        if (Schema::hasColumn('lro_ledger', 'consumer_zone_id')) {
            Schema::table('lro_ledger', function (Blueprint $table) {
                $table->dropForeign(['consumer_zone_id']);
            });

            Schema::table('lro_ledger', function (Blueprint $table) {
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
        if (!Schema::hasTable('lro_ledger')) {
            return;
        }

        if (Schema::hasColumn('lro_ledger', 'account_name')) {
            Schema::table('lro_ledger', function (Blueprint $table) {
                $table->dropColumn('account_name');
            });
        }
    }
};
