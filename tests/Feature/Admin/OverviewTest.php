<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('admin.overview'))->assertRedirect(route('login'));
    }

    public function test_non_admin_users_are_forbidden()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.overview'))
            ->assertForbidden();
    }

    public function test_admin_flag_cannot_be_mass_assigned()
    {
        $user = User::create(['name' => 'X', 'email' => 'x@example.com', 'password' => 'password', 'admin' => true]);

        $this->assertFalse($user->fresh()->admin);
    }

    public function test_admins_can_view_the_overview()
    {
        User::factory()->count(2)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.overview'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/overview')
                ->where('stats.total_users', 3)
                ->where('stats.admins', 1)
                ->has('recent_users', 3));
    }
}
