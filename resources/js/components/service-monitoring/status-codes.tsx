import { cn } from '@/lib/utils';
import type { ServiceOverview } from '@/types';

/**
 * How often each HTTP status code was returned, as proportional bars.
 */
export function StatusCodes({
    codes,
}: {
    codes: NonNullable<ServiceOverview['status_codes']>;
}) {
    if (codes.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No responses in this period.
            </p>
        );
    }

    const total = codes.reduce((sum, { count }) => sum + count, 0);

    return (
        <ul className="space-y-3">
            {codes.map(({ status_code, count }) => {
                const share = (count / total) * 100;
                const ok = status_code < 400;

                return (
                    <li key={status_code} className="space-y-1.5">
                        <div className="flex items-baseline justify-between gap-2 text-sm">
                            <span className="font-mono font-medium">
                                {status_code}
                                <span className="ml-2 font-sans text-xs font-normal text-muted-foreground">
                                    {ok ? 'Up' : 'Down'}
                                </span>
                            </span>
                            <span className="text-muted-foreground tabular-nums">
                                {count.toLocaleString()} ·{' '}
                                {share < 0.1 ? '<0.1' : share.toFixed(1)}%
                            </span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className={cn(
                                    'h-full rounded-full',
                                    ok ? 'bg-emerald-500' : 'bg-red-500',
                                )}
                                style={{ width: `${Math.max(share, 1)}%` }}
                            />
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}
