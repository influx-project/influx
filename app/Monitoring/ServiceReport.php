<?php

namespace App\Monitoring;

use App\Enums\ServiceType;
use App\Models\Incident;
use App\Models\Metric;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use stdClass;

/**
 * Summarises a service's background checks and incidents for its pages.
 */
class ServiceReport
{
    /**
     * The number of days shown on the daily uptime history.
     */
    public const HISTORY_DAYS = 90;

    /**
     * How far back derived alerts are shown.
     */
    public const ALERT_DAYS = 30;

    /**
     * Get the service's current state and latest check.
     *
     * @return array<string, mixed>
     */
    public function status(Service $service): array
    {
        $service->loadMissing(['latestMetric', 'ongoingIncident']);

        $latest = $service->latestMetric;
        $incident = $service->ongoingIncident;

        $state = match (true) {
            ! $service->type->collectsMetrics() => 'unsupported',
            ! $service->enabled || ! $service->collect_metrics => 'paused',
            $incident !== null => 'down',
            $latest === null => 'pending',
            default => 'up',
        };

        $since = match ($state) {
            'down' => $incident->started_at,
            'up' => $service->incidents()->max('ended_at') ?? $service->metrics()->min('checked_at'),
            default => null,
        };

        return [
            'state' => $state,
            'since' => $since === null ? null : CarbonImmutable::parse($since),
            'last_check' => $latest === null ? null : $this->metric($latest),
            'next_check_at' => $state === 'up' || $state === 'down' || $state === 'pending' ? $service->next_check_at : null,
        ];
    }

    /**
     * Get the headline numbers, charts and recent checks for the given range.
     *
     * @return array<string, mixed>
     */
    public function overview(Service $service, TimeRange $range): array
    {
        $to = now();
        $from = $to->copy()->subSeconds($range->seconds());

        $checks = $service->metrics()
            ->where('checked_at', '>=', $from)
            ->orderBy('checked_at')
            ->toBase()
            ->get(['checked_at', 'successful', 'latency_ms']);

        $latencies = $checks
            ->filter(fn (stdClass $check): bool => (bool) $check->successful && $check->latency_ms !== null)
            ->map(fn (stdClass $check): float => (float) $check->latency_ms)
            ->sort()
            ->values();

        return [
            'stats' => [
                'uptime' => $this->uptime($service, $from, $to),
                'checks' => $checks->count(),
                'failed_checks' => $checks->reject(fn (stdClass $check): bool => (bool) $check->successful)->count(),
                'average_latency_ms' => $latencies->isEmpty() ? null : round($latencies->avg(), 1),
                'p95_latency_ms' => $this->percentile($latencies, 0.95),
                'incidents' => $service->incidents()->overlapping($from, $to)->count(),
            ],
            'series' => $this->series($checks, $from, $to, $range->bucketSeconds()),
            'status_codes' => $service->type === ServiceType::Http ? $this->statusCodes($service, $from) : null,
            'recent_checks' => $service->metrics()->latest('checked_at')->limit(10)->get()->map($this->metric(...)),
        ];
    }

    /**
     * Get uptime over several windows, a daily history and the service's incidents.
     *
     * @return array<string, mixed>
     */
    public function downtime(Service $service): array
    {
        $now = now();
        $monthAgo = $now->copy()->subDays(30);

        $recent = $service->incidents()->overlapping($monthAgo, $now)->get();
        $resolved = $recent->whereNotNull('ended_at');

        return [
            'uptime' => collect(['24h' => 1, '7d' => 7, '30d' => 30, '90d' => 90])
                ->map(fn (int $days): ?float => $this->uptime($service, $now->copy()->subDays($days), $now)),
            'stats' => [
                'incidents' => $recent->count(),
                'downtime_seconds' => $recent->sum(fn (Incident $incident): int => $this->overlap($incident, $monthAgo, $now)),
                'mean_time_to_recovery_seconds' => $resolved->isEmpty() ? null : (int) round($resolved->avg(fn (Incident $incident): int => $incident->durationInSeconds())),
                'longest_seconds' => $recent->isEmpty() ? null : $recent->max(fn (Incident $incident): int => $incident->durationInSeconds()),
            ],
            'daily' => $this->daily($service, $now),
        ];
    }

    /**
     * Get the service's incidents, newest first.
     *
     * @return LengthAwarePaginator<int, Incident>
     */
    public function incidents(Service $service, int $perPage = 10): LengthAwarePaginator
    {
        return $service->incidents()->latest('started_at')->latest('id')->paginate($perPage);
    }

