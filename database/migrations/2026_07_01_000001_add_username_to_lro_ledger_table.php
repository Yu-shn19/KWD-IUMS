<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('lro_ledger') && !Schema::hasColumn('lro_ledger', 'username')) {
            Schema::table('lro_ledger', function (Blueprint $table) {
                $table->string('username')->nullable()->after('remarks');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lro_ledger') && Schema::hasColumn('lro_ledger', 'username')) {
            Schema::table('lro_ledger', function (Blueprint $table) {
                $table->dropColumn('username');
            });
        }
    }
};
