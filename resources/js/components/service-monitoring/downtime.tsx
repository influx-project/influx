import { Clock, Hourglass, Siren, Timer } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDuration, formatUptime } from '@/lib/format';
import type {
    Incident,
    Paginated,
    Service,
    ServiceDowntime as Downtime,
    ServiceStatus,
} from '@/types';
import { CollectionNotice } from './collection-notice';
import { IncidentsTable } from './incidents-table';
import { StatCard } from './stat-card';
import { UptimeHistory } from './uptime-history';

const windows = [
    ['24h', 'Last 24 hours'],
    ['7d', 'Last 7 days'],
    ['30d', 'Last 30 days'],
    ['90d', 'Last 90 days'],
] as const;

/**
 * The downtime tab: uptime over several windows, a daily history and every incident.
 */
export function ServiceDowntime({
    service,
    status,
    downtime,
    incidents,
}: {
    service: Service;
    status: ServiceStatus;
    downtime: Downtime;
    incidents: Paginated<Incident>;
}) {
    const { stats } = downtime;

    return (
        <div className="flex flex-col gap-4">
            <CollectionNotice service={service} status={status} />

            <Card>
                <CardHeader>
                    <CardTitle>Uptime</CardTitle>
                    <CardDescription>
                        The share of time the service was up.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        {windows.map(([key, label]) => (
                            <div key={key} className="space-y-1">
                                <dt className="text-sm text-muted-foreground">
                                    {label}
                                </dt>
                                <dd className="text-2xl font-semibold tracking-tight tabular-nums">
                                    {formatUptime(downtime.uptime[key])}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    <UptimeHistory days={downtime.daily} />
                </CardContent>
            </Card>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Incidents"
                    icon={Siren}
                    value={stats.incidents.toLocaleString()}
                    detail="Last 30 days"
                />
                <StatCard
                    label="Total downtime"
                    icon={Clock}
                    value={formatDuration(stats.downtime_seconds)}
                    detail="Last 30 days"
                />
                <StatCard
                    label="Mean time to recovery"
                    icon={Timer}
                    value={
                        stats.mean_time_to_recovery_seconds === null
                            ? '—'
                            : formatDuration(
                                  stats.mean_time_to_recovery_seconds,
                              )
                    }
                    detail="Resolved incidents, last 30 days"
                />
                <StatCard
                    label="Longest incident"
                    icon={Hourglass}
                    value={
                        stats.longest_seconds === null
                            ? '—'
                            : formatDuration(stats.longest_seconds)
                    }
                    detail="Last 30 days"
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Incidents</CardTitle>
                    <CardDescription>
                        An incident starts at the first failed check and ends at
                        the next successful one.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <IncidentsTable incidents={incidents} />
                </CardContent>
            </Card>
        </div>
    );
}
