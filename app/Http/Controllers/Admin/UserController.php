<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\QueriesServices;
use App\Concerns\QueriesUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use QueriesServices, QueriesUsers;

    /**
     * Show the searchable, sortable list of users.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('admin/users/index', [
            'users' => UserResource::collection($this->paginatedUsers($request)),
            'query' => [
                'filter' => (object) $request->array('filter'),
                'sort' => $request->string('sort', '-created_at')->toString(),
                'per_page' => $request->integer('per_page', $this->defaultUsersPerPage),
            ],
        ]);
    }

    /**
     * Show the form for creating a user.
     */
    public function create(): Response
    {
        return Inertia::render('admin/users/create');
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = $request->fillUser(new User);
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User created.')]);

        return to_route('admin.users.show', $user);
    }

    /**
     * Show the given user, along with a table of the services assigned to them.
     */
    public function show(Request $request, User $user): Response
    {
        return Inertia::render('admin/users/show', [
            'user' => UserResource::make($user)->resolve(),
            'services' => ServiceResource::collection($this->paginatedServices($request, owner: $user)),
            'servicesQuery' => $this->servicesQueryState($request),
            'serviceOptions' => $this->serviceOptions(),
        ]);
    }

    /**
     * Show the form for editing the given user.
     */
    public function edit(User $user): Response
    {
        return Inertia::render('admin/users/edit', [
            'user' => UserResource::make($user)->resolve(),
        ]);
    }

    /**
     * Update the given user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $request->fillUser($user)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return to_route('admin.users.show', $user);
    }

    /**
     * Delete the given user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403, __('You cannot delete your own account from the admin area.'));

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User deleted.')]);

        return to_route('admin.users.index');
    }
}
