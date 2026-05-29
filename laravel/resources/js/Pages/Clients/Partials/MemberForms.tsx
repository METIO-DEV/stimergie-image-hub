import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export type Option = {
    value: string;
    label: string;
};

export type Membership = {
    id: number;
    role: string;
    status: string;
    isDefault: boolean;
    user: {
        id: number;
        name: string;
        email: string;
        status: string;
    };
};

type MemberFormData = {
    name: string;
    email: string;
    role: string;
    status: string;
    is_default: boolean;
};

export function AddMemberForm({
    clientId,
    roleOptions,
    statusOptions,
}: {
    clientId: number;
    roleOptions: Option[];
    statusOptions: Option[];
}) {
    const { data, setData, post, processing, errors, reset } =
        useForm<MemberFormData>({
            name: '',
            email: '',
            role: 'member',
            status: 'active',
            is_default: false,
        });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('clients.members.store', clientId), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form onSubmit={submit} className="mt-6 grid gap-4 lg:grid-cols-6">
            <div className="lg:col-span-2">
                <InputLabel htmlFor="member-name" value="Nom" />
                <TextInput
                    id="member-name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div className="lg:col-span-2">
                <InputLabel htmlFor="member-email" value="Email" />
                <TextInput
                    id="member-email"
                    type="email"
                    className="mt-1 block w-full"
                    value={data.email}
                    onChange={(event) => setData('email', event.target.value)}
                    required
                />
                <InputError message={errors.email} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="member-role" value="Role" />
                <select
                    id="member-role"
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.role}
                    onChange={(event) => setData('role', event.target.value)}
                >
                    {roleOptions.map((role) => (
                        <option key={role.value} value={role.value}>
                            {role.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.role} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="member-status" value="Statut" />
                <select
                    id="member-status"
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.status}
                    onChange={(event) => setData('status', event.target.value)}
                >
                    {statusOptions.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.status} className="mt-2" />
            </div>

            <div className="flex items-center gap-3 lg:col-span-6">
                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        checked={data.is_default}
                        onChange={(event) =>
                            setData('is_default', event.target.checked)
                        }
                    />
                    Client par defaut pour cet utilisateur
                </label>

                <PrimaryButton disabled={processing}>
                    Ajouter le membre
                </PrimaryButton>
            </div>
        </form>
    );
}

export function MemberRow({
    clientId,
    membership,
    roleOptions,
    statusOptions,
}: {
    clientId: number;
    membership: Membership;
    roleOptions: Option[];
    statusOptions: Option[];
}) {
    const { data, setData, patch, processing, errors } = useForm({
        role: membership.role,
        status: membership.status,
        is_default: membership.isDefault,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        patch(route('clients.members.update', [clientId, membership.id]), {
            preserveScroll: true,
        });
    };

    const removeMember = () => {
        if (!window.confirm('Retirer cet utilisateur du client ?')) {
            return;
        }

        router.delete(
            route('clients.members.destroy', [clientId, membership.id]),
            { preserveScroll: true },
        );
    };

    return (
        <tr>
            <td className="px-6 py-4 align-top">
                <div className="font-medium text-gray-950">
                    {membership.user.name}
                </div>
                <div className="text-sm text-gray-500">
                    {membership.user.email}
                </div>
            </td>
            <td className="px-6 py-4 align-top">
                <form onSubmit={submit} className="grid gap-3 md:grid-cols-4">
                    <div>
                        <select
                            className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.role}
                            onChange={(event) =>
                                setData('role', event.target.value)
                            }
                        >
                            {roleOptions.map((role) => (
                                <option key={role.value} value={role.value}>
                                    {role.label}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.role} className="mt-2" />
                    </div>

                    <div>
                        <select
                            className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.status}
                            onChange={(event) =>
                                setData('status', event.target.value)
                            }
                        >
                            {statusOptions.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.status} className="mt-2" />
                    </div>

                    <label className="flex items-center gap-2 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            checked={data.is_default}
                            onChange={(event) =>
                                setData('is_default', event.target.checked)
                            }
                        />
                        Defaut
                    </label>

                    <div className="flex gap-2">
                        <SecondaryButton type="submit" disabled={processing}>
                            Sauver
                        </SecondaryButton>
                        <button
                            type="button"
                            onClick={removeMember}
                            className="text-sm font-medium text-red-600 hover:text-red-800"
                        >
                            Retirer
                        </button>
                    </div>
                </form>
            </td>
        </tr>
    );
}
