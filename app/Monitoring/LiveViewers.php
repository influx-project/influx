<?php

namespace App\Monitoring;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Finds the services someone is watching live, by asking the WebSocket server
 * which of their live channels have subscribers.
 */
class LiveViewers
{
    public function __construct(protected BroadcastFactory $broadcast) {}

    /**
     * Get the IDs of the services with at least one live subscriber.
     *
     * @return list<int>
     */
    public function watchedServiceIds(): array
    {
        try {
            $broadcaster = $this->broadcast->connection();

            // Only Pusher-compatible servers such as Reverb can list their channels.
            if (! $broadcaster instanceof PusherBroadcaster) {
                return [];
            }

            // Decoded as arrays rather than via the SDK's getChannels(), which throws when
            // no channels are occupied because the server then sends `"channels": []`.
            $response = $broadcaster->getPusher()->get('/channels', ['filter_by_prefix' => 'private-services.'], true);
        } catch (Throwable $e) {
            Log::warning('Could not list live channels from the WebSocket server.', ['exception' => $e->getMessage()]);

            return [];
        }

        $channels = is_array($response) && is_array($response['channels'] ?? null) ? $response['channels'] : [];

        return self::serviceIdsFromChannels(array_keys($channels));
    }

    /**
     * Extract service IDs from channel names such as `private-services.12.live`.
     *
     * @param  array<int|string, int|string>  $channels
     * @return list<int>
     */
    public static function serviceIdsFromChannels(array $channels): array
    {
        $ids = [];

        foreach ($channels as $channel) {
            if (preg_match('/^private-services\.(\d+)\.live$/', (string) $channel, $matches) === 1) {
                $ids[] = (int) $matches[1];
            }
        }

        return array_values(array_unique($ids));
    }
}
