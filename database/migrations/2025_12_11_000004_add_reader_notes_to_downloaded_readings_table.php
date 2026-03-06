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
            if (!Schema::hasColumn('downloaded_readings', 'reader_notes')) {
                $table->text('reader_notes')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('downloaded_readings', function (Blueprint $table) {
            if (Schema::hasColumn('downloaded_readings', 'reader_notes')) {
                $table->dropColumn('reader_notes');
            }
        });
    }
};

