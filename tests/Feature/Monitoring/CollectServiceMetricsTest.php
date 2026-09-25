<?php

namespace Tests\Feature\Monitoring;

use App\Enums\ServiceType;
use App\Jobs\CheckService;
use App\Models\Incident;
use App\Models\Metric;
use App\Models\Service;
use App\Monitoring\ServiceMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class CollectServiceMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_enabled_collected_services_that_are_due_are_queued()
    {
        Queue::fake();
        $this->freezeSecond();

        $never = Service::factory()->create(['check_interval' => 120]);
        $overdue = Service::factory()->create();
        Service::withoutTimestamps(fn () => $overdue->forceFill(['next_check_at' => now()->subSecond()])->saveQuietly());
        $notDue = Service::factory()->create();
        Service::withoutTimestamps(fn () => $notDue->forceFill(['next_check_at' => now()->addMinute()])->saveQuietly());
        Service::factory()->disabled()->create();
        Service::factory()->create(['collect_metrics' => false]);
        $daemon = Service::factory()->create(['type' => ServiceType::InfluxDaemon]);

        $this->artisan('services:collect-metrics')
            ->expectsOutputToContain('Queued 3 checks.')
            ->assertSuccessful();

        Queue::assertPushed(CheckService::class, 3);
        Queue::assertPushed(CheckService::class, fn (CheckService $job) => $job->service->is($daemon));
        Queue::assertPushed(CheckService::class, fn (CheckService $job) => $job->service->is($never));
        Queue::assertPushed(CheckService::class, fn (CheckService $job) => $job->service->is($overdue));
        $this->assertTrue($never->fresh()->next_check_at->equalTo(now()->addSeconds(120)));
    }

    public function test_force_queues_services_that_are_not_due()
    {
        Queue::fake();
        $service = Service::factory()->create();
        Service::withoutTimestamps(fn () => $service->forceFill(['next_check_at' => now()->addHour()])->saveQuietly());

        $this->artisan('services:collect-metrics', ['--force' => true])->assertSuccessful();

        Queue::assertPushed(CheckService::class, 1);
    }

    public function test_scheduling_a_check_does_not_touch_the_services_updated_at()
    {
        Queue::fake();
        $service = Service::factory()->create(['updated_at' => now()->subDay()]);

        $this->artisan('services:collect-metrics');

        $this->assertTrue($service->fresh()->updated_at->equalTo($service->updated_at));
    }

    public function test_the_job_skips_services_whose_monitoring_was_switched_off()
    {
        $service = Service::factory()->disabled()->create();

        $this->mock(ServiceMonitor::class, fn (MockInterface $mock) => $mock->shouldNotReceive('check'));

        app()->call([new CheckService($service), 'handle']);
    }

    public function test_the_job_is_unique_per_service()
    {
        $service = Service::factory()->create(['timeout' => 20]);
        $job = new CheckService($service);

        $this->assertSame((string) $service->id, $job->uniqueId());
        $this->assertSame(35, $job->timeout);
    }

    public function test_changing_how_a_service_is_checked_makes_it_due_immediately()
    {
        $service = Service::factory()->create();
        Service::withoutTimestamps(fn () => $service->forceFill(['next_check_at' => now()->addHour()])->saveQuietly());

        $service->update(['name' => 'Renamed']);
        $this->assertNotNull($service->fresh()->next_check_at);

        $service->update(['check_interval' => 30]);
        $this->assertNull($service->fresh()->next_check_at);
    }

    public function test_stopping_collection_closes_the_ongoing_incident()
    {
        $service = Service::factory()->create();
        $incident = Incident::factory()->for($service)->ongoing()->create(['started_at' => now()->subHour()]);

        $service->update(['name' => 'Renamed']);
        $this->assertNull($incident->fresh()->ended_at);

        $service->update(['enabled' => false]);
        $this->assertNotNull($incident->fresh()->ended_at);
    }

    public function test_metrics_older_than_the_retention_period_are_pruned()
    {
        $service = Service::factory()->create();
        $old = Metric::factory()->for($service)->create(['checked_at' => now()->subDays(Metric::RETENTION_DAYS + 1)]);
        $recent = Metric::factory()->for($service)->create(['checked_at' => now()->subDays(Metric::RETENTION_DAYS - 1)]);

        $this->artisan('model:prune', ['--model' => [Metric::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    public function test_deleting_a_service_deletes_its_metrics_and_incidents()
    {
        $service = Service::factory()->create();
        Metric::factory()->for($service)->create();
        Incident::factory()->for($service)->create();

        $service->delete();

        $this->assertSame(0, Metric::count());
        $this->assertSame(0, Incident::count());
    }
}
