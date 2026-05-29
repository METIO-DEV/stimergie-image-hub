import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type ClientSummary = {
    id: number;
    name: string;
    slug: string;
    status: string;
    projectsCount: number;
    imagesCount: number;
    membersCount: number;
};

type Props = {
    clients: ClientSummary[];
    canCreateClient: boolean;
};

export default function ClientsIndex({ clients, canCreateClient }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Clients
                    </h2>
                    {canCreateClient && (
                        <Link
                            href={route('clients.create')}
                            className="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                        >
                            Nouveau client
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="Clients" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-950">
                            Comptes clients
                        </h1>
                        <p className="mt-1 max-w-3xl text-sm text-gray-600">
                            Les clients deviennent les espaces metier. Les membres,
                            projets, images et droits seront administres depuis cet
                            ecran.
                        </p>
                    </div>

                    <div className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                        Client
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                        Statut
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">
                                        Membres
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">
                                        Projets
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">
                                        Images
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 bg-white">
                                {clients.length > 0 ? (
                                    clients.map((client) => (
                                        <tr key={client.id}>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <div className="font-medium text-gray-950">
                                                    {client.name}
                                                </div>
                                                <div className="text-sm text-gray-500">
                                                    {client.slug}
                                                </div>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                                {client.status}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-700">
                                                {client.membersCount}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-700">
                                                {client.projectsCount}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-700">
                                                {client.imagesCount}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                                <Link
                                                    href={route(
                                                        'clients.show',
                                                        client.id,
                                                    )}
                                                    className="text-gray-700 hover:text-gray-950"
                                                >
                                                    Ouvrir
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-6 py-10 text-center text-sm text-gray-500"
                                        >
                                            Aucun client disponible pour cet utilisateur.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
