<?php

namespace App\Listeners;

use App\Jobs\StreamLiveCheck;
use App\Models\Service;
use App\Monitoring\LiveViewers;
use Laravel\Reverb\Events\ChannelCreated;

/**
 * Starts live checks for a service as soon as someone starts watching it.
 *
 * Runs inside the Reverb server, which fires ChannelCreated when a channel gets its
 * first subscriber. The checks stop by themselves when the last subscriber leaves.
 */
class StartLiveChecks
{
    /**
     * Handle the event.
     */
    public function handle(ChannelCreated $event): void
    {
        foreach (LiveViewers::serviceIdsFromChannels([$event->channel->name()]) as $id) {
            $service = Service::find($id);

            if ($service?->isStreamingLive()) {
                StreamLiveCheck::dispatch($service);
            }
        }
    }
}
