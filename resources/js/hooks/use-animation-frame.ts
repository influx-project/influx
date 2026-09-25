import { useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';

const REDUCED_MOTION = '(prefers-reduced-motion: reduce)';

function prefersReducedMotion(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia(REDUCED_MOTION).matches
    );
}

/**
 * Call `onFrame` with the current time on every animation frame, or once a second
 * when the viewer prefers reduced motion. Frames pause while the tab is hidden.
 *
 * The callback should write to the DOM directly rather than set state, so animating
 * does not re-render React 60+ times a second.
 */
export function useAnimationFrame(onFrame: (now: number) => void): void {
    const callback = useRef(onFrame);

    useEffect(() => {
        callback.current = onFrame;
    });

    useEffect(() => {
        if (prefersReducedMotion()) {
            callback.current(Date.now());
            const timer = window.setInterval(
                () => callback.current(Date.now()),
                1000,
            );

            return () => window.clearInterval(timer);
        }

        let frame = 0;
        const tick = () => {
            callback.current(Date.now());
            frame = window.requestAnimationFrame(tick);
        };
        frame = window.requestAnimationFrame(tick);

        return () => window.cancelAnimationFrame(frame);
    }, []);
}

/**
 * The element's width in CSS pixels, kept up to date as it resizes.
 */
export function useElementWidth(ref: RefObject<HTMLElement | null>): number {
    const [width, setWidth] = useState(0);

    useEffect(() => {
        const element = ref.current;

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(([entry]) =>
            setWidth(entry.contentRect.width),
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, [ref]);

    return width;
}

/**
 * Ease from the previous value to `target` over `duration` milliseconds, jumping
 * straight there when the viewer prefers reduced motion.
 */
export function useTweenedNumber(
    target: number | null,
    duration = 450,
): number | null {
    const [value, setValue] = useState(target);
    const current = useRef(target);

    useEffect(() => {
        const from = current.current;

        if (target === null || from === null || prefersReducedMotion()) {
            current.current = target;
            setValue(target);

            return;
        }

        const start = performance.now();
        let frame = 0;

        const tick = (time: number) => {
            const progress = Math.min(1, (time - start) / duration);
            // Ease out cubic: quick to respond, gentle to settle.
            const eased = 1 - (1 - progress) ** 3;
            current.current = from + (target - from) * eased;
            setValue(current.current);

            if (progress < 1) {
                frame = window.requestAnimationFrame(tick);
            }
        };
        frame = window.requestAnimationFrame(tick);

        return () => window.cancelAnimationFrame(frame);
    }, [target, duration]);

    return value;
}
