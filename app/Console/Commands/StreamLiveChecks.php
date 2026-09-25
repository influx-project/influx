<?php

namespace App\Console\Commands;

use App\Enums\ServiceType;
use App\Jobs\StreamLiveCheck;
use App\Models\Service;
use App\Monitoring\LiveViewers;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('services:stream-live')]
#[Description('Queue a live check for every service someone is watching live')]
class StreamLiveChecks extends Command
{
    /**
     * The minimum number of seconds between live checks of the same service.
     */
    public const INTERVAL = 3;

    /**
     * Execute the console command.
     */
    public function handle(LiveViewers $viewers): int
    {
        $ids = $viewers->watchedServiceIds();

        if ($ids === []) {
            return self::SUCCESS;
        }

        $queued = 0;

        Service::query()
            ->whereKey($ids)
            ->where('enabled', true)
            ->where('stream_metrics', true)
            ->whereIn('type', ServiceType::collectable())
            ->each(function (Service $service) use (&$queued): void {
                // Space live checks out, however often the command runs.
                if (Cache::add("live-check:{$service->id}", true, self::INTERVAL)) {
                    StreamLiveCheck::dispatch($service);
                    $queued++;
                }
            });

        $this->components->info("Queued {$queued} live ".str('check')->plural($queued).'.');

        return self::SUCCESS;
    }
}
