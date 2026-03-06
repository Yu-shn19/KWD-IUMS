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
        Schema::create('downloaded_readings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('schedule_id'); // Reference to meter_reading_schedules
            $table->unsignedBigInteger('reader_id'); // Reference to users
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->integer('previous_reading')->default(0);
            $table->integer('current_reading')->nullable();
            $table->integer('consumption')->nullable();
            $table->date('reading_date')->nullable();
            $table->enum('status', ['pending', 'completed'])->default('pending');
            $table->text('reader_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('schedule_id')->references('id')->on('meter_reading_schedules')->onDelete('cascade');
            $table->foreign('reader_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes for faster queries
            $table->index('reader_id');
            $table->index('schedule_id');
            $table->index('status');
            $table->unique(['schedule_id', 'reader_id']); // One reading per schedule per reader
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('downloaded_readings');
    }
};
