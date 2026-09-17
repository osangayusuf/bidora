<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A package-based subscription has no Paystack Plan/interval — it is
        // charged locally via a saved card authorization instead of a native
        // Paystack Subscription. Both columns must become optional so a row
        // can belong to either a paystack_plan_id (existing flow) or a
        // subscription_package_id (new flow), never both.
        $this->dropPlanForeignKey();

        Schema::table('point_subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('paystack_plan_id')->nullable()->change();
            $table->string('frequency')->nullable()->change();
            $table->foreignId('subscription_package_id')
                ->nullable()
                ->after('paystack_plan_id')
                ->constrained(indexName: 'fk_point_subscriptions_package_id')
                ->cascadeOnDelete();
            $table->timestamp('next_charge_at')->nullable()->after('next_payment_date');
        });

        Schema::table('point_subscriptions', function (Blueprint $table) {
            $table->foreign('paystack_plan_id', 'fk_point_subscriptions_plan_id')
                ->references('id')->on('paystack_plans')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('point_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['subscription_package_id']);
            $table->dropColumn(['subscription_package_id', 'next_charge_at']);
        });

        $this->dropPlanForeignKey();

        Schema::table('point_subscriptions', function (Blueprint $table) {
            $table->unsignedBigInteger('paystack_plan_id')->nullable(false)->change();
            $table->string('frequency')->nullable(false)->change();
        });

        Schema::table('point_subscriptions', function (Blueprint $table) {
            $table->foreign('paystack_plan_id', 'fk_point_subscriptions_plan_id')
                ->references('id')->on('paystack_plans')->cascadeOnDelete();
        });
    }

    /**
     * SQLite's schema grammar can only drop a foreign key given the column(s)
     * it's on (it rebuilds the table); MySQL/Postgres need the explicit
     * custom constraint name it was created with. Branch so this migration
     * runs cleanly on both.
     */
    private function dropPlanForeignKey(): void
    {
        Schema::table('point_subscriptions', function (Blueprint $table) {
            if (DB::getDriverName() === 'sqlite') {
                $table->dropForeign(['paystack_plan_id']);
            } else {
                $table->dropForeign('fk_point_subscriptions_plan_id');
            }
        });
    }
};
