import type { LiveCheck } from '@/types';

/** How long live checks are kept for, so returning to a service shows the last few minutes. */
export const LIVE_CACHE_MS = 5 * 60 * 1000;

const STORAGE_PREFIX = 'live-checks:';

/**
 * Checks per service. Module state survives Inertia page visits; the sessionStorage
 * mirror also survives full reloads within the tab.
 */
const memory = new Map<number, LiveCheck[]>();

/**
 * Drop checks older than the cache window.
 */
export function withinCacheWindow(
    checks: LiveCheck[],
    now: number = Date.now(),
): LiveCheck[] {
    return checks.filter(
        ({ checked_at }) =>
            now - new Date(checked_at).getTime() <= LIVE_CACHE_MS,
    );
}

/**
 * Get the service's live checks from the last five minutes, oldest first.
 */
export function readLiveChecks(serviceId: number): LiveCheck[] {
    const cached = memory.get(serviceId) ?? readStorage(serviceId);

    return withinCacheWindow(cached);
}

/**
 * Remember the service's live checks, keeping only the last five minutes.
 */
export function writeLiveChecks(serviceId: number, checks: LiveCheck[]): void {
    const kept = withinCacheWindow(checks);
    memory.set(serviceId, kept);

    try {
        window.sessionStorage.setItem(
            STORAGE_PREFIX + serviceId,
            JSON.stringify(kept),
        );
    } catch {
        // Storage can be full or unavailable; the in-memory copy still covers page visits.
    }
}

function readStorage(serviceId: number): LiveCheck[] {
    try {
        const stored = window.sessionStorage.getItem(
            STORAGE_PREFIX + serviceId,
        );
        const parsed: unknown = stored ? JSON.parse(stored) : [];

        return Array.isArray(parsed) ? (parsed as LiveCheck[]) : [];
    } catch {
        return [];
    }
}
