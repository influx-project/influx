<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * How the panel connects to an Influx Daemon service, and how far it has read the daemon's samples.
 *
 * @property int $id
 * @property int $service_id
 * @property string $token The bearer token the daemon expects, stored encrypted.
 * @property array<string, mixed>|null $agent What the daemon last reported about itself and its host.
 * @property string|null $stream_id The daemon process the cursor belongs to; it changes when the daemon restarts.
 * @property int|null $last_seq The last sample read from that process.
 * @property CarbonImmutable|null $last_seen_at When the panel last reached the daemon.
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Service $service
 */
#[Fillable(['token'])]
#[Hidden(['token'])]
class Daemon extends Model
{
    /**
     * The length of generated tokens. The daemon requires at least 32 characters.
     */
    public const TOKEN_LENGTH = 48;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'agent' => 'array',
            'last_seq' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Generate a new token for a daemon.
     */
    public static function generateToken(): string
    {
        return Str::random(self::TOKEN_LENGTH);
    }

    /**
     * Get the service the daemon reports for.
     *
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
