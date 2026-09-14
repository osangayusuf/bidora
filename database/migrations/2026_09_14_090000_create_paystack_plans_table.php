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
        Schema::create('paystack_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('amount_kobo');
            $table->string('interval');
            $table->string('plan_code')->unique();
            $table->string('name');
            $table->timestamps();

            $table->unique(['amount_kobo', 'interval'], 'paystack_plans_amount_interval_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paystack_plans');
    }
};
