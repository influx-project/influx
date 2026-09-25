<?php

namespace App\Concerns;

use App\Enums\ServiceType;
use App\Http\Resources\IncidentResource;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Monitoring\Daemon\DaemonClient;
use App\Monitoring\Daemon\DaemonReport;
use App\Monitoring\ServiceReport;
use App\Monitoring\TimeRange;
use Illuminate\Http\Request;

/**
 * Builds the props for the tabs of a service's pages, shared by the user and admin controllers.
 */
trait PresentsServiceMonitoring
{
    /**
     * Get the props every service tab needs for its header.
     *
     * @return array<string, mixed>
     */
    protected function serviceTabProps(Service $service): array
    {
        return [
            'service' => ServiceResource::make($service->loadMissing('owner'))->resolve(),
            'status' => app(ServiceReport::class)->status($service),
        ];
    }

    /**
     * Get the props for the overview tab: headline numbers, charts and recent checks.
     *
     * @return array<string, mixed>
     */
    protected function overviewProps(Request $request, Service $service): array
    {
        $range = TimeRange::tryFrom((string) $request->query('range')) ?? TimeRange::Day;

        return [
            ...$this->serviceTabProps($service),
            'range' => $range->value,
            'ranges' => TimeRange::options(),
            'overview' => app(ServiceReport::class)->overview($service, $range),
        ];
    }

    /**
     * Get the props for the daemon tab: what the Influx Daemon reported about its host and containers.
     *
     * @return array<string, mixed>
     */
    protected function daemonProps(Request $request, Service $service): array
    {
        $range = TimeRange::tryFrom((string) $request->query('range')) ?? TimeRange::Day;

        return [
            ...$this->serviceTabProps($service),
            'range' => $range->value,
            'ranges' => TimeRange::options(),
            'daemon' => app(DaemonReport::class)->summary($service, $range),
        ];
    }

    /**
     * Get how to connect an Influx Daemon to the panel, for the settings of Influx Daemon services.
     *
     * Only for people who can update the service, since the token grants access to the daemon.
     *
     * @return array<string, mixed>
     */
    protected function daemonConnectionProps(Service $service): array
    {
        if ($service->type !== ServiceType::InfluxDaemon) {
            return ['daemon_connection' => null];
        }

        return [
            'daemon_connection' => [
                'token' => $service->ensureDaemon()->token,
                'url' => DaemonClient::for($service)->baseUrl(),
            ],
        ];
    }

    /**
     * Get the props for the downtime tab: uptime, daily history and incidents.
     *
     * @return array<string, mixed>
     */
    protected function downtimeProps(Service $service): array
    {
        $report = app(ServiceReport::class);

        return [
            ...$this->serviceTabProps($service),
            'downtime' => $report->downtime($service),
            'incidents' => IncidentResource::collection($report->incidents($service)),
        ];
    }

    /**
     * Get the props for the alerts tab: events derived from the service's checks.
     *
     * @return array<string, mixed>
     */
    protected function alertsProps(Service $service): array
    {
        return [
            ...$this->serviceTabProps($service),
            'alerts' => app(ServiceReport::class)->alerts($service),
        ];
    }
}
