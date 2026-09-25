<?php

namespace Tests\Feature\Monitoring;

use App\Enums\ServiceType;
use App\Models\Service;
use App\Monitoring\Checkers\DnsChecker;
use App\Monitoring\Checkers\HttpChecker;
use App\Monitoring\Checkers\PingChecker;
use App\Monitoring\Checkers\TcpChecker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckersTest extends TestCase
{
    protected function service(array $attributes): Service
    {
        return new Service([...['timeout' => 2, 'use_ssl' => false], ...$attributes]);
    }

    public function test_http_checks_record_the_status_code()
    {
        Http::fake(['*' => Http::response('ok', 204)]);

        $result = (new HttpChecker)->check($this->service(['type' => ServiceType::Http, 'host' => 'example.com', 'port' => 443, 'use_ssl' => true]));

        $this->assertTrue($result->successful);
        $this->assertSame(204, $result->statusCode);
        $this->assertNotNull($result->latencyMs);
        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/');
    }

    public function test_http_error_responses_count_as_down()
    {
        Http::fake(['*' => Http::response('', 503)]);

        $result = (new HttpChecker)->check($this->service(['type' => ServiceType::Http, 'host' => 'example.com', 'port' => 80]));

        $this->assertFalse($result->successful);
        $this->assertSame(503, $result->statusCode);
        $this->assertSame('HTTP 503 Service Unavailable', $result->error);
    }

    public function test_http_connection_failures_count_as_down()
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $result = (new HttpChecker)->check($this->service(['type' => ServiceType::Http, 'host' => 'example.com', 'port' => 80]));

        $this->assertFalse($result->successful);
        $this->assertNull($result->statusCode);
        $this->assertStringContainsString('Failed to connect', $result->error);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function httpUrls(): array
    {
        return [
            'default http port' => [['host' => 'example.com', 'port' => 80], 'http://example.com/'],
            'custom port' => [['host' => 'example.com', 'port' => 8080], 'http://example.com:8080/'],
            'https' => [['host' => 'example.com', 'port' => 8443, 'use_ssl' => true], 'https://example.com:8443/'],
            'ipv6' => [['host' => '2001:db8::1', 'port' => 80], 'http://[2001:db8::1]/'],
        ];
    }

    #[DataProvider('httpUrls')]
    public function test_http_urls_are_built_from_the_service(array $attributes, string $url)
    {
        $this->assertSame($url, (new HttpChecker)->url($this->service(['type' => ServiceType::Http, ...$attributes])));
    }

    public function test_tcp_checks_succeed_when_the_port_accepts_connections()
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);

        $result = (new TcpChecker)->check($this->service(['type' => ServiceType::Tcp, 'host' => '127.0.0.1', 'port' => $port]));

        fclose($server);
        $this->assertTrue($result->successful);
    }

    public function test_tcp_checks_fail_when_the_port_is_closed()
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
        fclose($server);

        $result = (new TcpChecker)->check($this->service(['type' => ServiceType::Tcp, 'host' => '127.0.0.1', 'port' => $port]));

        $this->assertFalse($result->successful);
        $this->assertStringContainsString("Could not connect to 127.0.0.1:{$port}", $result->error);
    }

    public function test_ping_reads_the_round_trip_time()
    {
        Process::fake([
            '*' => Process::result("64 bytes from 10.0.0.1: icmp_seq=1 ttl=64 time=3.21 ms\n"),
        ]);

        $result = (new PingChecker)->check($this->service(['type' => ServiceType::Ping, 'host' => '10.0.0.1', 'timeout' => 5]));

        $this->assertTrue($result->successful);
        $this->assertSame(3.21, $result->latencyMs);
        Process::assertRan(fn ($process) => $process->command === ['ping', '-n', '-c', '1', '-W', '5', '10.0.0.1']);
    }

    public function test_ping_without_a_reply_is_down()
    {
        Process::fake(['*' => Process::result(exitCode: 1)]);

        $result = (new PingChecker)->check($this->service(['type' => ServiceType::Ping, 'host' => '10.0.0.1']));

        $this->assertFalse($result->successful);
        $this->assertSame('No reply', $result->error);
    }

    public function test_dns_queries_and_responses_are_matched_by_id()
    {
        $checker = new DnsChecker;
        $query = $checker->query(0xBEEF);

        $this->assertSame(17, strlen($query));
        $this->assertFalse($checker->isResponseTo($query, 0xBEEF), 'A query is not a response.');

        $response = pack('nnnnnn', 0xBEEF, 0x8180, 1, 0, 0, 0);
        $this->assertTrue($checker->isResponseTo($response, 0xBEEF));
        $this->assertFalse($checker->isResponseTo($response, 0x1234));
        $this->assertFalse($checker->isResponseTo('short', 0xBEEF));
    }
}
