<?php

namespace App\Http\Controllers;

use App\Enums\ServiceType;
use App\Models\Daemon;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Replaces the token an Influx Daemon service's daemon expects, e.g. after it leaked.
 */
class DaemonTokenController extends Controller
{
    /**
     * Generate a new token. The daemon rejects the panel until its config has the new one.
     */
    public function __invoke(Service $service): RedirectResponse
    {
        Gate::authorize('update', $service);
        abort_unless($service->type === ServiceType::InfluxDaemon, 404);

        $service->ensureDaemon()->update(['token' => Daemon::generateToken()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token regenerated. Update the daemon’s config to use it.')]);

        return back();
    }
}
