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
            if (! Schema::hasColumn('consumer_zone', 'bill_disc_updated_at')) {
                $table->date('bill_disc_updated_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumer_zone', function (Blueprint $table) {
            if (Schema::hasColumn('consumer_zone', 'bill_disc_updated_at')) {
                $table->dropColumn('bill_disc_updated_at');
            }
        });
    }
};
