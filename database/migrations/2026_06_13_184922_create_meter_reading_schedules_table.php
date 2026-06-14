<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meter_reading_schedules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consumer_zone_id')->nullable()
                ->constrained('consumer_zone')
                ->cascadeOnDelete();

            $table->foreignId('assigned_reader_id')
                ->nullable()
                ->constrained('users');

            $table->date('bill_month');
            $table->date('bill_date');
            $table->date('due_date');
            $table->date('disconnection_date');

            $table->date('previous_reading_date')->nullable();
            $table->integer('previous_reading');

            $table->integer('current_reading')->nullable();
            $table->date('reading_date')->nullable();

            $table->integer('consumption')->default(0);
            $table->decimal('current_bill', 12, 2)->default(0);
            $table->decimal('arrears', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            $table->string('status');

            $table->integer('sedr_number');

            $table->string('prepared_by')->nullable();

            $table->timestamps();
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
