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
        Schema::create('point_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(indexName: 'fk_point_subscriptions_user_id')->cascadeOnDelete();
            $table->foreignId('paystack_plan_id')->constrained(indexName: 'fk_point_subscriptions_plan_id')->cascadeOnDelete();

            // The reference of the initializing transaction, used to correlate
            // the very first charge.success webhook before we know the
            // subscription_code (which only arrives via subscription.create).
            $table->string('pending_reference')->nullable();

            $table->string('subscription_code')->nullable()->unique();
            $table->string('email_token')->nullable();
            $table->string('customer_code')->nullable();
            $table->string('authorization_code')->nullable();
            $table->string('authorization_last4', 4)->nullable();
            $table->string('authorization_brand')->nullable();

            $table->decimal('amount_naira', 15, 2);
            $table->string('frequency');
            $table->string('status')->default('pending');

            $table->timestamp('next_payment_date')->nullable();
            $table->timestamp('last_charged_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'point_subscriptions_user_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_subscriptions');
    }
};
