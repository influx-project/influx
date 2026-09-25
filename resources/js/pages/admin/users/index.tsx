import { Head, Link } from '@inertiajs/react';
import { Eye, MoreHorizontal, Pencil, Plus } from 'lucide-react';
import {
    DataTableFilter,
    DataTableFrame,
    DataTablePagination,
    DataTableSearch,
    DataTableToolbar,
    SortableTableHead,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { UserInfo } from '@/components/user-info';
import { useTableQuery } from '@/hooks/use-table-query';
import { overview } from '@/routes/admin';
import { create, edit, index, show } from '@/routes/admin/users';
import type { Paginated, TableQuery, User } from '@/types';

type UserFilters = {
    search?: string;
    admin?: string;
    verified?: string;
    two_factor?: string;
};

const booleanFilters: {
    key: Exclude<keyof UserFilters, 'search'>;
    label: string;
    options: { value: string; label: string }[];
}[] = [
    {
        key: 'admin',
        label: 'Role',
        options: [
            { value: 'true', label: 'Admins' },
            { value: 'false', label: 'Members' },
        ],
    },
    {
        key: 'verified',
        label: 'Email',
        options: [
            { value: 'true', label: 'Verified' },
            { value: 'false', label: 'Unverified' },
        ],
    },
    {
        key: 'two_factor',
        label: 'Two-factor',
        options: [
            { value: 'true', label: '2FA enabled' },
            { value: 'false', label: '2FA disabled' },
        ],
    },
];

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString(undefined, {
        dateStyle: 'medium',
    });
}

export default function UsersIndex({
    users,
    query,
}: {
    users: Paginated<User>;
    query: TableQuery<UserFilters>;
}) {
    const table = useTableQuery(query, {
        route: index,
        only: ['users', 'query'],
        defaultSort: '-created_at',
    });

    const onSort = (sort: string) => table.visit({ sort });

    return (
        <>
            <Head title="Users" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <Heading
                        title="Users"
                        description="Search, filter and manage every account."
                    />
                    <Button asChild className="shrink-0">
                        <Link href={create()}>
                            <Plus />
                            New user
                        </Link>
                    </Button>
                </div>

                <DataTableToolbar
                    hasActiveFilters={table.hasActiveFilters}
                    onClearFilters={table.clearFilters}
                >
                    <DataTableSearch
                        value={table.search}
                        onChange={table.setSearch}
                        placeholder="Search by name or email"
                        label="Search users"
                    />
                    <div className="grid grid-cols-1 gap-2 sm:grid-cols-3 lg:flex">
                        {booleanFilters.map(({ key, label, options }) => (
                            <DataTableFilter
                                key={key}
                                label={label}
                                value={query.filter[key]}
                                options={options}
                                onChange={(value) =>
                                    table.visit({ filter: { [key]: value } })
                                }
                            />
                        ))}
                    </div>
                </DataTableToolbar>

                <DataTableFrame isLoading={table.isLoading}>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <SortableTableHead
                                    column="name"
                                    label="Name"
                                    sort={query.sort}
                                    onSort={onSort}
                                    className="pl-4"
                                />
                                <SortableTableHead
                                    column="email"
                                    label="Email"
                                    sort={query.sort}
                                    onSort={onSort}
                                    className="hidden md:table-cell"
                                />
                                <TableHead>Role</TableHead>
                                <TableHead className="hidden sm:table-cell">
                                    Status
                                </TableHead>
                                <SortableTableHead
                                    column="created_at"
                                    label="Joined"
                                    sort={query.sort}
                                    onSort={onSort}
                                    className="hidden lg:table-cell"
                                />
                                <TableHead className="w-12 pr-4">
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="h-32 text-center text-muted-foreground"
                                    >
                                        {table.hasActiveFilters
                                            ? 'No users match your search or filters.'
                                            : 'No users yet.'}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                users.data.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell className="pl-4">
                                            <Link
                                                href={show(user.id)}
                                                className="flex max-w-64 items-center gap-2 rounded-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                            >
                                                <UserInfo user={user} />
                                            </Link>
                                        </TableCell>
                                        <TableCell className="hidden max-w-64 truncate text-muted-foreground md:table-cell">
                                            {user.email}
                                        </TableCell>
                                        <TableCell>
                                            {user.admin ? (
                                                <Badge>Admin</Badge>
                                            ) : (
                                                <Badge variant="secondary">
                                                    Member
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="hidden sm:table-cell">
                                            <div className="flex gap-1">
                                                <Badge variant="outline">
                                                    {user.email_verified_at ===
                                                    null
                                                        ? 'Unverified'
                                                        : 'Verified'}
                                                </Badge>
                                                {user.two_factor_enabled && (
                                                    <Badge variant="outline">
                                                        2FA
                                                    </Badge>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="hidden text-muted-foreground lg:table-cell">
                                            <time dateTime={user.created_at}>
                                                {formatDate(user.created_at)}
                                            </time>
                                        </TableCell>
                                        <TableCell className="pr-4 text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Actions for ${user.name}`}
                                                    >
                                                        <MoreHorizontal />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={show(user.id)}
                                                        >
                                                            <Eye />
                                                            View
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={edit(user.id)}
                                                        >
                                                            <Pencil />
                                                            Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </DataTableFrame>

                <DataTablePagination
                    paginated={users}
                    perPage={query.per_page}
                    onPerPageChange={(per_page) => table.visit({ per_page })}
                    only={table.only}
                />
            </div>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        { title: 'Admin', href: overview() },
        { title: 'Users', href: index() },
    ],
};
