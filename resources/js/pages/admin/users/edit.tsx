import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import AdminUserForm from '@/components/admin-user-form';
import Heading from '@/components/heading';
import { overview } from '@/routes/admin';
import { edit, index, show } from '@/routes/admin/users';
import type { User } from '@/types';

export default function EditUser({ user }: { user: User }) {
    const { auth } = usePage().props;

    setLayoutProps({
        breadcrumbs: [
            { title: 'Admin', href: overview() },
            { title: 'Users', href: index() },
            { title: user.name, href: show(user.id) },
            { title: 'Edit', href: edit(user.id) },
        ],
    });

    return (
        <>
            <Head title={`Edit ${user.name}`} />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col p-4">
                <Heading
                    title="Edit user"
                    description={`Update ${user.name}'s details and access.`}
                />
                <AdminUserForm
                    key={user.id}
                    user={user}
                    isCurrentUser={auth.user.id === user.id}
                />
            </div>
        </>
    );
}
