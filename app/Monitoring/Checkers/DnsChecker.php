<?php

namespace App\Monitoring\Checkers;

use App\Models\Service;
use App\Monitoring\CheckResult;
use App\Monitoring\Stopwatch;

/**
 * Sends the server a query for the root zone's name servers over UDP. Any
 * well-formed reply counts as up, even a refusal, since it proves the server is answering.
 */
class DnsChecker implements Checker
{
    public function check(Service $service): CheckResult
    {
        $host = str_contains($service->host, ':') ? "[{$service->host}]" : $service->host;
        $port = $service->port ?? 53;
        $id = random_int(0, 0xFFFF);

        $stopwatch = Stopwatch::start();
        $socket = @stream_socket_client("udp://{$host}:{$port}", $errorCode, $errorMessage, $service->timeout);

        if ($socket === false) {
            return CheckResult::down("Could not reach {$host}:{$port}: {$errorMessage}");
        }

        try {
            stream_set_timeout($socket, $service->timeout);
            @fwrite($socket, $this->query($id));
            $response = @fread($socket, 512);
            $timedOut = stream_get_meta_data($socket)['timed_out'];
        } finally {
            fclose($socket);
        }

        $latency = $stopwatch->elapsedMs();

        if ($timedOut || $response === false || $response === '') {
            return CheckResult::down('No response to DNS query');
        }

        return $this->isResponseTo($response, $id)
            ? CheckResult::up($latency)
            : CheckResult::down('Malformed DNS response', $latency);
    }

    /**
     * Build a recursive query for the root zone's NS records.
     */
    public function query(int $id): string
    {
        // Header: ID, flags (RD), QDCOUNT=1, ANCOUNT, NSCOUNT, ARCOUNT; then the root name, QTYPE=NS, QCLASS=IN.
        return pack('nnnnnn', $id, 0x0100, 1, 0, 0, 0)."\0".pack('nn', 2, 1);
    }

    /**
     * Determine whether the packet is a reply to the query with the given ID.
     */
    public function isResponseTo(string $packet, int $id): bool
    {
        if (strlen($packet) < 12) {
            return false;
        }

        $header = unpack('nid/nflags', $packet);

        return $header !== false && $header['id'] === $id && ($header['flags'] & 0x8000) !== 0;
    }
}
