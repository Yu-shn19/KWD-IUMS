<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add paid_at column to lro_ledgers table (between correct_reading and created_at)
     */
    public function up(): void
    {
        Schema::table('lro_ledgers', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('correct_reading');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lro_ledgers', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
    }
};
