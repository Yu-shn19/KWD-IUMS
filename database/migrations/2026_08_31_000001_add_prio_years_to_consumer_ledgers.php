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

        if (Schema::hasColumn('consumer_ledgers', 'prio_years')) {
            return;
        }

        Schema::table('consumer_ledgers', function (Blueprint $table) {
            $table->decimal('prio_years', 12, 2)->default(0)->after('others');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('consumer_ledgers')) {
            return;
        }

        if (! Schema::hasColumn('consumer_ledgers', 'prio_years')) {
            return;
        }

        Schema::table('consumer_ledgers', function (Blueprint $table) {
            $table->dropColumn('prio_years');
        });
    }
};