    /**
     * Get the notable events derived from the service's checks: outages, recoveries and slow responses.
     *
     * @return array<string, mixed>
     */
    public function alerts(Service $service): array
    {
        $from = now()->subDays(self::ALERT_DAYS);
        $slowThresholdMs = $service->timeout * 500;

        $events = collect();

        foreach ($service->incidents()->where(fn ($query) => $query->where('started_at', '>=', $from)->orWhere('ended_at', '>=', $from)->orWhereNull('ended_at'))->get() as $incident) {
            if ($incident->started_at->gte($from)) {
                $events->push([
                    'id' => "incident-{$incident->id}-down",
                    'kind' => 'down',
                    'at' => $incident->started_at,
                    'ended_at' => $incident->ended_at,
                    'cause' => $incident->cause,
                    'status_code' => $incident->status_code,
                    'checks' => $incident->failed_checks,
                ]);
            }

            if ($incident->ended_at !== null) {
                $events->push([
                    'id' => "incident-{$incident->id}-recovered",
                    'kind' => 'recovered',
                    'at' => $incident->ended_at,
                    'duration_seconds' => $incident->durationInSeconds(),
                ]);
            }
        }

        $events->push(...$this->slowPeriods($service, $from, $slowThresholdMs));

        return [
            'events' => $events->sortByDesc(fn (array $event) => $event['at']->getTimestamp())->take(100)->values(),
            'slow_threshold_ms' => $slowThresholdMs,
            'days' => self::ALERT_DAYS,
        ];
    }

    /**
     * Get the percentage of the period the service was up, or null if it was never checked.
     */
    public function uptime(Service $service, CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        return $this->uptimeFromIncidents(
            $service->incidents()->overlapping($from, $to)->get(),
            $this->firstSeen($service),
            $from,
            $to,
        );
    }

    /**
     * Get when the service was first checked, as far back as the metrics and incidents go.
     */
    protected function firstSeen(Service $service): ?CarbonImmutable
    {
        return collect([
            $service->metrics()->min('checked_at'),
            $service->incidents()->min('started_at'),
        ])->filter()->map(fn (string $value): CarbonImmutable => CarbonImmutable::parse($value))->min();
    }

    /**
     * Group the checks into evenly sized buckets, including empty ones, for charting.
     *
     * @param  Collection<int, stdClass>  $checks
     * @return list<array<string, mixed>>
     */
    protected function series(Collection $checks, CarbonImmutable $from, CarbonImmutable $to, int $bucketSeconds): array
    {
        $start = intdiv($from->getTimestamp(), $bucketSeconds) * $bucketSeconds;
        $buckets = [];

        for ($time = $start; $time <= $to->getTimestamp(); $time += $bucketSeconds) {
            $buckets[$time] = ['checks' => 0, 'failed' => 0, 'latencies' => []];
        }

        foreach ($checks as $check) {
            $time = intdiv(strtotime($check->checked_at), $bucketSeconds) * $bucketSeconds;

            if (! isset($buckets[$time])) {
                continue;
            }

            $buckets[$time]['checks']++;

            if (! $check->successful) {
                $buckets[$time]['failed']++;
            } elseif ($check->latency_ms !== null) {
                $buckets[$time]['latencies'][] = (float) $check->latency_ms;
            }
        }

        return array_map(fn (int $time, array $bucket): array => [
            'time' => CarbonImmutable::createFromTimestamp($time)->toIso8601String(),
            'checks' => $bucket['checks'],
            'successful' => $bucket['checks'] - $bucket['failed'],
            'failed' => $bucket['failed'],
            'average_latency_ms' => $bucket['latencies'] === [] ? null : round(array_sum($bucket['latencies']) / count($bucket['latencies']), 1),
            'max_latency_ms' => $bucket['latencies'] === [] ? null : round(max($bucket['latencies']), 1),
        ], array_keys($buckets), $buckets);
    }

    /**
     * Count the HTTP status codes returned since the given time.
     *
     * @return list<array{status_code: int, count: int}>
     */
    protected function statusCodes(Service $service, CarbonImmutable $from): array
    {
        return array_values($service->metrics()
            ->where('checked_at', '>=', $from)
            ->whereNotNull('status_code')
            ->groupBy('status_code')
            ->orderBy('status_code')
            ->selectRaw('status_code, count(*) as aggregate')
            ->toBase()
            ->get()
            ->map(fn (stdClass $row): array => ['status_code' => (int) $row->status_code, 'count' => (int) $row->aggregate])
            ->all());
    }

