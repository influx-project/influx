<?php

namespace App\Monitoring\Daemon;

use App\Models\Service;
use App\Monitoring\TimeRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use stdClass;

/**
 * Summarises what an Influx Daemon reported, for the service's daemon tab.
 */
class DaemonReport
{
    /**
     * Get what the daemon says about its host, when the panel last reached it, and its history over the range.
     *
     * @return array<string, mixed>
     */
    public function summary(Service $service, TimeRange $range): array
    {
        $daemon = $service->daemon;
        $to = now();
        $from = $to->copy()->subSeconds($range->seconds());

        $minutes = $service->daemonMetrics()
            ->where('minute', '>=', $from)
            ->orderBy('minute')
            ->toBase()
            ->get();

        return [
            'agent' => $daemon?->agent,
            'last_seen_at' => $daemon?->last_seen_at,
            'history' => [
                'stats' => $this->stats($minutes),
                'series' => $this->series($minutes, $from, $to, $range->bucketSeconds()),
            ],
        ];
    }

    /**
     * Get the headline figures for the range.
     *
     * @param  Collection<int, stdClass>  $minutes
     * @return array<string, mixed>
     */
    protected function stats(Collection $minutes): array
    {
        $latest = $minutes->last();

        return [
            'samples' => (int) $minutes->sum('samples'),
            'cpu_percent' => $this->weightedAverage($minutes, fn (stdClass $minute): float => (float) $minute->cpu_percent),
            'cpu_percent_max' => $minutes->isEmpty() ? null : round((float) $minutes->max('cpu_percent_max'), 2),
            'memory_percent' => $this->weightedAverage($minutes, $this->memoryPercent(...)),
            'disk_used_percent' => $latest?->disk_used_percent === null ? null : round((float) $latest->disk_used_percent, 2),
            'network_rx_bytes' => (int) $minutes->sum('network_rx_bytes'),
            'network_tx_bytes' => (int) $minutes->sum('network_tx_bytes'),
            'containers_running' => $latest?->containers_running,
            'containers_total' => $latest?->containers_total,
        ];
    }

    /**
     * Group the minutes into evenly sized buckets, including empty ones, for charting.
     *
     * Rates are the bytes moved in the bucket over the seconds its samples cover, and
     * container counts are from the bucket's last minute.
     *
     * @param  Collection<int, stdClass>  $minutes
     * @return list<array<string, mixed>>
     */
    protected function series(Collection $minutes, CarbonImmutable $from, CarbonImmutable $to, int $bucketSeconds): array
    {
        $start = intdiv($from->getTimestamp(), $bucketSeconds) * $bucketSeconds;
        $buckets = [];

        for ($time = $start; $time <= $to->getTimestamp(); $time += $bucketSeconds) {
            $buckets[$time] = collect();
        }

        foreach ($minutes as $minute) {
            $time = intdiv(CarbonImmutable::parse($minute->minute)->getTimestamp(), $bucketSeconds) * $bucketSeconds;
            if (isset($buckets[$time])) {
                $buckets[$time]->push($minute);
            }
        }

        return array_map(function (int $time, Collection $bucket): array {
            $seconds = (int) $bucket->sum('seconds');
            $rate = fn (string $column): ?float => $seconds === 0 ? null : round($bucket->sum($column) / $seconds, 2);
            $last = $bucket->last();

            return [
                'time' => CarbonImmutable::createFromTimestamp($time)->toIso8601String(),
                'cpu_percent' => $this->weightedAverage($bucket, fn (stdClass $minute): float => (float) $minute->cpu_percent),
                'cpu_percent_max' => $bucket->isEmpty() ? null : round((float) $bucket->max('cpu_percent_max'), 2),
                'memory_percent' => $this->weightedAverage($bucket, $this->memoryPercent(...)),
                'disk_read_bytes_per_second' => $rate('disk_read_bytes'),
                'disk_write_bytes_per_second' => $rate('disk_write_bytes'),
                'network_rx_bytes_per_second' => $rate('network_rx_bytes'),
                'network_tx_bytes_per_second' => $rate('network_tx_bytes'),
                'containers_running' => $last?->containers_running,
                'containers_unhealthy' => $last?->containers_unhealthy,
                'containers_total' => $last?->containers_total,
            ];
        }, array_keys($buckets), $buckets);
    }

    /**
     * Average a per-minute value, weighting each minute by its number of samples.
     *
     * @param  Collection<int, stdClass>  $minutes
     * @param  callable(stdClass): float  $value
     */
    protected function weightedAverage(Collection $minutes, callable $value): ?float
    {
        $samples = (int) $minutes->sum('samples');

        if ($samples === 0) {
            return null;
        }

        return round($minutes->sum(fn (stdClass $minute): float => $value($minute) * $minute->samples) / $samples, 2);
    }

    /**
     * Get the share of memory in use during the minute, as a percentage.
     */
    protected function memoryPercent(stdClass $minute): float
    {
        return $minute->memory_total_bytes > 0 ? 100 * $minute->memory_used_bytes / $minute->memory_total_bytes : 0;
    }
}
