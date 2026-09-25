import { Form, Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { ServicesTable } from '@/components/services-table';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
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
import { useInitials } from '@/hooks/use-initials';
import { overview } from '@/routes/admin';
import {
    create as createService,
    edit as editService,
    show as showService,
} from '@/routes/admin/services';
import { edit, index, show } from '@/routes/admin/users';
import type {
    Paginated,
    Service,
    ServiceFilters,
    ServiceOptions,
    TableQuery,
    User,
} from '@/types';

function formatDateTime(value: string | null): string {
    return value === null
        ? '—'
        : new Date(value).toLocaleString(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          });
}

export default function ShowUser({
    user,
    services,
    servicesQuery,
    serviceOptions,
}: {
    user: User;
    services: Paginated<Service>;
    servicesQuery: TableQuery<ServiceFilters>;
    serviceOptions: ServiceOptions;
}) {
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const isCurrentUser = auth.user.id === user.id;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Admin', href: overview() },
            { title: 'Users', href: index() },
            { title: user.name, href: show(user.id) },
        ],
    });

    const details: { label: string; value: React.ReactNode }[] = [
        { label: 'User ID', value: user.id },
        {
            label: 'Role',
            value: user.admin ? (
                <Badge>Admin</Badge>
            ) : (
                <Badge variant="secondary">Member</Badge>
            ),
        },
        {
            label: 'Email verification',
            value:
                user.email_verified_at === null ? (
                    <Badge variant="outline">Unverified</Badge>
                ) : (
                    `Verified ${formatDateTime(user.email_verified_at)}`
                ),
        },
        {
            label: 'Two-factor authentication',
            value: user.two_factor_enabled ? 'Enabled' : 'Not enabled',
        },
        { label: 'Created', value: formatDateTime(user.created_at) },
        { label: 'Last updated', value: formatDateTime(user.updated_at) },
    ];

    return (
        <>
            <Head title={user.name} />
            <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex min-w-0 items-center gap-4">
                        <Avatar className="size-14 shrink-0">
                            <AvatarFallback className="bg-neutral-200 text-lg text-black dark:bg-neutral-700 dark:text-white">
                                {getInitials(user.name)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="min-w-0">
                            <h1 className="truncate text-xl font-semibold tracking-tight">
                                {user.name}
                            </h1>
                            <p className="truncate text-sm text-muted-foreground">
                                {user.email}
                            </p>
                        </div>
                    </div>

                    <div className="flex shrink-0 gap-2">
                        <Button variant="outline" asChild>
                            <Link href={edit(user.id)}>
                                <Pencil />
                                Edit
                            </Link>
                        </Button>

                        {!isCurrentUser && (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button
                                        variant="destructive"
                                        data-test="delete-user-button"
                                    >
                                        <Trash2 />
                                        Delete
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>
                                        Delete {user.name}?
                                    </DialogTitle>
                                    <DialogDescription>
                                        This permanently deletes the account for{' '}
                                        {user.email} along with all of its data.
                                        This cannot be undone.
                                    </DialogDescription>

                                    <Form
                                        {...UserController.destroy.form(
                                            user.id,
                                        )}
                                    >
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
                                                    data-test="confirm-delete-user-button"
                                                >
                                                    {processing && <Spinner />}
                                                    Delete user
                                                </Button>
                                            </DialogFooter>
                                        )}
                                    </Form>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Account details</CardTitle>
                        <CardDescription>
                            {isCurrentUser
                                ? 'This is your account. Delete it from your profile settings instead.'
                                : 'Account status and history.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="divide-y">
                            {details.map(({ label, value }) => (
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

                <Card>
                    <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="space-y-1.5">
                            <CardTitle>Services</CardTitle>
                            <CardDescription>
                                Services assigned to {user.name}.
                            </CardDescription>
                        </div>
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={createService({
                                    query: { owner: user.id },
                                })}
                            >
                                <Plus />
                                Add service
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <ServicesTable
                            services={services}
                            query={servicesQuery}
                            options={serviceOptions}
                            route={(options) => show(user.id, options)}
                            only={['services', 'servicesQuery']}
                            showHref={showService}
                            editHref={editService}
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
