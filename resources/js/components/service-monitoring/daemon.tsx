import { Link } from '@inertiajs/react';
import {
    Boxes,
    CirclePause,
    CircleX,
    Cpu,
    HardDrive,
    MemoryStick,
    Network,
    Plug,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { ServiceEndpoint } from '@/components/service-badges';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    formatBytes,
    formatDateTime,
    formatPercent,
    formatRate,
    formatRelative,
    formatSeconds,
} from '@/lib/format';
import type {
    DaemonAgent,
    Service,
    ServiceDaemon as Daemon,
    ServiceStatus,
    TimeRangeOption,
    TimeRangeValue,
} from '@/types';
import type { RouteDefinition } from '@/wayfinder';
import { DaemonHistoryChart } from './daemon-history-chart';
import { DaemonLivePanel } from './daemon-live-panel';
import { EmptyState } from './empty-state';
import { HistoricalBadge } from './live-badge';
import { StatCard } from './stat-card';

/**
 * The daemon tab: live and historical host and container metrics reported by Influx Daemon.
 */
export function ServiceDaemon({
    service,
    status,
    daemon,
    range,
    ranges,
    settingsHref,
}: {
    service: Service;
    status: ServiceStatus;
    daemon: Daemon;
    range: TimeRangeValue;
    ranges: TimeRangeOption[];
    settingsHref: RouteDefinition<'get'>;
}) {
    const rangeLabel =
        ranges.find(({ value }) => value === range)?.label.toLowerCase() ??
        range;
    const spansDays = range !== '24h';
    const { stats, series } = daemon.history;
    const hasSamples = stats.samples > 0;
    const noSamples = `No samples, ${rangeLabel}`;

    return (
        <div className="flex flex-col gap-4">
            <DaemonNotice
                service={service}
                status={status}
                daemon={daemon}
                settingsHref={settingsHref}
            />

            {daemon.agent && (
                <HostCard
                    agent={daemon.agent}
                    lastSeenAt={daemon.last_seen_at}
                />
            )}

            <DaemonLivePanel service={service} settingsHref={settingsHref} />

            <div className="flex flex-wrap items-center justify-between gap-2 pt-2">
                <h2 className="text-base font-semibold tracking-tight">
                    History
                </h2>
                <HistoricalBadge
                    detail={
                        service.collect_metrics
                            ? `collected every ${formatSeconds(service.check_interval)}`
                            : undefined
                    }
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Average CPU"
                    icon={Cpu}
                    value={formatPercent(stats.cpu_percent)}
                    detail={
                        hasSamples
                            ? `Peak ${formatPercent(stats.cpu_percent_max)}, ${rangeLabel}`
                            : noSamples
                    }
                />
                <StatCard
                    label="Average memory"
                    icon={MemoryStick}
                    value={formatPercent(stats.memory_percent)}
                    detail={hasSamples ? rangeLabel : noSamples}
                />
                <StatCard
                    label="Fullest disk"
                    icon={HardDrive}
                    value={formatPercent(stats.disk_used_percent)}
                    detail={hasSamples ? 'Latest sample' : noSamples}
                />
                <StatCard
                    label="Network transferred"
                    icon={Network}
                    value={
                        hasSamples
                            ? formatBytes(
                                  stats.network_rx_bytes +
                                      stats.network_tx_bytes,
                              )
                            : '—'
                    }
                    detail={
                        hasSamples
                            ? `${formatBytes(stats.network_rx_bytes)} in · ${formatBytes(stats.network_tx_bytes)} out`
                            : noSamples
                    }
                />
            </div>

            <HistoryCard
                title="CPU and memory"
                description={`Average use, ${rangeLabel}.`}
                icon={Cpu}
                hasSamples={hasSamples}
            >
                <DaemonHistoryChart
                    series={series}
                    spansDays={spansDays}
                    maxValue={100}
                    format={formatPercent}
                    lines={[
                        { key: 'cpu_percent', label: 'CPU', color: 'first' },
                        {
                            key: 'memory_percent',
                            label: 'Memory',
                            color: 'second',
                        },
                    ]}
                    extraRows={(point) => [
                        {
                            label: 'CPU peak',
                            value: formatPercent(point.cpu_percent_max),
                        },
                    ]}
                />
            </HistoryCard>

            <div className="grid gap-4 lg:grid-cols-2">
                <HistoryCard
                    title="Network"
                    description={`Average traffic in and out, ${rangeLabel}.`}
                    icon={Network}
                    hasSamples={hasSamples}
                >
                    <DaemonHistoryChart
                        series={series}
                        spansDays={spansDays}
                        format={formatRate}
                        lines={[
                            {
                                key: 'network_rx_bytes_per_second',
                                label: 'In',
                                color: 'first',
                            },
                            {
                                key: 'network_tx_bytes_per_second',
                                label: 'Out',
                                color: 'second',
                            },
                        ]}
                    />
                </HistoryCard>
                <HistoryCard
                    title="Disk I/O"
                    description={`Average reads and writes, ${rangeLabel}.`}
                    icon={HardDrive}
                    hasSamples={hasSamples}
                >
                    <DaemonHistoryChart
                        series={series}
                        spansDays={spansDays}
                        format={formatRate}
                        lines={[
                            {
                                key: 'disk_read_bytes_per_second',
                                label: 'Reads',
                                color: 'first',
                            },
                            {
                                key: 'disk_write_bytes_per_second',
                                label: 'Writes',
                                color: 'second',
                            },
                        ]}
                    />
                </HistoryCard>
            </div>

            <HistoryCard
                title="Containers"
                description={`Running and unhealthy containers, ${rangeLabel}.`}
                icon={Boxes}
                hasSamples={series.some(
                    (point) => point.containers_total !== null,
                )}
                emptyMessage={
                    daemon.agent && !daemon.agent.containers_available
                        ? 'The daemon can’t see a container runtime on this host.'
                        : undefined
                }
            >
                <DaemonHistoryChart
                    series={series}
                    spansDays={spansDays}
                    stepped
                    format={formatCount}
                    lines={[
                        {
                            key: 'containers_running',
                            label: 'Running',
                            color: 'first',
                        },
                        {
                            key: 'containers_unhealthy',
                            label: 'Unhealthy',
                            color: 'critical',
                        },
                    ]}
                    extraRows={(point) => [
                        {
                            label: 'All containers',
                            value: formatCount(point.containers_total),
                        },
                    ]}
                />
            </HistoryCard>
        </div>
    );
}

