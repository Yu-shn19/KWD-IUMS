<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('downloaded_readings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consumer_zone_id')->nullable()
                ->constrained('consumer_zone')
                ->cascadeOnDelete();

            $table->foreignId('schedule_id')
                ->constrained('meter_reading_schedules')
                ->cascadeOnDelete();

            $table->foreignId('reader_id')
                ->nullable()
                ->constrained('users');

            $table->integer('previous_reading');
            $table->integer('current_reading')->nullable();

            $table->integer('consumption')->nullable();

            $table->decimal('current_bill', 12, 2)->nullable();

            $table->date('reading_date')->nullable();

            $table->string('status')->nullable();

            $table->text('reader_notes')->nullable();

            $table->string('prepared_by')->nullable();
            $table->datetime('paid_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('downloaded_readings');
    }
};