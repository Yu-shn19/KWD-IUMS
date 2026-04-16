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
        Schema::create('consumer_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reading_id')->index(); // FK to downloaded_readings.id (or normalized readings table)
            $table->unsignedBigInteger('consumer_id')->nullable()->index();
            $table->string('payment_method', 50)->nullable();
            $table->decimal('payment_amount', 12, 2)->default(0);
            $table->decimal('amount_tendered', 12, 2)->default(0);
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->string('or_number', 50)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumer_payments');
    }
};