    /**
     * Get the uptime and downtime of each of the last {@see HISTORY_DAYS} days.
     *
     * @return list<array<string, mixed>>
     */
    protected function daily(Service $service, CarbonImmutable $now): array
    {
        $firstDay = $now->copy()->startOfDay()->subDays(self::HISTORY_DAYS - 1);
        $incidents = $service->incidents()->overlapping($firstDay, $now)->get();
        $firstSeen = $this->firstSeen($service);

        return array_map(function (int $offset) use ($firstDay, $firstSeen, $now, $incidents): array {
            $dayStart = $firstDay->copy()->addDays($offset);
            $dayEnd = $dayStart->copy()->addDay()->min($now);
            $overlapping = $incidents->filter(fn (Incident $incident): bool => $this->overlap($incident, $dayStart, $dayEnd) > 0);

            return [
                'date' => $dayStart->toDateString(),
                'uptime' => $this->uptimeFromIncidents($overlapping, $firstSeen, $dayStart, $dayEnd),
                'downtime_seconds' => $overlapping->sum(fn (Incident $incident): int => $this->overlap($incident, $dayStart, $dayEnd)),
                'incidents' => $overlapping->count(),
            ];
        }, range(0, self::HISTORY_DAYS - 1));
    }

    /**
     * Get the percentage of the period the service was up, given the incidents overlapping it.
     *
     * Returns null when the period ends before the service was first checked.
     *
     * @param  Collection<int, Incident>  $incidents
     */
    protected function uptimeFromIncidents(Collection $incidents, ?CarbonImmutable $firstSeen, CarbonImmutable $from, CarbonImmutable $to): ?float
    {
        if ($firstSeen === null) {
            return null;
        }

        $from = $from->max($firstSeen);
        $total = $from->diffInSeconds($to, absolute: false);

        if ($total <= 0) {
            return null;
        }

        $down = $incidents->sum(fn (Incident $incident): int => $this->overlap($incident, $from, $to));

        return round(100 * (1 - min($down, $total) / $total), 3);
    }

    /**
     * Group runs of consecutive successful checks slower than the threshold into periods.
     *
     * A run ends at the next check that was fast enough. Failed checks do not end it,
     * since they are reported as incidents in their own right.
     *
     * @return list<array<string, mixed>>
     */
    protected function slowPeriods(Service $service, CarbonImmutable $from, int $thresholdMs): array
    {
        $checks = $service->metrics()
            ->where('checked_at', '>=', $from)
            ->where('successful', true)
            ->orderBy('checked_at')
            ->select(['id', 'checked_at', 'latency_ms'])
            ->toBase()
            ->lazy();

        $periods = [];
        $current = null;

        foreach ($checks as $check) {
            if ($check->latency_ms === null || $check->latency_ms <= $thresholdMs) {
                if ($current !== null) {
                    $periods[] = $current;
                    $current = null;
                }

                continue;
            }

            $checkedAt = CarbonImmutable::parse($check->checked_at);

            $current ??= [
                'id' => "slow-{$check->id}",
                'kind' => 'slow',
                'at' => $checkedAt,
                'ended_at' => $checkedAt,
                'checks' => 0,
                'peak_latency_ms' => 0.0,
            ];

            $current['ended_at'] = $checkedAt;
            $current['checks']++;
            $current['peak_latency_ms'] = max($current['peak_latency_ms'], (float) $check->latency_ms);
        }

        if ($current !== null) {
            $periods[] = $current;
        }

        return $periods;
    }

    /**
     * Get the number of seconds the incident overlaps the period.
     */
    protected function overlap(Incident $incident, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $start = $incident->started_at->max($from);
        $end = ($incident->ended_at ?? now())->min($to);

        return max(0, (int) $start->diffInSeconds($end, absolute: false));
    }

    /**
     * Get the value below which the given fraction of the sorted values fall.
     *
     * @param  Collection<int, float>  $sorted
     */
    protected function percentile(Collection $sorted, float $fraction): ?float
    {
        if ($sorted->isEmpty()) {
            return null;
        }

        return round($sorted[(int) ceil($fraction * $sorted->count()) - 1], 1);
    }

    /**
     * Format a check result for the UI.
     *
     * @return array<string, mixed>
     */
    public function metric(Metric $metric): array
    {
        return [
            'id' => $metric->id,
            'checked_at' => $metric->checked_at,
            'successful' => $metric->successful,
            'latency_ms' => $metric->latency_ms,
            'status_code' => $metric->status_code,
            'error' => $metric->error,
        ];
    }
}
