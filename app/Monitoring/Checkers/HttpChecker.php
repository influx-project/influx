<?php

namespace App\Monitoring\Checkers;

use App\Models\Service;
use App\Monitoring\CheckResult;
use App\Monitoring\Stopwatch;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Requests the service's root URL. Redirects are not followed, so a 3xx counts as up;
 * 4xx and 5xx responses count as down.
 */
class HttpChecker implements Checker
{
    public function check(Service $service): CheckResult
    {
        $stopwatch = Stopwatch::start();

        try {
            $response = Http::timeout($service->timeout)
                ->connectTimeout($service->timeout)
                ->withoutRedirecting()
                ->withUserAgent(config('app.name').' monitor')
                ->get($this->url($service));
        } catch (ConnectionException $e) {
            return CheckResult::down($e->getMessage());
        }

        $latency = $stopwatch->elapsedMs();
        $status = $response->status();

        return $status < 400
            ? CheckResult::up($latency, $status)
            : CheckResult::down("HTTP {$status} {$response->reason()}", $latency, $status);
    }

    /**
     * Get the URL requested for the service.
     */
    public function url(Service $service): string
    {
        $scheme = $service->use_ssl ? 'https' : 'http';
        $host = str_contains($service->host, ':') ? "[{$service->host}]" : $service->host;
        $defaultPort = $service->use_ssl ? 443 : 80;
        $port = $service->port === null || $service->port === $defaultPort ? '' : ":{$service->port}";

        return "{$scheme}://{$host}{$port}/";
    }
}
