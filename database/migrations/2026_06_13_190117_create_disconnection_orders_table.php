<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('disconnection_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consumer_zone_id')
                ->constrained('consumer_zone')
                ->cascadeOnDelete();

            $table->foreignId('disconnector_id')
                ->nullable()
                ->constrained('users');


            $table->decimal('total_outstanding', 12, 2);

            $table->integer('unpaid_months');

            $table->date('disconnection_date');
            $table->date('disconnected_at')->nullable();


            $table->string('status');

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disconnection_orders');
    }
};
