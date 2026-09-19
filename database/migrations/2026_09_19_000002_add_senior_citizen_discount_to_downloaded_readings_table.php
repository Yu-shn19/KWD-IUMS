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

        if (Schema::hasColumn('downloaded_readings', 'senior_citizen_discount')) {
            return;
        }

        Schema::table('downloaded_readings', function (Blueprint $table) {
            if (Schema::hasColumn('downloaded_readings', 'current_meter_rental')) {
                $table->decimal('senior_citizen_discount', 12, 2)
                    ->nullable()
                    ->default(0)
                    ->after('current_meter_rental');
            } else {
                $table->decimal('senior_citizen_discount', 12, 2)
                    ->nullable()
                    ->default(0);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('downloaded_readings')) {
            return;
        }

        if (!Schema::hasColumn('downloaded_readings', 'senior_citizen_discount')) {
            return;
        }

        Schema::table('downloaded_readings', function (Blueprint $table) {
            $table->dropColumn('senior_citizen_discount');
        });
    }
};
