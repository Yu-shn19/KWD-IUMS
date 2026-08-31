<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('consumer_ledgers')) {
            return;
        }

        if (Schema::hasColumn('consumer_ledgers', 'current_arrears')) {
            return;
        }

        Schema::table('consumer_ledgers', function (Blueprint $table) {
            $after = Schema::hasColumn('consumer_ledgers', 'prio_years') ? 'prio_years' : 'others';
            $table->decimal('current_arrears', 12, 2)->default(0)->after($after);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('consumer_ledgers')) {
            return;
        }

        if (! Schema::hasColumn('consumer_ledgers', 'current_arrears')) {
            return;
        }

        Schema::table('consumer_ledgers', function (Blueprint $table) {
            $table->dropColumn('current_arrears');
        });
    }
};
