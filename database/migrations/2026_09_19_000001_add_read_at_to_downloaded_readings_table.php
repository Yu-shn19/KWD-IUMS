<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('downloaded_readings')) {
            return;
        }

        if (Schema::hasColumn('downloaded_readings', 'read_at')) {
            return;
        }

        Schema::table('downloaded_readings', function (Blueprint $table) {
            // Manila wall-clock when the reader took the reading (not UTC).
            $table->dateTime('read_at')->nullable()->after('reading_date');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('downloaded_readings')) {
            return;
        }

        if (!Schema::hasColumn('downloaded_readings', 'read_at')) {
            return;
        }

        Schema::table('downloaded_readings', function (Blueprint $table) {
            $table->dropColumn('read_at');
        });
    }
};
