<?php

namespace Database\Factories;

use App\Models\Metric;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Metric>
 */
class MetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'checked_at' => now(),
            'successful' => true,
            'latency_ms' => fake()->randomFloat(2, 5, 250),
            'status_code' => null,
            'error' => null,
        ];
    }

    /**
     * Indicate that the check failed.
     */
    public function failed(string $error = 'Connection refused'): static
    {
        return $this->state(fn (array $attributes) => [
            'successful' => false,
            'latency_ms' => null,
            'error' => $error,
        ]);
    }
}
