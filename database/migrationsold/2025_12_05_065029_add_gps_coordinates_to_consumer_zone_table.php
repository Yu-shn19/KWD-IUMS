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
        Schema::table('consumer_zone', function (Blueprint $table) {
            // Add GPS coordinates columns
            if (!Schema::hasColumn('consumer_zone', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('address1');
            }
            if (!Schema::hasColumn('consumer_zone', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumer_zone', function (Blueprint $table) {
            if (Schema::hasColumn('consumer_zone', 'latitude')) {
                $table->dropColumn('latitude');
            }
            if (Schema::hasColumn('consumer_zone', 'longitude')) {
                $table->dropColumn('longitude');
            }
        });
    }
};
