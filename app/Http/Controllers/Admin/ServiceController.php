<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\QueriesServices;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets administrators manage every service and who it is assigned to.
 */
class ServiceController extends Controller
{
    use QueriesServices;

    /**
     * Show every service.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('admin/services/index', [
            'services' => ServiceResource::collection($this->paginatedServices($request)),
            'query' => $this->servicesQueryState($request),
            'options' => $this->serviceOptions(),
            'owners' => $this->owners(),
        ]);
    }

    /**
     * Show the form for creating a service.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('admin/services/create', [
            'options' => $this->serviceOptions(),
            'owners' => $this->owners(),
            'defaultOwnerId' => $request->integer('owner') ?: null,
        ]);
    }

    /**
     * Store a new service.
     */
    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $service = $request->makeService();
        $service->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service created.')]);

        return to_route('admin.services.show', $service);
    }

    /**
     * Show the given service.
     */
    public function show(Service $service): Response
    {
        return Inertia::render('admin/services/show', [
            'service' => ServiceResource::make($service->load('owner'))->resolve(),
        ]);
    }

    /**
     * Show the form for editing the given service.
     */
    public function edit(Service $service): Response
    {
        return Inertia::render('admin/services/edit', [
            'service' => ServiceResource::make($service)->resolve(),
            'options' => $this->serviceOptions(),
            'owners' => $this->owners(),
        ]);
    }

    /**
     * Update the given service.
     */
    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $request->fillService($service)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service updated.')]);

        return to_route('admin.services.show', $service);
    }

    /**
     * Delete the given service.
     */
    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service deleted.')]);

        return to_route('admin.services.index');
    }

    /**
     * Get every user a service can be assigned to.
     *
     * @return Collection<int, User>
     */
    protected function owners(): Collection
    {
        return User::orderBy('name')->get(['id', 'name', 'email']);
    }
}
