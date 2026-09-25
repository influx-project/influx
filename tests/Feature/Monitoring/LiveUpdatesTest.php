<?php

namespace Tests\Feature\Monitoring;

use App\Enums\ServiceType;
use App\Events\LiveCheckCompleted;
use App\Jobs\StreamLiveCheck;
use App\Listeners\StartLiveChecks;
use App\Models\Service;
use App\Models\User;
use App\Monitoring\Checkers\TcpChecker;
use App\Monitoring\CheckResult;
use App\Monitoring\LiveViewers;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Reverb\Events\ChannelCreated;
use Laravel\Reverb\Protocols\Pusher\Channels\Channel;
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

    public function test_a_single_service_is_watched_while_its_channel_is_occupied()
    {
        $this->assertTrue($this->viewersWithResponse('/channels/private-services.3.live', ['occupied' => true])->isWatched(3));
        $this->assertFalse($this->viewersWithResponse('/channels/private-services.3.live', ['occupied' => false])->isWatched(3));
    }

    /**
     * Make a viewers lookup backed by a WebSocket server reporting the given occupied channels.
     *
     * @param  array<string, array<mixed>>  $channels
     */
    protected function viewersWithChannels(array $channels): LiveViewers
    {
        return $this->viewersWithResponse('/channels', ['channels' => $channels], ['filter_by_prefix' => 'private-services.']);
    }

    /**
     * Make a viewers lookup backed by a WebSocket server that answers the given API path.
     *
     * @param  array<string, mixed>  $response
     * @param  array<string, string>  $params
     */
    protected function viewersWithResponse(string $path, array $response, array $params = []): LiveViewers
    {
        $pusher = Mockery::mock(Pusher::class);
        $pusher->shouldReceive('get')->with($path, $params, true)->andReturn($response);

        $broadcast = Mockery::mock(BroadcastFactory::class);
        $broadcast->shouldReceive('connection')->andReturn(new PusherBroadcaster($pusher));

        return new LiveViewers($broadcast);
    }

    /**
     * Report the given services as watched, and every other service as not.
     *
     * @param  list<int>  $ids
     */
    protected function watching(array $ids): void
    {
        $this->mock(LiveViewers::class, function (MockInterface $mock) use ($ids) {
            $mock->shouldReceive('watchedServiceIds')->andReturn($ids);
            $mock->shouldReceive('isWatched')->andReturnUsing(fn (int $id) => in_array($id, $ids, true));
        });
    }

    /**
     * Make TCP checks succeed with the given latency.
     */
    protected function fakeTcpUp(float $latency = 42.5): void
    {
        $this->app->instance(TcpChecker::class, new class($latency) extends TcpChecker
        {
            public function __construct(private float $latency) {}

            public function check(Service $service): CheckResult
            {
                return CheckResult::up($this->latency);
            }
        });
    }

    /**
     * Reverb's event for a channel getting its first subscriber. The channel is mocked, since
     * real channels can only be created inside the Reverb server.
     */
    protected function channelCreated(string $name): ChannelCreated
    {
        $channel = Mockery::mock(Channel::class);
        $channel->shouldReceive('name')->andReturn($name);

        return new ChannelCreated($channel);
    }

    public function test_watching_a_service_starts_its_live_checks()
    {
        Queue::fake();
        $service = Service::factory()->create();
        $liveOff = Service::factory()->create(['stream_metrics' => false]);

        (new StartLiveChecks)->handle($this->channelCreated("private-services.{$service->id}.live"));
        (new StartLiveChecks)->handle($this->channelCreated("private-services.{$liveOff->id}.live"));
        (new StartLiveChecks)->handle($this->channelCreated('private-App.Models.User.1'));

        Queue::assertPushedOn(StreamLiveCheck::QUEUE, StreamLiveCheck::class, fn (StreamLiveCheck $job) => $job->service->is($service));
        Queue::assertPushed(StreamLiveCheck::class, 1);
    }

    public function test_a_live_check_broadcasts_its_result_and_queues_the_next_one()
    {
        $this->freezeSecond();
        Queue::fake();
        Event::fake([LiveCheckCompleted::class]);
        $this->fakeTcpUp(42.5);
        $service = Service::factory()->create();
        $this->watching([$service->id]);

        app()->call([new StreamLiveCheck($service), 'handle']);

        Event::assertDispatched(LiveCheckCompleted::class, fn (LiveCheckCompleted $event) => $event->service->is($service)
            && $event->broadcastWith()['successful'] === true
            && $event->broadcastWith()['latency_ms'] === 42.5);
        Queue::assertPushedOn(StreamLiveCheck::QUEUE, StreamLiveCheck::class, fn (StreamLiveCheck $job) => $job->service->is($service)
            && now()->addSeconds(StreamLiveCheck::INTERVAL)->equalTo($job->delay));
        $this->assertSame(0, $service->metrics()->count(), 'Live checks are not recorded.');
        $this->assertSame(0, $service->incidents()->count());
        $this->assertNull($service->fresh()->last_checked_at);
    }

    public function test_live_checks_stop_when_no_one_is_watching()
    {
        Queue::fake();
        Event::fake([LiveCheckCompleted::class]);
        $service = Service::factory()->create();
        $this->watching([]);

        app()->call([new StreamLiveCheck($service), 'handle']);

        Event::assertNotDispatched(LiveCheckCompleted::class);
        Queue::assertNothingPushed();
    }

    public function test_live_checks_stop_once_live_updates_are_switched_off()
    {
        Queue::fake();
        Event::fake([LiveCheckCompleted::class]);
        $service = Service::factory()->create(['stream_metrics' => false]);
        $this->watching([$service->id]);

        app()->call([new StreamLiveCheck($service), 'handle']);

        Event::assertNotDispatched(LiveCheckCompleted::class);
        Queue::assertNothingPushed();
    }

    public function test_a_second_chain_for_the_same_service_ends_itself()
    {
        Queue::fake();
        Event::fake([LiveCheckCompleted::class]);
        $this->fakeTcpUp();
        $service = Service::factory()->create();
        $this->watching([$service->id]);

        app()->call([new StreamLiveCheck($service), 'handle']);
        app()->call([new StreamLiveCheck($service), 'handle']);

        Event::assertDispatchedTimes(LiveCheckCompleted::class, 1);
        Queue::assertPushed(StreamLiveCheck::class, 1);
    }

    public function test_the_safety_net_restarts_only_watched_services_whose_checks_stopped()
    {
        Queue::fake();
        $stalled = Service::factory()->create();
        $running = Service::factory()->create();
        $liveOff = Service::factory()->create(['stream_metrics' => false]);
        $daemon = Service::factory()->create(['type' => ServiceType::InfluxDaemon]);
        Service::factory()->create();
        Cache::put(StreamLiveCheck::lastCheckKey($running->id), true, StreamLiveCheck::STALE_AFTER);
        $this->watching([$stalled->id, $running->id, $liveOff->id, $daemon->id]);

        $this->artisan('services:stream-live')->expectsOutputToContain('Restarted live checks for 1 service.');

        Queue::assertPushed(StreamLiveCheck::class, 1);
        Queue::assertPushed(StreamLiveCheck::class, fn (StreamLiveCheck $job) => $job->service->is($stalled));
    }

    public function test_the_command_does_nothing_when_no_one_is_watching()
    {
        Queue::fake();
        Service::factory()->create();

        $this->artisan('services:stream-live')->assertSuccessful();

        Queue::assertNothingPushed();
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
