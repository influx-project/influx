import { History } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * Marks data as streaming live: a pulsing red dot while live, grey when paused.
 */
export function LiveBadge({ live }: { live: boolean }) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'gap-1.5 font-semibold tracking-wide uppercase',
                live
                    ? 'border-red-500/40 text-red-600 dark:text-red-400'
                    : 'text-muted-foreground',
            )}
        >
            <span className="relative flex size-2" aria-hidden>
                {live && (
                    <span className="absolute inline-flex size-full rounded-full bg-red-500 opacity-75 motion-safe:animate-ping" />
                )}
                <span
                    className={cn(
                        'relative inline-flex size-2 rounded-full',
                        live ? 'bg-red-500' : 'bg-muted-foreground',
                    )}
                />
            </span>
            {live ? 'Live' : 'Live off'}
        </Badge>
    );
}

/**
 * Marks data as coming from stored background checks rather than the live stream.
 */
export function HistoricalBadge({ detail }: { detail?: string }) {
    return (
        <Badge variant="outline" className="gap-1.5 text-muted-foreground">
            <History className="size-3" aria-hidden />
            Historical{detail && ` · ${detail}`}
        </Badge>
    );
}
