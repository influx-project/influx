<?php

namespace Database\Seeders;

use App\Enums\ServiceType;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Simulates a week of background checks for every collected service, with the
 * occasional outage and slow spell, so the monitoring pages have something to show.
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
            if (! $service->isCollectingMetrics()) {
                return;
            }

            $this->simulate($service);
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
