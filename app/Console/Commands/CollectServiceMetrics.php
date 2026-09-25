<?php

namespace App\Console\Commands;

use App\Enums\ServiceType;
use App\Jobs\CheckService;
use App\Models\Service;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('services:collect-metrics {--force : Check every eligible service, even if it is not due yet}')]
#[Description('Queue a background check for every enabled service that is due one')]
class CollectServiceMetrics extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $queued = 0;

        Service::query()
            ->where('enabled', true)
            ->where('collect_metrics', true)
            ->whereIn('type', ServiceType::collectable())
            ->unless($this->option('force'), fn ($query) => $query->where(
                fn ($query) => $query->whereNull('next_check_at')->orWhere('next_check_at', '<=', now()),
            ))
            ->lazyById()
            ->each(function (Service $service) use (&$queued): void {
                Service::withoutTimestamps(fn () => $service->forceFill([
                    'next_check_at' => now()->addSeconds($service->check_interval),
                ])->saveQuietly());

                CheckService::dispatch($service);
                $queued++;
            });

        $this->components->info("Queued {$queued} ".str('check')->plural($queued).'.');

        return self::SUCCESS;
    }
}
