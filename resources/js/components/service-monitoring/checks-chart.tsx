import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { formatDateTime } from '@/lib/format';
import type { ServiceSeriesPoint } from '@/types';
import { ChartTooltipBox, formatAxisTime } from './chart-tooltip';

const config = {
    successful: { label: 'Successful', color: 'var(--color-emerald-500)' },
    failed: { label: 'Failed', color: 'var(--color-red-500)' },
} satisfies ChartConfig;

/**
 * Successful and failed checks per bucket, stacked.
 */
export function ChecksChart({
    series,
    spansDays,
}: {
    series: ServiceSeriesPoint[];
    spansDays: boolean;
}) {
    return (
        <ChartContainer config={config} className="aspect-auto h-56 w-full">
            <BarChart
                data={series}
                margin={{ left: 4, right: 12, top: 8 }}
                barCategoryGap={1}
            >
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
                    width={40}
                    allowDecimals={false}
                />
                <ChartTooltip
                    cursor={{ fill: 'var(--muted)', opacity: 0.5 }}
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
                                        label: 'Successful',
                                        value: point.successful,
                                        color: 'var(--color-successful)',
                                    },
                                    {
                                        label: 'Failed',
                                        value: point.failed,
                                        color: 'var(--color-failed)',
                                    },
                                ]}
                            />
                        );
                    }}
                />
                <ChartLegend content={<ChartLegendContent />} />
                <Bar
                    dataKey="successful"
                    stackId="checks"
                    fill="var(--color-successful)"
                    isAnimationActive={false}
                />
                <Bar
                    dataKey="failed"
                    stackId="checks"
                    fill="var(--color-failed)"
                    radius={[2, 2, 0, 0]}
                    isAnimationActive={false}
                />
            </BarChart>
        </ChartContainer>
    );
}
