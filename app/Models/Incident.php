<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A period during which a service's checks were failing.
 *
 * Opened by the first failed check and closed by the next successful one.
 *
 * @property int $id
 * @property int $service_id
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $ended_at Null while the incident is ongoing.
 * @property string|null $cause The error reported by the check that opened the incident.
 * @property int|null $status_code
 * @property int $failed_checks
 * @property-read Service $service
 */
#[Fillable([
    'started_at',
    'ended_at',
    'cause',
    'status_code',
    'failed_checks',
])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

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
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'status_code' => 'integer',
            'failed_checks' => 'integer',
        ];
    }

    /**
     * Get the service that was down.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Scope the query to incidents that have not been resolved.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ongoing(Builder $query): void
    {
        $query->whereNull('ended_at');
    }

    /**
     * Scope the query to incidents that overlap the given period.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function overlapping(Builder $query, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $query->where('started_at', '<', $to)
            ->where(fn (Builder $query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $from));
    }

    /**
     * Get the number of seconds the incident lasted, up to now if it is ongoing.
     */
    public function durationInSeconds(): int
    {
        return (int) $this->started_at->diffInSeconds($this->ended_at ?? now(), absolute: true);
    }
}
