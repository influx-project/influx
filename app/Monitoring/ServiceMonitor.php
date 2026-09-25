<?php

namespace App\Monitoring;

use App\Enums\ServiceType;
use App\Models\Metric;
use App\Models\Service;
use App\Monitoring\Checkers\Checker;
use App\Monitoring\Checkers\DaemonChecker;
use App\Monitoring\Checkers\DnsChecker;
use App\Monitoring\Checkers\HttpChecker;
use App\Monitoring\Checkers\PingChecker;
use App\Monitoring\Checkers\SmtpChecker;
use App\Monitoring\Checkers\SshChecker;
use App\Monitoring\Checkers\TcpChecker;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Checks a service, records the result and opens or closes its incidents.
 */
class ServiceMonitor
{
    public function __construct(protected Container $container) {}

    /**
     * Check the service and record the result.
     */
    public function check(Service $service): Metric
    {
        $checkedAt = now();
        $result = $this->probe($service);

        return DB::transaction(fn (): Metric => $this->record($service, $result, $checkedAt));
    }

    /**
     * Check the service without recording anything, as live updates do.
     */
    public function probe(Service $service): CheckResult
    {
        try {
            return $this->checkerFor($service->type)->check($service);
        } catch (Throwable $e) {
            report($e);

            return CheckResult::down($e->getMessage());
        }
    }

    /**
     * Get the checker for the given type.
     */
    public function checkerFor(ServiceType $type): Checker
    {
        $checker = match ($type) {
            ServiceType::Http => HttpChecker::class,
            ServiceType::Tcp, ServiceType::Database => TcpChecker::class,
            ServiceType::Ssh => SshChecker::class,
            ServiceType::Ping => PingChecker::class,
            ServiceType::Dns => DnsChecker::class,
            ServiceType::Smtp => SmtpChecker::class,
            ServiceType::InfluxDaemon => DaemonChecker::class,
        };

        return $this->container->make($checker);
    }

    /**
     * Store the result and update the service's incidents to match it.
     */
    protected function record(Service $service, CheckResult $result, \DateTimeInterface $checkedAt): Metric
    {
        $metric = $service->metrics()->create([
            'checked_at' => $checkedAt,
            'successful' => $result->successful,
            'latency_ms' => $result->latencyMs,
            'status_code' => $result->statusCode,
            'error' => $result->error,
        ]);

        $incident = $service->incidents()->ongoing()->latest('started_at')->lockForUpdate()->first();

        if ($result->successful) {
            $incident?->update(['ended_at' => $checkedAt]);
        } elseif ($incident !== null) {
            $incident->increment('failed_checks');
        } else {
            $service->incidents()->create([
                'started_at' => $checkedAt,
                'cause' => $result->error,
                'status_code' => $result->statusCode,
            ]);
        }

        Service::withoutTimestamps(fn () => $service->forceFill(['last_checked_at' => $checkedAt])->saveQuietly());

        return $metric;
    }
}
