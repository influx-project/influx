import { CircleCheck, CircleX } from 'lucide-react';
import type { ServiceCheck } from '@/types';

/**
 * Whether a check succeeded, with an icon and label.
 */
export function CheckResult({ check }: { check: ServiceCheck }) {
    return check.successful ? (
        <span className="inline-flex items-center gap-1.5">
            <CircleCheck
                className="size-4 text-emerald-600 dark:text-emerald-400"
                aria-hidden
            />
            Up
        </span>
    ) : (
        <span className="inline-flex items-center gap-1.5">
            <CircleX
                className="size-4 text-red-600 dark:text-red-400"
                aria-hidden
            />
            Down
        </span>
    );
}
