import { Link } from '@inertiajs/react';
import { useConnectionStatus, useEcho } from '@laravel/echo-react';
import { Loader, WifiOff } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useLiveUpdatesPreference } from '@/hooks/use-live-updates';
import {
    formatBytes,
    formatDuration,
    formatPercent,
    formatRate,
    formatRelative,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import type {
    DaemonContainer,
    DaemonContainerState,
    DaemonSample,
    Service,
} from '@/types';
import type { RouteDefinition } from '@/wayfinder';
import { LiveBadge } from './live-badge';

/**
 * Streams the host and container metrics the panel pulls from Influx Daemon while the
 * viewer has live updates switched on.
 */
export function DaemonLivePanel({
    service,
    settingsHref,
}: {
    service: Service;
    settingsHref: RouteDefinition<'get'>;
}) {
    const [preferred, setPreferred] = useLiveUpdatesPreference();
    const available = service.enabled && service.stream_metrics;
    const live = available && preferred;

    return (
        <Card
            className={cn(
                'transition-colors',
                live && 'border-red-500/30 dark:border-red-500/25',
            )}
        >
            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-4">
                <div className="space-y-1.5">
                    <CardTitle className="flex items-center gap-2">
                        Live metrics
                        <LiveBadge live={live} />
                    </CardTitle>
                    <CardDescription>
                        {live
                            ? 'Pulled from the daemon every few seconds while you watch. Live samples are not saved.'
                            : 'Showing historical data from stored samples only.'}
                    </CardDescription>
                </div>
                <div className="flex items-center gap-2">
                    <Switch
                        id="live-updates"
                        checked={live}
                        disabled={!available}
                        onCheckedChange={setPreferred}
                    />
                    <Label htmlFor="live-updates">Live updates</Label>
                </div>
            </CardHeader>
            <CardContent>
                {live ? (
                    <DaemonLiveStream key={service.id} service={service} />
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {available ? (
                            'Live updates are switched off in this browser. Switch them on to watch the host as it happens.'
                        ) : (
                            <>
                                {service.enabled
                                    ? 'Live updates are switched off for this service.'
                                    : 'Monitoring is paused for this service.'}{' '}
                                <Link
                                    href={settingsHref}
                                    className="font-medium text-foreground underline underline-offset-4"
                                >
                                    Change this in Settings
                                </Link>
                                .
                            </>
                        )}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

/**
 * Subscribes to the service's live channel for as long as it is mounted, showing the
 * latest sample broadcast as a `daemon` event.
 */
function DaemonLiveStream({ service }: { service: Service }) {
    const [sample, setSample] = useState<DaemonSample | null>(null);
    const connection = useConnectionStatus();

    useEcho<DaemonSample>(`services.${service.id}.live`, '.daemon', setSample, [
        service.id,
    ]);

    if (connection !== 'connected') {
        return <ConnectionState connection={connection} />;
    }

    if (sample === null) {
        return (
            <p className="text-sm text-muted-foreground" aria-live="polite">
                Waiting for the first sample from Influx Daemon…
            </p>
        );
    }

    const { cpu, memory, disk_io, network } = sample;

    return (
        <div className="flex flex-col gap-6" aria-live="polite">
            <p className="text-sm text-muted-foreground">
                Sampled{' '}
                <time dateTime={sample.collected_at}>
                    {formatRelative(sample.collected_at)}
                </time>{' '}
                · Host up {formatDuration(sample.uptime_seconds)}
            </p>

            <div className="grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
                <Reading
                    label="CPU"
                    value={formatPercent(cpu.usage_percent)}
                    detail={`Load ${[cpu.load_1, cpu.load_5, cpu.load_15].map((load) => load.toFixed(2)).join(' · ')}`}
                    usage={cpu.usage_percent}
                />
                <Reading
                    label="Memory"
                    value={formatPercent(
                        percentOf(memory.used_bytes, memory.total_bytes),
                    )}
                    detail={`${formatBytes(memory.used_bytes)} of ${formatBytes(memory.total_bytes)}${
                        memory.swap_total_bytes > 0
                            ? ` · swap ${formatBytes(memory.swap_used_bytes)}`
                            : ''
                    }`}
                    usage={percentOf(memory.used_bytes, memory.total_bytes)}
                />
                <Reading
                    label="Network in"
                    value={formatRate(network.rx_bytes_per_second)}
                    detail={`Out ${formatRate(network.tx_bytes_per_second)}`}
                />
                <Reading
                    label="Disk reads"
                    value={formatRate(disk_io.read_bytes_per_second)}
                    detail={`Writes ${formatRate(disk_io.write_bytes_per_second)}`}
                />
            </div>

            {sample.disks.length > 0 && (
                <section className="space-y-3">
                    <h3 className="text-sm font-medium">Disks</h3>
                    <div className="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                        {sample.disks.map((disk) => (
                            <Reading
                                key={disk.mount}
                                label={
                                    <span className="font-mono text-xs">
                                        {disk.mount}
                                    </span>
                                }
                                value={formatPercent(
                                    percentOf(
                                        disk.used_bytes,
                                        disk.total_bytes,
                                    ),
                                )}
                                detail={`${formatBytes(disk.used_bytes)} of ${formatBytes(disk.total_bytes)} · ${disk.filesystem}`}
                                usage={percentOf(
                                    disk.used_bytes,
                                    disk.total_bytes,
                                )}
                                compact
                            />
                        ))}
                    </div>
                </section>
            )}

            <section className="space-y-3">
                <h3 className="text-sm font-medium">Containers</h3>
                {sample.containers === null ? (
                    <p className="text-sm text-muted-foreground">
                        The daemon can't see a container runtime on this host.
                    </p>
                ) : (
                    <Containers containers={sample.containers} />
                )}
            </section>
        </div>
    );
}

/**
 * One live reading, with a usage bar when it is a share of a whole.
 */
function Reading({
    label,
    value,
    detail,
    usage,
    compact = false,
}: {
    label: ReactNode;
    value: string;
    detail: string;
    usage?: number | null;
    compact?: boolean;
}) {
    return (
        <div className="min-w-0 space-y-1.5">
            <div className="flex items-baseline justify-between gap-2">
                <span className="truncate text-sm text-muted-foreground">
                    {label}
                </span>
                <span
                    className={cn(
                        'font-semibold tracking-tight tabular-nums',
                        compact ? 'text-sm' : 'text-2xl',
                    )}
                >
                    {value}
                </span>
            </div>
            {usage !== undefined && (
                <Progress
                    value={usage === null ? 0 : Math.min(usage, 100)}
                    aria-label={typeof label === 'string' ? label : undefined}
                />
            )}
            <p className="truncate text-xs text-muted-foreground tabular-nums">
                {detail}
            </p>
        </div>
    );
}

const stateStyles: Record<DaemonContainerState, string> = {
    running: 'border-emerald-500/40 text-emerald-700 dark:text-emerald-400',
    restarting: 'border-amber-500/40 text-amber-700 dark:text-amber-400',
    paused: 'text-muted-foreground',
    created: 'text-muted-foreground',
    exited: 'border-red-500/40 text-red-700 dark:text-red-400',
    dead: 'border-red-500/40 text-red-700 dark:text-red-400',
};

function Containers({ containers }: { containers: DaemonContainer[] }) {
    if (containers.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No containers on this host.
            </p>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>State</TableHead>
                    <TableHead className="text-right">CPU</TableHead>
                    <TableHead className="text-right">Memory</TableHead>
                    <TableHead className="hidden text-right sm:table-cell">
                        Restarts
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {containers.map((container) => (
                    <TableRow key={container.id}>
                        <TableCell className="max-w-36 sm:max-w-64">
                            <p className="truncate font-medium">
                                {container.name}
                            </p>
                            <p className="truncate font-mono text-xs text-muted-foreground">
                                {container.image}
                            </p>
                        </TableCell>
                        <TableCell>
                            <div className="flex flex-wrap gap-1">
                                <Badge
                                    variant="outline"
                                    className={cn(
                                        'capitalize',
                                        stateStyles[container.state],
                                    )}
                                >
                                    {container.state}
                                </Badge>
                                {container.health === 'unhealthy' && (
                                    <Badge
                                        variant="outline"
                                        className={stateStyles.dead}
                                    >
                                        Unhealthy
                                    </Badge>
                                )}
                            </div>
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {formatPercent(container.cpu_percent)}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {formatBytes(container.memory_used_bytes)}
                        </TableCell>
                        <TableCell className="hidden text-right tabular-nums sm:table-cell">
                            {container.restart_count}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function ConnectionState({
    connection,
}: {
    connection: ReturnType<typeof useConnectionStatus>;
}) {
    const connecting =
        connection === 'connecting' || connection === 'reconnecting';
    const Icon = connecting ? Loader : WifiOff;

    return (
        <p
            className="flex items-start gap-2 text-sm text-muted-foreground"
            aria-live="polite"
        >
            <Icon
                className={cn(
                    'mt-0.5 size-4 shrink-0',
                    connecting && 'motion-safe:animate-spin',
                )}
                aria-hidden
            />
            {connection === 'connecting'
                ? 'Connecting to the live stream…'
                : connection === 'reconnecting'
                  ? 'Connection lost, reconnecting…'
                  : 'Can’t reach the live stream. Is the WebSocket server running?'}
        </p>
    );
}

function percentOf(used: number, total: number): number | null {
    return total > 0 ? (used / total) * 100 : null;
}
