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
            $table->string('prepared_by')->nullable()->after('official_receipt_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('downloaded_readings', function (Blueprint $table) {
            $table->dropColumn('prepared_by');
        });
    }
};
