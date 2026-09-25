<?php

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Models\DaemonMetric;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DaemonMetric>
 */
class DaemonMetricFactory extends Factory
{
    /**
     * Define the model's default state: a minute of 5-second samples from a lightly loaded host.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cpu = fake()->randomFloat(2, 2, 40);

        return [
            'service_id' => Service::factory()->state(['type' => ServiceType::InfluxDaemon]),
            'minute' => now()->startOfMinute(),
            'samples' => 12,
            'seconds' => 60,
            'cpu_percent' => $cpu,
            'cpu_percent_max' => min(100, $cpu + fake()->randomFloat(2, 0, 30)),
            'load_1' => fake()->randomFloat(2, 0, 4),
            'memory_used_bytes' => fake()->numberBetween(2, 12) * 1024 ** 3,
            'memory_total_bytes' => 16 * 1024 ** 3,
            'swap_used_bytes' => 0,
            'disk_used_percent' => fake()->randomFloat(2, 20, 90),
            'disk_read_bytes' => fake()->numberBetween(0, 50) * 1024 ** 2,
            'disk_write_bytes' => fake()->numberBetween(0, 50) * 1024 ** 2,
            'network_rx_bytes' => fake()->numberBetween(1, 500) * 1024 ** 2,
            'network_tx_bytes' => fake()->numberBetween(1, 200) * 1024 ** 2,
            'containers_total' => 6,
            'containers_running' => 5,
            'containers_unhealthy' => 0,
        ];
    }
}
