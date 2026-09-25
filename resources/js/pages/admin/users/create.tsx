import { Head } from '@inertiajs/react';
import AdminUserForm from '@/components/admin-user-form';
import Heading from '@/components/heading';
import { overview } from '@/routes/admin';
import { create, index } from '@/routes/admin/users';

export default function CreateUser() {
    return (
        <>
            <Head title="Create user" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col p-4">
                <Heading
                    title="Create user"
                    description="Add a new account and choose its access."
                />
                <AdminUserForm />
            </div>
        </>
    );
}

CreateUser.layout = {
    breadcrumbs: [
        { title: 'Admin', href: overview() },
        { title: 'Users', href: index() },
        { title: 'Create', href: create() },
    ],
};
