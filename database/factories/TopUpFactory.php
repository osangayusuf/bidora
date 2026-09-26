<?php

namespace Database\Factories;

use App\Enums\TopUpStatus;
use App\Models\TopUp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TopUp>
 */
class TopUpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomElement([100, 500, 1000, 5000]);

        return [
            'user_id' => User::factory(),
            'reference' => 'ref_'.fake()->unique()->bothify('??????####'),
            'amount_naira' => $amount,
            'points_per_naira' => 10,
            'points' => $amount * 10,
            'status' => TopUpStatus::PENDING,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => TopUpStatus::COMPLETED,
            'paid_at' => now(),
        ]);
    }
}
