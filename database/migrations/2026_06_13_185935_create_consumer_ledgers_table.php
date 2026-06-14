<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumer_ledgers', function (Blueprint $table) {
            $table->id();

            // Required: every ledger row belongs to a consumer
            $table->foreignId('consumer_zone_id')
                ->constrained('consumer_zone')
                ->cascadeOnDelete();

            // Optional links — null on delete to preserve ledger history
            $table->foreignId('consumer_payment_id')
                ->nullable()
                ->constrained('consumer_payments')
                ->nullOnDelete();

            $table->foreignId('schedule_id')
                ->nullable()
                ->constrained('meter_reading_schedules')
                ->nullOnDelete();

            $table->foreignId('downloaded_reading_id')
                ->nullable()
                ->constrained('downloaded_readings')
                ->nullOnDelete();

            $table->foreignId('penalty_id')
                ->nullable()
                ->constrained('penalties')
                ->nullOnDelete();

            $table->foreignId('billing_adjustment_id')
                ->nullable()
                ->constrained('billing_adjustments')
                ->nullOnDelete();

            $table->string('trans')->nullable()->index();
            $table->date('date')->nullable()->index();
            $table->date('due_date')->nullable()->index();
            $table->string('reference')->nullable();

            $table->decimal('reading', 10, 2)->nullable()->default(0);
            $table->decimal('volume', 10, 2)->nullable()->default(0);
            $table->decimal('billamount', 12, 2)->default(0);
            $table->decimal('penalty', 12, 2)->default(0);
            $table->decimal('others', 12, 2)->default(0);
            $table->decimal('debit', 12, 2)->default(0)->index();
            $table->decimal('credit', 12, 2)->default(0)->index();
            $table->decimal('balance', 12, 2)->default(0)->index();

            $table->string('username')->nullable();
            $table->timestamp('txtime')->nullable()->index();
            $table->timestamp('paid_at')->nullable()->index();
            $table->string('cl_ctrl')->nullable();
            $table->timestamps();

            $table->index(['consumer_zone_id', 'date']);
            $table->index(['consumer_zone_id', 'trans']);
            $table->index(['consumer_zone_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumer_ledgers');
    }
};