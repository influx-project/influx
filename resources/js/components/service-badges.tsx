import { Lock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { Service, ServiceImportance } from '@/types';

const importanceVariants: Record<
    ServiceImportance,
    'outline' | 'secondary' | 'default' | 'destructive'
> = {
    low: 'outline',
    normal: 'secondary',
    high: 'default',
    critical: 'destructive',
};

/**
 * The importance level a service is monitored at.
 */
export function ImportanceBadge({
    service,
}: {
    service: Pick<Service, 'importance' | 'importance_label'>;
}) {
    return (
        <Badge variant={importanceVariants[service.importance]}>
            {service.importance_label}
        </Badge>
    );
}

/**
 * Whether background monitoring of a service is active or paused.
 */
export function MonitoringBadge({ enabled }: { enabled: boolean }) {
    return (
        <Badge variant="outline" className="gap-1.5">
            <span
                className={
                    enabled
                        ? 'size-1.5 rounded-full bg-emerald-500'
                        : 'size-1.5 rounded-full bg-muted-foreground'
                }
                aria-hidden
            />
            {enabled ? 'Active' : 'Paused'}
        </Badge>
    );
}

/**
 * Format a service's host and port, bracketing IPv6 addresses (e.g. `[::1]:443`).
 */
export function formatEndpoint(
    service: Pick<Service, 'host' | 'port'>,
): string {
    const host = service.host.includes(':')
        ? `[${service.host}]`
        : service.host;

    return service.port === null ? host : `${host}:${service.port}`;
}

/**
 * A service's endpoint, with a lock when it connects over SSL/TLS.
 */
export function ServiceEndpoint({
    service,
}: {
    service: Pick<Service, 'host' | 'port' | 'use_ssl'>;
}) {
    return (
        <span className="inline-flex max-w-full items-center gap-1.5 font-mono text-xs">
            {service.use_ssl && (
                <Lock
                    className="size-3 shrink-0 text-muted-foreground"
                    aria-label="Connects over SSL/TLS"
                />
            )}
            <span className="truncate">{formatEndpoint(service)}</span>
        </span>
    );
}
