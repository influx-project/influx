<?php

namespace Tests\Feature;

use App\Enums\ServiceType;
use App\Models\DaemonMetric;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_database_can_be_seeded_more_than_once()
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::where('email', 'test@example.com')->count());
    }

    public function test_seeded_influx_daemon_services_have_history_and_can_be_viewed()
    {
        $this->seed(DatabaseSeeder::class);

        $service = Service::where('type', ServiceType::InfluxDaemon)->firstOrFail();
        $this->assertGreaterThan(0, DaemonMetric::where('service_id', $service->id)->count());

        $this->actingAs($service->owner)->get(route('services.daemon', $service))->assertOk();
        $this->actingAs($service->owner)->get(route('services.edit', $service))->assertOk();
    }
}