/**
 * Explains why the daemon is not reporting, when it is not.
 */
function DaemonNotice({
    service,
    status,
    daemon,
    settingsHref,
}: {
    service: Service;
    status: ServiceStatus;
    daemon: Daemon;
    settingsHref: RouteDefinition<'get'>;
}) {
    const settingsLink = (children: ReactNode) => (
        <Link
            href={settingsHref}
            className="font-medium text-foreground underline underline-offset-4"
        >
            {children}
        </Link>
    );

    if (!service.enabled) {
        return (
            <Alert>
                <CirclePause />
                <AlertTitle>Monitoring is paused</AlertTitle>
                <AlertDescription>
                    Monitoring is switched off in this service’s settings, so
                    the panel won’t contact the daemon.
                </AlertDescription>
            </Alert>
        );
    }

    if (status.state === 'down') {
        return (
            <Alert variant="destructive">
                <CircleX />
                <AlertTitle>Can’t reach Influx Daemon</AlertTitle>
                <AlertDescription>
                    <p>
                        {status.last_check?.error ??
                            'The last check of the daemon failed.'}
                    </p>
                    <p>
                        Check that the daemon is running on{' '}
                        <ServiceEndpoint service={service} /> and uses the token
                        in {settingsLink('Settings')}.
                    </p>
                </AlertDescription>
            </Alert>
        );
    }

    if (daemon.last_seen_at === null) {
        return (
            <Alert>
                <Plug />
                <AlertTitle>Waiting for Influx Daemon</AlertTitle>
                <AlertDescription>
                    <p>
                        Install Influx Daemon on the host and give it the token
                        from {settingsLink('Settings')}. Once it’s running on{' '}
                        <ServiceEndpoint service={service} />, the panel will
                        pull host and container metrics from it and show them
                        here.
                    </p>
                </AlertDescription>
            </Alert>
        );
    }

    return null;
}

/**
 * The host the daemon runs on, as it describes itself.
 */
function HostCard({
    agent,
    lastSeenAt,
}: {
    agent: DaemonAgent;
    lastSeenAt: string | null;
}) {
    const details: [string, string][] = [
        ['Hostname', agent.hostname],
        ['Operating system', `${agent.os} (${agent.arch})`],
        ['Kernel', agent.kernel],
        ['CPU', `${agent.cpu_model}, ${agent.cpu_cores} cores`],
        ['Memory', formatBytes(agent.memory_total_bytes)],
        ['Booted', formatDateTime(agent.boot_time)],
        [
            'Containers',
            agent.containers_available ? 'Docker available' : 'Not visible',
        ],
        [
            'Daemon',
            `v${agent.version}${lastSeenAt ? `, seen ${formatRelative(lastSeenAt)}` : ''}`,
        ],
    ];

    return (
        <Card>
            <CardHeader>
                <CardTitle>Host</CardTitle>
                <CardDescription>As reported by Influx Daemon.</CardDescription>
            </CardHeader>
            <CardContent>
                <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    {details.map(([label, value]) => (
                        <div key={label} className="min-w-0">
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="truncate" title={value}>
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </CardContent>
        </Card>
    );
}

/**
 * A chart of stored samples, or a placeholder until there are some.
 */
function HistoryCard({
    title,
    description,
    icon,
    hasSamples,
    emptyMessage = 'This chart fills in once the panel has collected samples from Influx Daemon.',
    children,
}: {
    title: string;
    description: string;
    icon: LucideIcon;
    hasSamples: boolean;
    emptyMessage?: string;
    children: ReactNode;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                {hasSamples ? (
                    children
                ) : (
                    <EmptyState icon={icon} title="No samples yet">
                        {emptyMessage}
                    </EmptyState>
                )}
            </CardContent>
        </Card>
    );
}

function formatCount(value: number | null): string {
    return value === null ? '—' : value.toLocaleString();
}
