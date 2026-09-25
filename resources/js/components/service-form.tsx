import { Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type {
    Service,
    ServiceImportance,
    ServiceOptions,
    ServiceOwner,
    ServiceTypeValue,
} from '@/types';
import type { RouteDefinition } from '@/wayfinder';

const UNASSIGNED = 'none';

type ServiceFormData = {
    name: string;
    description: string;
    location: string;
    type: ServiceTypeValue;
    host: string;
    port: string;
    use_ssl: boolean;
    importance: ServiceImportance;
    check_interval: string;
    timeout: string;
    collect_metrics: boolean;
    stream_metrics: boolean;
    enabled: boolean;
    user_id: string;
};

type BooleanField =
    | 'use_ssl'
    | 'collect_metrics'
    | 'stream_metrics'
    | 'enabled';

export default function ServiceForm({
    service,
    options,
    submitRoute,
    cancelHref,
    owners,
    defaultOwnerId = null,
}: {
    service?: Service;
    options: ServiceOptions;
    submitRoute: RouteDefinition<'post'> | RouteDefinition<'put'>;
    cancelHref: RouteDefinition<'get'>;
    /** When given, the service can be assigned to any of these users (admins only). */
    owners?: ServiceOwner[];
    defaultOwnerId?: number | null;
}) {
    const isEditing = service !== undefined;

    const defaultPortFor = (
        type: ServiceTypeValue,
        useSsl: boolean,
    ): string => {
        const option = options.types.find(({ value }) => value === type);
        const port = useSsl ? option?.default_ssl_port : option?.default_port;

        return port === null || port === undefined ? '' : String(port);
    };

    const ownerId = service ? service.user_id : defaultOwnerId;

    const form = useForm<ServiceFormData>({
        name: service?.name ?? '',
        description: service?.description ?? '',
        location: service?.location ?? '',
        type: service?.type ?? 'http',
        host: service?.host ?? '',
        port:
            service?.port === null || service?.port === undefined
                ? service
                    ? ''
                    : defaultPortFor('http', true)
                : String(service.port),
        use_ssl: service?.use_ssl ?? true,
        importance: service?.importance ?? 'normal',
        check_interval: String(service?.check_interval ?? 60),
        timeout: String(service?.timeout ?? 10),
        collect_metrics: service?.collect_metrics ?? true,
        stream_metrics: service?.stream_metrics ?? true,
        enabled: service?.enabled ?? true,
        user_id: ownerId === null ? UNASSIGNED : String(ownerId),
    });
    const { data, setData, processing, errors } = form;

    const selectedType = options.types.find(({ value }) => value === data.type);
    const usesPort = selectedType?.uses_port ?? true;

    /**
     * Switch the port to the new protocol's default, unless the user typed their own.
     */
    const updateConnection = (type: ServiceTypeValue, useSsl: boolean) => {
        setData((previous) => {
            const hadDefaultPort =
                previous.port === '' ||
                previous.port ===
                    defaultPortFor(previous.type, previous.use_ssl);

            return {
                ...previous,
                type,
                use_ssl: useSsl,
                port: hadDefaultPort
                    ? defaultPortFor(type, useSsl)
                    : previous.port,
            };
        });
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform(({ user_id, ...rest }) =>
            owners === undefined
                ? rest
                : {
                      ...rest,
                      user_id: user_id === UNASSIGNED ? null : Number(user_id),
                  },
        );

        form.submit(submitRoute, { preserveScroll: true });
    };

    const toggle = (field: BooleanField, checked: boolean | 'indeterminate') =>
        setData(field, checked === true);

    return (
        <form onSubmit={handleSubmit} noValidate>
            <Card>
                <Section
                    title="Details"
                    description="What this service is and where it runs."
                >
                    <Field label="Name" htmlFor="name" error={errors.name}>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            placeholder="Production API"
                            aria-invalid={errors.name ? true : undefined}
                        />
                    </Field>
                    <Field
                        label="Location"
                        htmlFor="location"
                        error={errors.location}
                        hint="Optional. A data centre, region or rack."
                    >
                        <Input
                            id="location"
                            value={data.location}
                            onChange={(e) =>
                                setData('location', e.target.value)
                            }
                            placeholder="Frankfurt, eu-central-1"
                            aria-invalid={errors.location ? true : undefined}
                        />
                    </Field>
                    <Field
                        label="Description"
                        htmlFor="description"
                        error={errors.description}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            id="description"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            rows={3}
                            placeholder="Optional notes for whoever responds to an alert."
                            aria-invalid={errors.description ? true : undefined}
                        />
                    </Field>
                </Section>

                <Separator />

                <Section
                    title="Connection"
                    description="How the monitor reaches the service."
                >
                    <Field label="Type" htmlFor="type" error={errors.type}>
                        <Select
                            value={data.type}
                            onValueChange={(type) =>
                                updateConnection(
                                    type as ServiceTypeValue,
                                    type === 'ping' ? false : data.use_ssl,
                                )
                            }
                        >
                            <SelectTrigger id="type" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {options.types.map((type) => (
                                    <SelectItem
                                        key={type.value}
                                        value={type.value}
                                    >
                                        {type.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <div className="grid grid-cols-[1fr_7rem] gap-4">
                        <Field
                            label="Host"
                            htmlFor="host"
                            error={errors.host}
                            hint="IP address or hostname."
                        >
                            <Input
                                id="host"
                                value={data.host}
                                onChange={(e) =>
                                    setData('host', e.target.value.trim())
                                }
                                required
                                spellCheck={false}
                                autoCapitalize="off"
                                placeholder="203.0.113.10"
                                className="font-mono"
                                aria-invalid={errors.host ? true : undefined}
                            />
                        </Field>
                        <Field label="Port" htmlFor="port" error={errors.port}>
                            <Input
                                id="port"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={65535}
                                value={usesPort ? data.port : ''}
                                onChange={(e) =>
                                    setData('port', e.target.value)
                                }
                                disabled={!usesPort}
                                required={usesPort}
                                placeholder={usesPort ? '443' : 'N/A'}
                                className="font-mono"
                                aria-invalid={errors.port ? true : undefined}
                            />
                        </Field>
                    </div>
                    <CheckboxField
                        id="use_ssl"
                        label="Connect using HTTPS / SSL / TLS"
                        description={
                            usesPort
                                ? 'Use an encrypted connection and check the certificate.'
                                : 'Not available for ping checks.'
                        }
                        checked={data.use_ssl}
                        disabled={!usesPort}
                        onCheckedChange={(checked) =>
                            updateConnection(data.type, checked === true)
                        }
                        error={errors.use_ssl}
                        className="sm:col-span-2"
                    />
                </Section>

                <Separator />

                <Section
                    title="Monitoring"
                    description="How closely the service is watched."
                >
                    <Field
                        label="Importance"
                        htmlFor="importance"
                        error={errors.importance}
                        hint="Sets how urgently problems are escalated."
                    >
                        <Select
                            value={data.importance}
                            onValueChange={(importance) =>
                                setData(
                                    'importance',
                                    importance as ServiceImportance,
                                )
                            }
                        >
                            <SelectTrigger id="importance" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {options.importances.map((importance) => (
                                    <SelectItem
                                        key={importance.value}
                                        value={importance.value}
                                    >
                                        {importance.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <div className="grid grid-cols-2 gap-4">
                        <Field
                            label="Check every (s)"
                            htmlFor="check_interval"
                            error={errors.check_interval}
                        >
                            <Input
                                id="check_interval"
                                type="number"
                                inputMode="numeric"
                                min={10}
                                max={86400}
                                value={data.check_interval}
                                onChange={(e) =>
                                    setData('check_interval', e.target.value)
                                }
                                aria-invalid={
                                    errors.check_interval ? true : undefined
                                }
                            />
                        </Field>
                        <Field
                            label="Timeout (s)"
                            htmlFor="timeout"
                            error={errors.timeout}
                        >
                            <Input
                                id="timeout"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={120}
                                value={data.timeout}
                                onChange={(e) =>
                                    setData('timeout', e.target.value)
                                }
                                aria-invalid={errors.timeout ? true : undefined}
                            />
                        </Field>
                    </div>
                    <CheckboxField
                        id="enabled"
                        label="Monitoring enabled"
                        description="Untick to pause all checks without deleting the service."
                        checked={data.enabled}
                        onCheckedChange={(checked) =>
                            toggle('enabled', checked)
                        }
                        error={errors.enabled}
                    />
                </Section>

                <Separator />

                <Section
                    title="Metrics"
                    description="Where collected metrics go."
                >
                    <CheckboxField
                        id="collect_metrics"
                        label="Background collection"
                        description="Record metrics on a schedule with the monitoring Artisan command."
                        checked={data.collect_metrics}
                        onCheckedChange={(checked) =>
                            toggle('collect_metrics', checked)
                        }
                        error={errors.collect_metrics}
                    />
                    <CheckboxField
                        id="stream_metrics"
                        label="Live updates"
                        description="Stream metrics to the UI instantly over a websocket."
                        checked={data.stream_metrics}
                        onCheckedChange={(checked) =>
                            toggle('stream_metrics', checked)
                        }
                        error={errors.stream_metrics}
                    />
                </Section>

                {owners !== undefined && (
                    <>
                        <Separator />
                        <Section
                            title="Ownership"
                            description="Who is responsible for this service."
                        >
                            <Field
                                label="Assigned to"
                                htmlFor="user_id"
                                error={errors.user_id}
                            >
                                <Select
                                    value={data.user_id}
                                    onValueChange={(userId) =>
                                        setData('user_id', userId)
                                    }
                                >
                                    <SelectTrigger
                                        id="user_id"
                                        className="w-full"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={UNASSIGNED}>
                                            Unassigned
                                        </SelectItem>
                                        {owners.map((owner) => (
                                            <SelectItem
                                                key={owner.id}
                                                value={String(owner.id)}
                                            >
                                                {owner.name} ({owner.email})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                        </Section>
                    </>
                )}

                <CardFooter className="justify-end gap-2 border-t">
                    <Button variant="outline" asChild>
                        <Link href={cancelHref}>Cancel</Link>
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing}
                        data-test="save-service-button"
                    >
                        {processing && <Spinner />}
                        {isEditing ? 'Save changes' : 'Create service'}
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="grid gap-6 sm:grid-cols-2">
                {children}
            </CardContent>
        </>
    );
}

function Field({
    label,
    htmlFor,
    error,
    hint,
    className,
    children,
}: {
    label: string;
    htmlFor: string;
    error?: string;
    hint?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            {hint && !error && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

function CheckboxField({
    id,
    label,
    description,
    checked,
    disabled,
    onCheckedChange,
    error,
    className,
}: {
    id: string;
    label: string;
    description: string;
    checked: boolean;
    disabled?: boolean;
    onCheckedChange: (checked: boolean | 'indeterminate') => void;
    error?: string;
    className?: string;
}) {
    return (
        <div className={cn('flex items-start gap-3', className)}>
            <Checkbox
                id={id}
                checked={checked}
                disabled={disabled}
                onCheckedChange={onCheckedChange}
                aria-describedby={`${id}-description`}
                className="mt-0.5"
            />
            <div className="grid gap-1">
                <Label htmlFor={id}>{label}</Label>
                <p
                    id={`${id}-description`}
                    className="text-sm text-muted-foreground"
                >
                    {description}
                </p>
                <InputError message={error} />
            </div>
        </div>
    );
}
