import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ChevronLeft, ChevronRight, PartyPopper } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime, formatDuration } from '@/lib/format';
import type { Incident, Paginated } from '@/types';
import { EmptyState } from './empty-state';

/**
 * Every period the service was down, newest first, a page at a time.
 */
export function IncidentsTable({
    incidents,
}: {
    incidents: Paginated<Incident>;
}) {
    const { data, meta, links } = incidents;

    if (meta.total === 0) {
        return (
            <EmptyState icon={PartyPopper} title="No incidents recorded">
                Every check so far has succeeded.
            </EmptyState>
        );
    }

    return (
        <div className="space-y-4">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Started</TableHead>
                        <TableHead>Duration</TableHead>
                        <TableHead>State</TableHead>
                        <TableHead className="text-right">
                            Failed checks
                        </TableHead>
                        <TableHead>Cause</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {data.map((incident) => (
                        <TableRow key={incident.id}>
                            <TableCell className="whitespace-nowrap">
                                <time dateTime={incident.started_at}>
                                    {formatDateTime(incident.started_at)}
                                </time>
                            </TableCell>
                            <TableCell className="whitespace-nowrap tabular-nums">
                                {formatDuration(incident.duration_seconds)}
                            </TableCell>
                            <TableCell>
                                {incident.ended_at === null ? (
                                    <Badge variant="destructive">Ongoing</Badge>
                                ) : (
                                    <Badge variant="secondary">Resolved</Badge>
                                )}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {incident.failed_checks.toLocaleString()}
                            </TableCell>
                            <TableCell className="max-w-80 truncate text-muted-foreground">
                                <span title={incident.cause ?? undefined}>
                                    {incident.cause ?? '—'}
                                </span>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>

            {meta.last_page > 1 && (
                <div className="flex items-center justify-between gap-4">
                    <p className="text-sm text-muted-foreground tabular-nums">
                        Showing {meta.from}–{meta.to} of {meta.total}
                    </p>
                    <nav className="flex gap-2" aria-label="Incident pages">
                        <PageLink href={links.prev} label="Previous page">
                            <ChevronLeft />
                        </PageLink>
                        <PageLink href={links.next} label="Next page">
                            <ChevronRight />
                        </PageLink>
                    </nav>
                </div>
            )}
        </div>
    );
}

function PageLink({
    href,
    label,
    children,
}: {
    href: string | null;
    label: string;
    children: ReactNode;
}) {
    if (href === null) {
        return (
            <Button variant="outline" size="icon" disabled aria-label={label}>
                {children}
            </Button>
        );
    }

    return (
        <Button variant="outline" size="icon" asChild>
            <Link
                href={href}
                only={['incidents']}
                preserveScroll
                preserveState
                aria-label={label}
            >
                {children}
            </Link>
        </Button>
    );
}
