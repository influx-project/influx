<?php

namespace Tests\Feature\Monitoring;

use App\Enums\ServiceType;
use App\Events\DaemonSampleReceived;
use App\Events\LiveCheckCompleted;
use App\Jobs\CheckService;
use App\Jobs\StreamLiveCheck;
use App\Models\Daemon;
use App\Models\DaemonMetric;
use App\Models\Service;
use App\Models\User;
use App\Monitoring\Checkers\DaemonChecker;
use App\Monitoring\Daemon\DaemonReport;
use App\Monitoring\Daemon\DaemonSync;
use App\Monitoring\LiveViewers;
use App\Monitoring\TimeRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Mockery\MockInterface;
use Tests\TestCase;

class InfluxDaemonTest extends TestCase
{
    use RefreshDatabase;

    protected const BASE_URL = 'http://edge.example.com:7462';

    protected function daemonService(array $attributes = []): Service
    {
        return Service::factory()->create([
            'type' => ServiceType::InfluxDaemon,
            'host' => 'edge.example.com',
            'port' => 7462,
            ...$attributes,
        ]);
    }

    /**
     * Build a sample in the daemon's wire format.
     *
     * @return array<string, mixed>
     */
    protected function sample(int $seq, string $collectedAt, array $overrides = []): array
    {
        return array_replace_recursive([
            'seq' => $seq,
            'collected_at' => $collectedAt,
            'uptime_seconds' => 1000,
            'cpu' => ['usage_percent' => 10.0, 'load_1' => 1.0, 'load_5' => 0.5, 'load_15' => 0.25],
            'memory' => ['used_bytes' => 4000, 'total_bytes' => 16000, 'swap_used_bytes' => 0, 'swap_total_bytes' => 0],
            'disks' => [
                ['mount' => '/', 'device' => '/dev/sda1', 'filesystem' => 'ext4', 'used_bytes' => 50, 'total_bytes' => 100],
                ['mount' => '/data', 'device' => '/dev/sdb1', 'filesystem' => 'xfs', 'used_bytes' => 90, 'total_bytes' => 100],
            ],
            'disk_io' => ['read_bytes_per_second' => 100.0, 'write_bytes_per_second' => 200.0],
            'network' => ['rx_bytes_per_second' => 1000.0, 'tx_bytes_per_second' => 500.0],
            'containers' => [
                ['id' => 'a', 'name' => 'web', 'image' => 'nginx', 'state' => 'running', 'health' => 'healthy', 'started_at' => null, 'restart_count' => 0, 'cpu_percent' => 1.0, 'memory_used_bytes' => 10, 'memory_limit_bytes' => null],
                ['id' => 'b', 'name' => 'db', 'image' => 'postgres', 'state' => 'running', 'health' => 'unhealthy', 'started_at' => null, 'restart_count' => 2, 'cpu_percent' => 5.0, 'memory_used_bytes' => 20, 'memory_limit_bytes' => null],
                ['id' => 'c', 'name' => 'job', 'image' => 'busybox', 'state' => 'exited', 'health' => null, 'started_at' => null, 'restart_count' => 0, 'cpu_percent' => null, 'memory_used_bytes' => null, 'memory_limit_bytes' => null],
            ],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function info(string $streamId = 'stream-1'): array
    {
        return [
            'version' => '0.1.0',
            'stream_id' => $streamId,
            'hostname' => 'edge-01',
            'os' => 'Debian GNU/Linux 12 (bookworm)',
            'kernel' => '6.1.0-25-amd64',
            'arch' => 'amd64',
            'cpu_model' => 'AMD EPYC 7763',
            'cpu_cores' => 8,
            'memory_total_bytes' => 16000,
            'boot_time' => '2026-09-10T08:12:44Z',
            'sample_interval_seconds' => 5,
            'retention_seconds' => 21600,
            'containers_available' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $samples
     * @return array<string, mixed>
     */
    protected function page(array $samples, string $streamId = 'stream-1', bool $hasMore = false): array
    {
        return [
            'stream_id' => $streamId,
            'first_seq' => 1,
            'last_seq' => $samples === [] ? 0 : end($samples)['seq'],
            'samples' => $samples,
            'has_more' => $hasMore,
        ];
    }

    protected function error(string $code, int $status)
    {
        return Http::response(['error' => ['code' => $code, 'message' => "Daemon says {$code}."]], $status);
    }

    public function test_influx_daemon_services_get_a_token_when_first_needed()
    {
        $service = $this->daemonService();

        $token = $service->ensureDaemon()->token;

        $this->assertSame(Daemon::TOKEN_LENGTH, strlen($token));
        $this->assertSame($token, $service->fresh()->ensureDaemon()->token, 'The token is only generated once.');
        $this->assertSame(1, Daemon::count());
        $this->assertNotSame($token, DB::table('daemons')->where('service_id', $service->id)->value('token'), 'The token is stored encrypted.');
    }

    public function test_only_influx_daemon_services_have_a_daemon()
    {
        $this->expectException(LogicException::class);

        Service::factory()->create()->ensureDaemon();
    }

    public function test_services_that_never_got_a_token_are_given_one_when_checked()
    {
        // As when seeding without model events, or creating the service before the daemons table existed.
        $service = Service::withoutEvents(fn () => $this->daemonService());
        Http::fake([self::BASE_URL.'/v1/snapshot' => Http::response(['stream_id' => 'stream-1', 'sample' => $this->sample(1, '2026-09-25T12:00:00Z')])]);

        $this->assertTrue(app(DaemonChecker::class)->check($service)->successful);

        $token = Daemon::sole()->token;
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', "Bearer {$token}"));
    }

    public function test_services_that_never_got_a_token_show_one_in_their_settings()
    {
        $user = User::factory()->create();
        $service = Service::withoutEvents(fn () => $this->daemonService(['user_id' => $user->id]));

        $this->actingAs($user)
            ->get(route('services.edit', $service))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('daemon_connection.token', Daemon::sole()->token));
    }

    public function test_changing_a_service_into_an_influx_daemon_gives_it_a_token()
    {
        $user = User::factory()->create();
        $service = Service::factory()->for($user, 'owner')->create();

        $this->actingAs($user)->put(route('services.update', $service), ['type' => 'influx_daemon', 'port' => 7462]);

        $this->actingAs($user)
            ->get(route('services.edit', $service))
            ->assertInertia(fn (Assert $page) => $page->has('daemon_connection.token'));
    }

    public function test_an_influx_daemon_service_can_be_created_and_connected_through_the_ui()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('services.store'), [
            'name' => 'Edge host',
            'type' => 'influx_daemon',
            'host' => 'edge.example.com',
            'port' => 7462,
        ]);

        $service = Service::sole();
        $response->assertRedirect(route('services.show', $service));

        $this->actingAs($user)->get(route('services.show', $service))->assertOk();
        $this->actingAs($user)->get(route('services.daemon', $service))->assertOk();
        $this->actingAs($user)
            ->get(route('services.edit', $service))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('daemon_connection.token', $service->ensureDaemon()->token)
                ->where('daemon_connection.url', self::BASE_URL));
    }

    public function test_the_daemon_port_is_the_default_for_the_type()
    {
        $this->assertSame(7462, ServiceType::InfluxDaemon->defaultPort());
    }

    public function test_a_daemon_that_answers_with_a_sample_is_up()
    {
        $service = $this->daemonService(['use_ssl' => true, 'port' => 8443]);
        Http::fake(['https://edge.example.com:8443/v1/snapshot' => Http::response(['stream_id' => 'stream-1', 'sample' => $this->sample(1, '2026-09-25T12:00:00Z')])]);

        ['result' => $result, 'sample' => $sample] = app(DaemonChecker::class)->snapshot($service);

        $this->assertTrue($result->successful);
        $this->assertNotNull($result->latencyMs);
        $this->assertSame(1, $sample['seq']);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', "Bearer {$service->ensureDaemon()->token}"));
    }

    public function test_a_daemon_that_has_not_taken_its_first_sample_is_up()
    {
        $service = $this->daemonService();
        Http::fake([self::BASE_URL.'/v1/snapshot' => $this->error('not_ready', 503)]);

        ['result' => $result, 'sample' => $sample] = app(DaemonChecker::class)->snapshot($service);

        $this->assertTrue($result->successful);
        $this->assertNull($sample);
    }

    public function test_a_daemon_that_rejects_the_token_is_down_with_an_explanation()
    {
        $service = $this->daemonService();
        Http::fake([self::BASE_URL.'/v1/snapshot' => $this->error('invalid_token', 401)]);

        $result = app(DaemonChecker::class)->check($service);

        $this->assertFalse($result->successful);
        $this->assertSame(401, $result->statusCode);
        $this->assertStringContainsString('rejected the token', $result->error);
    }

    public function test_something_other_than_a_daemon_answering_is_down()
    {
        $service = $this->daemonService();
        Http::fake([self::BASE_URL.'/v1/snapshot' => Http::response('<html>Not found</html>', 404)]);

        $result = app(DaemonChecker::class)->check($service);

        $this->assertFalse($result->successful);
        $this->assertStringContainsString('not from Influx Daemon', $result->error);
    }

    public function test_an_unreachable_daemon_is_down()
    {
        $service = $this->daemonService();
        Http::fake([self::BASE_URL.'/*' => Http::failedConnection()]);

        $this->assertFalse(app(DaemonChecker::class)->check($service)->successful);
    }

    public function test_the_first_sync_stores_the_agent_and_rolls_samples_up_by_the_minute()
    {
        $service = $this->daemonService();
        Http::fake([
            self::BASE_URL.'/v1/info' => Http::response($this->info()),
            self::BASE_URL.'/v1/samples*' => Http::response($this->page([
                $this->sample(1, '2026-09-25T12:00:50Z'),
                $this->sample(2, '2026-09-25T12:00:55Z', ['cpu' => ['usage_percent' => 30.0], 'memory' => ['used_bytes' => 8000]]),
                [...$this->sample(3, '2026-09-25T12:01:00Z'), 'containers' => null, 'disks' => []],
            ])),
        ]);

        $this->assertSame(3, app(DaemonSync::class)->sync($service));

        $daemon = $service->daemon()->first();
        $this->assertSame('stream-1', $daemon->stream_id);
        $this->assertSame(3, $daemon->last_seq);
        $this->assertNotNull($daemon->last_seen_at);
        $this->assertSame('edge-01', $daemon->agent['hostname']);
        $this->assertArrayNotHasKey('stream_id', $daemon->agent);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/v1/samples?after=0&limit=5000')
            && $request->hasHeader('Accept-Encoding', 'gzip'));

        [$first, $second] = $service->daemonMetrics()->orderBy('minute')->get();

        $this->assertTrue($first->minute->equalTo(CarbonImmutable::parse('2026-09-25T12:00:00Z')));
        $this->assertSame(2, $first->samples);
        $this->assertSame(10, $first->seconds);
        $this->assertSame(20.0, $first->cpu_percent);
        $this->assertSame(30.0, $first->cpu_percent_max);
        $this->assertSame(6000, $first->memory_used_bytes);
        $this->assertSame(90.0, $first->disk_used_percent);
        $this->assertSame(2 * 1000 * 5, $first->network_rx_bytes);
        $this->assertSame(2 * 200 * 5, $first->disk_write_bytes);
        $this->assertSame([3, 2, 1], [$first->containers_total, $first->containers_running, $first->containers_unhealthy]);

        $this->assertSame(1, $second->samples);
        $this->assertNull($second->disk_used_percent);
        $this->assertNull($second->containers_total);
    }

