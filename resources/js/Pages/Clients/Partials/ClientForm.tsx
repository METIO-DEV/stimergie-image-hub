import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Link } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export type ClientFormData = {
    name: string;
    slug: string;
    status: string;
};

type StatusOption = {
    value: string;
    label: string;
};

type Props = {
    data: ClientFormData;
    errors: Partial<Record<keyof ClientFormData, string>>;
    processing: boolean;
    statuses: StatusOption[];
    submitLabel: string;
    onSubmit: FormEventHandler;
    setData: (key: keyof ClientFormData, value: string) => void;
};

export default function ClientForm({
    data,
    errors,
    processing,
    statuses,
    submitLabel,
    onSubmit,
    setData,
}: Props) {
    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <div>
                <InputLabel htmlFor="name" value="Nom du client" />
                <TextInput
                    id="name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    required
                    isFocused
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="slug" value="Identifiant URL" />
                <TextInput
                    id="slug"
                    className="mt-1 block w-full"
                    value={data.slug}
                    onChange={(event) => setData('slug', event.target.value)}
                    placeholder="genere depuis le nom si vide"
                />
                <InputError message={errors.slug} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="status" value="Statut" />
                <select
                    id="status"
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.status}
                    onChange={(event) => setData('status', event.target.value)}
                    required
                >
                    {statuses.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </select>
                <InputError message={errors.status} className="mt-2" />
            </div>

            <div className="flex items-center gap-3">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
                <Link
                    href={route('clients.index')}
                    className="text-sm font-medium text-gray-600 hover:text-gray-950"
                >
                    Annuler
                </Link>
            </div>
        </form>
    );
}
