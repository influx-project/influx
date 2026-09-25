import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * A centred message for when there is nothing to show yet.
 */
export function EmptyState({
    icon: Icon,
    title,
    children,
}: {
    icon: LucideIcon;
    title: string;
    children?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-6 py-12 text-center">
            <Icon className="size-8 text-muted-foreground" aria-hidden />
            <p className="font-medium">{title}</p>
            {children && (
                <div className="max-w-md text-sm text-muted-foreground">
                    {children}
                </div>
            )}
        </div>
    );
}
