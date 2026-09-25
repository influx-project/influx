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
    useTweenedNumber,
} from '@/hooks/use-animation-frame';
import { formatLatency } from '@/lib/format';
import { LIVE_CACHE_MS } from '@/lib/live-check-cache';
import { cn } from '@/lib/utils';
import type { LiveCheck } from '@/types';
import { ChartTooltipBox } from './chart-tooltip';

/*
 * Rendering notes: everything here is plain SVG, redrawn in real pixel coordinates.
 * There are deliberately no CSS transforms, transitions, `will-change` or CSS masks,
 * which would hand the drawing to the GPU compositor as a bitmap to stretch and shift,
 * blurring it. Instead each frame sets an SVG `transform` attribute to a pixel-snapped
 * offset, so the browser repaints the (small) chart sharply every time.
 */

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
const CHART_PADDING = 8;
const HEARTBEAT_HEIGHT = 20;

/** Widths of the faded edges, where results dissolve as they leave and ease in as they arrive. */
const FADE_LEFT_PX = 32;
const FADE_RIGHT_PX = 12;

/**
 * Round a length to whole device pixels, so edges land on the pixel grid rather than
 * being smeared across two pixels.
 */
function snap(px: number): number {
    const ratio = window.devicePixelRatio || 1;

    return Math.round(px * ratio) / ratio;
}

/** How far the track has scrolled: time `t` is drawn at `offset + (t - anchor) * pxPerMs`. */
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
 * Split the successful checks into runs of pixel points, breaking wherever they are
 * far apart or a check failed, and trace each run as a line and a filled area.
 */
function segments(
    checks: LiveCheck[],
    x: (time: number) => number,
    y: (latency: number) => number,
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
            run.push([x(time), y(check.latency_ms)]);
        }

        previousTime = time;
    }

    if (run.length > 0) {
        runs.push(run);
    }

    const baseline = y(0);

    return runs.map((points) => {
        const line = monotonePath(points);
        const first = points[0][0];
        const last = points.at(-1)![0];

        return {
            line,
            area: `${line}L${last},${baseline}L${first},${baseline}Z`,
        };
    });
}

/**
 * An SVG mask fading the left and right edges, so content scrolling past dissolves.
 */
function EdgeFade({
    id,
    width,
    height,
}: {
    id: string;
    width: number;
    height: number;
}) {
    const left = width > 0 ? Math.min(FADE_LEFT_PX / width, 0.5) : 0;
    const right = width > 0 ? 1 - Math.min(FADE_RIGHT_PX / width, 0.5) : 1;

    return (
        <>
            <linearGradient id={`${id}-fade`} x1="0" x2="1" y1="0" y2="0">
                <stop offset={0} stopColor="#000" />
                <stop offset={left} stopColor="#fff" />
                <stop offset={right} stopColor="#fff" />
                <stop offset={1} stopColor="#000" />
            </linearGradient>
            <mask id={id} maskUnits="userSpaceOnUse">
                <rect width={width} height={height} fill={`url(#${id}-fade)`} />
            </mask>
        </>
    );
}

/**
 * Response time of each live check, scrolling smoothly right to left over the cache window.
 *
 * Paths are rebuilt when checks arrive, and for a moment while the scale eases to a new
 * maximum; otherwise each frame only moves the track.
 */
