import type { ReactNode } from 'react';

/**
 * The box a chart's hover tooltip renders in, matching the shadcn chart tooltip.
 */
export function ChartTooltipBox({
    title,
    rows,
}: {
    title: ReactNode;
    rows: { label: string; value: ReactNode; color?: string }[];
}) {
    return (
        <div className="grid min-w-40 gap-1.5 rounded-lg border border-border/50 bg-background px-2.5 py-1.5 text-xs shadow-xl">
            <p className="font-medium">{title}</p>
            {rows.map(({ label, value, color }) => (
                <div key={label} className="flex items-center gap-2">
                    {color && (
                        <span
                            className="size-2.5 shrink-0 rounded-[2px]"
                            style={{ backgroundColor: color }}
                            aria-hidden
                        />
                    )}
                    <span className="text-muted-foreground">{label}</span>
                    <span className="ml-auto font-mono font-medium text-foreground tabular-nums">
                        {value}
                    </span>
                </div>
            ))}
        </div>
    );
}

/**
 * Format a bucket's start time for a chart axis: times for a day, dates beyond that.
 */
export function formatAxisTime(value: string, spansDays: boolean): string {
    const date = new Date(value);

    return spansDays
        ? date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
        : date.toLocaleTimeString(undefined, {
              hour: '2-digit',
              minute: '2-digit',
          });
}
