<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumer_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('consumer_id')->nullable()->change();
            $table->string('account_name', 255)->nullable()->after('consumer_id');
        });
    }

    public function down(): void
    {
        Schema::table('consumer_payments', function (Blueprint $table) {
            $table->dropColumn('account_name');
            $table->unsignedBigInteger('consumer_id')->nullable(false)->change();
        });
    }
};
