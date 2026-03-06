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
        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->integer('consumer_zone_id')->nullable()->index();
            $table->unsignedBigInteger('schedule_id')->nullable()->index();
            $table->unsignedBigInteger('downloaded_reading_id')->nullable()->index();
            $table->date('date'); // Penalty date (one day after due date)
            $table->date('due_date')->nullable(); // Original due date
            $table->string('reference', 50)->nullable(); // MM-YYYY format (e.g., "12-2025")
            $table->decimal('bill_amount', 12, 2)->default(0); // Bill amount that penalty is based on
            $table->decimal('penalty_amount', 12, 2)->default(0); // 10% of bill amount
            $table->decimal('balance', 12, 2)->default(0); // Balance after penalty
            $table->string('username', 100)->nullable(); // Prepared by
            $table->dateTime('txtime')->nullable(); // Transaction time
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('consumer_zone_id')->references('id')->on('consumer_zone')->onDelete('cascade');
            $table->foreign('schedule_id')->references('id')->on('meter_reading_schedules')->onDelete('cascade');
            $table->foreign('downloaded_reading_id')->references('id')->on('downloaded_readings')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penalties');
    }
};
