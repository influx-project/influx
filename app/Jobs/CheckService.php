<?php

namespace App\Jobs;

use App\Models\Service;
use App\Monitoring\ServiceMonitor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs one background check of a service.
 */
class CheckService implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted. A failed check is recorded, not retried.
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

    /**
     * The number of seconds after which the job's unique lock is released.
     */
    public int $uniqueFor;

    public function __construct(public Service $service)
    {
        $this->timeout = $service->timeout + 15;
        $this->uniqueFor = $service->timeout + 60;
    }

    /**
     * Execute the job.
     */
    public function handle(ServiceMonitor $monitor): void
    {
        // Monitoring may have been switched off while the job was queued.
        if (! $this->service->isCollectingMetrics()) {
            return;
        }

        $monitor->check($this->service);
    }

    /**
     * Get the unique ID for the job, so a slow service never has two checks queued at once.
     */
    public function uniqueId(): string
    {
        return (string) $this->service->id;
    }
}
