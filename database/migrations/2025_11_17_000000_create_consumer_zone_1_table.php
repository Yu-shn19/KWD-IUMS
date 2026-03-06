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
        if (!Schema::hasTable('consumer_zone')) {
            Schema::create('consumer_zone', function (Blueprint $table) {
                $table->id();
                $table->string('account_no')->unique();
                $table->string('account_name');
                $table->text('address1')->nullable();
                $table->string('zone_code')->index();
                $table->unsignedInteger('sequence')->nullable();
                $table->string('category_code')->nullable();
                $table->string('meter_number')->nullable();
                $table->string('meter_brand')->nullable();
                $table->string('rate_code')->nullable();
                $table->date('install_date')->nullable();
                $table->string('status_code')->nullable();
                $table->decimal('consumer_deposit', 12, 2)->default(0);
                $table->decimal('installation_fee', 12, 2)->default(0);
                $table->decimal('installation_balance', 12, 2)->default(0);
                $table->decimal('balance', 12, 2)->default(0);
                $table->string('cons_ctrl')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumer_zone');
    }
};

