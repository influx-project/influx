import { CircleCheck, CircleX, Gauge, ShieldCheck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    formatDateTime,
    formatDuration,
    formatLatency,
    formatRelative,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ServiceAlert } from '@/types';
import { EmptyState } from './empty-state';

const kinds: Record<
    ServiceAlert['kind'],
    { icon: LucideIcon; severity: string; className: string }
> = {
    down: {
        icon: CircleX,
        severity: 'Critical',
        className: 'text-red-600 dark:text-red-400',
    },
    slow: {
        icon: Gauge,
        severity: 'Warning',
        className: 'text-amber-600 dark:text-amber-400',
    },
    recovered: {
        icon: CircleCheck,
        severity: 'Resolved',
        className: 'text-emerald-600 dark:text-emerald-400',
    },
};

function describe(alert: ServiceAlert): { title: string; detail: string } {
    switch (alert.kind) {
        case 'down':
            return {
                title:
                    alert.ended_at === null
                        ? 'Service is down'
                        : 'Service went down',
                detail: [
                    alert.cause ?? 'The check failed',
                    `${alert.checks} failed ${alert.checks === 1 ? 'check' : 'checks'}`,
                ].join(' · '),
            };
        case 'recovered':
            return {
                title: 'Service recovered',
                detail: `Back up after ${formatDuration(alert.duration_seconds)} of downtime`,
            };
        case 'slow': {
            const seconds =
                (new Date(alert.ended_at).getTime() -
                    new Date(alert.at).getTime()) /
                1000;

            return {
                title: 'Slow responses',
                detail: `${alert.checks} slow ${alert.checks === 1 ? 'check' : 'checks'}${seconds > 0 ? ` over ${formatDuration(seconds)}` : ''}, peaking at ${formatLatency(alert.peak_latency_ms)}`,
            };
        }
    }
}

/**
 * Outages, recoveries and slow spells, newest first.
 */
export function AlertsFeed({ events }: { events: ServiceAlert[] }) {
    if (events.length === 0) {
        return (
            <EmptyState icon={ShieldCheck} title="Nothing to report">
                No outages or slow responses in this period.
            </EmptyState>
        );
    }

    return (
        <ol className="divide-y">
            {events.map((alert) => {
                const { icon: Icon, severity, className } = kinds[alert.kind];
                const { title, detail } = describe(alert);

                return (
                    <li
                        key={alert.id}
                        className="flex gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <Icon
                            className={cn('mt-0.5 size-5 shrink-0', className)}
                            aria-hidden
                        />
                        <div className="min-w-0 flex-1 space-y-0.5">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="font-medium">{title}</p>
                                <Badge variant="outline">{severity}</Badge>
                            </div>
                            <p className="text-sm break-words text-muted-foreground">
                                {detail}
                            </p>
                        </div>
                        <time
                            dateTime={alert.at}
                            title={formatDateTime(alert.at)}
                            className="shrink-0 text-right text-xs text-muted-foreground"
                        >
                            {formatRelative(alert.at)}
                            <br />
                            <span className="hidden sm:inline">
                                {formatDateTime(alert.at)}
                            </span>
                        </time>
                    </li>
                );
            })}
        </ol>
    );
}
