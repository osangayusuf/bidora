<?php

namespace Database\Seeders;

use App\Enums\PackageRenewalCycle;
use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

class SubscriptionPackageSeeder extends Seeder
{
    /**
     * Seed the initial subscription package catalog. Safe to re-run —
     * existing rows are matched and updated by slug rather than duplicated.
     * Once seeded, packages are managed via the admin CRUD screen.
     */
    public function run(): void
    {
        $packages = [
            ['name' => 'Solo', 'slug' => 'solo', 'renewal_cycle' => PackageRenewalCycle::ONE_OFF, 'points_allocated' => 5000, 'price_naira' => 500, 'sort_order' => 1],
            ['name' => 'Jara', 'slug' => 'jara', 'renewal_cycle' => PackageRenewalCycle::DAILY, 'points_allocated' => 1500, 'price_naira' => 100, 'sort_order' => 2],
            ['name' => 'Awoof', 'slug' => 'awoof', 'renewal_cycle' => PackageRenewalCycle::WEEKLY, 'points_allocated' => 6000, 'price_naira' => 500, 'sort_order' => 3],
            ['name' => 'Confam', 'slug' => 'confam', 'renewal_cycle' => PackageRenewalCycle::BI_WEEKLY, 'points_allocated' => 12500, 'price_naira' => 1000, 'sort_order' => 4, 'is_active' => false],
            ['name' => 'Sure Tin', 'slug' => 'sure-tin', 'renewal_cycle' => PackageRenewalCycle::MONTHLY, 'points_allocated' => 26000, 'price_naira' => 2000, 'sort_order' => 5],
        ];

        foreach ($packages as $package) {
            SubscriptionPackage::updateOrCreate(
                ['slug' => $package['slug']],
                $package,
            );
        }
    }
}
