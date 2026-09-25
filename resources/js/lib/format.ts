/**
 * Format a timestamp as a medium date and short time in the viewer's locale.
 */
export function formatDateTime(value: string): string {
    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

/**
 * Format a whole number of seconds as its largest exact unit, e.g. `5 min`.
 */
export function formatSeconds(seconds: number): string {
    if (seconds % 3600 === 0) {
        return `${seconds / 3600} h`;
    }

    if (seconds % 60 === 0) {
        return `${seconds / 60} min`;
    }

    return `${seconds} s`;
}

/**
 * Format a length of time using its two largest units, e.g. `2h 5m` or `45s`.
 */
export function formatDuration(seconds: number): string {
    const units: [number, string][] = [
        [86400, 'd'],
        [3600, 'h'],
        [60, 'm'],
        [1, 's'],
    ];

    const parts: string[] = [];
    let remaining = Math.max(0, Math.round(seconds));

    for (const [size, label] of units) {
        if (remaining >= size || (parts.length === 0 && size === 1)) {
            parts.push(`${Math.floor(remaining / size)}${label}`);
            remaining %= size;
        }

        if (parts.length === 2) {
            break;
        }
    }

    return parts.join(' ');
}

/**
 * Format a latency in milliseconds, switching to seconds from one second up.
 */
export function formatLatency(ms: number | null): string {
    if (ms === null) {
        return '—';
    }

    if (ms >= 1000) {
        return `${(ms / 1000).toFixed(2)} s`;
    }

    return `${ms < 10 ? ms.toFixed(1) : Math.round(ms)} ms`;
}

/**
 * Format an uptime percentage, keeping enough precision that 99.95% does not round to 100%.
 */
export function formatUptime(uptime: number | null): string {
    if (uptime === null) {
        return '—';
    }

    if (uptime === 100) {
        return '100%';
    }

    return `${Math.min(uptime, 99.99).toFixed(uptime >= 99 ? 2 : 1)}%`;
}

const relativeFormatter = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

/**
 * Format a timestamp relative to now, e.g. `3 minutes ago` or `in 20 seconds`.
 */
export function formatRelative(
    value: string,
    now: number = Date.now(),
): string {
    const seconds = Math.round((new Date(value).getTime() - now) / 1000);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relativeFormatter.format(Math.round(seconds / size), unit);
        }
    }

    return relativeFormatter.format(seconds, 'second');
}
