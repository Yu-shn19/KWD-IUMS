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
            if (! Schema::hasColumn('consumer_zone', 'bill_disc_percent')) {
                $table->string('bill_disc_percent', 50)->nullable();
            }
            if (! Schema::hasColumn('consumer_zone', 'bill_disc_amount')) {
                $table->decimal('bill_disc_amount', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('consumer_zone', 'osca_id_no')) {
                $table->string('osca_id_no', 100)->nullable();
            }
            if (! Schema::hasColumn('consumer_zone', 'remark')) {
                $table->text('remark')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumer_zone', function (Blueprint $table) {
            if (Schema::hasColumn('consumer_zone', 'bill_disc_percent')) {
                $table->dropColumn('bill_disc_percent');
            }
            if (Schema::hasColumn('consumer_zone', 'bill_disc_amount')) {
                $table->dropColumn('bill_disc_amount');
            }
            if (Schema::hasColumn('consumer_zone', 'osca_id_no')) {
                $table->dropColumn('osca_id_no');
            }
            if (Schema::hasColumn('consumer_zone', 'remark')) {
                $table->dropColumn('remark');
            }
        });
    }
};