export function LiveLatencyChart({ checks }: { checks: LiveCheck[] }) {
    const container = useRef<HTMLDivElement>(null);
    const track = useRef<SVGGElement>(null);
    const width = useElementWidth(container);
    const [anchor] = useState(() => Date.now());
    const [hovered, setHovered] = useState<{
        check: LiveCheck;
        left: number;
    } | null>(null);
    const id = useId();
    const offset = useRef(0);

    const pxPerMs = width / LIVE_CACHE_MS;
    const plotHeight = CHART_HEIGHT - CHART_PADDING * 2;
    const successful = checks.filter(({ latency_ms }) => latency_ms !== null);
    const targetMax = niceMax(
        Math.max(10, ...successful.map(({ latency_ms }) => latency_ms!)) * 1.15,
    );
    // Eased in JS, redrawing real coordinates, rather than stretching a bitmap with CSS.
    const yMax = useTweenedNumber(targetMax, 600) ?? targetMax;
    const latest = successful.at(-1);

    const paths = useMemo(
        () =>
            segments(
                checks,
                (time) => (time - anchor) * pxPerMs,
                (latency) =>
                    CHART_HEIGHT -
                    CHART_PADDING -
                    (latency / yMax) * plotHeight,
            ),
        [checks, anchor, pxPerMs, yMax, plotHeight],
    );

    useAnimationFrame((now) => {
        offset.current = snap(trackOffset(width, anchor, now));
        track.current?.setAttribute(
            'transform',
            `translate(${offset.current} 0)`,
        );
    });

    const pointAt = (check: LiveCheck) => ({
        x: (new Date(check.checked_at).getTime() - anchor) * pxPerMs,
        y:
            CHART_HEIGHT -
            CHART_PADDING -
            ((check.latency_ms ?? 0) / yMax) * plotHeight,
    });

    const onPointerMove = (event: PointerEvent<HTMLDivElement>) => {
        const bounds = event.currentTarget.getBoundingClientRect();
        const left = event.clientX - bounds.left;
        const time = anchor + (left - offset.current) / pxPerMs;
        let nearest: LiveCheck | null = null;
        let nearestDistance = SLOT_MS;

        for (const check of successful) {
            const distance = Math.abs(
                new Date(check.checked_at).getTime() - time,
            );

            if (distance < nearestDistance) {
                nearest = check;
                nearestDistance = distance;
            }
        }

        setHovered(nearest ? { check: nearest, left } : null);
    };

    const hoveredPoint = hovered ? pointAt(hovered.check) : null;

    return (
        <div className="flex gap-2">
            <div
                className="flex w-14 shrink-0 flex-col justify-between py-1 text-right text-xs text-muted-foreground tabular-nums"
                aria-hidden
            >
                <span>{formatLatency(targetMax)}</span>
                <span>{formatLatency(targetMax / 2)}</span>
                <span>0 ms</span>
            </div>

            <div
                ref={container}
                className="relative min-w-0 flex-1 overflow-hidden"
                style={{ height: CHART_HEIGHT }}
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
                    className="block"
                    aria-hidden
                >
                    <defs>
                        <linearGradient
                            id={`${id}-area`}
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
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
                        <EdgeFade
                            id={`${id}-mask`}
                            width={width}
                            height={CHART_HEIGHT}
                        />
                    </defs>

                    {[0, 0.5, 1].map((fraction) => (
                        <line
                            key={fraction}
                            x1={0}
                            x2={width}
                            y1={CHART_PADDING + plotHeight * fraction}
                            y2={CHART_PADDING + plotHeight * fraction}
                            className="stroke-border"
                            strokeDasharray={fraction === 1 ? undefined : '3 3'}
                            shapeRendering="crispEdges"
                        />
                    ))}

                    <g mask={`url(#${id}-mask)`}>
                        <g ref={track}>
                            {paths.map(({ area, line }, index) => (
                                <g key={index}>
                                    <path d={area} fill={`url(#${id}-area)`} />
                                    <path
                                        d={line}
                                        fill="none"
                                        stroke="var(--chart-1)"
                                        strokeWidth={2}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                </g>
                            ))}

                            {latest && <HeadMarker {...pointAt(latest)} />}

                            {hoveredPoint && (
                                <g className="pointer-events-none">
                                    <line
                                        x1={hoveredPoint.x}
                                        x2={hoveredPoint.x}
                                        y1={CHART_PADDING}
                                        y2={CHART_HEIGHT - CHART_PADDING}
                                        className="stroke-muted-foreground"
                                        strokeDasharray="3 3"
                                    />
                                    <circle
                                        cx={hoveredPoint.x}
                                        cy={hoveredPoint.y}
                                        r={4}
                                        className="fill-background"
                                        stroke="var(--chart-1)"
                                        strokeWidth={2}
                                    />
                                </g>
                            )}
                        </g>
                    </g>
                </svg>

                {hovered && (
                    <div
                        className="pointer-events-none absolute top-0 z-10"
                        style={
                            hovered.left > width / 2
                                ? { right: width - hovered.left + 12 }
                                : { left: hovered.left + 12 }
                        }
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
 * A softly pulsing dot on the latest result. The pulse animates opacity only, since
 * animating a scale would hand the dot to the compositor and blur it.
 */
function HeadMarker({ x, y }: { x: number; y: number }) {
    return (
        <g className="pointer-events-none">
            <circle
                cx={x}
                cy={y}
                r={7}
                fill="var(--chart-1)"
                fillOpacity={0.25}
                className="motion-safe:animate-pulse"
            />
            <circle
                cx={x}
                cy={y}
                r={3.5}
                fill="var(--chart-1)"
                className="stroke-background"
                strokeWidth={1.5}
            />
        </g>
    );
}

/**
 * One slot per four seconds over the cache window, green when up, red when down and
 * grey when there was no result, scrolling smoothly right to left.
 */
export function LiveHeartbeat({ checks }: { checks: LiveCheck[] }) {
    const container = useRef<HTMLDivElement>(null);
    const track = useRef<SVGGElement>(null);
    const width = useElementWidth(container);
    const [anchor] = useState(() => Date.now());
    const id = useId();
    // The first slot in view; changes once every four seconds as a slot scrolls off.
    const [firstSlot, setFirstSlot] = useState(() =>
        slotStart(anchor - DISPLAY_DELAY_MS - LIVE_CACHE_MS),
    );

    const pxPerMs = width / LIVE_CACHE_MS;

    useAnimationFrame((now) => {
        track.current?.setAttribute(
            'transform',
            `translate(${snap(trackOffset(width, anchor, now))} 0)`,
        );

        const first = slotStart(now - DISPLAY_DELAY_MS - LIVE_CACHE_MS);

        if (first !== firstSlot) {
            setFirstSlot(first);
        }
    });

    const bySlot = new Map<number, LiveCheck>();

    for (const check of checks) {
        bySlot.set(slotStart(new Date(check.checked_at).getTime()), check);
    }

    // Every slot in view plus one either side, with edges on whole device pixels so
    // they stay sharp, and a one-device-pixel gap between them.
    const gap = width > 0 ? snap(1) : 1;
    const slots = Array.from(
        { length: LIVE_CACHE_MS / SLOT_MS + 2 },
        (_, index) => {
            const start = firstSlot + index * SLOT_MS;
            const left = snap((start - anchor) * pxPerMs);
            const right = snap((start + SLOT_MS - anchor) * pxPerMs);

            return {
                start,
                left,
                width: Math.max(right - left - gap, gap),
                check: bySlot.get(start),
            };
        },
    );

    return (
        <div className="space-y-1.5">
            <div
                ref={container}
                className="overflow-hidden"
                style={{ height: HEARTBEAT_HEIGHT }}
            >
                <svg
                    width={width}
                    height={HEARTBEAT_HEIGHT}
                    className="block"
                    role="img"
                    aria-label={`${checks.length} live checks in the last 5 minutes, ${checks.filter(({ successful }) => !successful).length} failed`}
                >
                    <defs>
                        <EdgeFade
                            id={`${id}-mask`}
                            width={width}
                            height={HEARTBEAT_HEIGHT}
                        />
                    </defs>
                    <g mask={`url(#${id}-mask)`}>
                        <g ref={track}>
                            {slots.map(
                                ({ start, left, width: slotWidth, check }) =>
                                    check ? (
                                        <Tooltip key={start}>
                                            <TooltipTrigger asChild>
                                                <rect
                                                    x={left}
                                                    width={slotWidth}
                                                    height={HEARTBEAT_HEIGHT}
                                                    shapeRendering="crispEdges"
                                                    className={cn(
                                                        check.successful
                                                            ? 'fill-emerald-500'
                                                            : 'fill-red-500',
                                                    )}
                                                />
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                {new Date(
                                                    check.checked_at,
                                                ).toLocaleTimeString()}{' '}
                                                ·{' '}
                                                {check.successful
                                                    ? 'Up'
                                                    : 'Down'}{' '}
                                                ·{' '}
                                                {formatLatency(
                                                    check.latency_ms,
                                                )}
                                            </TooltipContent>
                                        </Tooltip>
                                    ) : (
                                        <rect
                                            key={start}
                                            x={left}
                                            width={slotWidth}
                                            height={HEARTBEAT_HEIGHT}
                                            shapeRendering="crispEdges"
                                            className="fill-muted"
                                        />
                                    ),
                            )}
                        </g>
                    </g>
                </svg>
            </div>
            <div className="flex justify-between text-xs text-muted-foreground">
                <span>5 min ago</span>
                <span>Now</span>
            </div>
        </div>
    );
}

/**
 * The start of the four-second slot the time falls in, aligned to the clock.
 */
function slotStart(time: number): number {
    return Math.floor(time / SLOT_MS) * SLOT_MS;
}
