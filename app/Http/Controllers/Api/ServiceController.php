<?php

namespace App\Http\Controllers\Api;

use App\Concerns\QueriesServices;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Administrators can manage every service; other users only their own.
 */
class ServiceController extends Controller
{
    use QueriesServices;

    /**
     * List services, with filtering, sorting and pagination from the query string.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        return ServiceResource::collection($this->paginatedServices($request, owner: $user->admin ? null : $user));
    }

    /**
     * Store a new service, owned by the current user unless an administrator assigns it.
     */
    public function store(StoreServiceRequest $request): JsonResponse
    {
        $service = $request->makeService();
        $service->save();

        return ServiceResource::make($service->load('owner'))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show the given service.
     */
    public function show(Service $service): ServiceResource
    {
        Gate::authorize('view', $service);

        return ServiceResource::make($service->load('owner'));
    }

    /**
     * Update the given service.
     */
    public function update(UpdateServiceRequest $request, Service $service): ServiceResource
    {
        $request->fillService($service)->save();

        return ServiceResource::make($service->load('owner'));
    }

    /**
     * Delete the given service.
     */
    public function destroy(Service $service): Response
    {
        Gate::authorize('delete', $service);

        $service->delete();

        return response()->noContent();
    }
}
