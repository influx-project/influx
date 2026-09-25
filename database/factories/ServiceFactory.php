<?php

namespace Database\Factories;

use App\Enums\ServiceImportance;
use App\Enums\ServiceType;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => ucfirst(fake()->unique()->word()).' '.fake()->randomElement(['API', 'database', 'web server', 'cache', 'mail relay']),
            'description' => fake()->optional()->sentence(),
            'location' => fake()->optional()->city(),
            'type' => ServiceType::Tcp,
            'host' => fake()->ipv4(),
            'port' => fake()->numberBetween(1024, 65535),
            'use_ssl' => false,
            'importance' => ServiceImportance::Normal,
            'check_interval' => 60,
            'timeout' => 10,
            'collect_metrics' => true,
            'stream_metrics' => true,
            'enabled' => true,
        ];
    }

    /**
     * Indicate that the service is not assigned to anyone.
     */
    public function unassigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }

    /**
     * Indicate that the service is an HTTPS web server.
     */
    public function https(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ServiceType::Http,
            'host' => fake()->domainName(),
            'port' => 443,
            'use_ssl' => true,
        ]);
    }

    /**
     * Indicate that the service is checked with ICMP ping, which has no port.
     */
    public function ping(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ServiceType::Ping,
            'port' => null,
            'use_ssl' => false,
        ]);
    }

    /**
     * Indicate that the service is a host running Influx Daemon on its default port.
     */
    public function influxDaemon(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ServiceType::InfluxDaemon,
            'port' => ServiceType::INFLUX_DAEMON_PORT,
            'use_ssl' => false,
        ]);
    }

    /**
     * Indicate the importance the service is monitored at.
     */
    public function importance(ServiceImportance $importance): static
    {
        return $this->state(fn (array $attributes) => [
            'importance' => $importance,
        ]);
    }

    /**
     * Indicate that monitoring of the service is paused.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }
}
