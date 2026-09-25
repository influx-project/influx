<?php

namespace App\Events;

use App\Models\Service;
use App\Monitoring\CheckResult;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A live check of a service finished, for anyone watching its page.
 *
 * Broadcast immediately, since it is already dispatched from a queued job.
 */
class LiveCheckCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Service $service,
        public CheckResult $result,
        public CarbonImmutable $checkedAt,
    ) {}

    /**
     * Get the name of the channel a service's live checks are broadcast on.
     */
    public static function channelName(int $serviceId): string
    {
        return "services.{$serviceId}.live";
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(self::channelName($this->service->id))];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'check';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'checked_at' => $this->checkedAt->toIso8601String(),
            'successful' => $this->result->successful,
            'latency_ms' => $this->result->latencyMs,
            'status_code' => $this->result->statusCode,
            'error' => $this->result->error,
        ];
    }
}
