<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('meter_reading_schedules')) {
            return;
        }

        if (! Schema::hasColumn('meter_reading_schedules', 'penalty')) {
            Schema::table('meter_reading_schedules', function (Blueprint $table) {
                $table->decimal('penalty', 12, 2)->default(0)->after('arrears');
            });
        }

        if (! Schema::hasColumn('meter_reading_schedules', 'meter_rental_arrears')) {
            Schema::table('meter_reading_schedules', function (Blueprint $table) {
                $after = Schema::hasColumn('meter_reading_schedules', 'penalty') ? 'penalty' : 'arrears';
                $table->decimal('meter_rental_arrears', 12, 2)->default(0)->after($after);
            });
        }

        if (! Schema::hasColumn('meter_reading_schedules', 'prior_years')) {
            Schema::table('meter_reading_schedules', function (Blueprint $table) {
                $after = 'arrears';
                if (Schema::hasColumn('meter_reading_schedules', 'meter_rental_arrears')) {
                    $after = 'meter_rental_arrears';
                } elseif (Schema::hasColumn('meter_reading_schedules', 'penalty')) {
                    $after = 'penalty';
                }
                $table->decimal('prior_years', 12, 2)->default(0)->after($after);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('meter_reading_schedules')) {
            return;
        }

        $columns = array_values(array_filter(
            ['penalty', 'meter_rental_arrears', 'prior_years'],
            fn (string $column) => Schema::hasColumn('meter_reading_schedules', $column)
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('meter_reading_schedules', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
