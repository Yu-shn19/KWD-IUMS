<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('outstanding_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consumer_zone_id')->nullable()->index();
            $table->string('account_no', 50)->index();
            $table->decimal('amount', 12, 2);
            $table->decimal('previous_balance', 12, 2)->default(0);
            $table->decimal('new_balance', 12, 2)->default(0);
            $table->string('reference', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('created_by', 150)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('outstanding_payments');
    }
};

