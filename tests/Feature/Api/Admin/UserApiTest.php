<?php

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_401_for_guests()
    {
        $this->getJson(route('api.admin.users.index'))->assertUnauthorized();
    }

    public function test_returns_403_for_non_admin_users()
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.admin.users.index'))
            ->assertForbidden();
    }

    public function test_index_returns_filtered_sorted_and_paginated_users()
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin', 'created_at' => now()->subDay()]);
        User::factory()->unverified()->create(['name' => 'Unverified']);
        $verified = User::factory()->create(['name' => 'Verified']);

        $this->actingAs($admin)
            ->getJson(route('api.admin.users.index', [
                'filter' => ['verified' => 'true'],
                'sort' => '-created_at',
                'per_page' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $verified->id)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'admin', 'email_verified_at', 'two_factor_enabled', 'created_at', 'updated_at']], 'links', 'meta']);
    }

    public function test_index_returns_400_for_a_sort_that_is_not_allowed()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('api.admin.users.index', ['sort' => 'password']))
            ->assertBadRequest();
    }

    public function test_index_returns_400_for_a_filter_that_is_not_allowed()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('api.admin.users.index', ['filter' => ['password' => 'x']]))
            ->assertBadRequest();
    }

    public function test_store_creates_a_user_and_returns_201()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('api.admin.users.store'), [
                'name' => 'Api User',
                'email' => 'api@example.com',
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'api@example.com')
            ->assertJsonPath('data.admin', false)
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('users', ['email' => 'api@example.com', 'admin' => false, 'email_verified_at' => null]);
    }

    public function test_store_returns_422_with_validation_errors()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('api.admin.users.store'), ['email' => 'not-an-email', 'admin' => 'maybe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name' => 'The name field is required.',
                'email' => 'The email field must be a valid email address.',
                'password' => 'The password field is required.',
                'admin' => 'The admin field must be true or false.',
            ]);
    }

    public function test_show_returns_the_user()
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('api.admin.users.show', $user))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', $user->name);
    }

    public function test_partial_update_of_the_email_resets_verification()
    {
        $user = User::factory()->create(['name' => 'Unchanged']);

        $this->actingAs(User::factory()->admin()->create())
            ->patchJson(route('api.admin.users.update', $user), ['email' => 'changed@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email', 'changed@example.com')
            ->assertJsonPath('data.name', 'Unchanged')
            ->assertJsonPath('data.email_verified_at', null);
    }

    public function test_destroy_deletes_the_user_and_returns_204()
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->deleteJson(route('api.admin.users.destroy', $user))
            ->assertNoContent();

        $this->assertModelMissing($user);
    }

    public function test_destroy_returns_403_when_admins_delete_themselves()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->deleteJson(route('api.admin.users.destroy', $admin))
            ->assertForbidden();

        $this->assertModelExists($admin);
    }
}
