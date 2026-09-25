<?php

namespace App\Jobs;

use App\Enums\ServiceType;
use App\Models\Service;
use App\Monitoring\Daemon\DaemonException;
use App\Monitoring\Daemon\DaemonSync;
use App\Monitoring\ServiceMonitor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

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
    public function handle(ServiceMonitor $monitor, DaemonSync $sync): void
    {
        // Monitoring may have been switched off while the job was queued.
        if (! $this->service->isCollectingMetrics()) {
            return;
        }

        $metric = $monitor->check($this->service);

        // A reachable Influx Daemon also has samples to collect.
        if ($this->service->type === ServiceType::InfluxDaemon && $metric->successful) {
            try {
                $sync->sync($this->service);
            } catch (ConnectionException|DaemonException $e) {
                Log::warning('Could not read samples from Influx Daemon.', ['service' => $this->service->id, 'exception' => $e->getMessage()]);
            }
        }
    }

    /**
     * Get the unique ID for the job, so a slow service never has two checks queued at once.
     */
    public function uniqueId(): string
    {
        return (string) $this->service->id;
    }
}
