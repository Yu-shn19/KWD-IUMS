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
            // Add timestamps only if they don't exist
            if (!Schema::hasColumn('consumer_zone', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('consumer_zone', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumer_zone', function (Blueprint $table) {
            // Remove timestamps if they exist
            if (Schema::hasColumn('consumer_zone', 'created_at')) {
                $table->dropColumn('created_at');
            }
            if (Schema::hasColumn('consumer_zone', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};
