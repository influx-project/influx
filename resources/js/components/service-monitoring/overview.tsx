import { Activity, CircleAlert, Gauge, ListChecks } from 'lucide-react';
import { serviceStates } from '@/components/service-status';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    formatLatency,
    formatRelative,
    formatSeconds,
    formatUptime,
} from '@/lib/format';
import type {
    Service,
    ServiceOverview as Overview,
    ServiceStatus,
    TimeRangeOption,
    TimeRangeValue,
} from '@/types';
import type { RouteDefinition } from '@/wayfinder';
import { ChecksChart } from './checks-chart';
import { CheckResult } from './check-result';
import { CollectionNotice } from './collection-notice';
import { LatencyChart } from './latency-chart';
import { HistoricalBadge } from './live-badge';
import { LivePanel } from './live-panel';
import { RecentChecks } from './recent-checks';
import { StatCard } from './stat-card';
import { StatusCodes } from './status-codes';

/**
 * The overview tab: live status, then headline numbers, charts and recent checks from background checks.
 */
export function ServiceOverview({
    service,
    status,
    overview,
    range,
    ranges,
    settingsHref,
}: {
    service: Service;
    status: ServiceStatus;
    overview: Overview;
    range: TimeRangeValue;
    ranges: TimeRangeOption[];
    settingsHref: RouteDefinition<'get'>;
}) {
    const { stats, series } = overview;
    const rangeLabel =
        ranges.find(({ value }) => value === range)?.label.toLowerCase() ??
        range;
    const spansDays = range !== '24h';
    const state = serviceStates[status.state];

    return (
        <div className="flex flex-col gap-4">
            <CollectionNotice service={service} status={status} />

            <LivePanel
                service={service}
                status={status}
                settingsHref={settingsHref}
            />

            <div className="flex flex-wrap items-center justify-between gap-2 pt-2">
                <h2 className="text-base font-semibold tracking-tight">
                    History
                </h2>
                <HistoricalBadge
                    detail={
                        service.collect_metrics
                            ? `background checks every ${formatSeconds(service.check_interval)}`
                            : undefined
                    }
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Current status"
                    icon={state.icon}
                    value={state.label}
                    detail={
                        status.since
                            ? `Since ${formatRelative(status.since)}`
                            : undefined
                    }
                />
                <StatCard
                    label="Uptime"
                    icon={Activity}
                    value={formatUptime(stats.uptime)}
                    detail={`${stats.incidents} ${stats.incidents === 1 ? 'incident' : 'incidents'}, ${rangeLabel}`}
                />
                <StatCard
                    label="Average response"
                    icon={Gauge}
                    value={formatLatency(stats.average_latency_ms)}
                    detail={
                        stats.p95_latency_ms !== null
                            ? `95th percentile ${formatLatency(stats.p95_latency_ms)}`
                            : undefined
                    }
                />
                <StatCard
                    label="Checks"
                    icon={ListChecks}
                    value={stats.checks.toLocaleString()}
                    detail={`${stats.failed_checks.toLocaleString()} failed`}
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Response time</CardTitle>
                    <CardDescription>
                        Average and slowest successful response, {rangeLabel}.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <LatencyChart series={series} spansDays={spansDays} />
                </CardContent>
            </Card>

            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Checks</CardTitle>
                        <CardDescription>
                            Successful and failed checks, {rangeLabel}.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ChecksChart series={series} spansDays={spansDays} />
                    </CardContent>
                </Card>

                {overview.status_codes !== null ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Status codes</CardTitle>
                            <CardDescription>
                                HTTP responses, {rangeLabel}.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <StatusCodes codes={overview.status_codes} />
                        </CardContent>
                    </Card>
                ) : (
                    <LastCheckCard status={status} />
                )}
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Recent checks</CardTitle>
                    <CardDescription>The last 10 results.</CardDescription>
                </CardHeader>
                <CardContent>
                    <RecentChecks
                        checks={overview.recent_checks}
                        showStatusCode={overview.status_codes !== null}
                    />
                </CardContent>
            </Card>
        </div>
    );
}

/**
 * The latest check in detail, for services without status codes to break down.
 */
function LastCheckCard({ status }: { status: ServiceStatus }) {
    const check = status.last_check;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Last check</CardTitle>
                <CardDescription>The most recent result.</CardDescription>
            </CardHeader>
            <CardContent>
                {check === null ? (
                    <p className="text-sm text-muted-foreground">
                        No checks have run yet.
                    </p>
                ) : (
                    <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <dt className="text-muted-foreground">Result</dt>
                        <dd>
                            <CheckResult check={check} />
                        </dd>
                        <dt className="text-muted-foreground">Checked</dt>
                        <dd>{formatRelative(check.checked_at)}</dd>
                        <dt className="text-muted-foreground">Response time</dt>
                        <dd className="tabular-nums">
                            {formatLatency(check.latency_ms)}
                        </dd>
                        {check.error && (
                            <>
                                <dt className="flex items-center gap-1.5 text-muted-foreground">
                                    <CircleAlert
                                        className="size-3.5"
                                        aria-hidden
                                    />
                                    Error
                                </dt>
                                <dd className="break-words">{check.error}</dd>
                            </>
                        )}
                    </dl>
                )}
            </CardContent>
        </Card>
    );
}
