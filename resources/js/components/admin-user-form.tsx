import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
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
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import type { User } from '@/types';

type AdminUserFormData = {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    admin: boolean;
    email_verified: boolean;
};

export default function AdminUserForm({
    user,
    isCurrentUser = false,
}: {
    user?: User;
    isCurrentUser?: boolean;
}) {
    const isEditing = user !== undefined;

    const { data, setData, submit, processing, errors, reset } =
        useForm<AdminUserFormData>({
            name: user?.name ?? '',
            email: user?.email ?? '',
            password: '',
            password_confirmation: '',
            admin: user?.admin ?? false,
            email_verified: isEditing ? user.email_verified_at !== null : true,
        });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        submit(
            isEditing ? UserController.update(user.id) : UserController.store(),
            {
                preserveScroll: true,
                onFinish: () => reset('password', 'password_confirmation'),
            },
        );
    };

    const cancelHref = isEditing
        ? UserController.show(user.id)
        : UserController.index();

    return (
        <form onSubmit={handleSubmit} noValidate>
            <Card>
                <CardHeader>
                    <CardTitle>Profile</CardTitle>
                    <CardDescription>
                        The user's display name and sign-in email address.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-6 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            autoComplete="off"
                            placeholder="Full name"
                            aria-invalid={errors.name ? true : undefined}
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoComplete="off"
                            placeholder="email@example.com"
                            aria-invalid={errors.email ? true : undefined}
                        />
                        <InputError message={errors.email} />
                    </div>
                </CardContent>

                <Separator />

                <CardHeader>
                    <CardTitle>Password</CardTitle>
                    <CardDescription>
                        {isEditing
                            ? 'Leave both fields blank to keep the current password.'
                            : 'The password the user will sign in with.'}
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-6 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="password">
                            {isEditing ? 'New password' : 'Password'}
                        </Label>
                        <PasswordInput
                            id="password"
                            value={data.password}
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            required={!isEditing}
                            autoComplete="new-password"
                            placeholder="Password"
                            aria-invalid={errors.password ? true : undefined}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">
                            Confirm password
                        </Label>
                        <PasswordInput
                            id="password_confirmation"
                            value={data.password_confirmation}
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                            required={!isEditing}
                            autoComplete="new-password"
                            placeholder="Confirm password"
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>
                </CardContent>

                <Separator />

                <CardHeader>
                    <CardTitle>Access</CardTitle>
                    <CardDescription>
                        Permissions and account status.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-6">
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="admin"
                            checked={data.admin}
                            onCheckedChange={(checked) =>
                                setData('admin', checked === true)
                            }
                            disabled={isCurrentUser}
                            aria-describedby="admin-description"
                            className="mt-0.5"
                        />
                        <div className="grid gap-1">
                            <Label htmlFor="admin">Administrator</Label>
                            <p
                                id="admin-description"
                                className="text-sm text-muted-foreground"
                            >
                                {isCurrentUser
                                    ? 'You cannot remove your own administrator access.'
                                    : 'Can access the admin area and manage all users.'}
                            </p>
                            <InputError message={errors.admin} />
                        </div>
                    </div>

                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="email_verified"
                            checked={data.email_verified}
                            onCheckedChange={(checked) =>
                                setData('email_verified', checked === true)
                            }
                            aria-describedby="email-verified-description"
                            className="mt-0.5"
                        />
                        <div className="grid gap-1">
                            <Label htmlFor="email_verified">
                                Email verified
                            </Label>
                            <p
                                id="email-verified-description"
                                className="text-sm text-muted-foreground"
                            >
                                Mark the email address as verified so the user
                                is not asked to confirm it.
                            </p>
                            <InputError message={errors.email_verified} />
                        </div>
                    </div>
                </CardContent>

                <CardFooter className="justify-end gap-2 border-t">
                    <Button variant="outline" asChild>
                        <Link href={cancelHref}>Cancel</Link>
                    </Button>
                    <Button
                        type="submit"
                        disabled={processing}
                        data-test="save-user-button"
                    >
                        {processing && <Spinner />}
                        {isEditing ? 'Save changes' : 'Create user'}
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}