    public function test_later_syncs_continue_from_the_cursor_and_merge_into_the_same_minute()
    {
        $service = $this->daemonService();
        Http::fakeSequence(self::BASE_URL.'/v1/samples*')
            ->push($this->page([$this->sample(1, '2026-09-25T12:00:05Z')]))
            ->push($this->page([$this->sample(2, '2026-09-25T12:00:10Z', ['cpu' => ['usage_percent' => 40.0]])]));
        Http::fake([self::BASE_URL.'/v1/info' => Http::response($this->info())]);

        app(DaemonSync::class)->sync($service);
        app(DaemonSync::class)->sync($service->fresh());

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'after=1&'));
        $metric = $service->daemonMetrics()->sole();
        $this->assertSame(2, $metric->samples);
        $this->assertSame(25.0, $metric->cpu_percent);
        $this->assertSame(2, $service->daemon()->first()->last_seq);
    }

    public function test_the_cursor_starts_again_when_the_daemon_restarts()
    {
        $service = $this->daemonService();
        $service->ensureDaemon()->forceFill(['stream_id' => 'old-stream', 'last_seq' => 500])->save();
        Http::fake([
            self::BASE_URL.'/v1/info' => Http::response($this->info('new-stream')),
            self::BASE_URL.'/v1/samples*' => Http::response($this->page([$this->sample(1, '2026-09-25T12:00:05Z')], 'new-stream')),
        ]);

        app(DaemonSync::class)->sync($service);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'after=0&'));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'after=500'));
        $this->assertSame(['new-stream', 1], [$service->daemon()->first()->stream_id, $service->daemon()->first()->last_seq]);
    }

    public function test_a_restart_between_requests_is_noticed_from_the_samples_response()
    {
        $service = $this->daemonService();
        Http::fake([
            self::BASE_URL.'/v1/info' => Http::sequence()->push($this->info('stream-1'))->push($this->info('stream-2')),
            self::BASE_URL.'/v1/samples*' => Http::sequence()
                ->push($this->page([$this->sample(9, '2026-09-25T12:00:05Z')], 'stream-2'))
                ->push($this->page([$this->sample(1, '2026-09-25T12:00:05Z')], 'stream-2')),
        ]);

        $this->assertSame(1, app(DaemonSync::class)->sync($service));
        $this->assertSame(['stream-2', 1], [$service->daemon()->first()->stream_id, $service->daemon()->first()->last_seq]);
    }

    public function test_long_backlogs_are_read_page_by_page()
    {
        $service = $this->daemonService();
        Http::fake([
            self::BASE_URL.'/v1/info' => Http::response($this->info()),
            self::BASE_URL.'/v1/samples*' => Http::sequence()
                ->push($this->page([$this->sample(1, '2026-09-25T12:00:05Z')], hasMore: true))
                ->push($this->page([$this->sample(2, '2026-09-25T12:00:10Z')])),
        ]);

        $this->assertSame(2, app(DaemonSync::class)->sync($service));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'after=1&'));
    }

    public function test_the_background_check_records_the_result_and_collects_samples()
    {
        $service = $this->daemonService();
        Http::fake([
            self::BASE_URL.'/v1/snapshot' => Http::response(['stream_id' => 'stream-1', 'sample' => $this->sample(1, '2026-09-25T12:00:05Z')]),
            self::BASE_URL.'/v1/info' => Http::response($this->info()),
            self::BASE_URL.'/v1/samples*' => Http::response($this->page([$this->sample(1, '2026-09-25T12:00:05Z')])),
        ]);

        app()->call([new CheckService($service), 'handle']);

        $this->assertTrue($service->latestMetric->successful);
        $this->assertSame(1, $service->daemonMetrics()->count());
    }

    public function test_the_background_check_does_not_collect_samples_from_a_daemon_that_is_down()
    {
        $service = $this->daemonService();
        Http::fake([self::BASE_URL.'/*' => Http::failedConnection()]);

        app()->call([new CheckService($service), 'handle']);

        $this->assertFalse($service->latestMetric->successful);
        $this->assertSame(1, $service->incidents()->ongoing()->count());
        Http::assertSentCount(1);
    }

    public function test_a_failed_sample_collection_is_logged_rather_than_failing_the_check()
    {
        $service = $this->daemonService();
        Http::fake([
            self::BASE_URL.'/v1/snapshot' => Http::response(['stream_id' => 'stream-1', 'sample' => $this->sample(1, '2026-09-25T12:00:05Z')]),
            self::BASE_URL.'/v1/info' => $this->error('internal', 500),
        ]);
        Log::spy();

        app()->call([new CheckService($service), 'handle']);

        $this->assertTrue($service->latestMetric->successful);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'Influx Daemon'));
    }

    public function test_live_viewers_receive_the_daemons_latest_sample_and_a_live_check()
    {
        Event::fake([LiveCheckCompleted::class, DaemonSampleReceived::class]);
        Queue::fake();
        $service = $this->daemonService();
        $this->mock(LiveViewers::class, fn (MockInterface $mock) => $mock->shouldReceive('isWatched')->andReturnTrue());
        Http::fake([self::BASE_URL.'/v1/snapshot' => Http::response(['stream_id' => 'stream-1', 'sample' => $this->sample(7, '2026-09-25T12:00:05Z')])]);

        app()->call([new StreamLiveCheck($service), 'handle']);

        Event::assertDispatched(LiveCheckCompleted::class, fn (LiveCheckCompleted $event) => $event->result->successful);
        Event::assertDispatched(DaemonSampleReceived::class, function (DaemonSampleReceived $event) use ($service) {
            return $event->broadcastAs() === 'daemon'
                && $event->broadcastWith()['seq'] === 7
                && $event->broadcastOn()[0]->name === 'private-services.'.$service->id.'.live';
        });
        Queue::assertPushed(StreamLiveCheck::class);
        $this->assertSame(0, $service->metrics()->count(), 'Live checks are not recorded.');
    }

    public function test_live_viewers_still_get_a_check_when_the_daemon_is_down()
    {
        Event::fake([LiveCheckCompleted::class, DaemonSampleReceived::class]);
        Queue::fake();
        $service = $this->daemonService();
        $this->mock(LiveViewers::class, fn (MockInterface $mock) => $mock->shouldReceive('isWatched')->andReturnTrue());
        Http::fake([self::BASE_URL.'/*' => Http::failedConnection()]);

        app()->call([new StreamLiveCheck($service), 'handle']);

        Event::assertDispatched(LiveCheckCompleted::class, fn (LiveCheckCompleted $event) => ! $event->result->successful);
        Event::assertNotDispatched(DaemonSampleReceived::class);
    }

    public function test_the_report_summarises_the_stored_minutes()
    {
        // Both minutes fall in the 12:00 bucket of the 24-hour range.
        $this->travelTo(CarbonImmutable::parse('2026-09-25 12:14:30'));
        $service = $this->daemonService();
        DaemonMetric::factory()->for($service)->create([
            'minute' => now()->subMinutes(10)->startOfMinute(),
            'samples' => 12, 'seconds' => 60,
            'cpu_percent' => 10, 'cpu_percent_max' => 50,
            'memory_used_bytes' => 4000, 'memory_total_bytes' => 16000,
            'disk_used_percent' => 40,
            'network_rx_bytes' => 6000, 'network_tx_bytes' => 600,
            'containers_total' => 4, 'containers_running' => 3,
        ]);
        DaemonMetric::factory()->for($service)->create([
            'minute' => now()->subMinutes(5)->startOfMinute(),
            'samples' => 4, 'seconds' => 20,
            'cpu_percent' => 50, 'cpu_percent_max' => 70,
            'memory_used_bytes' => 8000, 'memory_total_bytes' => 16000,
            'disk_used_percent' => 45,
            'network_rx_bytes' => 2000, 'network_tx_bytes' => 200,
            'containers_total' => 5, 'containers_running' => 5,
        ]);
        DaemonMetric::factory()->for($service)->create(['minute' => now()->subDays(2)]);

        $summary = app(DaemonReport::class)->summary($service, TimeRange::Day);
        $stats = $summary['history']['stats'];

        $this->assertSame(16, $stats['samples']);
        $this->assertSame(20.0, $stats['cpu_percent']);
        $this->assertSame(70.0, $stats['cpu_percent_max']);
        $this->assertSame(31.25, $stats['memory_percent']);
        $this->assertSame(45.0, $stats['disk_used_percent']);
        $this->assertSame(8000, $stats['network_rx_bytes']);
        $this->assertSame([5, 5], [$stats['containers_running'], $stats['containers_total']]);

        $series = $summary['history']['series'];
        $this->assertCount(97, $series);
        $filled = array_values(array_filter($series, fn (array $point) => $point['cpu_percent'] !== null));
        $this->assertCount(1, $filled);
        $this->assertSame(round(8000 / 80, 2), $filled[0]['network_rx_bytes_per_second']);
        $this->assertNull($series[0]['network_rx_bytes_per_second']);
    }

    public function test_the_daemon_tab_shows_the_agent_and_history()
    {
        $user = User::factory()->create();
        $service = $this->daemonService(['user_id' => $user->id]);
        $service->ensureDaemon()->forceFill(['agent' => ['hostname' => 'edge-01'], 'last_seen_at' => now()])->save();
        DaemonMetric::factory()->for($service)->create();

        $this->actingAs($user)
            ->get(route('services.daemon', $service))
            ->assertInertia(fn (Assert $page) => $page
                ->where('daemon.agent.hostname', 'edge-01')
                ->whereNot('daemon.last_seen_at', null)
                ->where('daemon.history.stats.samples', 12)
                ->has('daemon.history.series', 97)
                ->missing('daemon.token'));
    }

    public function test_the_settings_of_influx_daemon_services_show_how_to_connect_the_daemon()
    {
        $user = User::factory()->create();
        $service = $this->daemonService(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('services.edit', $service))
            ->assertInertia(fn (Assert $page) => $page
                ->where('daemon_connection.token', $service->ensureDaemon()->token)
                ->where('daemon_connection.url', self::BASE_URL)
                ->missing('service.daemon'));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.services.edit', $service))
            ->assertInertia(fn (Assert $page) => $page->where('daemon_connection.token', $service->ensureDaemon()->token));

        $other = Service::factory()->for($user, 'owner')->create();
        $this->actingAs($user)
            ->get(route('services.edit', $other))
            ->assertInertia(fn (Assert $page) => $page->where('daemon_connection', null));
    }

    public function test_the_token_is_not_exposed_by_the_api()
    {
        $user = User::factory()->create();
        $service = $this->daemonService(['user_id' => $user->id]);

        $this->actingAs($user)
            ->getJson("/api/services/{$service->id}")
            ->assertOk()
            ->assertDontSee($service->ensureDaemon()->token);
    }

    public function test_owners_and_admins_can_regenerate_the_token()
    {
        $user = User::factory()->create();
        $service = $this->daemonService(['user_id' => $user->id]);
        $original = $service->ensureDaemon()->token;

        $this->actingAs($user)
            ->from(route('services.edit', $service))
            ->post(route('services.daemon.token', $service))
            ->assertRedirect(route('services.edit', $service));

        $regenerated = $service->daemon()->first()->token;
        $this->assertNotSame($original, $regenerated);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.services.daemon.token', $service))
            ->assertRedirect();

        $this->assertNotSame($regenerated, $service->daemon()->first()->token);
    }

    public function test_other_users_cannot_regenerate_the_token()
    {
        $service = $this->daemonService();
        $original = $service->ensureDaemon()->token;

        $this->actingAs(User::factory()->create())
            ->post(route('services.daemon.token', $service))
            ->assertNotFound();

        $this->assertSame($original, $service->daemon()->first()->token);
    }

    public function test_only_influx_daemon_services_have_a_token_to_regenerate()
    {
        $user = User::factory()->create();
        $service = Service::factory()->for($user, 'owner')->create();

        $this->actingAs($user)
            ->post(route('services.daemon.token', $service))
            ->assertNotFound();
    }

    public function test_old_minutes_are_pruned()
    {
        $service = $this->daemonService();
        DaemonMetric::factory()->for($service)->create(['minute' => now()->subDays(DaemonMetric::RETENTION_DAYS + 1)]);
        $recent = DaemonMetric::factory()->for($service)->create(['minute' => now()->subDay()]);

        $this->artisan('model:prune', ['--model' => [DaemonMetric::class]]);

        $this->assertSame([$recent->id], DaemonMetric::pluck('id')->all());
    }
}
