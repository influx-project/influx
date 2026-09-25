import { useId, useMemo, useRef, useState } from 'react';
import type { PointerEvent } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    useAnimationFrame,
    useElementWidth,
} from '@/hooks/use-animation-frame';
import { formatLatency } from '@/lib/format';
import { LIVE_CACHE_MS } from '@/lib/live-check-cache';
import { cn } from '@/lib/utils';
import type { LiveCheck } from '@/types';
import { ChartTooltipBox } from './chart-tooltip';

/**
 * How far behind real time the live visuals run. Results arrive shortly after the time
 * they were taken, so running a little behind lets them glide in from the right edge
 * rather than pop into view already on screen.
 */
const DISPLAY_DELAY_MS = 2500;

/** Width of each heartbeat tick, matching the roughly four-second live cadence. */
export const SLOT_MS = 4000;

/** Checks further apart than this have a gap between them, e.g. while the viewer was away. */
const GAP_MS = 15_000;

const CHART_HEIGHT = 112;

/** Fades the edges, so results dissolve as they leave and ease in as they arrive. */
const EDGE_MASK = {
    maskImage:
        'linear-gradient(to right, transparent, #000 32px, #000 calc(100% - 12px), transparent)',
};
const CHART_PADDING = 8;

/** Where time `t` sits on screen, given how far the track has scrolled. */
function trackOffset(width: number, anchor: number, now: number): number {
    return width - ((now - DISPLAY_DELAY_MS - anchor) * width) / LIVE_CACHE_MS;
}

/**
 * Round up to a tidy axis maximum (1, 2, 2.5 or 5 times a power of ten), so the
 * scale only changes when latency moves meaningfully.
 */
function niceMax(value: number): number {
    const magnitude = 10 ** Math.floor(Math.log10(value));

    for (const step of [1, 2, 2.5, 5, 10]) {
        if (value <= step * magnitude) {
            return step * magnitude;
        }
    }

    return 10 * magnitude;
}

/**
 * A monotone cubic curve through the points (Fritsch–Carlson), which never overshoots
 * between them, like d3's curveMonotoneX.
 */
function monotonePath(points: [number, number][]): string {
    const n = points.length;

    if (n === 0) {
        return '';
    }

    if (n === 1) {
        return `M${points[0][0]},${points[0][1]}h0`;
    }

    const dx: number[] = [];
    const slopes: number[] = [];

    for (let i = 0; i < n - 1; i++) {
        dx[i] = points[i + 1][0] - points[i][0] || 1e-6;
        slopes[i] = (points[i + 1][1] - points[i][1]) / dx[i];
    }

    const tangents = [slopes[0]];

    for (let i = 1; i < n - 1; i++) {
        tangents[i] =
            slopes[i - 1] * slopes[i] <= 0
                ? 0
                : (3 * (dx[i - 1] + dx[i])) /
                  ((2 * dx[i] + dx[i - 1]) / slopes[i - 1] +
                      (dx[i] + 2 * dx[i - 1]) / slopes[i]);
    }

    tangents[n - 1] = slopes[n - 2];

    let path = `M${points[0][0]},${points[0][1]}`;

    for (let i = 0; i < n - 1; i++) {
        const third = dx[i] / 3;
        path += `C${points[i][0] + third},${points[i][1] + tangents[i] * third},${points[i + 1][0] - third},${points[i + 1][1] - tangents[i + 1] * third},${points[i + 1][0]},${points[i + 1][1]}`;
    }

    return path;
}

/**
 * Split the successful checks into runs, breaking wherever they are far apart or a check failed.
 */
function segments(
    checks: LiveCheck[],
    anchor: number,
    pxPerMs: number,
): { line: string; area: string }[] {
    const runs: [number, number][][] = [];
    let run: [number, number][] = [];
    let previousTime: number | null = null;

    for (const check of checks) {
        const time = new Date(check.checked_at).getTime();
        const broken =
            check.latency_ms === null ||
            (previousTime !== null && time - previousTime > GAP_MS);

        if (broken && run.length > 0) {
            runs.push(run);
            run = [];
        }

        if (check.latency_ms !== null) {
            run.push([(time - anchor) * pxPerMs, check.latency_ms]);
        }

        previousTime = time;
    }

    if (run.length > 0) {
        runs.push(run);
    }

    return runs.map((points) => {
        const line = monotonePath(points);
        const first = points[0][0];
        const last = points.at(-1)![0];

        return { line, area: `${line}L${last},0L${first},0Z` };
    });
}

/**
 * Response time of each live check, scrolling smoothly right to left over the cache window.
 *
 * Paths are rebuilt only when checks arrive; each frame just moves the track, and a new
 * scale eases in with a CSS transition while `non-scaling-stroke` keeps the line crisp.
 */
