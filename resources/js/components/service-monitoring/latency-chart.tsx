import { Area, AreaChart, CartesianGrid, Line, XAxis, YAxis } from 'recharts';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { formatDateTime, formatLatency } from '@/lib/format';
import type { ServiceSeriesPoint } from '@/types';
import { ChartTooltipBox, formatAxisTime } from './chart-tooltip';

const config = {
    average_latency_ms: { label: 'Average', color: 'var(--chart-1)' },
    max_latency_ms: { label: 'Slowest', color: 'var(--muted-foreground)' },
} satisfies ChartConfig;

/**
 * Average and slowest response time per bucket, with gaps where nothing succeeded.
 */
export function LatencyChart({
    series,
    spansDays,
}: {
    series: ServiceSeriesPoint[];
    spansDays: boolean;
}) {
    return (
        <ChartContainer config={config} className="aspect-auto h-64 w-full">
            <AreaChart data={series} margin={{ left: 4, right: 12, top: 8 }}>
                <defs>
                    <linearGradient
                        id="latency-fill"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop
                            offset="0%"
                            stopColor="var(--color-average_latency_ms)"
                            stopOpacity={0.25}
                        />
                        <stop
                            offset="100%"
                            stopColor="var(--color-average_latency_ms)"
                            stopOpacity={0.02}
                        />
                    </linearGradient>
                </defs>
                <CartesianGrid vertical={false} strokeOpacity={0.5} />
                <XAxis
                    dataKey="time"
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                    minTickGap={48}
                    tickFormatter={(value: string) =>
                        formatAxisTime(value, spansDays)
                    }
                />
                <YAxis
                    tickLine={false}
                    axisLine={false}
                    width={56}
                    tickFormatter={(value: number) => formatLatency(value)}
                />
                <ChartTooltip
                    cursor={{ strokeDasharray: '3 3' }}
                    content={({ active, payload }) => {
                        const point = payload?.[0]?.payload as
                            | ServiceSeriesPoint
                            | undefined;

                        if (!active || !point) {
                            return null;
                        }

                        return (
                            <ChartTooltipBox
                                title={formatDateTime(point.time)}
                                rows={[
                                    {
                                        label: 'Average',
                                        value: formatLatency(
                                            point.average_latency_ms,
                                        ),
                                        color: 'var(--color-average_latency_ms)',
                                    },
                                    {
                                        label: 'Slowest',
                                        value: formatLatency(
                                            point.max_latency_ms,
                                        ),
                                        color: 'var(--color-max_latency_ms)',
                                    },
                                    {
                                        label: 'Checks',
                                        value: point.checks,
                                    },
                                ]}
                            />
                        );
                    }}
                />
                <ChartLegend content={<ChartLegendContent />} />
                <Area
                    dataKey="average_latency_ms"
                    type="monotone"
                    stroke="var(--color-average_latency_ms)"
                    strokeWidth={2}
                    fill="url(#latency-fill)"
                    connectNulls={false}
                    isAnimationActive={false}
                />
                <Line
                    dataKey="max_latency_ms"
                    type="monotone"
                    stroke="var(--color-max_latency_ms)"
                    strokeWidth={1.5}
                    strokeDasharray="4 4"
                    dot={false}
                    connectNulls={false}
                    isAnimationActive={false}
                />
            </AreaChart>
        </ChartContainer>
    );
}
