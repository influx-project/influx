<?php

namespace App\Models;

use App\Enums\ServiceImportance;
use App\Enums\ServiceType;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A monitored endpoint such as a web server, database or SSH port.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $description
 * @property string|null $location
 * @property ServiceType $type
 * @property string $host
 * @property int|null $port
 * @property bool $use_ssl
 * @property ServiceImportance $importance
 * @property int $check_interval Seconds between background checks.
 * @property int $timeout Seconds to wait for a response before a check fails.
 * @property bool $collect_metrics Collect metrics in the background via the scheduled Artisan command.
 * @property bool $stream_metrics Stream live metrics to the UI over a websocket.
 * @property bool $enabled
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $next_check_at When the background collector should next check the service; null means as soon as possible.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $owner
 * @property-read Collection<int, Metric> $metrics
 * @property-read Collection<int, Incident> $incidents
 * @property-read Metric|null $latestMetric
 * @property-read Incident|null $ongoingIncident
 */
#[Fillable([
    'name',
    'description',
    'location',
    'type',
    'host',
    'port',
    'use_ssl',
    'importance',
    'check_interval',
    'timeout',
    'collect_metrics',
    'stream_metrics',
    'enabled',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'use_ssl' => false,
        'importance' => 'normal',
        'check_interval' => 60,
        'timeout' => 10,
        'collect_metrics' => true,
        'stream_metrics' => true,
        'enabled' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
            'port' => 'integer',
            'use_ssl' => 'boolean',
            'importance' => ServiceImportance::class,
            'check_interval' => 'integer',
            'timeout' => 'integer',
            'collect_metrics' => 'boolean',
            'stream_metrics' => 'boolean',
            'enabled' => 'boolean',
            'last_checked_at' => 'datetime',
            'next_check_at' => 'datetime',
        ];
    }

    /**
     * The attributes that change how or whether a service is checked.
     *
     * @var list<string>
     */
    protected const MONITORING_ATTRIBUTES = [
        'type',
        'host',
        'port',
        'use_ssl',
        'check_interval',
        'timeout',
        'collect_metrics',
        'enabled',
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        // Check a service again promptly after its monitoring configuration changes.
        static::saving(function (Service $service): void {
            if ($service->exists && $service->isDirty(self::MONITORING_ATTRIBUTES)) {
                $service->next_check_at = null;
            }
        });

        // Nothing will close an ongoing incident once collection stops, so close it now.
        static::updated(function (Service $service): void {
            if ($service->wasChanged(self::MONITORING_ATTRIBUTES) && ! $service->isCollectingMetrics()) {
                $service->incidents()->ongoing()->update(['ended_at' => now()]);
            }
        });
    }

    /**
     * Get the user the service is assigned to.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the results of the service's background checks.
     *
     * @return HasMany<Metric, $this>
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(Metric::class);
    }

    /**
     * Get the service's most recent check result.
     *
     * @return HasOne<Metric, $this>
     */
    public function latestMetric(): HasOne
    {
        return $this->hasOne(Metric::class)->latestOfMany('checked_at');
    }

    /**
     * Get the periods during which the service was down.
     *
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * Get the incident the service is currently in, if it is down.
     *
     * @return HasOne<Incident, $this>
     */
    public function ongoingIncident(): HasOne
    {
        return $this->hasOne(Incident::class)->ongoing()->latestOfMany('started_at');
    }

    /**
     * Determine whether the background collector checks this service.
     */
    public function isCollectingMetrics(): bool
    {
        return $this->enabled && $this->collect_metrics && $this->type->collectsMetrics();
    }

    /**
     * Determine whether the service can be probed for live updates while someone watches it.
     */
    public function isStreamingLive(): bool
    {
        return $this->enabled && $this->stream_metrics && $this->type->collectsMetrics();
    }

    /**
     * Determine whether the service is assigned to the given user.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
