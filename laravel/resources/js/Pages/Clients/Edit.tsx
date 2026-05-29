import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import ClientForm, { ClientFormData } from './Partials/ClientForm';

type ClientEditable = ClientFormData & {
    id: number;
};

type StatusOption = {
    value: string;
    label: string;
};

type Props = {
    client: ClientEditable;
    statuses: StatusOption[];
};

export default function ClientsEdit({ client, statuses }: Props) {
    const { data, setData, patch, processing, errors } = useForm<ClientFormData>({
        name: client.name,
        slug: client.slug,
        status: client.status,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        patch(route('clients.update', client.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Modifier {client.name}
                </h2>
            }
        >
            <Head title={`Modifier ${client.name}`} />

            <div className="py-8">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow sm:rounded-lg">
                        <ClientForm
                            data={data}
                            errors={errors}
                            processing={processing}
                            statuses={statuses}
                            submitLabel="Enregistrer"
                            onSubmit={submit}
                            setData={setData}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
