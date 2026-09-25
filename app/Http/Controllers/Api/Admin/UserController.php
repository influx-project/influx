<?php

namespace App\Http\Controllers\Api\Admin;

use App\Concerns\QueriesUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    use QueriesUsers;

    /**
     * List users, with filtering, sorting and pagination from the query string.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection($this->paginatedUsers($request));
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $request->fillUser(new User);
        $user->save();

        return UserResource::make($user)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show the given user.
     */
    public function show(User $user): UserResource
    {
        return UserResource::make($user);
    }

    /**
     * Update the given user.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $request->fillUser($user)->save();

        return UserResource::make($user);
    }

    /**
     * Delete the given user.
     */
    public function destroy(Request $request, User $user): Response
    {
        abort_if($request->user()->is($user), 403, __('You cannot delete your own account from the admin area.'));

        $user->delete();

        return response()->noContent();
    }
}
