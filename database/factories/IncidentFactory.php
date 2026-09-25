<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-30 days', '-1 hour');

        return [
            'service_id' => Service::factory(),
            'started_at' => $startedAt,
            'ended_at' => (clone $startedAt)->modify('+'.fake()->numberBetween(1, 45).' minutes'),
            'cause' => 'Connection refused',
            'status_code' => null,
            'failed_checks' => fake()->numberBetween(1, 20),
        ];
    }

    /**
     * Indicate that the incident has not been resolved.
     */
    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => null,
        ]);
    }
}
