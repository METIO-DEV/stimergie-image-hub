import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";
import ClientForm, { ClientFormData } from "./Partials/ClientForm";

type StatusOption = {
    value: string;
    label: string;
};

type Props = {
    statuses: StatusOption[];
};

export default function ClientsCreate({ statuses }: Props) {
    const { data, setData, post, processing, errors } = useForm<ClientFormData>(
        {
            name: "",
            slug: "",
            status: "active",
            logo: null,
        },
    );

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route("clients.store"), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <p className="text-sm font-medium text-muted-foreground">
                        Clients
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                        Nouveau client
                    </h1>
                </div>
            }
        >
            <Head title="Nouveau client" />

            <div className="py-8">
                <div className="container max-w-3xl">
                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Informations client
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ClientForm
                                data={data}
                                errors={errors}
                                processing={processing}
                                statuses={statuses}
                                submitLabel="Creer le client"
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
