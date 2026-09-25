<?php

namespace Tests\Feature\Monitoring;

use App\Models\Incident;
use App\Models\Metric;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ServicePagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, list<string>}>
     */
    public static function tabs(): array
    {
        return [
            'overview' => ['show', 'show', ['overview', 'range', 'ranges']],
            'downtime' => ['downtime', 'downtime', ['downtime', 'incidents']],
            'alerts' => ['alerts', 'alerts', ['alerts']],
            'information' => ['information', 'information', []],
            'settings' => ['edit', 'edit', ['options']],
        ];
    }

    #[DataProvider('tabs')]
    public function test_owners_can_view_each_tab(string $route, string $component, array $props)
    {
        $user = User::factory()->create();
        $service = Service::factory()->for($user, 'owner')->create();
        Metric::factory()->for($service)->create();

        $this->actingAs($user)
            ->get(route("services.{$route}", $service))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($component, $props, $service) {
                $page->component("services/{$component}")
                    ->where('service.id', $service->id)
                    ->where('status.state', 'up');

                foreach ($props as $prop) {
                    $page->has($prop);
                }
            });
    }

    #[DataProvider('tabs')]
    public function test_other_users_cannot_see_the_tabs(string $route)
    {
        $service = Service::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route("services.{$route}", $service))
            ->assertNotFound();
    }

    #[DataProvider('tabs')]
    public function test_admins_can_view_each_tab_of_any_service(string $route, string $component, array $props)
    {
        $service = Service::factory()->unassigned()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route("admin.services.{$route}", $service))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($component, $props) {
                $page->component("admin/services/{$component}")->where('status.state', 'pending');

                foreach ($props as $prop) {
                    $page->has($prop);
                }
            });
    }

    public function test_the_overview_range_can_be_chosen()
    {
        $user = User::factory()->create();
        $service = Service::factory()->for($user, 'owner')->create();

        $this->actingAs($user)
            ->get(route('services.show', [$service, 'range' => '7d']))
            ->assertInertia(fn (Assert $page) => $page->where('range', '7d')->has('overview.series', 85));

        $this->actingAs($user)
            ->get(route('services.show', [$service, 'range' => 'forever']))
            ->assertInertia(fn (Assert $page) => $page->where('range', '24h'));
    }

    public function test_incidents_are_paginated_newest_first()
    {
        $user = User::factory()->create();
        $service = Service::factory()->for($user, 'owner')->create();
        Incident::factory()->for($service)->count(11)->sequence(fn ($sequence) => [
            'started_at' => now()->subHours($sequence->index + 1),
            'ended_at' => now()->subHours($sequence->index + 1)->addMinutes(5),
        ])->create();

        $this->actingAs($user)
            ->get(route('services.downtime', $service))
            ->assertInertia(fn (Assert $page) => $page
                ->has('incidents.data', 10)
                ->where('incidents.data.0.duration_seconds', 300)
                ->where('incidents.meta.total', 11));
    }
}
