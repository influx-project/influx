import { Form, Link } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import type { ReactNode } from 'react';
import {
    ImportanceBadge,
    MonitoringBadge,
    ServiceEndpoint,
} from '@/components/service-badges';
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
import type { Service } from '@/types';
import type { RouteDefinition, RouteFormDefinition } from '@/wayfinder';

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

function formatSeconds(seconds: number): string {
    if (seconds % 3600 === 0) {
        return `${seconds / 3600} h`;
    }

    if (seconds % 60 === 0) {
        return `${seconds / 60} min`;
    }

    return `${seconds} s`;
}

/**
 * A service's full details, with edit and delete actions.
 */
export function ServiceDetails({
    service,
    editHref,
    destroyForm,
    ownerHref,
}: {
    service: Service;
    editHref: RouteDefinition<'get'>;
    destroyForm: RouteFormDefinition<'post'>;
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
        <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0 space-y-1">
                    <h1 className="truncate text-xl font-semibold tracking-tight">
                        {service.name}
                    </h1>
                    <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <ServiceEndpoint service={service} />
                        {service.location && <span>· {service.location}</span>}
                    </div>
                    {service.description && (
                        <p className="pt-1 text-sm whitespace-pre-line">
                            {service.description}
                        </p>
                    )}
                </div>

                <div className="flex shrink-0 gap-2">
                    <Button variant="outline" asChild>
                        <Link href={editHref}>
                            <Pencil />
                            Edit
                        </Link>
                    </Button>

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button
                                variant="destructive"
                                data-test="delete-service-button"
                            >
                                <Trash2 />
                                Delete
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>Delete {service.name}?</DialogTitle>
                            <DialogDescription>
                                Monitoring of {service.host} stops immediately
                                and the service's configuration is permanently
                                removed. This cannot be undone.
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
                </div>
            </div>

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
