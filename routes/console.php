<?php

use App\Console\Commands\CollectServiceMetrics;
use App\Console\Commands\StreamLiveChecks;
use App\Models\DaemonMetric;
use App\Models\Metric;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The shortest check interval is 10 seconds, so look for due services that often.
// Overlap locks expire after a minute, so a run that was killed mid-way cannot block these for a day.
Schedule::command(CollectServiceMetrics::class)->everyTenSeconds()->withoutOverlapping(1);

// Live checks start when someone starts watching and stop when they leave; this only
// restarts any that broke along the way.
Schedule::command(StreamLiveChecks::class)->everyMinute()->withoutOverlapping(1);

Schedule::command('model:prune', ['--model' => [Metric::class, DaemonMetric::class]])->daily();
