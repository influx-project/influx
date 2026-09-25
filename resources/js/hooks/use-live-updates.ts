import { useCallback, useState } from 'react';

const STORAGE_KEY = 'live-updates';

function readPreference(): boolean {
    try {
        return window.localStorage.getItem(STORAGE_KEY) !== 'off';
    } catch {
        return true;
    }
}

/**
 * Whether the viewer wants live updates on service pages. On unless they switched it off,
 * remembered in this browser only.
 */
export function useLiveUpdatesPreference(): [
    boolean,
    (enabled: boolean) => void,
] {
    const [enabled, setEnabled] = useState(() =>
        typeof window === 'undefined' ? true : readPreference(),
    );

    const update = useCallback((value: boolean) => {
        setEnabled(value);

        try {
            window.localStorage.setItem(STORAGE_KEY, value ? 'on' : 'off');
        } catch {
            // Storage can be unavailable, e.g. in private windows; the choice still applies until reload.
        }
    }, []);

    return [enabled, update];
}
