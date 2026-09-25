import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';

/**
 * A headline number with a label and optional supporting detail.
 */
export function StatCard({
    label,
    value,
    detail,
    icon: Icon,
}: {
    label: string;
    value: ReactNode;
    detail?: ReactNode;
    icon?: LucideIcon;
}) {
    return (
        <Card className="gap-0 py-4">
            <CardContent className="space-y-1 px-4">
                <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                    {Icon && <Icon className="size-4" aria-hidden />}
                    {label}
                </p>
                <p className="text-2xl font-semibold tracking-tight tabular-nums">
                    {value}
                </p>
                {detail && (
                    <p className="text-xs text-muted-foreground tabular-nums">
                        {detail}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
