import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatDuration, formatUptime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { ServiceDailyUptime } from '@/types';

function tone(day: ServiceDailyUptime): string {
    if (day.uptime === null) {
        return 'bg-muted';
    }

    if (day.downtime_seconds === 0) {
        return 'bg-emerald-500';
    }

    return day.uptime >= 99 ? 'bg-amber-500' : 'bg-red-500';
}

function describe(day: ServiceDailyUptime): string {
    if (day.uptime === null) {
        return 'No data';
    }

    if (day.downtime_seconds === 0) {
        return 'No downtime';
    }

    return `${formatDuration(day.downtime_seconds)} down across ${day.incidents} ${day.incidents === 1 ? 'incident' : 'incidents'}`;
}

/**
 * One bar per day, colored by how much downtime the day had.
 */
export function UptimeHistory({ days }: { days: ServiceDailyUptime[] }) {
    return (
        <div className="space-y-3">
            <ol
                className="flex h-10 items-stretch gap-px sm:gap-0.5"
                aria-label={`Daily uptime for the last ${days.length} days`}
            >
                {days.map((day) => {
                    const date = new Date(`${day.date}T00:00:00`);
                    const label = date.toLocaleDateString(undefined, {
                        weekday: 'short',
                        month: 'short',
                        day: 'numeric',
                    });

                    return (
                        <li key={day.date} className="flex min-w-0 flex-1">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <button
                                        type="button"
                                        className={cn(
                                            'flex-1 rounded-[2px] transition-opacity hover:opacity-75 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                            tone(day),
                                        )}
                                        aria-label={`${label}: ${formatUptime(day.uptime)} uptime, ${describe(day)}`}
                                    />
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p className="font-medium">{label}</p>
                                    <p>
                                        {formatUptime(day.uptime)} uptime ·{' '}
                                        {describe(day)}
                                    </p>
                                </TooltipContent>
                            </Tooltip>
                        </li>
                    );
                })}
            </ol>

            <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 text-xs text-muted-foreground">
                <span>{days.length} days ago</span>
                <ul className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    {[
                        ['bg-emerald-500', 'No downtime'],
                        ['bg-amber-500', 'Under 1%'],
                        ['bg-red-500', '1% or more'],
                        ['bg-muted', 'No data'],
                    ].map(([className, label]) => (
                        <li key={label} className="flex items-center gap-1.5">
                            <span
                                className={cn(
                                    'size-2.5 rounded-[2px]',
                                    className,
                                )}
                                aria-hidden
                            />
                            {label}
                        </li>
                    ))}
                </ul>
                <span>Today</span>
            </div>
        </div>
    );
}
