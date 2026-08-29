<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('consumer_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reading_id')
                ->nullable();

            $table->foreignId('consumer_zone_id')
                ->constrained('consumer_zone')
                ->cascadeOnDelete();

            $table->string('payment_method')->nullable();

            $table->decimal('payment_amount', 12, 2);

            $table->decimal('senior_citizen_discount', 12, 2)->default(0);
            $table->decimal('current_billing', 12, 2)->default(0);
            $table->decimal('current_penalty', 12, 2)->default(0);

            $table->decimal('mr_arrears', 12, 2)->default(0);

            $table->decimal('current_arrears', 12, 2)->default(0);
            $table->decimal('prio_years', 12, 2)->default(0);

            $table->decimal('advances', 12, 2)->default(0);
            $table->decimal('current_mr', 12, 2)->default(0);

            $table->decimal('amount_tendered', 12, 2)->default(0);
            $table->decimal('change_amount', 12, 2)->default(0);

            $table->string('or_number')->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->text('remarks')->nullable();

            $table->string('created_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumer_payments');
    }
};
