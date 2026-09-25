<?php

namespace App\Providers;

use App\Jobs\StreamLiveCheck;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDevCommands();
    }

    /**
     * Configure the processes `php artisan dev` runs alongside the defaults.
     */
    protected function configureDevCommands(): void
    {
        // Background collection and live updates are driven by the scheduler.
        DevCommands::artisan('schedule:work', 'schedule');

        DevCommands::artisan('queue:listen --tries=1 --timeout=0', 'queue');

        // Live checks get a worker of their own, polling every second, so they are never
        // stuck behind background checks that are waiting to time out.
        DevCommands::artisan('queue:listen --queue='.StreamLiveCheck::QUEUE.' --tries=1 --timeout=0 --sleep=1', 'live');
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
