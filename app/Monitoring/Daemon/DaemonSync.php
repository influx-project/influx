<?php

namespace App\Monitoring\Daemon;

use App\Models\Daemon;
use App\Models\DaemonMetric;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pulls the samples an Influx Daemon has taken since the panel last asked, and stores
 * them rolled up by the minute.
 *
 * The daemon numbers its samples, so the panel keeps a cursor: the daemon process it
 * last read from and the last sample it read. The daemon keeps several hours of samples,
 * so after an outage the panel catches up on everything the daemon still has.
 */
class DaemonSync
{
    /**
     * The most pages read in one sync, so a long backlog cannot hold up the queue. The rest is read next time.
     */
    public const MAX_PAGES = 5;

    /**
     * The sampling interval assumed if the daemon has not said, matching its default.
     */
    public const DEFAULT_INTERVAL = 5;

    /**
     * Read the daemon's new samples and store them, returning how many were read.
     *
     * @throws ConnectionException
     * @throws DaemonException
     */
    public function sync(Service $service): int
    {
        $daemon = $service->ensureDaemon();
        $client = DaemonClient::for($service);

        $this->updateAgent($daemon, $client->info());

        $read = 0;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $client->samples($daemon->last_seq ?? 0);

            // The daemon restarted since the info request, so its samples are numbered afresh.
            if ($response['stream_id'] !== $daemon->stream_id) {
                $this->updateAgent($daemon, $client->info());

                continue;
            }

            $samples = $response['samples'];

            DB::transaction(function () use ($service, $daemon, $samples): void {
                if ($samples !== []) {
                    $this->store($service, $samples, $daemon->agent['sample_interval_seconds'] ?? self::DEFAULT_INTERVAL);
                    $daemon->last_seq = (int) Arr::last($samples)['seq'];
                }

                $daemon->last_seen_at = now();
                $daemon->save();
            });

            $read += count($samples);

            if (! $response['has_more']) {
                break;
            }
        }

        return $read;
    }

    /**
     * Store what the daemon says about itself, starting the cursor afresh if it is a new daemon process.
     *
     * @param  array<string, mixed>  $info
     */
    protected function updateAgent(Daemon $daemon, array $info): void
    {
        if ($info['stream_id'] !== $daemon->stream_id) {
            $daemon->stream_id = $info['stream_id'];
            $daemon->last_seq = 0;
        }

        $daemon->agent = Arr::except($info, ['stream_id', 'retention_seconds']);
        $daemon->last_seen_at = now();
        $daemon->save();
    }

    /**
     * Add the samples to the minutes they were taken in.
     *
     * @param  list<array<string, mixed>>  $samples  Oldest first.
     */
    protected function store(Service $service, array $samples, int $interval): void
    {
        $minutes = collect($samples)->groupBy(
            fn (array $sample): int => intdiv(CarbonImmutable::parse($sample['collected_at'])->getTimestamp(), 60) * 60,
        );

        $existing = $service->daemonMetrics()
            ->whereIn('minute', $minutes->keys()->map(fn (int $time): CarbonImmutable => CarbonImmutable::createFromTimestamp($time)))
            ->get()
            ->keyBy(fn (DaemonMetric $metric): int => $metric->minute->getTimestamp());

        foreach ($minutes as $time => $minuteSamples) {
            $metric = $existing->get($time) ?? new DaemonMetric(['minute' => CarbonImmutable::createFromTimestamp($time)]);

            $this->add($metric, $minuteSamples, $interval);

            $service->daemonMetrics()->save($metric);
        }
    }

    /**
     * Fold later samples into a minute's figures.
     *
     * @param  Collection<int, array<string, mixed>>  $samples  Oldest first, all later than those already in the minute.
     */
    protected function add(DaemonMetric $metric, Collection $samples, int $interval): void
    {
        $before = $metric->samples ?? 0;
        $after = $before + $samples->count();
        $latest = $samples->last();

        $average = fn (int|float|null $current, string $key): float => (($current ?? 0) * $before + $samples->sum($key)) / $after;
        $total = fn (?int $current, string $key): int => ($current ?? 0) + (int) round($samples->sum($key) * $interval);

        $metric->fill([
            'samples' => $after,
            'seconds' => ($metric->seconds ?? 0) + $samples->count() * $interval,
            'cpu_percent' => round($average($metric->cpu_percent, 'cpu.usage_percent'), 2),
            'cpu_percent_max' => max($metric->cpu_percent_max ?? 0, $samples->max('cpu.usage_percent')),
            'load_1' => round($average($metric->load_1, 'cpu.load_1'), 2),
            'memory_used_bytes' => (int) round($average($metric->memory_used_bytes, 'memory.used_bytes')),
            'memory_total_bytes' => $latest['memory']['total_bytes'],
            'swap_used_bytes' => (int) round($average($metric->swap_used_bytes, 'memory.swap_used_bytes')),
            'disk_used_percent' => $this->fullestDisk($latest['disks']),
            'disk_read_bytes' => $total($metric->disk_read_bytes, 'disk_io.read_bytes_per_second'),
            'disk_write_bytes' => $total($metric->disk_write_bytes, 'disk_io.write_bytes_per_second'),
            'network_rx_bytes' => $total($metric->network_rx_bytes, 'network.rx_bytes_per_second'),
            'network_tx_bytes' => $total($metric->network_tx_bytes, 'network.tx_bytes_per_second'),
            ...$this->containerCounts($latest['containers']),
        ]);
    }

    /**
     * Get how full the fullest disk is, as a percentage.
     *
     * @param  list<array{used_bytes: int, total_bytes: int}>  $disks
     */
    protected function fullestDisk(array $disks): ?float
    {
        $percentages = array_map(
            fn (array $disk): float => $disk['total_bytes'] > 0 ? 100 * $disk['used_bytes'] / $disk['total_bytes'] : 0,
            $disks,
        );

        return $percentages === [] ? null : round(max($percentages), 2);
    }

    /**
     * Count the containers by state, or return nulls when the daemon cannot see a container runtime.
     *
     * @param  list<array{state: string, health: string|null}>|null  $containers
     * @return array{containers_total: int|null, containers_running: int|null, containers_unhealthy: int|null}
     */
    protected function containerCounts(?array $containers): array
    {
        if ($containers === null) {
            return ['containers_total' => null, 'containers_running' => null, 'containers_unhealthy' => null];
        }

        return [
            'containers_total' => count($containers),
            'containers_running' => count(array_filter($containers, fn (array $container): bool => $container['state'] === 'running')),
            'containers_unhealthy' => count(array_filter($containers, fn (array $container): bool => $container['health'] === 'unhealthy')),
        ];
    }
}
