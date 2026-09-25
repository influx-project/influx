<?php

namespace Tests\Feature\Api;

use App\Enums\ServiceImportance;
use App\Enums\ServiceType;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Primary website',
            'type' => ServiceType::Http->value,
            'host' => 'example.com',
            'port' => 443,
            'use_ssl' => true,
            ...$overrides,
        ];
    }

    public function test_returns_401_for_guests()
    {
        $this->getJson(route('api.services.index'))->assertUnauthorized();
    }

    public function test_index_only_returns_the_current_users_services()
    {
        $user = User::factory()->create();
        $own = Service::factory()->for($user, 'owner')->create();
        Service::factory()->create();
        Service::factory()->unassigned()->create();

        $this->actingAs($user)
            ->getJson(route('api.services.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);
    }

    public function test_index_returns_every_service_to_admins_and_filters_by_owner()
    {
        $admin = User::factory()->admin()->create();
        Service::factory()->count(2)->create();
        $unassigned = Service::factory()->unassigned()->create();

        $this->actingAs($admin)
            ->getJson(route('api.services.index'))
            ->assertJsonPath('meta.total', 3);

        $this->actingAs($admin)
            ->getJson(route('api.services.index', ['filter' => ['owner' => 'none']]))
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $unassigned->id)
            ->assertJsonPath('data.0.owner', null);
    }

    public function test_index_sorts_by_importance_rank_rather_than_alphabetically()
    {
        $user = User::factory()->create();

        foreach ([ServiceImportance::Normal, ServiceImportance::Critical, ServiceImportance::Low, ServiceImportance::High] as $importance) {
            Service::factory()->for($user, 'owner')->importance($importance)->create();
        }

        $this->actingAs($user)
            ->getJson(route('api.services.index', ['sort' => '-importance']))
            ->assertOk()
            ->assertJsonPath('data.*.importance', ['critical', 'high', 'normal', 'low']);
    }

    public function test_index_filters_by_search_type_and_enabled()
    {
        $user = User::factory()->create();
        $match = Service::factory()->for($user, 'owner')->https()->create(['name' => 'Billing API']);
        Service::factory()->for($user, 'owner')->https()->disabled()->create(['name' => 'Billing admin']);
        Service::factory()->for($user, 'owner')->create(['name' => 'Billing database']);

        $this->actingAs($user)
            ->getJson(route('api.services.index', ['filter' => ['search' => 'billing', 'type' => 'http', 'enabled' => 'true']]))
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_index_returns_400_for_a_sort_that_is_not_allowed()
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.services.index', ['sort' => 'user_id']))
            ->assertBadRequest();
    }

    public function test_non_admins_cannot_filter_by_owner()
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('api.services.index', ['filter' => ['owner' => 'none']]))
            ->assertBadRequest();
    }

    public function test_store_creates_a_service_owned_by_the_current_user_with_defaults()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.services.store'), $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.owner.id', $user->id)
            ->assertJsonPath('data.importance', 'normal')
            ->assertJsonPath('data.collect_metrics', true)
            ->assertJsonPath('data.stream_metrics', true)
            ->assertJsonPath('data.enabled', true);

        $this->assertDatabaseHas('services', ['name' => 'Primary website', 'user_id' => $user->id, 'use_ssl' => true]);
    }

    public function test_store_returns_422_when_a_non_admin_assigns_an_owner()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.services.store'), $this->validPayload(['user_id' => User::factory()->create()->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id' => 'Only administrators can choose who a service is assigned to.']);
    }

    public function test_admins_can_create_unassigned_services_or_assign_them_to_anyone()
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();

        $this->actingAs($admin)
            ->postJson(route('api.services.store'), $this->validPayload(['user_id' => $owner->id]))
            ->assertCreated()
            ->assertJsonPath('data.owner.id', $owner->id);

        $this->actingAs($admin)
            ->postJson(route('api.services.store'), $this->validPayload(['user_id' => null]))
            ->assertCreated()
            ->assertJsonPath('data.owner', null);
    }

    public function test_store_returns_422_with_validation_errors()
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('api.services.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name' => 'The name field is required.',
                'type' => 'The type field is required.',
                'host' => 'The host field is required.',
            ]);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidServiceProvider(): array
    {
        return [
            'host with a scheme' => [['host' => 'https://example.com'], 'host', 'The host must be a valid IP address or hostname.'],
            'host with spaces' => [['host' => 'not a host'], 'host', 'The host must be a valid IP address or hostname.'],
            'missing port' => [['port' => null], 'port', 'The port field is required.'],
            'port out of range' => [['port' => 70000], 'port', 'The port field must be between 1 and 65535.'],
            'port on ping' => [['type' => 'ping', 'port' => 22], 'port', 'Ping services do not use a port.'],
            'unknown type' => [['type' => 'carrier-pigeon'], 'type', 'The selected type is invalid.'],
            'unknown importance' => [['importance' => 'urgent'], 'importance', 'The selected importance is invalid.'],
            'timeout not below interval' => [['check_interval' => 30, 'timeout' => 30], 'timeout', 'The timeout must be shorter than the check interval.'],
        ];
    }

    #[DataProvider('invalidServiceProvider')]
    public function test_store_rejects_invalid_input(array $overrides, string $field, string $message)
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('api.services.store'), $this->validPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field => $message]);
    }

    public function test_store_accepts_ipv6_addresses_and_ping_services_without_a_port()
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('api.services.store'), $this->validPayload(['type' => 'ping', 'host' => '2001:db8::1', 'port' => null]))
            ->assertCreated()
            ->assertJsonPath('data.port', null);
    }

    public function test_returns_404_for_another_users_service()
    {
        $user = User::factory()->create();
        $service = Service::factory()->create();

        $this->actingAs($user)->getJson(route('api.services.show', $service))->assertNotFound();
        $this->actingAs($user)->patchJson(route('api.services.update', $service), ['name' => ''])->assertNotFound();
        $this->actingAs($user)->deleteJson(route('api.services.destroy', $service))->assertNotFound();

        $this->assertModelExists($service);
    }

    public function test_owners_can_partially_update_their_service()
    {
        $user = User::factory()->create();
        $service = Service::factory()->for($user, 'owner')->create(['name' => 'Old', 'port' => 8080]);

        $this->actingAs($user)
            ->patchJson(route('api.services.update', $service), ['name' => 'New', 'stream_metrics' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.port', 8080)
            ->assertJsonPath('data.stream_metrics', false)
            ->assertJsonPath('data.collect_metrics', true);
    }

    public function test_switching_to_ping_clears_the_port_and_switching_back_requires_one()
    {
        $user = User::factory()->create();
        $service = Service::factory()->for($user, 'owner')->create(['port' => 8080]);

        $this->actingAs($user)
            ->patchJson(route('api.services.update', $service), ['type' => 'ping'])
            ->assertOk()
            ->assertJsonPath('data.port', null);

        $this->actingAs($user)
            ->patchJson(route('api.services.update', $service), ['type' => 'tcp'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['port' => 'The port field is required.']);
    }

    public function test_owners_cannot_reassign_their_service()
    {
        $user = User::factory()->create();
        $service = Service::factory()->for($user, 'owner')->create();

        $this->actingAs($user)
            ->patchJson(route('api.services.update', $service), ['user_id' => User::factory()->create()->id])
            ->assertUnprocessable();

        $this->assertSame($user->id, $service->fresh()->user_id);
    }

    public function test_owners_can_delete_their_service_and_get_204()
    {
        $user = User::factory()->create();
        $service = Service::factory()->for($user, 'owner')->create();

        $this->actingAs($user)
            ->deleteJson(route('api.services.destroy', $service))
            ->assertNoContent();

        $this->assertModelMissing($service);
    }
}
