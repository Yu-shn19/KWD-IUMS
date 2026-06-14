<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('penalties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consumer_zone_id')
                ->constrained('consumer_zone')
                ->cascadeOnDelete();

            $table->foreignId('schedule_id')
                ->nullable()
                ->constrained('meter_reading_schedules');

            $table->date('date');
            $table->date('due_date')->nullable();

            $table->string('reference')->nullable();

            $table->decimal('bill_amount', 12, 2);
            $table->decimal('penalty_amount', 12, 2);
            $table->decimal('balance', 12, 2);

            $table->string('username')->nullable();
            $table->date('txtime')->nullable();
            $table->date('paid_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalties');
    }
};
