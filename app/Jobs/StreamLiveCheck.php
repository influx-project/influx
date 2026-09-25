<?php

namespace App\Jobs;

use App\Events\LiveCheckCompleted;
use App\Models\Service;
use App\Monitoring\LiveViewers;
use App\Monitoring\ServiceMonitor;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Checks a service for the people watching it live, broadcasts the result, and queues
 * the next check a few seconds later. Nothing is recorded.
 *
 * Each service has at most one chain of these jobs. It is started when someone starts
 * watching (see StartLiveChecks) and ends by itself once no one is watching any more,
 * so no work is done for services nobody is looking at.
 */
class StreamLiveCheck implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    /**
     * The queue live checks run on, which needs a worker of its own.
     */
    public const QUEUE = 'live';

    /**
     * Seconds from the start of one live check to the start of the next.
     */
    public const INTERVAL = 4;

    /**
     * Seconds without a live check after which a watched service's chain is considered
     * broken, e.g. because a worker was killed mid-job, and is restarted.
     */
    public const STALE_AFTER = 15;

    /**
     * The number of times the job may be attempted. A stale live result is not worth retrying.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * Delete the job if the service was deleted before it ran.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout;

    public function __construct(public Service $service)
    {
        $this->timeout = $service->timeout + 15;

        // A queue of their own, so live checks never wait behind slow background checks.
        $this->onQueue(self::QUEUE);
    }

    /**
     * Execute the job.
     */
    public function handle(ServiceMonitor $monitor, LiveViewers $viewers): void
    {
        $id = $this->service->id;

        // The chain ends once live updates are switched off or the last viewer leaves.
        if (! $this->service->isStreamingLive() || ! $viewers->isWatched($id)) {
            return;
        }

        // If a second chain was started for the same service (say, a viewer left and came
        // straight back), whichever runs second within the interval ends here.
        if (! Cache::add("live-check:{$id}", true, self::INTERVAL - 1)) {
            return;
        }

        Cache::put(self::lastCheckKey($id), true, self::STALE_AFTER);

        $startedAt = now();
        $result = $monitor->probe($this->service);

        if ($result !== null) {
            LiveCheckCompleted::dispatch($this->service, $result, $startedAt);
        }

        // Keep a steady cadence however long the check itself took.
        $next = $startedAt->addSeconds(self::INTERVAL);
        self::dispatch($this->service)->delay($next->isFuture() ? $next : now());
    }

    /**
     * Get the unique ID for the job, so each service has only one live check queued at a time.
     */
    public function uniqueId(): string
    {
        return "live:{$this->service->id}";
    }

    /**
     * The cache key present while a service's chain is running, used to spot broken chains.
     */
    public static function lastCheckKey(int $serviceId): string
    {
        return "live-chain:{$serviceId}";
    }
}
