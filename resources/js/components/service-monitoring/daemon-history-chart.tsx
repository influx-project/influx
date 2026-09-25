import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { formatDateTime } from '@/lib/format';
import type { DaemonSeriesPoint } from '@/types';
import { ChartTooltipBox, formatAxisTime } from './chart-tooltip';

type SeriesKey = Exclude<keyof DaemonSeriesPoint, 'time'>;

/**
 * The two series colors, validated as a pair against each theme's surface for
 * contrast and colorblind separation. Dark mode uses its own steps rather than
 * the light ones, which are too dim against the dark surface there.
 */
export const daemonSeriesColors = {
    first: { light: 'var(--chart-1)', dark: 'var(--chart-4)' },
    second: { light: 'var(--chart-2)', dark: 'var(--chart-5)' },
    /** Reserved for bad states, such as unhealthy containers. */
    critical: { light: 'var(--destructive)', dark: 'var(--destructive)' },
} satisfies Record<string, { light: string; dark: string }>;

export type DaemonChartSeries = {
    key: SeriesKey;
    label: string;
    color: keyof typeof daemonSeriesColors;
};

/**
 * A line chart of up to two same-unit series from a daemon's history, with gaps
 * where no samples were stored, a legend and a hover tooltip.
 */
export function DaemonHistoryChart({
    series,
    lines,
    format,
    spansDays,
    maxValue,
    stepped = false,
    extraRows,
}: {
    series: DaemonSeriesPoint[];
    lines: DaemonChartSeries[];
    /** Formats values of this chart's unit, for the axis and tooltip. */
    format: (value: number | null) => string;
    spansDays: boolean;
    /** Fixes the top of the axis, e.g. 100 for percentages. */
    maxValue?: number;
    /** Draws steps rather than curves, for whole-number counts. */
    stepped?: boolean;
    /** Further figures to show in the tooltip, such as a peak. */
    extraRows?: (
        point: DaemonSeriesPoint,
    ) => { label: string; value: string }[];
}) {
    const config = Object.fromEntries(
        lines.map(({ key, label, color }) => [
            key,
            { label, theme: daemonSeriesColors[color] },
        ]),
    ) satisfies ChartConfig;

    return (
        <ChartContainer config={config} className="aspect-auto h-56 w-full">
            <LineChart data={series} margin={{ left: 4, right: 12, top: 8 }}>
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
                    width={72}
                    domain={[0, maxValue ?? 'auto']}
                    allowDecimals={false}
                    tickFormatter={(value: number) => format(value)}
                />
                <ChartTooltip
                    cursor={{ strokeDasharray: '3 3' }}
                    content={({ active, label }) => {
                        // Looked up by time, since the payload leaves out empty
                        // values and would be empty for a bucket with no samples.
                        const point = series.find(({ time }) => time === label);

                        if (!active || !point) {
                            return null;
                        }

                        return (
                            <ChartTooltipBox
                                title={formatDateTime(point.time)}
                                rows={[
                                    ...lines.map(({ key, label }) => ({
                                        label,
                                        value: format(
                                            point[key] as number | null,
                                        ),
                                        color: `var(--color-${key})`,
                                    })),
                                    ...(extraRows?.(point) ?? []),
                                ]}
                            />
                        );
                    }}
                />
                <ChartLegend content={<ChartLegendContent />} />
                {lines.map(({ key }) => (
                    <Line
                        key={key}
                        dataKey={key}
                        type={stepped ? 'stepAfter' : 'monotone'}
                        stroke={`var(--color-${key})`}
                        strokeWidth={2}
                        dot={(props: DotProps) => (
                            <IsolatedDot
                                key={`${key}-${props.index}`}
                                {...props}
                                series={series}
                                dataKey={key}
                            />
                        )}
                        activeDot={{ r: 4 }}
                        connectNulls={false}
                        isAnimationActive={false}
                    />
                ))}
            </LineChart>
        </ChartContainer>
    );
}

type DotProps = { cx?: number; cy?: number; index: number; stroke?: string };

/**
 * Marks a value with no neighbours, which a line alone cannot show: e.g. the first
 * few minutes of samples, or a bucket between two gaps.
 */
function IsolatedDot({
    cx,
    cy,
    index,
    stroke,
    series,
    dataKey,
}: DotProps & { series: DaemonSeriesPoint[]; dataKey: SeriesKey }) {
    const isolated =
        series[index]?.[dataKey] != null &&
        series[index - 1]?.[dataKey] == null &&
        series[index + 1]?.[dataKey] == null;

    if (!isolated || cx === undefined || cy === undefined) {
        return <g />;
    }

    return <circle cx={cx} cy={cy} r={3} fill={stroke} />;
}
