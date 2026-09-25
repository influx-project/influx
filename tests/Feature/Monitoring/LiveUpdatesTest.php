<?php

namespace Tests\Feature\Monitoring;

use App\Console\Commands\StreamLiveChecks;
use App\Enums\ServiceType;
use App\Events\LiveCheckCompleted;
use App\Jobs\StreamLiveCheck;
use App\Models\Service;
use App\Models\User;
use App\Monitoring\Checkers\TcpChecker;
use App\Monitoring\CheckResult;
use App\Monitoring\LiveViewers;
use App\Monitoring\ServiceMonitor;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Pusher\Pusher;
use Tests\TestCase;

class LiveUpdatesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Authorize channels against a Pusher-compatible broadcaster, since the test default is `null`.
     */
    protected function useReverb(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        require base_path('routes/channels.php');
    }

    protected function authorizeLiveChannel(User $user, Service $service)
    {
        return $this->actingAs($user)->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-'.LiveCheckCompleted::channelName($service->id),
        ]);
    }

    public function test_owners_and_admins_can_watch_a_service_live()
    {
        $this->useReverb();
        $owner = User::factory()->create();
        $service = Service::factory()->for($owner, 'owner')->create();

        $this->authorizeLiveChannel($owner, $service)->assertOk()->assertJsonStructure(['auth']);
        $this->authorizeLiveChannel(User::factory()->admin()->create(), $service)->assertOk();
    }

    public function test_other_users_cannot_watch_a_service_live()
    {
        $this->useReverb();
        $service = Service::factory()->create();

        $this->authorizeLiveChannel(User::factory()->create(), $service)->assertForbidden();
    }

    public function test_service_ids_are_read_from_live_channel_names()
    {
        $this->assertSame([12, 7], LiveViewers::serviceIdsFromChannels([
            'private-services.12.live',
            'private-services.7.live',
            'private-services.12.live',
            'private-services.abc.live',
            'private-App.Models.User.1',
        ]));
    }

    public function test_no_services_are_watched_without_a_pusher_compatible_broadcaster()
    {
        $this->assertSame([], app(LiveViewers::class)->watchedServiceIds());
    }

    public function test_watched_services_are_read_from_the_websocket_server()
    {
        $this->assertSame([3], $this->viewersWithChannels(['private-services.3.live' => []])->watchedServiceIds());
    }

    public function test_no_occupied_channels_means_no_watched_services()
    {
        // Reverb sends an empty JSON array, not an object, when no channels are occupied.
        $this->assertSame([], $this->viewersWithChannels([])->watchedServiceIds());
    }

    /**
     * Make a viewers lookup backed by a WebSocket server reporting the given occupied channels.
     *
     * @param  array<string, array<mixed>>  $channels
     */
    protected function viewersWithChannels(array $channels): LiveViewers
    {
        $pusher = Mockery::mock(Pusher::class);
        $pusher->shouldReceive('get')
            ->with('/channels', ['filter_by_prefix' => 'private-services.'], true)
            ->andReturn(['channels' => $channels]);

        $broadcast = Mockery::mock(BroadcastFactory::class);
        $broadcast->shouldReceive('connection')->andReturn(new PusherBroadcaster($pusher));

        return new LiveViewers($broadcast);
    }

    public function test_only_watched_services_with_live_updates_on_are_checked_a_few_seconds_apart()
    {
        Queue::fake();

        $watched = Service::factory()->create();
        $liveOff = Service::factory()->create(['stream_metrics' => false]);
        $disabled = Service::factory()->disabled()->create();
        $daemon = Service::factory()->create(['type' => ServiceType::InfluxDaemon]);
        Service::factory()->create();

        $this->mock(LiveViewers::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('watchedServiceIds')
            ->andReturn([$watched->id, $liveOff->id, $disabled->id, $daemon->id]));

        $this->artisan('services:stream-live')->expectsOutputToContain('Queued 1 live check.');
        Queue::assertPushedOn(StreamLiveCheck::QUEUE, StreamLiveCheck::class, fn (StreamLiveCheck $job) => $job->service->is($watched));

        // Throttled: the service was checked less than three seconds ago.
        $this->artisan('services:stream-live')->expectsOutputToContain('Queued 0 live checks.');

        $this->travel(StreamLiveChecks::INTERVAL)->seconds();
        $this->artisan('services:stream-live')->expectsOutputToContain('Queued 1 live check.');

        // The first check has not run yet, so the job's unique lock stops a second one piling up behind it.
        Queue::assertPushed(StreamLiveCheck::class, 1);
    }

    public function test_the_command_does_nothing_when_no_one_is_watching()
    {
        Queue::fake();
        Service::factory()->create();

        $this->artisan('services:stream-live')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_live_checks_are_broadcast_but_not_recorded()
    {
        Event::fake([LiveCheckCompleted::class]);
        $this->app->instance(TcpChecker::class, new class extends TcpChecker
        {
            public function check(Service $service): CheckResult
            {
                return CheckResult::up(42.5);
            }
        });
        $service = Service::factory()->create();

        (new StreamLiveCheck($service))->handle(app(ServiceMonitor::class));

        Event::assertDispatched(LiveCheckCompleted::class, fn (LiveCheckCompleted $event) => $event->service->is($service)
            && $event->broadcastWith()['successful'] === true
            && $event->broadcastWith()['latency_ms'] === 42.5);
        $this->assertSame(0, $service->metrics()->count());
        $this->assertSame(0, $service->incidents()->count());
        $this->assertNull($service->fresh()->last_checked_at);
    }

    public function test_live_checks_stop_once_live_updates_are_switched_off()
    {
        Event::fake([LiveCheckCompleted::class]);
        $service = Service::factory()->create(['stream_metrics' => false]);

        (new StreamLiveCheck($service))->handle(app(ServiceMonitor::class));

        Event::assertNotDispatched(LiveCheckCompleted::class);
    }

    public function test_live_check_events_broadcast_on_the_services_private_channel()
    {
        $this->freezeSecond();
        $service = Service::factory()->create();
        $event = new LiveCheckCompleted($service, CheckResult::down('HTTP 502 Bad Gateway', 12, 502), now());

        $this->assertSame("private-services.{$service->id}.live", $event->broadcastOn()[0]->name);
        $this->assertSame('check', $event->broadcastAs());
        $this->assertSame([
            'checked_at' => now()->toIso8601String(),
            'successful' => false,
            'latency_ms' => 12.0,
            'status_code' => 502,
            'error' => 'HTTP 502 Bad Gateway',
        ], $event->broadcastWith());
    }
}
