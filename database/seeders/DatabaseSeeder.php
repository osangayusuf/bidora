<?php

namespace Database\Seeders;

use App\Models\Auction;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // $this->call([
        //     RoleSeeder::class,
        //     SubscriptionPackageSeeder::class,
        // ]);

        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        Auction::factory(10)->active()->create();
        Auction::factory(10)->closed()->create();
        Auction::factory(10)->triggered()->create();
    }
}