export function LiveLatencyChart({ checks }: { checks: LiveCheck[] }) {
    const container = useRef<HTMLDivElement>(null);
    const track = useRef<HTMLDivElement>(null);
    const width = useElementWidth(container);
    const [anchor] = useState(() => Date.now());
    const [hovered, setHovered] = useState<{
        check: LiveCheck;
        left: number;
    } | null>(null);
    const gradientId = useId();
    const offset = useRef(0);

    const pxPerMs = width / LIVE_CACHE_MS;
    const plotHeight = CHART_HEIGHT - CHART_PADDING * 2;
    const successful = checks.filter(({ latency_ms }) => latency_ms !== null);
    const yMax = niceMax(
        Math.max(10, ...successful.map(({ latency_ms }) => latency_ms!)) * 1.15,
    );
    const yScale = plotHeight / yMax;
    const paths = useMemo(
        () => segments(checks, anchor, pxPerMs),
        [checks, anchor, pxPerMs],
    );
    const latest = successful.at(-1);

    useAnimationFrame((now) => {
        offset.current = trackOffset(width, anchor, now);

        // An HTML layer moved with translate3d is scrolled by the compositor, without repainting the SVG.
        if (track.current) {
            track.current.style.transform = `translate3d(${offset.current}px, 0, 0)`;
        }
    });

    const pointAt = (check: LiveCheck) => ({
        x: (new Date(check.checked_at).getTime() - anchor) * pxPerMs,
        y: CHART_HEIGHT - CHART_PADDING - (check.latency_ms ?? 0) * yScale,
    });

    const onPointerMove = (event: PointerEvent<HTMLDivElement>) => {
        const bounds = event.currentTarget.getBoundingClientRect();
        const left = event.clientX - bounds.left;
        const time = anchor + (left - offset.current) / pxPerMs;
        let nearest: LiveCheck | null = null;

        for (const check of successful) {
            const distance = Math.abs(
                new Date(check.checked_at).getTime() - time,
            );

            if (
                distance < SLOT_MS &&
                (!nearest ||
                    distance <
                        Math.abs(new Date(nearest.checked_at).getTime() - time))
            ) {
                nearest = check;
            }
        }

        setHovered(nearest ? { check: nearest, left } : null);
    };

    // Scale the plot so y is latency: flip it and stretch it, easing whenever the scale changes.
    const scaleStyle = {
        transform: `translateY(${CHART_HEIGHT - CHART_PADDING}px) scaleY(${-yScale})`,
        transition: 'transform 600ms cubic-bezier(0.22, 1, 0.36, 1)',
    };

    return (
        <div className="flex gap-2">
            <div
                className="flex w-14 shrink-0 flex-col justify-between py-1 text-right text-xs text-muted-foreground tabular-nums"
                aria-hidden
            >
                <span key={yMax} className="animate-in duration-300 fade-in">
                    {formatLatency(yMax)}
                </span>
                <span
                    key={`mid-${yMax}`}
                    className="animate-in duration-300 fade-in"
                >
                    {formatLatency(yMax / 2)}
                </span>
                <span>0 ms</span>
            </div>

            <div
                ref={container}
                className="relative min-w-0 flex-1 overflow-hidden"
                style={{ height: CHART_HEIGHT, ...EDGE_MASK }}
                onPointerMove={onPointerMove}
                onPointerLeave={() => setHovered(null)}
                role="img"
                aria-label={
                    latest
                        ? `Live response time, latest ${formatLatency(latest.latency_ms)}`
                        : 'Live response time, no results yet'
                }
            >
                <svg
                    width={width}
                    height={CHART_HEIGHT}
                    className="absolute inset-0 block"
                    aria-hidden
                >
                    {[0, 0.5, 1].map((fraction) => (
                        <line
                            key={fraction}
                            x1={0}
                            x2={width}
                            y1={CHART_PADDING + plotHeight * fraction}
                            y2={CHART_PADDING + plotHeight * fraction}
                            className="stroke-border"
                            strokeDasharray={fraction === 1 ? undefined : '3 3'}
                        />
                    ))}
                </svg>

                <div
                    ref={track}
                    className="absolute inset-0 will-change-transform"
                >
                    <svg
                        width={width}
                        height={CHART_HEIGHT}
                        className="block overflow-visible"
                        aria-hidden
                    >
                        <defs>
                            <linearGradient
                                id={gradientId}
                                x1="0"
                                y1="1"
                                x2="0"
                                y2="0"
                            >
                                <stop
                                    offset="0%"
                                    stopColor="var(--chart-1)"
                                    stopOpacity={0.22}
                                />
                                <stop
                                    offset="100%"
                                    stopColor="var(--chart-1)"
                                    stopOpacity={0}
                                />
                            </linearGradient>
                        </defs>

                        <g style={scaleStyle}>
                            {paths.map(({ area, line }) => (
                                <g key={line}>
                                    <path
                                        d={area}
                                        fill={`url(#${gradientId})`}
                                    />
                                    <path
                                        d={line}
                                        fill="none"
                                        stroke="var(--chart-1)"
                                        strokeWidth={2}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        vectorEffect="non-scaling-stroke"
                                    />
                                </g>
                            ))}
                        </g>

                        {latest && <HeadMarker {...pointAt(latest)} />}

                        {hovered && (
                            <g className="pointer-events-none">
                                <line
                                    x1={pointAt(hovered.check).x}
                                    x2={pointAt(hovered.check).x}
                                    y1={CHART_PADDING}
                                    y2={CHART_HEIGHT - CHART_PADDING}
                                    className="stroke-muted-foreground"
                                    strokeDasharray="3 3"
                                />
                                <circle
                                    cx={pointAt(hovered.check).x}
                                    cy={pointAt(hovered.check).y}
                                    r={4}
                                    className="fill-background stroke-(--chart-1)"
                                    strokeWidth={2}
                                />
                            </g>
                        )}
                    </svg>
                </div>

                {hovered && (
                    <div
                        className="pointer-events-none absolute top-0 z-10"
                        style={{
                            left: hovered.left,
                            transform: `translateX(${hovered.left > width / 2 ? 'calc(-100% - 12px)' : '12px'})`,
                        }}
                    >
                        <ChartTooltipBox
                            title={new Date(
                                hovered.check.checked_at,
                            ).toLocaleTimeString()}
                            rows={[
                                {
                                    label: 'Response time',
                                    value: formatLatency(
                                        hovered.check.latency_ms,
                                    ),
                                    color: 'var(--chart-1)',
                                },
                                ...(hovered.check.status_code !== null
                                    ? [
                                          {
                                              label: 'Status',
                                              value: hovered.check.status_code,
                                          },
                                      ]
                                    : []),
                            ]}
                        />
                    </div>
                )}
            </div>
        </div>
    );
}

