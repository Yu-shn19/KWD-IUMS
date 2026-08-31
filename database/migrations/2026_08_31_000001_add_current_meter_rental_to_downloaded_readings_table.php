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

        if (Schema::hasColumn('downloaded_readings', 'current_meter_rental')) {
            return;
        }

        Schema::table('downloaded_readings', function (Blueprint $table) {
            $table->decimal('current_meter_rental', 12, 2)
                ->nullable()
                ->after('current_billing');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('downloaded_readings')) {
            return;
        }

        if (!Schema::hasColumn('downloaded_readings', 'current_meter_rental')) {
            return;
        }

        Schema::table('downloaded_readings', function (Blueprint $table) {
            $table->dropColumn('current_meter_rental');
        });
    }
};
