import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import {
    AddMemberForm,
    MemberRow,
    Membership,
    Option,
} from './Partials/MemberForms';

type ProjectSummary = {
    id: number;
    name: string;
    slug: string;
    status: string;
    type: string | null;
};

type ClientDetails = {
    id: number;
    name: string;
    slug: string;
    status: string;
    projectsCount: number;
    imagesCount: number;
    membersCount: number;
    memberships: Membership[];
    projects: ProjectSummary[];
};

type Props = {
    client: ClientDetails;
    canUpdateClient: boolean;
    canManageMembers: boolean;
    roleOptions: Option[];
    membershipStatuses: Option[];
};

export default function ClientsShow({
    client,
    canUpdateClient,
    canManageMembers,
    roleOptions,
    membershipStatuses,
}: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {client.name}
                    </h2>
                    {canUpdateClient && (
                        <Link
                            href={route('clients.edit', client.id)}
                            className="rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700"
                        >
                            Modifier
                        </Link>
                    )}
                </div>
            }
        >
            <Head title={client.name} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Metric label="Statut" value={client.status} />
                        <Metric label="Membres" value={client.membersCount} />
                        <Metric label="Projets" value={client.projectsCount} />
                        <Metric label="Images" value={client.imagesCount} />
                    </div>

                    <section className="bg-white p-6 shadow sm:rounded-lg">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <h3 className="text-lg font-semibold text-gray-950">
                                    Utilisateurs du client
                                </h3>
                                <p className="mt-1 text-sm text-gray-600">
                                    Le rattachement client/utilisateur est centralise
                                    ici via un role de membership.
                                </p>
                            </div>
                            {canManageMembers && (
                                <span className="rounded-md border border-gray-200 px-3 py-2 text-xs font-medium text-gray-500">
                                    Gestion active
                                </span>
                            )}
                        </div>

                        {canManageMembers && (
                            <AddMemberForm
                                clientId={client.id}
                                roleOptions={roleOptions}
                                statusOptions={membershipStatuses}
                            />
                        )}

                        <div className="mt-5 overflow-hidden border border-gray-200 sm:rounded-lg">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                            Utilisateur
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                            Acces
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {client.memberships.map((membership) =>
                                        canManageMembers ? (
                                            <MemberRow
                                                key={membership.id}
                                                clientId={client.id}
                                                membership={membership}
                                                roleOptions={roleOptions}
                                                statusOptions={membershipStatuses}
                                            />
                                        ) : (
                                            <tr key={membership.id}>
                                                <td className="px-6 py-4">
                                                    <div className="font-medium text-gray-950">
                                                        {membership.user.name}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {membership.user.email}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-700">
                                                    {membership.role} ·{' '}
                                                    {membership.status}
                                                    {membership.isDefault
                                                        ? ' · defaut'
                                                        : ''}
                                                </td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="bg-white p-6 shadow sm:rounded-lg">
                        <h3 className="text-lg font-semibold text-gray-950">
                            Projets recents
                        </h3>

                        {client.projects.length > 0 ? (
                            <div className="mt-5 grid gap-3 md:grid-cols-2">
                                {client.projects.map((project) => (
                                    <div
                                        key={project.id}
                                        className="rounded-md border border-gray-200 p-4"
                                    >
                                        <div className="font-medium text-gray-950">
                                            {project.name}
                                        </div>
                                        <div className="mt-1 text-sm text-gray-500">
                                            {project.slug} · {project.status}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="mt-4 text-sm text-gray-500">
                                Aucun projet rattache pour le moment.
                            </p>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Metric({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="bg-white p-5 shadow sm:rounded-lg">
            <div className="text-sm font-medium text-gray-500">{label}</div>
            <div className="mt-2 text-2xl font-semibold text-gray-950">
                {value}
            </div>
        </div>
    );
}
