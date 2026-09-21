<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Package subscriptions now run on native Paystack Plans, one per
     * package. A package plan can share its amount and interval with a
     * top-up preset plan (e.g. Awoof and a weekly ₦500 top-up), so the
     * (amount, interval) uniqueness no longer holds.
     */
    public function up(): void
    {
        Schema::table('paystack_plans', function (Blueprint $table) {
            $table->dropUnique('paystack_plans_amount_interval_unique');
            $table->foreignId('subscription_package_id')
                ->nullable()
                ->after('id')
                ->constrained('subscription_packages')
                ->nullOnDelete();
            $table->index(['amount_kobo', 'interval'], 'paystack_plans_amount_interval_index');
        });

        // Paystack has no fortnightly interval, so bi-weekly packages can
        // no longer be sold. Existing subscribers keep renewing until they cancel.
        DB::table('subscription_packages')
            ->where('renewal_cycle', 'bi_weekly')
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        Schema::table('paystack_plans', function (Blueprint $table) {
            $table->dropForeign(['subscription_package_id']);
            $table->dropColumn('subscription_package_id');
            $table->dropIndex('paystack_plans_amount_interval_index');
            $table->unique(['amount_kobo', 'interval'], 'paystack_plans_amount_interval_unique');
        });
    }
};
