<?php

namespace App\Monitoring;

use App\Events\LiveCheckCompleted;
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
        $response = $this->request('/channels', ['filter_by_prefix' => 'private-services.']);
        $channels = is_array($response['channels'] ?? null) ? $response['channels'] : [];

        return self::serviceIdsFromChannels(array_keys($channels));
    }

    /**
     * Determine whether anyone is watching the service live right now.
     */
    public function isWatched(int $serviceId): bool
    {
        $response = $this->request('/channels/private-'.LiveCheckCompleted::channelName($serviceId));

        return ($response['occupied'] ?? false) === true;
    }

    /**
     * Query the WebSocket server's HTTP API, returning the decoded body or null if it cannot be reached.
     *
     * Decoded as arrays rather than via the SDK's getChannels(), which throws when no channels
     * are occupied because the server then sends `"channels": []`.
     *
     * @param  array<string, string>  $params
     * @return array<string, mixed>|null
     */
    protected function request(string $path, array $params = []): ?array
    {
        try {
            $broadcaster = $this->broadcast->connection();

            // Only Pusher-compatible servers such as Reverb have this API.
            if (! $broadcaster instanceof PusherBroadcaster) {
                return null;
            }

            $response = $broadcaster->getPusher()->get($path, $params, true);
        } catch (Throwable $e) {
            Log::warning('Could not query the WebSocket server.', ['path' => $path, 'exception' => $e->getMessage()]);

            return null;
        }

        return is_array($response) ? $response : null;
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
