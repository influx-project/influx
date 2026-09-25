<?php

namespace App\Monitoring\Daemon;

use App\Models\Service;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Talks to a service's Influx Daemon over its HTTP API, as documented in the daemon's README.
 */
class DaemonClient
{
    /**
     * The most samples the daemon returns per page.
     */
    public const PAGE_SIZE = 5000;

    public function __construct(protected Service $service) {}

    /**
     * Get a client for the given Influx Daemon service.
     */
    public static function for(Service $service): self
    {
        return new self($service);
    }

    /**
     * Get what the daemon reports about itself and its host.
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     * @throws DaemonException
     */
    public function info(): array
    {
        return $this->get('/v1/info');
    }

    /**
     * Get the daemon's latest sample, with the ID of the daemon process that took it.
     *
     * @return array{stream_id: string, sample: array<string, mixed>}
     *
     * @throws ConnectionException
     * @throws DaemonException Including a "not ready" one before the daemon's first sample.
     */
    public function snapshot(): array
    {
        /** @var array{stream_id: string, sample: array<string, mixed>} */
        return $this->get('/v1/snapshot');
    }

    /**
     * Get the buffered samples taken after the given one, oldest first.
     *
     * @return array{stream_id: string, first_seq: int, last_seq: int, samples: list<array<string, mixed>>, has_more: bool}
     *
     * @throws ConnectionException
     * @throws DaemonException
     */
    public function samples(int $after = 0, int $limit = self::PAGE_SIZE): array
    {
        /** @var array{stream_id: string, first_seq: int, last_seq: int, samples: list<array<string, mixed>>, has_more: bool} */
        return $this->get('/v1/samples', ['after' => $after, 'limit' => $limit]);
    }

    /**
     * Get the base URL of the daemon's API.
     */
    public function baseUrl(): string
    {
        $scheme = $this->service->use_ssl ? 'https' : 'http';
        $host = str_contains($this->service->host, ':') ? "[{$this->service->host}]" : $this->service->host;
        $port = $this->service->port ?? $this->service->type->defaultPort();

        return "{$scheme}://{$host}:{$port}";
    }

    /**
     * Make an authenticated GET request and decode its JSON body.
     *
     * @param  array<string, int|string>  $query
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     * @throws DaemonException
     */
    protected function get(string $path, array $query = []): array
    {
        $response = $this->request()->get($path, $query);
        $body = $response->json();

        if ($response->failed()) {
            throw $this->error($response, $body);
        }

        if (! is_array($body)) {
            throw new DaemonException('The response is not from Influx Daemon.', status: $response->status());
        }

        return $body;
    }

    /**
     * Build a request to the daemon.
     */
    protected function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken($this->service->ensureDaemon()->token)
            ->acceptJson()
            ->timeout($this->service->timeout)
            ->connectTimeout($this->service->timeout)
            ->withUserAgent(config('app.name').' panel')
            // Pages of samples are large; the daemon compresses them when asked.
            ->withOptions(['decode_content' => 'gzip']);
    }

    /**
     * Turn an error response into an exception, explaining the errors people can fix.
     */
    protected function error(Response $response, mixed $body): DaemonException
    {
        $code = is_array($body) ? ($body['error']['code'] ?? null) : null;
        $message = is_array($body) ? ($body['error']['message'] ?? null) : null;

        if (! is_string($code)) {
            return new DaemonException("HTTP {$response->status()} {$response->reason()}: the response is not from Influx Daemon.", status: $response->status());
        }

        $message = match ($code) {
            'invalid_token' => 'Influx Daemon rejected the token. Copy the token from this service’s settings into the daemon’s config.',
            'forbidden' => 'Influx Daemon refused the connection: the panel’s IP address is not in its allowed_ips.',
            'rate_limited' => 'Influx Daemon is rate limiting the panel after too many failed logins.',
            default => is_string($message) ? $message : "Influx Daemon returned {$code}.",
        };

        return new DaemonException($message, $code, $response->status());
    }
}
