<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('consumer_zone', function (Blueprint $table) {
            $table->id();

            $table->string('account_no')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('account_name');
            $table->string('gender', 20)->nullable();

            $table->string('address');

            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->string('zone_code')->nullable();
            $table->integer('sequence')->nullable();

            $table->string('category_code')->nullable();
            $table->string('meter_number')->nullable();
            $table->string('meter_brand')->nullable();

            $table->integer('base_reading')->nullable();
            $table->date('base_reading_date')->nullable();

            $table->string('rate_code')->nullable();

            $table->date('install_date')->nullable();
            $table->date('transaction_date')->nullable();

            $table->string('status_code')->nullable();

            $table->boolean('pending_install_activation')->default(false);
            $table->date('auto_activate_on')->nullable();

            $table->string('bill_disc_percent')->nullable();
            $table->string('osca_id_no')->nullable();
            $table->date('bill_disc_updated_at')->nullable();

            $table->string('cons_ctrl')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumer_zone');
    }
};