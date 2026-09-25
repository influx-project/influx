<?php

namespace App\Console\Commands;

use App\Jobs\StreamLiveCheck;
use App\Models\Service;
use App\Monitoring\LiveViewers;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('services:stream-live')]
#[Description('Restart live checks for any watched service whose checks have stopped')]
class StreamLiveChecks extends Command
{
    /**
     * Execute the console command.
     *
     * Live checks start when someone starts watching and keep themselves going, so this
     * is only a safety net for chains that broke, e.g. when a worker was killed mid-job.
     */
    public function handle(LiveViewers $viewers): int
    {
        $ids = $viewers->watchedServiceIds();

        if ($ids === []) {
            return self::SUCCESS;
        }

        $restarted = 0;

        Service::query()
            ->whereKey($ids)
            ->where('enabled', true)
            ->where('stream_metrics', true)
            ->each(function (Service $service) use (&$restarted): void {
                if (! Cache::has(StreamLiveCheck::lastCheckKey($service->id))) {
                    StreamLiveCheck::dispatch($service);
                    $restarted++;
                }
            });

        $this->components->info("Restarted live checks for {$restarted} ".str('service')->plural($restarted).'.');

        return self::SUCCESS;
    }
}
