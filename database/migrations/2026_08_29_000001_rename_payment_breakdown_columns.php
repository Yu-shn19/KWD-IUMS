<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->renameIfExists('consumer_payments', 'current_bill', 'current_billing');
        $this->renameIfExists('consumer_payments', 'penalty', 'current_penalty');
        $this->renameIfExists('consumer_payments', 'meter_maintenance', 'mr_arrears');
        $this->renameIfExists('consumer_payments', 'arrears_cy', 'current_arrears');
        $this->renameIfExists('consumer_payments', 'arrears_py', 'prio_years');
        $this->renameIfExists('consumer_payments', 'others', 'current_mr');

        $this->renameIfExists('downloaded_readings', 'current_bill', 'current_billing');
        $this->renameIfExists('meter_reading_schedules', 'current_bill', 'current_billing');
    }

    public function down(): void
    {
        $this->renameIfExists('consumer_payments', 'current_billing', 'current_bill');
        $this->renameIfExists('consumer_payments', 'current_penalty', 'penalty');
        $this->renameIfExists('consumer_payments', 'mr_arrears', 'meter_maintenance');
        $this->renameIfExists('consumer_payments', 'current_arrears', 'arrears_cy');
        $this->renameIfExists('consumer_payments', 'prio_years', 'arrears_py');
        $this->renameIfExists('consumer_payments', 'current_mr', 'others');

        $this->renameIfExists('downloaded_readings', 'current_billing', 'current_bill');
        $this->renameIfExists('meter_reading_schedules', 'current_billing', 'current_bill');
    }

    private function renameIfExists(string $table, string $from, string $to): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, $from) && !Schema::hasColumn($table, $to)) {
            Schema::table($table, function (Blueprint $blueprint) use ($from, $to) {
                $blueprint->renameColumn($from, $to);
            });
        }
    }
};