/**
 * A pulsing dot on the latest result, gliding to each new one.
 */
function HeadMarker({ x, y }: { x: number; y: number }) {
    return (
        <g
            className="pointer-events-none"
            style={{
                transform: `translate(${x}px, ${y}px)`,
                transition: 'transform 600ms cubic-bezier(0.22, 1, 0.36, 1)',
            }}
        >
            <circle
                r={7}
                className="fill-(--chart-1) opacity-25 motion-safe:animate-ping"
                style={{ transformBox: 'fill-box', transformOrigin: 'center' }}
            />
            <circle
                r={3.5}
                className="fill-(--chart-1) stroke-background"
                strokeWidth={1.5}
            />
        </g>
    );
}

/**
 * One tick per four seconds over the cache window, green when up and red when down,
 * scrolling smoothly right to left over a track of empty slots.
 */
export function LiveHeartbeat({ checks }: { checks: LiveCheck[] }) {
    const container = useRef<HTMLDivElement>(null);
    const track = useRef<HTMLDivElement>(null);
    const width = useElementWidth(container);
    const [anchor] = useState(() => Date.now());

    const pxPerMs = width / LIVE_CACHE_MS;
    const slotPx = SLOT_MS * pxPerMs;

    // One tick per slot, aligned to the clock so ticks and empty slots line up.
    const ticks = new Map<number, LiveCheck>();

    for (const check of checks) {
        const time = new Date(check.checked_at).getTime();
        ticks.set(Math.floor(time / SLOT_MS) * SLOT_MS, check);
    }

    useAnimationFrame((now) => {
        const offset = trackOffset(width, anchor, now);

        if (track.current) {
            track.current.style.transform = `translate3d(${offset}px, 0, 0)`;
        }

        // Scroll the empty slots with the ticks: slot boundaries fall on multiples of SLOT_MS.
        if (container.current && slotPx > 0) {
            const boundary = offset - anchor * pxPerMs;
            container.current.style.backgroundPositionX = `${((boundary % slotPx) + slotPx) % slotPx}px`;
        }
    });

    return (
        <div className="space-y-1.5">
            <div
                ref={container}
                className="relative h-5 overflow-hidden"
                style={{
                    ...EDGE_MASK,
                    backgroundImage:
                        'linear-gradient(to right, var(--muted) calc(100% - 1px), transparent calc(100% - 1px))',
                    backgroundSize: `${slotPx}px 100%`,
                }}
                role="list"
                aria-label={`${checks.length} live checks in the last 5 minutes`}
            >
                <div
                    ref={track}
                    className="absolute inset-y-0 left-0 will-change-transform"
                >
                    {[...ticks].map(([slot, check]) => (
                        <Tooltip key={slot}>
                            <TooltipTrigger asChild>
                                <span
                                    role="listitem"
                                    className={cn(
                                        'absolute inset-y-0 animate-in rounded-[1px] duration-500 fade-in',
                                        check.successful
                                            ? 'bg-emerald-500'
                                            : 'bg-red-500',
                                    )}
                                    style={{
                                        left: (slot - anchor) * pxPerMs,
                                        width: Math.max(slotPx - 1, 1),
                                    }}
                                    aria-label={`${new Date(check.checked_at).toLocaleTimeString()}: ${check.successful ? 'up' : 'down'}`}
                                />
                            </TooltipTrigger>
                            <TooltipContent>
                                {new Date(
                                    check.checked_at,
                                ).toLocaleTimeString()}{' '}
                                · {check.successful ? 'Up' : 'Down'} ·{' '}
                                {formatLatency(check.latency_ms)}
                            </TooltipContent>
                        </Tooltip>
                    ))}
                </div>
            </div>
            <div className="flex justify-between text-xs text-muted-foreground">
                <span>5 min ago</span>
                <span>Now</span>
            </div>
        </div>
    );
}
