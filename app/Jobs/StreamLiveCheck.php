<?php

namespace App\Jobs;

use App\Events\LiveCheckCompleted;
use App\Models\Service;
use App\Monitoring\ServiceMonitor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Checks a service for someone watching it live and broadcasts the result. Nothing is recorded.
 */
class StreamLiveCheck implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

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
     * The queue live checks run on, which needs a worker of its own.
     */
    public const QUEUE = 'live';

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout;

    /**
     * The number of seconds after which the job's unique lock is released.
     */
    public int $uniqueFor;

    public function __construct(public Service $service)
    {
        $this->timeout = $service->timeout + 15;
        $this->uniqueFor = $service->timeout + 30;

        // A queue of their own, so live checks never wait behind slow background checks.
        $this->onQueue(self::QUEUE);
    }

    /**
     * Execute the job.
     */
    public function handle(ServiceMonitor $monitor): void
    {
        // Live updates may have been switched off while the job was queued.
        if (! $this->service->isStreamingLive()) {
            return;
        }

        $checkedAt = now();
        $result = $monitor->probe($this->service);

        if ($result !== null) {
            LiveCheckCompleted::dispatch($this->service, $result, $checkedAt);
        }
    }

    /**
     * Get the unique ID for the job, so a slow service never has two live checks queued at once.
     */
    public function uniqueId(): string
    {
        return "live:{$this->service->id}";
    }
}
