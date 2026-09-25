<?php

namespace App\Http\Controllers;

use App\Concerns\QueriesServices;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets any user manage the services assigned to them.
 */
class ServiceController extends Controller
{
    use QueriesServices;

    /**
     * Show the current user's services.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('services/index', [
            'services' => ServiceResource::collection($this->paginatedServices($request, owner: $request->user())),
            'query' => $this->servicesQueryState($request),
            'options' => $this->serviceOptions(),
        ]);
    }

    /**
     * Show the form for creating a service.
     */
    public function create(): Response
    {
        return Inertia::render('services/create', [
            'options' => $this->serviceOptions(),
        ]);
    }

    /**
     * Store a new service owned by the current user.
     */
    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $service = $request->makeService();
        $service->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service created.')]);

        return to_route('services.show', $service);
    }

    /**
     * Show the given service.
     */
    public function show(Service $service): Response
    {
        Gate::authorize('view', $service);

        return Inertia::render('services/show', [
            'service' => ServiceResource::make($service->load('owner'))->resolve(),
        ]);
    }

    /**
     * Show the form for editing the given service.
     */
    public function edit(Service $service): Response
    {
        Gate::authorize('update', $service);

        return Inertia::render('services/edit', [
            'service' => ServiceResource::make($service)->resolve(),
            'options' => $this->serviceOptions(),
        ]);
    }

    /**
     * Update the given service.
     */
    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $request->fillService($service)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service updated.')]);

        return to_route('services.show', $service);
    }

    /**
     * Delete the given service.
     */
    public function destroy(Service $service): RedirectResponse
    {
        Gate::authorize('delete', $service);

        $service->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service deleted.')]);

        return to_route('services.index');
    }
}
