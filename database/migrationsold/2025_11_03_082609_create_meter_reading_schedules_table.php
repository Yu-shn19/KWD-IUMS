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
        Schema::create('meter_reading_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumer_id')->constrained()->onDelete('cascade');
            $table->foreignId('assigned_reader_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('zone');
            $table->string('account_number');
            $table->string('account_name');
            $table->text('address');
            $table->string('category');
            $table->string('meter_number');
            
            // Billing Period Information
            $table->date('bill_month');
            $table->date('bill_date');
            $table->date('due_date');
            $table->date('disconnection_date');
            
            // Reading Information
            $table->date('previous_reading_date')->nullable();
            $table->integer('previous_reading')->default(0);
            $table->integer('current_reading')->nullable();
            $table->date('reading_date')->nullable();
            $table->integer('consumption')->default(0);
            
            // Billing Information
            $table->decimal('current_bill', 10, 2)->default(0);
            $table->decimal('arrears', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            
            // Status and Notes
            $table->enum('status', ['Prepared', 'Assigned', 'In Progress', 'Completed', 'Verified'])->default('Prepared');
            $table->text('reader_notes')->nullable();
            $table->text('remarks')->nullable();
            
            // Tracking
            $table->integer('sedr_number');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['zone', 'bill_month']);
            $table->index(['assigned_reader_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meter_reading_schedules');
    }
};
