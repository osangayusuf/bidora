<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-off points purchases moved out of subscription packages into the
     * standalone top-up flow, so any one-off package (e.g. Solo) is retired.
     * The rows stay so historical purchases still resolve their package.
     */
    public function up(): void
    {
        DB::table('subscription_packages')
            ->where('renewal_cycle', 'one_off')
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
