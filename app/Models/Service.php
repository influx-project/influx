<?php

namespace App\Models;

use App\Enums\ServiceImportance;
use App\Enums\ServiceType;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $owner
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
        ];
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
     * Determine whether the service is assigned to the given user.
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
