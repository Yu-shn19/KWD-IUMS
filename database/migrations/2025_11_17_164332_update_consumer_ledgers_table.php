<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('consumer_ledgers', function (Blueprint $table) {

        // Add columns if they do not exist
        if (!Schema::hasColumn('consumer_ledgers', 'trans')) {
            $table->string('trans')->nullable()->after('account_no');
        }

        if (!Schema::hasColumn('consumer_ledgers', 'date')) {
            $table->string('date')->nullable()->after('trans');
        }

        if (Schema::hasColumn('consumer_ledgers', 'trans_date')) {
            $table->dropColumn('trans_date');
        }

        if (Schema::hasColumn('consumer_ledgers', 'reference_no')) {
            $table->renameColumn('reference_no', 'reference');
        }

        if (!Schema::hasColumn('consumer_ledgers', 'billamount')) {
            $table->decimal('billamount', 10, 2)->default(0)->after('volume');
        }

        // Remove old field if needed
        if (Schema::hasColumn('consumer_ledgers', 'bill_amount')) {
            $table->dropColumn('bill_amount');
        }
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
