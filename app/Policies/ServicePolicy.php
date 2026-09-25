<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ServicePolicy
{
    /**
     * Determine whether the user can list services.
     *
     * Non-admins only ever see their own services; queries are scoped accordingly.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the service.
     */
    public function view(User $user, Service $service): Response
    {
        return $this->ownerOrAdmin($user, $service);
    }

    /**
     * Determine whether the user can create services.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the service.
     */
    public function update(User $user, Service $service): Response
    {
        return $this->ownerOrAdmin($user, $service);
    }

    /**
     * Determine whether the user can delete the service.
     */
    public function delete(User $user, Service $service): Response
    {
        return $this->ownerOrAdmin($user, $service);
    }

    /**
     * Determine whether the user can assign services to any user, or leave them unassigned.
     */
    public function assignOwner(User $user): bool
    {
        return $user->admin;
    }

    /**
     * Allow admins and the service's owner. Everyone else gets a 404 so that
     * the existence of other users' services is not revealed.
     */
    protected function ownerOrAdmin(User $user, Service $service): Response
    {
        return $user->admin || $service->isOwnedBy($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
