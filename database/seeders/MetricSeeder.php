<?php

namespace Database\Seeders;

use App\Enums\ServiceType;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Simulates a week of background checks for every collected service, with the
 * occasional outage and slow spell, plus what Influx Daemon services' hosts reported,
 * so the monitoring pages have something to show.
 */
class MetricSeeder extends Seeder
{
    /**
     * The number of days of history to simulate.
     */
    protected const DAYS = 7;

    /**
     * Seconds between simulated checks, regardless of each service's interval, to keep seeding quick.
     */
    protected const INTERVAL = 300;

    /**
     * Seed the metrics and incidents.
     */
    public function run(): void
    {
        Service::each(function (Service $service): void {
            // Services seeded earlier already have their history.
            if (! $service->isCollectingMetrics() || $service->metrics()->exists()) {
                return;
            }

            $this->simulate($service);

            if ($service->type === ServiceType::InfluxDaemon) {
                $this->simulateDaemon($service);
            }
        });
    }

    /**
     * Simulate the service's checks, opening and closing incidents as it goes down and recovers.
     */
    protected function simulate(Service $service): void
    {
        $now = now();
        $time = $now->copy()->subDays(self::DAYS);
        $baseLatency = fake()->numberBetween(8, 180);
        $outages = $this->randomWindows($time, $now, fake()->numberBetween(0, 3), 10, 90);
        $slowSpells = $this->randomWindows($time, $now, fake()->numberBetween(0, 2), 20, 120);

        $metrics = [];
        $incident = null;

        for (; $time->lt($now); $time = $time->addSeconds(self::INTERVAL)) {
            $down = $this->within($time, $outages);
            // Slow spells cross the alert threshold of half the timeout.
            $latency = $this->within($time, $slowSpells)
                ? $service->timeout * 1000 * fake()->randomFloat(2, 0.55, 0.9)
                : $baseLatency * fake()->randomFloat(2, 0.7, 1.5);
            $isHttp = $service->type === ServiceType::Http;

            $metrics[] = [
                'service_id' => $service->id,
                'checked_at' => $time->toDateTimeString(),
                'successful' => ! $down,
                'latency_ms' => $down ? null : round($latency, 2),
                'status_code' => $isHttp ? ($down ? 503 : 200) : null,
                'error' => $down ? ($isHttp ? 'HTTP 503 Service Unavailable' : 'Connection refused') : null,
            ];

            if ($down && $incident === null) {
                $incident = ['started_at' => $time->toDateTimeString(), 'failed_checks' => 0, 'cause' => end($metrics)['error'], 'status_code' => end($metrics)['status_code']];
            }

            if ($down) {
                $incident['failed_checks']++;
            } elseif ($incident !== null) {
                $service->incidents()->create([...$incident, 'ended_at' => $time->toDateTimeString()]);
                $incident = null;
            }
        }

        if ($incident !== null) {
            $service->incidents()->create($incident);
        }

        foreach (array_chunk($metrics, 500) as $chunk) {
            DB::table('metrics')->insert($chunk);
        }

        Service::withoutTimestamps(fn () => $service->forceFill(['last_checked_at' => $now->copy()->subSeconds(self::INTERVAL)])->saveQuietly());
    }

    /**
     * Simulate a host's minutes of daemon samples: a daily rhythm of CPU, memory and
     * traffic, with a few containers of which one is occasionally unhealthy.
     */
    protected function simulateDaemon(Service $service): void
    {
        $now = now();
        $start = $now->copy()->subDays(self::DAYS)->startOfMinute();
        $memoryTotal = 16 * 1024 ** 3;
        $containers = fake()->numberBetween(3, 8);
        $rows = [];

        for ($time = $start; $time->lt($now); $time = $time->addSeconds(self::INTERVAL)) {
            // Busiest in the afternoon, quietest overnight.
            $load = (1 - cos(2 * M_PI * ($time->hour + $time->minute / 60 - 3) / 24)) / 2;
            $cpu = min(100, 5 + 55 * $load + fake()->randomFloat(2, 0, 10));

            $rows[] = [
                'service_id' => $service->id,
                'minute' => $time->toDateTimeString(),
                'samples' => 12,
                'seconds' => 60,
                'cpu_percent' => round($cpu, 2),
                'cpu_percent_max' => round(min(100, $cpu + fake()->randomFloat(2, 0, 25)), 2),
                'load_1' => round($cpu / 25, 2),
                'memory_used_bytes' => (int) ($memoryTotal * (0.35 + 0.3 * $load + fake()->randomFloat(3, 0, 0.05))),
                'memory_total_bytes' => $memoryTotal,
                'swap_used_bytes' => 0,
                // The fullest disk fills up slowly over the week.
                'disk_used_percent' => round(60 + 8 * $start->diffInSeconds($time) / $start->diffInSeconds($now), 2),
                'disk_read_bytes' => (int) (60 * (0.5 + 4 * $load) * 1024 ** 2 * fake()->randomFloat(2, 0.5, 1.5)),
                'disk_write_bytes' => (int) (60 * (0.3 + 2 * $load) * 1024 ** 2 * fake()->randomFloat(2, 0.5, 1.5)),
                'network_rx_bytes' => (int) (60 * (0.2 + 8 * $load) * 1024 ** 2 * fake()->randomFloat(2, 0.5, 1.5)),
                'network_tx_bytes' => (int) (60 * (0.1 + 3 * $load) * 1024 ** 2 * fake()->randomFloat(2, 0.5, 1.5)),
                'containers_total' => $containers,
                'containers_running' => $containers - (fake()->boolean(3) ? 1 : 0),
                'containers_unhealthy' => fake()->boolean(2) ? 1 : 0,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('daemon_metrics')->insert($chunk);
        }
    }

    /**
     * Pick random windows of time between the given times.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    protected function randomWindows(CarbonImmutable $from, CarbonImmutable $to, int $count, int $minMinutes, int $maxMinutes): array
    {
        return array_map(function () use ($from, $to, $minMinutes, $maxMinutes): array {
            $start = CarbonImmutable::createFromTimestamp(fake()->numberBetween($from->getTimestamp(), $to->getTimestamp() - 3600));

            return [$start, $start->copy()->addMinutes(fake()->numberBetween($minMinutes, $maxMinutes))];
        }, $count === 0 ? [] : range(1, $count));
    }

    /**
     * Determine whether the time falls in any of the windows.
     *
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $windows
     */
    protected function within(CarbonImmutable $time, array $windows): bool
    {
        foreach ($windows as [$start, $end]) {
            if ($time->between($start, $end)) {
                return true;
            }
        }

        return false;
    }
}
