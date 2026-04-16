<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('consumer_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('consumer_payments', 'advances')) {
                $table->decimal('advances', 12, 2)->default(0)->after('arrears_py');
            }
            if (!Schema::hasColumn('consumer_payments', 'others')) {
                $table->decimal('others', 12, 2)->default(0)->after('advances');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumer_payments', function (Blueprint $table) {
            $table->dropColumn(['advances', 'others']);
        });
    }
};
