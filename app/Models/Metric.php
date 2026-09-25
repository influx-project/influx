<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The result of a single background check of a service.
 *
 * @property int $id
 * @property int $service_id
 * @property CarbonImmutable $checked_at
 * @property bool $successful
 * @property float|null $latency_ms Milliseconds until the service responded, if it did.
 * @property int|null $status_code The HTTP status code, for HTTP services.
 * @property string|null $error Why the check failed, if it did.
 * @property-read Service $service
 */
#[Fillable([
    'checked_at',
    'successful',
    'latency_ms',
    'status_code',
    'error',
])]
class Metric extends Model
{
    /** @use HasFactory<MetricFactory> */
    use HasFactory, MassPrunable;

    /**
     * The number of days raw metrics are kept for.
     */
    public const RETENTION_DAYS = 30;

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
            'checked_at' => 'datetime',
            'successful' => 'boolean',
            'latency_ms' => 'float',
            'status_code' => 'integer',
        ];
    }

    /**
     * Get the service that was checked.
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
        return self::where('checked_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
