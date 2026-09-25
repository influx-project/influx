<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('services.index'))->assertRedirect(route('login'));
    }

    public function test_index_only_lists_the_current_users_services()
    {
        $user = User::factory()->admin()->create();
        $own = Service::factory()->for($user, 'owner')->create();
        Service::factory()->create();

        $this->actingAs($user)
            ->get(route('services.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('services/index')
                ->has('services.data', 1)
                ->where('services.data.0.id', $own->id)
                ->where('query.sort', '-importance')
                ->has('options.types')
                ->has('options.importances'));
    }

    public function test_users_can_create_a_service_they_own()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('services.store'), [
                'name' => 'Build server',
                'description' => 'CI runners',
                'location' => 'Frankfurt',
                'type' => 'ssh',
                'host' => '10.0.0.5',
                'port' => 22,
                'use_ssl' => false,
                'importance' => 'high',
                'check_interval' => 120,
                'timeout' => 5,
                'collect_metrics' => false,
                'stream_metrics' => true,
                'enabled' => true,
            ])
            ->assertRedirect(route('services.show', Service::sole()));

        $this->assertDatabaseHas('services', [
            'user_id' => $user->id,
            'name' => 'Build server',
            'location' => 'Frankfurt',
            'importance' => 'high',
            'check_interval' => 120,
            'collect_metrics' => false,
        ]);
    }

    public function test_users_can_create_and_update_an_influx_daemon_service()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('services.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('options.types', fn ($types) => collect($types)->contains(
                    fn ($type) => $type['value'] === 'influx_daemon' && $type['label'] === 'Influx Daemon',
                )));

        $this->actingAs($user)
            ->post(route('services.store'), [
                'name' => 'Edge daemon',
                'type' => 'influx_daemon',
                'host' => 'edge.internal',
                'port' => 9000,
            ])
            ->assertRedirect(route('services.show', Service::sole()));

        $service = Service::sole();
        $this->assertSame('influx_daemon', $service->type->value);

        $this->actingAs($user)
            ->put(route('services.update', $service), ['port' => 9001])
            ->assertRedirect(route('services.show', $service));
        $this->assertSame(9001, $service->fresh()->port);
    }

    public function test_owners_can_view_edit_and_update_their_service()
    {
        $user = User::factory()->create();
        $service = Service::factory()->for($user, 'owner')->create();

        $this->actingAs($user)
            ->get(route('services.show', $service))
            ->assertInertia(fn (Assert $page) => $page
                ->component('services/show')
                ->where('service.id', $service->id)
                ->where('service.owner.id', $user->id));

        $this->actingAs($user)->get(route('services.edit', $service))->assertOk();

        $this->actingAs($user)
            ->put(route('services.update', $service), ['name' => 'Renamed', 'enabled' => false])
            ->assertRedirect(route('services.show', $service));

        $this->assertSame('Renamed', $service->fresh()->name);
        $this->assertFalse($service->fresh()->enabled);
    }

    public function test_users_get_404_for_another_users_service()
    {
        $user = User::factory()->create();
        $service = Service::factory()->create();

        $this->actingAs($user)->get(route('services.show', $service))->assertNotFound();
        $this->actingAs($user)->get(route('services.edit', $service))->assertNotFound();
        $this->actingAs($user)->put(route('services.update', $service), ['name' => 'Hijacked'])->assertNotFound();
        $this->actingAs($user)->delete(route('services.destroy', $service))->assertNotFound();

        $this->assertModelExists($service);
        $this->assertNotSame('Hijacked', $service->fresh()->name);
    }

    public function test_owners_can_delete_their_service()
    {
        $user = User::factory()->create();
        $service = Service::factory()->for($user, 'owner')->create();

        $this->actingAs($user)
            ->delete(route('services.destroy', $service))
            ->assertRedirect(route('services.index'));

        $this->assertModelMissing($service);
    }

    public function test_deleting_a_user_unassigns_their_services()
    {
        $user = User::factory()->create();
        $service = Service::factory()->for($user, 'owner')->create();

        $user->delete();

        $this->assertModelExists($service);
        $this->assertNull($service->fresh()->user_id);
    }
}
