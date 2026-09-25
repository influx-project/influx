<?php

namespace Tests\Feature\Admin;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_users_are_forbidden()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.services.index'))
            ->assertForbidden();
    }

    public function test_index_lists_every_service_with_its_owner()
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create(['name' => 'Owner Person']);
        Service::factory()->for($owner, 'owner')->create(['name' => 'Alpha']);
        Service::factory()->unassigned()->create(['name' => 'Beta']);

        $this->actingAs($admin)
            ->get(route('admin.services.index', ['sort' => 'name']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/services/index')
                ->has('services.data', 2)
                ->where('services.data.0.owner.name', 'Owner Person')
                ->where('services.data.1.owner', null)
                ->has('owners', 2));
    }

    public function test_admins_can_create_a_service_for_another_user()
    {
        $owner = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.services.store'), [
                'name' => 'Customer DB',
                'type' => 'database',
                'host' => 'db.internal',
                'port' => 5432,
                'user_id' => $owner->id,
            ])
            ->assertRedirect(route('admin.services.show', Service::sole()));

        $this->assertSame($owner->id, Service::sole()->user_id);
    }

    public function test_admins_can_reassign_and_unassign_a_service()
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create();
        $newOwner = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.services.update', $service), ['user_id' => $newOwner->id])
            ->assertRedirect(route('admin.services.show', $service));
        $this->assertSame($newOwner->id, $service->fresh()->user_id);

        $this->actingAs($admin)->put(route('admin.services.update', $service), ['user_id' => null]);
        $this->assertNull($service->fresh()->user_id);
    }

    public function test_assigning_a_nonexistent_owner_is_rejected()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.services.update', Service::factory()->create()), ['user_id' => 999])
            ->assertSessionHasErrors(['user_id' => 'The selected user id is invalid.']);
    }

    public function test_admins_can_delete_any_service()
    {
        $service = Service::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.services.destroy', $service))
            ->assertRedirect(route('admin.services.index'));

        $this->assertModelMissing($service);
    }

    public function test_user_page_lists_only_that_users_services_with_table_controls()
    {
        $user = User::factory()->create();
        Service::factory()->for($user, 'owner')->create(['name' => 'Zulu']);
        Service::factory()->for($user, 'owner')->create(['name' => 'Alpha']);
        Service::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.users.show', [$user, 'sort' => 'name', 'per_page' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/show')
                ->has('services.data', 1)
                ->where('services.data.0.name', 'Alpha')
                ->where('services.meta.total', 2)
                ->where('servicesQuery.sort', 'name'));
    }
}
