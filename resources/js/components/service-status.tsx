import { CircleCheck, CircleDashed, CirclePause, CircleX } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ServiceState } from '@/types';

export const serviceStates: Record<
    ServiceState,
    { label: string; icon: LucideIcon; className: string }
> = {
    up: {
        label: 'Up',
        icon: CircleCheck,
        className: 'text-emerald-600 dark:text-emerald-400',
    },
    down: {
        label: 'Down',
        icon: CircleX,
        className: 'text-red-600 dark:text-red-400',
    },
    pending: {
        label: 'Awaiting first check',
        icon: CircleDashed,
        className: 'text-muted-foreground',
    },
    paused: {
        label: 'Paused',
        icon: CirclePause,
        className: 'text-muted-foreground',
    },
};

/**
 * A service's current state, shown with an icon and label so it never relies on color alone.
 */
export function ServiceStatusBadge({
    state,
    className,
}: {
    state: ServiceState;
    className?: string;
}) {
    const { label, icon: Icon, className: tone } = serviceStates[state];

    return (
        <Badge variant="outline" className={cn('gap-1.5', className)}>
            <Icon className={cn('size-3.5', tone)} aria-hidden />
            {label}
        </Badge>
    );
}
