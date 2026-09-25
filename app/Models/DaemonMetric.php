<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DaemonMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One minute of an Influx Daemon's samples, rolled up so that history stays small.
 *
 * Averages are weighted by the number of samples; byte counts are totals over the seconds covered.
 * The disk and container figures are from the minute's latest sample.
 *
 * @property int $id
 * @property int $service_id
 * @property CarbonImmutable $minute The start of the minute.
 * @property int $samples
 * @property int $seconds The number of seconds the samples cover.
 * @property float $cpu_percent
 * @property float $cpu_percent_max
 * @property float $load_1
 * @property int $memory_used_bytes
 * @property int $memory_total_bytes
 * @property int $swap_used_bytes
 * @property float|null $disk_used_percent How full the fullest disk was, if the host reported any.
 * @property int $disk_read_bytes
 * @property int $disk_write_bytes
 * @property int $network_rx_bytes
 * @property int $network_tx_bytes
 * @property int|null $containers_total Null when the daemon could not see a container runtime.
 * @property int|null $containers_running
 * @property int|null $containers_unhealthy
 * @property-read Service $service
 */
#[Guarded(['id'])]
class DaemonMetric extends Model
{
    /** @use HasFactory<DaemonMetricFactory> */
    use HasFactory, MassPrunable;

    /**
     * The number of days rolled up samples are kept for, matching background checks.
     */
    public const RETENTION_DAYS = Metric::RETENTION_DAYS;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minute' => 'datetime',
            'samples' => 'integer',
            'seconds' => 'integer',
            'cpu_percent' => 'float',
            'cpu_percent_max' => 'float',
            'load_1' => 'float',
            'memory_used_bytes' => 'integer',
            'memory_total_bytes' => 'integer',
            'swap_used_bytes' => 'integer',
            'disk_used_percent' => 'float',
            'disk_read_bytes' => 'integer',
            'disk_write_bytes' => 'integer',
            'network_rx_bytes' => 'integer',
            'network_tx_bytes' => 'integer',
            'containers_total' => 'integer',
            'containers_running' => 'integer',
            'containers_unhealthy' => 'integer',
        ];
    }

    /**
     * Get the service the samples came from.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the prunable model query.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::where('minute', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
