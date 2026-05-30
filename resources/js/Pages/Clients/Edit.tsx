import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";
import ClientForm, { ClientFormData } from "./Partials/ClientForm";

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
    const { data, setData, patch, processing, errors } =
        useForm<ClientFormData>({
            name: client.name,
            slug: client.slug,
            status: client.status,
        });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        patch(route("clients.update", client.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <p className="text-sm font-medium text-muted-foreground">
                        Clients
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                        Modifier {client.name}
                    </h1>
                </div>
            }
        >
            <Head title={`Modifier ${client.name}`} />

            <div className="py-8">
                <div className="container max-w-3xl">
                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Parametres client
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ClientForm
                                data={data}
                                errors={errors}
                                processing={processing}
                                statuses={statuses}
                                submitLabel="Enregistrer"
                                onSubmit={submit}
                                setData={setData}
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
