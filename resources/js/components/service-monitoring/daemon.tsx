import {
    Boxes,
    CirclePause,
    Cpu,
    HardDrive,
    MemoryStick,
    Network,
    Plug,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { ServiceEndpoint } from '@/components/service-badges';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatBytes, formatDateTime, formatRelative } from '@/lib/format';
import type {
    DaemonAgent,
    Service,
    ServiceDaemon as Daemon,
    TimeRangeOption,
    TimeRangeValue,
} from '@/types';
import type { RouteDefinition } from '@/wayfinder';
import { DaemonLivePanel } from './daemon-live-panel';
import { EmptyState } from './empty-state';
import { HistoricalBadge } from './live-badge';
import { StatCard } from './stat-card';

/**
 * The daemon tab: live and historical host and container metrics reported by Influx Daemon.
 */
export function ServiceDaemon({
    service,
    daemon,
    range,
    ranges,
    settingsHref,
}: {
    service: Service;
    daemon: Daemon;
    range: TimeRangeValue;
    ranges: TimeRangeOption[];
    settingsHref: RouteDefinition<'get'>;
}) {
    const rangeLabel =
        ranges.find(({ value }) => value === range)?.label.toLowerCase() ??
        range;

    return (
        <div className="flex flex-col gap-4">
            <DaemonNotice service={service} daemon={daemon} />

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
                        daemon.agent
                            ? `samples every ${daemon.agent.sample_interval_seconds} s`
                            : undefined
                    }
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Average CPU"
                    icon={Cpu}
                    value="—"
                    detail={`No samples, ${rangeLabel}`}
                />
                <StatCard
                    label="Average memory"
                    icon={MemoryStick}
                    value="—"
                    detail={`No samples, ${rangeLabel}`}
                />
                <StatCard
                    label="Fullest disk"
                    icon={HardDrive}
                    value="—"
                    detail="No samples yet"
                />
                <StatCard
                    label="Network transferred"
                    icon={Network}
                    value="—"
                    detail={`No samples, ${rangeLabel}`}
                />
            </div>

            <HistoryCard
                title="CPU and memory"
                description={`Average and peak usage, ${rangeLabel}.`}
                icon={Cpu}
            />

            <div className="grid gap-4 lg:grid-cols-2">
                <HistoryCard
                    title="Network"
                    description={`Traffic in and out, ${rangeLabel}.`}
                    icon={Network}
                />
                <HistoryCard
                    title="Disk I/O"
                    description={`Reads and writes, ${rangeLabel}.`}
                    icon={HardDrive}
                />
            </div>

            <HistoryCard
                title="Containers"
                description={`State changes, restarts and resource use, ${rangeLabel}.`}
                icon={Boxes}
            />
        </div>
    );
}

/**
 * Explains why the daemon has not reported anything, when it has not.
 */
function DaemonNotice({
    service,
    daemon,
}: {
    service: Service;
    daemon: Daemon;
}) {
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

    if (daemon.last_seen_at === null) {
        return (
            <Alert>
                <Plug />
                <AlertTitle>Waiting for Influx Daemon</AlertTitle>
                <AlertDescription>
                    <p>
                        Once Influx Daemon is running on{' '}
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
        ['Daemon version', agent.version],
        ['Last contact', lastSeenAt ? formatRelative(lastSeenAt) : 'Never'],
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
                            <dd className="truncate">{value}</dd>
                        </div>
                    ))}
                </dl>
            </CardContent>
        </Card>
    );
}

/**
 * A chart of stored samples. The panel does not store any yet, so this is a placeholder.
 */
function HistoryCard({
    title,
    description,
    icon,
}: {
    title: string;
    description: string;
    icon: LucideIcon;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                <EmptyState icon={icon} title="No samples yet">
                    This chart fills in once the panel stores samples pulled
                    from Influx Daemon.
                </EmptyState>
            </CardContent>
        </Card>
    );
}
