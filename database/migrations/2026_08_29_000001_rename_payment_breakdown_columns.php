<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('consumer_payments')) {
            Schema::table('consumer_payments', function (Blueprint $table) {
                if (Schema::hasColumn('consumer_payments', 'current_bill')) {
                    $table->renameColumn('current_bill', 'current_billing');
                }
                if (Schema::hasColumn('consumer_payments', 'penalty')) {
                    $table->renameColumn('penalty', 'current_penalty');
                }
                if (Schema::hasColumn('consumer_payments', 'meter_maintenance')) {
                    $table->renameColumn('meter_maintenance', 'mr_arrears');
                }
                if (Schema::hasColumn('consumer_payments', 'arrears_cy')) {
                    $table->renameColumn('arrears_cy', 'current_arrears');
                }
                if (Schema::hasColumn('consumer_payments', 'arrears_py')) {
                    $table->renameColumn('arrears_py', 'prio_years');
                }
                if (Schema::hasColumn('consumer_payments', 'others')) {
                    $table->renameColumn('others', 'current_mr');
                }
            });
        }

        if (Schema::hasTable('downloaded_readings')) {
            Schema::table('downloaded_readings', function (Blueprint $table) {
                if (Schema::hasColumn('downloaded_readings', 'current_bill')) {
                    $table->renameColumn('current_bill', 'current_billing');
                }
            });
        }

        if (Schema::hasTable('meter_reading_schedules')) {
            Schema::table('meter_reading_schedules', function (Blueprint $table) {
                if (Schema::hasColumn('meter_reading_schedules', 'current_bill')) {
                    $table->renameColumn('current_bill', 'current_billing');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('consumer_payments')) {
            Schema::table('consumer_payments', function (Blueprint $table) {
                if (Schema::hasColumn('consumer_payments', 'current_billing')) {
                    $table->renameColumn('current_billing', 'current_bill');
                }
                if (Schema::hasColumn('consumer_payments', 'current_penalty')) {
                    $table->renameColumn('current_penalty', 'penalty');
                }
                if (Schema::hasColumn('consumer_payments', 'mr_arrears')) {
                    $table->renameColumn('mr_arrears', 'meter_maintenance');
                }
                if (Schema::hasColumn('consumer_payments', 'current_arrears')) {
                    $table->renameColumn('current_arrears', 'arrears_cy');
                }
                if (Schema::hasColumn('consumer_payments', 'prio_years')) {
                    $table->renameColumn('prio_years', 'arrears_py');
                }
                if (Schema::hasColumn('consumer_payments', 'current_mr')) {
                    $table->renameColumn('current_mr', 'others');
                }
            });
        }

        if (Schema::hasTable('downloaded_readings')) {
            Schema::table('downloaded_readings', function (Blueprint $table) {
                if (Schema::hasColumn('downloaded_readings', 'current_billing')) {
                    $table->renameColumn('current_billing', 'current_bill');
                }
            });
        }

        if (Schema::hasTable('meter_reading_schedules')) {
            Schema::table('meter_reading_schedules', function (Blueprint $table) {
                if (Schema::hasColumn('meter_reading_schedules', 'current_billing')) {
                    $table->renameColumn('current_billing', 'current_bill');
                }
            });
        }
    }
};
