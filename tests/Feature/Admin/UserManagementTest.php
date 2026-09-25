<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_non_admin_users_are_forbidden()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->delete(route('admin.users.destroy', User::factory()->create()))->assertForbidden();
    }

    public function test_index_lists_users_filtered_sorted_and_paginated_from_the_query_string()
    {
        $admin = User::factory()->admin()->create(['name' => 'Zed Admin']);
        User::factory()->create(['name' => 'Bob Smith', 'email' => 'bob@example.com']);
        User::factory()->create(['name' => 'Alice Smith', 'email' => 'alice@example.com']);
        User::factory()->create(['name' => 'Carol Jones', 'email' => 'carol.smith@example.com']);
        User::factory()->create(['name' => 'Dave Other']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', [
                'filter' => ['search' => 'smith', 'admin' => 'false'],
                'sort' => 'name',
                'per_page' => 2,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/index')
                ->has('users.data', 2)
                ->where('users.data.0.name', 'Alice Smith')
                ->where('users.data.1.name', 'Bob Smith')
                ->where('users.meta.total', 3)
                ->where('users.meta.last_page', 2)
                ->where('query.filter.search', 'smith')
                ->where('query.sort', 'name')
                ->where('query.per_page', 2));
    }

    public function test_index_does_not_expose_sensitive_attributes()
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('users.data.0.two_factor_enabled', true)
                ->missing('users.data.0.password')
                ->missing('users.data.0.two_factor_secret')
                ->missing('users.data.0.remember_token'));
    }

    public function test_admins_can_create_a_user_with_every_field()
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'admin' => true,
            'email_verified' => true,
        ]);

        $user = User::where('email', 'new@example.com')->sole();

        $response->assertRedirect(route('admin.users.show', $user));
        $this->assertSame('New Person', $user->name);
        $this->assertTrue($user->admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_creating_a_user_requires_name_email_and_password()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.users.store'), [])
            ->assertSessionHasErrors([
                'name' => 'The name field is required.',
                'email' => 'The email field is required.',
                'password' => 'The password field is required.',
            ]);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_creating_a_user_rejects_a_duplicate_email()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Duplicate',
                'email' => $admin->email,
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
            ])
            ->assertSessionHasErrors(['email' => 'The email has already been taken.']);
    }

    public function test_admins_can_view_a_user()
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/show')
                ->where('user.id', $user->id)
                ->where('user.email', $user->email));
    }

    public function test_admins_can_update_a_user_and_a_blank_password_keeps_the_current_one()
    {
        $user = User::factory()->unverified()->create();
        $originalPassword = $user->password;

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $user), [
                'name' => 'Renamed',
                'email' => 'renamed@example.com',
                'password' => '',
                'password_confirmation' => '',
                'admin' => true,
                'email_verified' => true,
            ])
            ->assertRedirect(route('admin.users.show', $user));

        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertSame('renamed@example.com', $user->email);
        $this->assertTrue($user->admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame($originalPassword, $user->password);
    }

    public function test_admins_can_change_a_users_password()
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.users.update', $user), [
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-password', $user->fresh()->password));
    }

    public function test_admins_cannot_remove_their_own_admin_access()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), ['admin' => false])
            ->assertSessionHasErrors(['admin' => 'You cannot remove your own administrator access.']);

        $this->assertTrue($admin->fresh()->admin);
    }

    public function test_admins_can_delete_a_user()
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertModelMissing($user);
    }

    public function test_admins_cannot_delete_themselves()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertForbidden();

        $this->assertModelExists($admin);
    }
}
