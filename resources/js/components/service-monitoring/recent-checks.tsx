import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime, formatLatency } from '@/lib/format';
import type { ServiceCheck } from '@/types';
import { CheckResult } from './check-result';

/**
 * The service's latest check results.
 */
export function RecentChecks({
    checks,
    showStatusCode,
}: {
    checks: ServiceCheck[];
    showStatusCode: boolean;
}) {
    if (checks.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No checks have run yet.
            </p>
        );
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Checked</TableHead>
                    <TableHead>Result</TableHead>
                    <TableHead className="text-right">Response time</TableHead>
                    {showStatusCode && (
                        <TableHead className="text-right">Status</TableHead>
                    )}
                    <TableHead>Details</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {checks.map((check) => (
                    <TableRow key={check.id}>
                        <TableCell className="whitespace-nowrap">
                            <time dateTime={check.checked_at}>
                                {formatDateTime(check.checked_at)}
                            </time>
                        </TableCell>
                        <TableCell>
                            <CheckResult check={check} />
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {formatLatency(check.latency_ms)}
                        </TableCell>
                        {showStatusCode && (
                            <TableCell className="text-right font-mono">
                                {check.status_code ?? '—'}
                            </TableCell>
                        )}
                        <TableCell className="max-w-72 truncate text-muted-foreground">
                            <span title={check.error ?? undefined}>
                                {check.error ?? '—'}
                            </span>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
