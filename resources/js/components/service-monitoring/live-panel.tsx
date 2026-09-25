import { Link } from '@inertiajs/react';
import { useConnectionStatus, useEcho } from '@laravel/echo-react';
import { CircleCheck, CircleX, History, Loader, WifiOff } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTweenedNumber } from '@/hooks/use-animation-frame';
import { useLiveUpdatesPreference } from '@/hooks/use-live-updates';
import {
    readLiveChecks,
    withinCacheWindow,
    writeLiveChecks,
} from '@/lib/live-check-cache';
import { formatLatency, formatRelative } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { LiveCheck, Service, ServiceStatus } from '@/types';
import type { RouteDefinition } from '@/wayfinder';
import { LiveBadge } from './live-badge';
import { LiveHeartbeat, LiveLatencyChart } from './live-charts';

/** Seconds without a live result before the stream is considered stalled. */
const STALLED_AFTER = 20;

/**
 * Streams live check results while the viewer has live updates switched on.
 */
export function LivePanel({
    service,
    status,
    settingsHref,
}: {
    service: Service;
    status: ServiceStatus;
    settingsHref: RouteDefinition<'get'>;
}) {
    const [preferred, setPreferred] = useLiveUpdatesPreference();
    const available =
        service.enabled &&
        service.stream_metrics &&
        status.state !== 'unsupported';
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
                        Live status
                        <LiveBadge live={live} />
                    </CardTitle>
                    <CardDescription>
                        {live
                            ? 'Checked every few seconds while you watch. Live results are not saved.'
                            : 'Showing historical data from background checks only.'}
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
                    <LiveStream key={service.id} service={service} />
                ) : (
                    <LiveOffMessage
                        service={service}
                        status={status}
                        available={available}
                        settingsHref={settingsHref}
                    />
                )}
            </CardContent>
        </Card>
    );
}

function LiveOffMessage({
    service,
    status,
    available,
    settingsHref,
}: {
    service: Service;
    status: ServiceStatus;
    available: boolean;
    settingsHref: RouteDefinition<'get'>;
}) {
    if (available) {
        return (
            <p className="text-sm text-muted-foreground">
                Live updates are switched off in this browser. Switch them on to
                watch checks as they happen.
            </p>
        );
    }

    if (status.state === 'unsupported') {
        return (
            <p className="text-sm text-muted-foreground">
                Live updates aren't available for {service.type_label} services
                yet.
            </p>
        );
    }

    return (
        <p className="text-sm text-muted-foreground">
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
        </p>
    );
}

/**
 * Subscribes to the service's live channel for as long as it is mounted, starting from
 * the checks cached the last time the viewer watched it.
 */
function LiveStream({ service }: { service: Service }) {
    const [received, setReceived] = useState<LiveCheck[]>(() =>
        readLiveChecks(service.id),
    );
    const [subscribedAt] = useState(() => Date.now());
    const now = useNow();
    const connection = useConnectionStatus();

    useEcho<LiveCheck>(
        `services.${service.id}.live`,
        '.check',
        (check) =>
            setReceived((previous) =>
                withinCacheWindow([...previous, check], Date.now()),
            ),
        [service.id],
    );

    useEffect(() => {
        writeLiveChecks(service.id, received);
    }, [service.id, received]);

    const checks = withinCacheWindow(received, now);
    const latest = checks.at(-1);
    const shownLatency = useTweenedNumber(latest?.latency_ms ?? null);
    const latestAt = latest ? new Date(latest.checked_at).getTime() : null;
    // Cached from before this visit, and nothing new has arrived yet.
    const resuming = latestAt !== null && latestAt < subscribedAt;
    const stalled =
        connection === 'connected' &&
        now - Math.max(latestAt ?? 0, subscribedAt) > STALLED_AFTER * 1000;

    return (
        <div className="grid gap-6 md:grid-cols-[minmax(0,16rem)_minmax(0,1fr)]">
            <div className="space-y-3" aria-live="polite">
                <ConnectionState connection={connection} stalled={stalled} />

                {resuming && !stalled && (
                    <p className="flex items-start gap-2 text-sm text-muted-foreground">
                        <History
                            className="mt-0.5 size-4 shrink-0"
                            aria-hidden
                        />
                        Showing results from before you left. New results will
                        follow in a few seconds.
                    </p>
                )}

                {latest ? (
                    <>
                        <div className="flex items-center gap-2">
                            {latest.successful ? (
                                <CircleCheck
                                    className="size-6 text-emerald-600 dark:text-emerald-400"
                                    aria-hidden
                                />
                            ) : (
                                <CircleX
                                    className="size-6 text-red-600 dark:text-red-400"
                                    aria-hidden
                                />
                            )}
                            <span className="text-2xl font-semibold tracking-tight">
                                {latest.successful ? 'Up' : 'Down'}
                            </span>
                            <span className="ml-auto text-2xl font-semibold tracking-tight tabular-nums">
                                {formatLatency(shownLatency)}
                            </span>
                        </div>
                        <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            <dt className="text-muted-foreground">Updated</dt>
                            <dd className="tabular-nums">
                                <time dateTime={latest.checked_at}>
                                    {formatRelative(latest.checked_at, now)}
                                </time>
                            </dd>
                            {latest.status_code !== null && (
                                <>
                                    <dt className="text-muted-foreground">
                                        Status code
                                    </dt>
                                    <dd className="font-mono">
                                        {latest.status_code}
                                    </dd>
                                </>
                            )}
                            {latest.error && (
                                <>
                                    <dt className="text-muted-foreground">
                                        Error
                                    </dt>
                                    <dd className="break-words">
                                        {latest.error}
                                    </dd>
                                </>
                            )}
                        </dl>
                    </>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Waiting for the first live check…
                    </p>
                )}
            </div>

            <div className="min-w-0 space-y-3">
                <LiveLatencyChart checks={checks} />
                <LiveHeartbeat checks={checks} />
            </div>
        </div>
    );
}

function ConnectionState({
    connection,
    stalled,
}: {
    connection: ReturnType<typeof useConnectionStatus>;
    stalled: boolean;
}) {
    if (connection === 'connected' && !stalled) {
        return null;
    }

    const message =
        connection === 'connecting'
            ? 'Connecting to the live stream…'
            : connection === 'reconnecting'
              ? 'Connection lost, reconnecting…'
              : connection === 'connected'
                ? 'No live results lately. The scheduler or queue worker may not be running.'
                : 'Can’t reach the live stream. Is the WebSocket server running?';

    const Icon =
        connection === 'connecting' || connection === 'reconnecting'
            ? Loader
            : WifiOff;

    return (
        <p className="flex items-start gap-2 text-sm text-muted-foreground">
            <Icon
                className={cn(
                    'mt-0.5 size-4 shrink-0',
                    Icon === Loader && 'motion-safe:animate-spin',
                )}
                aria-hidden
            />
            {message}
        </p>
    );
}

/**
 * The current time, updated every second so relative times keep counting.
 */
function useNow(): number {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(timer);
    }, []);

    return now;
}
