<?php

namespace App\Events;

use App\Models\Service;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The panel pulled the latest sample from an Influx Daemon, for anyone watching its page.
 *
 * Broadcast on the same channel as live checks, immediately, since it is already dispatched from a queued job.
 */
class DaemonSampleReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $sample  As the daemon sent it.
     */
    public function __construct(
        public Service $service,
        public array $sample,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(LiveCheckCompleted::channelName($this->service->id))];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'daemon';
    }

    /**
     * Get the data to broadcast: the sample, unchanged.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->sample;
    }
}
