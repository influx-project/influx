import { Form, Link } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { ImportanceBadge, MonitoringBadge } from '@/components/service-badges';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime, formatSeconds } from '@/lib/format';
import type { Service } from '@/types';
import type { RouteDefinition, RouteFormDefinition } from '@/wayfinder';

/**
 * A service's configuration, monitoring settings and ownership.
 */
export function ServiceInformation({
    service,
    ownerHref,
}: {
    service: Service;
    /** When given, the owner's name links to their profile. */
    ownerHref?: (id: number) => RouteDefinition<'get'>;
}) {
    const owner = service.owner;

    const sections: {
        title: string;
        description: string;
        rows: { label: string; value: ReactNode }[];
    }[] = [
        {
            title: 'Connection',
            description: 'How the monitor reaches the service.',
            rows: [
                { label: 'Type', value: service.type_label },
                {
                    label: 'Host',
                    value: <code className="font-mono">{service.host}</code>,
                },
                { label: 'Port', value: service.port ?? '—' },
                {
                    label: 'Encryption',
                    value: service.use_ssl ? 'HTTPS / SSL / TLS' : 'None',
                },
            ],
        },
        {
            title: 'Monitoring',
            description: 'How closely the service is watched.',
            rows: [
                {
                    label: 'Importance',
                    value: <ImportanceBadge service={service} />,
                },
                {
                    label: 'Status',
                    value: <MonitoringBadge enabled={service.enabled} />,
                },
                {
                    label: 'Check interval',
                    value: `Every ${formatSeconds(service.check_interval)}`,
                },
                { label: 'Timeout', value: formatSeconds(service.timeout) },
                {
                    label: 'Background collection',
                    value: service.collect_metrics ? 'On' : 'Off',
                },
                {
                    label: 'Live updates',
                    value: service.stream_metrics ? 'On' : 'Off',
                },
            ],
        },
        {
            title: 'Ownership',
            description: 'Who is responsible for this service.',
            rows: [
                {
                    label: 'Assigned to',
                    value: owner ? (
                        ownerHref ? (
                            <Link
                                href={ownerHref(owner.id)}
                                className="underline-offset-4 hover:underline"
                            >
                                {owner.name}
                            </Link>
                        ) : (
                            owner.name
                        )
                    ) : (
                        <span className="text-muted-foreground">
                            Unassigned
                        </span>
                    ),
                },
                { label: 'Created', value: formatDateTime(service.created_at) },
                {
                    label: 'Last updated',
                    value: formatDateTime(service.updated_at),
                },
            ],
        },
    ];

    return (
        <div className="flex flex-col gap-4">
            {service.description && (
                <Card>
                    <CardHeader>
                        <CardTitle>Description</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm whitespace-pre-line">
                            {service.description}
                        </p>
                    </CardContent>
                </Card>
            )}

            {sections.map((section) => (
                <Card key={section.title}>
                    <CardHeader>
                        <CardTitle>{section.title}</CardTitle>
                        <CardDescription>{section.description}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="divide-y">
                            {section.rows.map(({ label, value }) => (
                                <div
                                    key={label}
                                    className="grid gap-1 py-3 first:pt-0 last:pb-0 sm:grid-cols-3 sm:gap-4"
                                >
                                    <dt className="text-sm text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="text-sm sm:col-span-2">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

/**
 * A danger-zone card for permanently deleting a service.
 */
export function DeleteServiceCard({
    service,
    destroyForm,
}: {
    service: Service;
    destroyForm: RouteFormDefinition<'post'>;
}) {
    return (
        <Card className="border-destructive/40">
            <CardHeader>
                <CardTitle>Delete service</CardTitle>
                <CardDescription>
                    Stops monitoring {service.host} and permanently removes the
                    service with all of its metrics and incidents.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            data-test="delete-service-button"
                        >
                            <Trash2 />
                            Delete service
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>Delete {service.name}?</DialogTitle>
                        <DialogDescription>
                            Monitoring of {service.host} stops immediately and
                            the service's configuration, metrics and incident
                            history are permanently removed. This cannot be
                            undone.
                        </DialogDescription>

                        <Form {...destroyForm}>
                            {({ processing }) => (
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button variant="secondary">
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                        data-test="confirm-delete-service-button"
                                    >
                                        {processing && <Spinner />}
                                        Delete service
                                    </Button>
                                </DialogFooter>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </CardContent>
        </Card>
    );
}
