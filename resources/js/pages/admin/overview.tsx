import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { overview } from '@/routes/admin';

type Stats = {
    total_users: number;
    admins: number;
    verified_users: number;
    two_factor_users: number;
    new_users_last_7_days: number;
};

type RecentUser = {
    id: number;
    name: string;
    email: string;
    admin: boolean;
    created_at: string;
};

const statCards: { key: keyof Stats; label: string }[] = [
    { key: 'total_users', label: 'Total users' },
    { key: 'new_users_last_7_days', label: 'New in last 7 days' },
    { key: 'verified_users', label: 'Verified users' },
    { key: 'two_factor_users', label: 'Two-factor enabled' },
    { key: 'admins', label: 'Administrators' },
];

export default function AdminOverview({
    stats,
    recent_users,
}: {
    stats: Stats;
    recent_users: RecentUser[];
}) {
    return (
        <>
            <Head title="Admin overview" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    {statCards.map(({ key, label }) => (
                        <Card key={key} className="gap-2 py-4">
                            <CardHeader>
                                <CardDescription>{label}</CardDescription>
                                <CardTitle className="text-3xl tabular-nums">
                                    {stats[key]}
                                </CardTitle>
                            </CardHeader>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent users</CardTitle>
                        <CardDescription>
                            The latest accounts to register.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="divide-y">
                            {recent_users.map((user) => (
                                <li
                                    key={user.id}
                                    className="flex items-center justify-between gap-4 py-3"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">
                                            {user.name}
                                        </p>
                                        <p className="truncate text-sm text-muted-foreground">
                                            {user.email}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 items-center gap-3">
                                        {user.admin && <Badge>Admin</Badge>}
                                        <time
                                            dateTime={user.created_at}
                                            className="text-sm text-muted-foreground"
                                        >
                                            {new Date(
                                                user.created_at,
                                            ).toLocaleDateString()}
                                        </time>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

AdminOverview.layout = {
    breadcrumbs: [
        { title: 'Admin', href: overview() },
        { title: 'Overview', href: overview() },
    ],
};
