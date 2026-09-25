<?php

namespace Tests\Feature\Monitoring;

use App\Enums\ServiceType;
use App\Models\Service;
use App\Monitoring\Checkers\TcpChecker;
use App\Monitoring\CheckResult;
use App\Monitoring\ServiceMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ServiceMonitorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Make TCP checks return the given results, in order.
     */
    protected function fakeTcpResults(CheckResult|RuntimeException ...$results): void
    {
        $this->app->instance(TcpChecker::class, new class($results) extends TcpChecker
        {
            public function __construct(private array $results) {}

            public function check(Service $service): CheckResult
            {
                $result = array_shift($this->results);

                if ($result instanceof RuntimeException) {
                    throw $result;
                }

                return $result;
            }
        });
    }

    public function test_a_successful_check_is_recorded_without_an_incident()
    {
        $this->freezeSecond();
        $this->fakeTcpResults(CheckResult::up(12.345));
        $service = Service::factory()->create();

        $metric = app(ServiceMonitor::class)->check($service);

        $this->assertTrue($metric->successful);
        $this->assertSame(12.35, $metric->latency_ms);
        $this->assertTrue($metric->checked_at->equalTo(now()));
        $this->assertTrue($service->fresh()->last_checked_at->equalTo(now()));
        $this->assertSame(0, $service->incidents()->count());
    }

    public function test_failures_open_one_incident_and_the_next_success_closes_it()
    {
        $this->fakeTcpResults(
            CheckResult::down('Connection refused'),
            CheckResult::down('Connection refused'),
            CheckResult::up(10),
        );
        $service = Service::factory()->create();
        $monitor = app(ServiceMonitor::class);

        $this->travelTo(now()->startOfMinute());
        $monitor->check($service);
        $startedAt = now();

        $this->travel(1)->minute();
        $monitor->check($service);

        $incident = $service->incidents()->sole();
        $this->assertNull($incident->ended_at);
        $this->assertSame(2, $incident->failed_checks);
        $this->assertSame('Connection refused', $incident->cause);
        $this->assertTrue($incident->started_at->equalTo($startedAt));

        $this->travel(1)->minute();
        $monitor->check($service);

        $this->assertTrue($incident->fresh()->ended_at->equalTo(now()));
        $this->assertSame(3, $service->metrics()->count());
    }

    public function test_a_checker_exception_is_recorded_as_a_failed_check()
    {
        $this->withoutExceptionHandling();
        $this->fakeTcpResults(new RuntimeException('Something broke'));
        $service = Service::factory()->create();

        $metric = app(ServiceMonitor::class)->check($service);

        $this->assertFalse($metric->successful);
        $this->assertSame('Something broke', $metric->error);
        $this->assertSame(1, $service->incidents()->ongoing()->count());
    }

    public function test_influx_daemon_services_are_not_checked()
    {
        $service = Service::factory()->create(['type' => ServiceType::InfluxDaemon]);

        $this->assertNull(app(ServiceMonitor::class)->check($service));
        $this->assertSame(0, $service->metrics()->count());
        $this->assertNull($service->fresh()->last_checked_at);
    }
}
