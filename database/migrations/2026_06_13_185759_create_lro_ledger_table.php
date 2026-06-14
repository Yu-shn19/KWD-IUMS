<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lro_ledger', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consumer_zone_id')
                ->constrained('consumer_zone')
                ->cascadeOnDelete();

            $table->string('type');
            $table->string('ledger');

            $table->date('date')->nullable();
            $table->string('bam_no')->nullable();

            $table->decimal('amount', 12, 2);

            $table->string('acct_code')->nullable();
            $table->text('remarks')->nullable();

            $table->string('status');

            $table->decimal('correct_reading', 12, 2)->nullable();

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lro_ledger');
    }
};