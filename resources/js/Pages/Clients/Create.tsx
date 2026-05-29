import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import ClientForm, { ClientFormData } from './Partials/ClientForm';

type StatusOption = {
    value: string;
    label: string;
};

type Props = {
    statuses: StatusOption[];
};

export default function ClientsCreate({ statuses }: Props) {
    const { data, setData, post, processing, errors } = useForm<ClientFormData>({
        name: '',
        slug: '',
        status: 'active',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('clients.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Nouveau client
                </h2>
            }
        >
            <Head title="Nouveau client" />

            <div className="py-8">
                <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow sm:rounded-lg">
                        <ClientForm
                            data={data}
                            errors={errors}
                            processing={processing}
                            statuses={statuses}
                            submitLabel="Creer le client"
                            onSubmit={submit}
                            setData={setData}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
