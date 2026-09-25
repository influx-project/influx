<?php

namespace App\Monitoring\Checkers;

use App\Models\Service;
use App\Monitoring\CheckResult;
use App\Monitoring\Stopwatch;
use RuntimeException;

/**
 * Base for checks that open a TCP connection, wrapped in TLS when the service uses SSL.
 */
abstract class SocketChecker implements Checker
{
    public function check(Service $service): CheckResult
    {
        $stopwatch = Stopwatch::start();

        try {
            $socket = $this->connect($service);
        } catch (RuntimeException $e) {
            return CheckResult::down($e->getMessage());
        }

        try {
            $error = $this->handshake($socket, $service);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        } finally {
            fclose($socket);
        }

        $latency = $stopwatch->elapsedMs();

        return $error === null ? CheckResult::up($latency) : CheckResult::down($error, $latency);
    }

    /**
     * Talk to the service over the open connection.
     *
     * @param  resource  $socket
     * @return string|null Why the service is not healthy, or null if it is.
     */
    abstract protected function handshake($socket, Service $service): ?string;

    /**
     * Open a connection to the service.
     *
     * @return resource
     *
     * @throws RuntimeException
     */
    protected function connect(Service $service)
    {
        $host = str_contains($service->host, ':') ? "[{$service->host}]" : $service->host;
        $transport = $service->use_ssl ? 'tls' : 'tcp';

        $context = stream_context_create(['ssl' => [
            'peer_name' => $service->host,
            'SNI_enabled' => true,
        ]]);

        $socket = @stream_socket_client(
            "{$transport}://{$host}:{$service->port}",
            $errorCode,
            $errorMessage,
            $service->timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($socket === false) {
            $reason = $errorMessage !== '' ? $errorMessage : 'Connection failed';

            throw new RuntimeException("Could not connect to {$host}:{$service->port}: {$reason}");
        }

        stream_set_timeout($socket, $service->timeout);

        return $socket;
    }

    /**
     * Read one line from the connection.
     *
     * @param  resource  $socket
     *
     * @throws RuntimeException
     */
    protected function readLine($socket): string
    {
        $line = fgets($socket, 1024);

        if (stream_get_meta_data($socket)['timed_out']) {
            throw new RuntimeException('Timed out waiting for a response');
        }

        if ($line === false) {
            throw new RuntimeException('The connection was closed without a response');
        }

        return rtrim($line, "\r\n");
    }
}
