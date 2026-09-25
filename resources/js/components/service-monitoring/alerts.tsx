import { CircleX, Gauge, Siren } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatLatency } from '@/lib/format';
import type { Service, ServiceAlerts as Alerts, ServiceStatus } from '@/types';
import { AlertsFeed } from './alerts-feed';
import { CollectionNotice } from './collection-notice';
import { StatCard } from './stat-card';

/**
 * The alerts tab: outages, recoveries and slow spells derived from the service's checks.
 */
export function ServiceAlerts({
    service,
    status,
    alerts,
}: {
    service: Service;
    status: ServiceStatus;
    alerts: Alerts;
}) {
    const { events } = alerts;
    const outages = events.filter(({ kind }) => kind === 'down').length;
    const slow = events.filter(({ kind }) => kind === 'slow').length;

    return (
        <div className="flex flex-col gap-4">
            <CollectionNotice service={service} status={status} />

            <div className="grid gap-4 sm:grid-cols-3">
                <StatCard
                    label="Active"
                    icon={Siren}
                    value={status.state === 'down' ? 1 : 0}
                    detail={
                        status.state === 'down'
                            ? 'The service is down'
                            : 'Nothing needs attention'
                    }
                />
                <StatCard
                    label="Outages"
                    icon={CircleX}
                    value={outages}
                    detail={`Last ${alerts.days} days`}
                />
                <StatCard
                    label="Slow periods"
                    icon={Gauge}
                    value={slow}
                    detail={`Responses over ${formatLatency(alerts.slow_threshold_ms)}`}
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Activity</CardTitle>
                    <CardDescription>
                        Raised automatically from background checks over the
                        last {alerts.days} days. Responses slower than half the
                        timeout ({formatLatency(alerts.slow_threshold_ms)})
                        count as slow.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <AlertsFeed events={events} />
                </CardContent>
            </Card>
        </div>
    );
}
