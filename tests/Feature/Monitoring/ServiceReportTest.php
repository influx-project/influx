<?php

namespace Tests\Feature\Monitoring;

use App\Enums\ServiceType;
use App\Models\Incident;
use App\Models\Metric;
use App\Models\Service;
use App\Monitoring\ServiceReport;
use App\Monitoring\TimeRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_reflects_the_latest_check_and_ongoing_incident()
    {
        $report = new ServiceReport;

        $this->assertSame('pending', $report->status(Service::factory()->create())['state']);
        $this->assertSame('paused', $report->status(Service::factory()->disabled()->create())['state']);
        $this->assertSame('paused', $report->status(Service::factory()->create(['collect_metrics' => false]))['state']);
        $this->assertSame('pending', $report->status(Service::factory()->create(['type' => ServiceType::InfluxDaemon]))['state']);

        $up = Service::factory()->create();
        Metric::factory()->for($up)->create();
        $this->assertSame('up', $report->status($up)['state']);

        $down = Service::factory()->create();
        Metric::factory()->for($down)->failed()->create();
        $incident = Incident::factory()->for($down)->ongoing()->create(['started_at' => now()->subMinutes(5)]);
        $status = $report->status($down);
        $this->assertSame('down', $status['state']);
        $this->assertTrue($status['since']->equalTo($incident->started_at));
    }

    public function test_uptime_subtracts_incident_time_since_the_first_check()
    {
        $this->freezeSecond();
        $service = Service::factory()->create();
        Metric::factory()->for($service)->create(['checked_at' => now()->subHours(10)]);
        Incident::factory()->for($service)->create([
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHours(2),
        ]);

        // Down for 1 of the 10 hours since the first check; the rest of the day is not counted.
        $this->assertSame(90.0, (new ServiceReport)->uptime($service, now()->subDay(), now()));
    }

    public function test_uptime_is_unknown_for_services_never_checked()
    {
        $this->assertNull((new ServiceReport)->uptime(Service::factory()->create(), now()->subDay(), now()));
    }

    public function test_overview_buckets_checks_for_the_charts()
    {
        $this->travelTo(now()->setTime(12, 0));
        $service = Service::factory()->https()->create();
        Metric::factory()->for($service)->create(['checked_at' => now()->subMinutes(20), 'latency_ms' => 100, 'status_code' => 200]);
        Metric::factory()->for($service)->create(['checked_at' => now()->subMinutes(19), 'latency_ms' => 300, 'status_code' => 200]);
        Metric::factory()->for($service)->failed()->create(['checked_at' => now()->subMinutes(18), 'status_code' => 500]);

        $overview = (new ServiceReport)->overview($service, TimeRange::Day);

        $this->assertSame(3, $overview['stats']['checks']);
        $this->assertSame(1, $overview['stats']['failed_checks']);
        $this->assertSame(200.0, $overview['stats']['average_latency_ms']);
        $this->assertSame(300.0, $overview['stats']['p95_latency_ms']);
        $this->assertCount(97, $overview['series']);

        $bucket = collect($overview['series'])->firstWhere('checks', 3);
        $this->assertSame(now()->subMinutes(30)->toIso8601String(), $bucket['time']);
        $this->assertSame(1, $bucket['failed']);
        $this->assertSame(200.0, $bucket['average_latency_ms']);
        $this->assertSame(300.0, $bucket['max_latency_ms']);

        $this->assertSame([['status_code' => 200, 'count' => 2], ['status_code' => 500, 'count' => 1]], $overview['status_codes']);
        $this->assertCount(3, $overview['recent_checks']);
    }

    public function test_status_codes_are_only_reported_for_http_services()
    {
        $this->assertNull((new ServiceReport)->overview(Service::factory()->create(), TimeRange::Day)['status_codes']);
    }

    public function test_downtime_summarises_incidents_and_days()
    {
        $this->travelTo(now()->setTime(12, 0));
        $service = Service::factory()->create(['created_at' => now()->subDays(100)]);
        Metric::factory()->for($service)->create(['checked_at' => now()->subDays(20)]);
        Incident::factory()->for($service)->create(['started_at' => now()->subDays(2), 'ended_at' => now()->subDays(2)->addMinutes(30)]);
        Incident::factory()->for($service)->create(['started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addMinutes(90)]);

        $downtime = (new ServiceReport)->downtime($service);

        $this->assertSame(2, $downtime['stats']['incidents']);
        $this->assertSame(120 * 60, $downtime['stats']['downtime_seconds']);
        $this->assertSame(60 * 60, $downtime['stats']['mean_time_to_recovery_seconds']);
        $this->assertSame(90 * 60, $downtime['stats']['longest_seconds']);
        $this->assertCount(ServiceReport::HISTORY_DAYS, $downtime['daily']);
        $this->assertNull($downtime['daily'][0]['uptime'], 'Days before the first check have no data.');

        $yesterday = collect($downtime['daily'])->firstWhere('date', now()->subDay()->toDateString());
        $this->assertSame(90 * 60, $yesterday['downtime_seconds']);
        $this->assertSame(1, $yesterday['incidents']);
    }

    public function test_alerts_include_outages_recoveries_and_slow_periods()
    {
        $this->freezeSecond();
        $service = Service::factory()->create(['timeout' => 2]);
        $incident = Incident::factory()->for($service)->create(['started_at' => now()->subHours(5), 'ended_at' => now()->subHours(4)]);

        // Two slow checks in a row, a normal one, then another slow one: two slow periods.
        foreach ([1500, 1800, 50, 1200] as $minutes => $latency) {
            Metric::factory()->for($service)->create(['checked_at' => now()->subMinutes(10 - $minutes), 'latency_ms' => $latency]);
        }

        $alerts = (new ServiceReport)->alerts($service);

        $this->assertSame(1000, $alerts['slow_threshold_ms']);
        $this->assertSame(['slow', 'slow', 'recovered', 'down'], $alerts['events']->pluck('kind')->all());

        [$latest, $earlier] = $alerts['events']->all();
        $this->assertSame(1, $latest['checks']);
        $this->assertSame(2, $earlier['checks']);
        $this->assertSame(1800.0, $earlier['peak_latency_ms']);
        $this->assertSame("incident-{$incident->id}-down", $alerts['events'][3]['id']);
    }
}
