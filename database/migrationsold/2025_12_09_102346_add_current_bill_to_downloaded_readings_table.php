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
        Schema::table('downloaded_readings', function (Blueprint $table) {
            $table->decimal('current_bill', 10, 2)->nullable()->after('consumption');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('downloaded_readings', function (Blueprint $table) {
            $table->dropColumn('current_bill');
        });
    }
};
