import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router } from "@inertiajs/react";
import { Trash2 } from "lucide-react";
import {
    AddMemberForm,
    MemberRow,
    Membership,
    Option,
} from "./Partials/MemberForms";

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
    canDeleteClient: boolean;
    canManageMembers: boolean;
    roleOptions: Option[];
    membershipStatuses: Option[];
};

export default function ClientsShow({
    client,
    canUpdateClient,
    canDeleteClient,
    canManageMembers,
    roleOptions,
    membershipStatuses,
}: Props) {
    const deleteClient = () => {
        if (
            !window.confirm(
                `Supprimer l'entreprise "${client.name}", ses projets et toutes ses images ? Cette action est définitive.`,
            )
        ) {
            return;
        }

        router.delete(route("clients.destroy", client.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-muted-foreground">
                            Entreprise
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            {client.name}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Retrouvez les membres et projets associés à cette entreprise.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        {canUpdateClient && (
                            <Button asChild>
                                <Link href={route("clients.edit", client.id)}>
                                    Modifier
                                </Link>
                            </Button>
                        )}
                        {canDeleteClient && (
                            <Button
                                variant="destructive"
                                onClick={deleteClient}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Supprimer
                            </Button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={client.name} />

            <div className="py-8">
                <div className="container space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Metric label="Statut" value={client.status} />
                        <Metric label="Membres" value={client.membersCount} />
                        <Metric label="Projets" value={client.projectsCount} />
                        <Metric label="Images" value={client.imagesCount} />
                    </div>

                    <Card className="border-border/70">
                        <CardHeader className="flex-row items-start justify-between gap-4 space-y-0">
                            <div>
                                <CardTitle className="text-lg">
                                    Utilisateurs de l'entreprise
                                </CardTitle>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Le rattachement entreprise/utilisateur est
                                    centralisé ici via un rôle de membership.
                                </p>
                            </div>
                            {canManageMembers && (
                                <Badge variant="outline">Gestion active</Badge>
                            )}
                        </CardHeader>

                        <CardContent>
                            {canManageMembers && (
                                <AddMemberForm
                                    clientId={client.id}
                                    roleOptions={roleOptions}
                                    statusOptions={membershipStatuses}
                                />
                            )}

                            <div className="mt-5 rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Utilisateur</TableHead>
                                            <TableHead>Accès</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {client.memberships.map((membership) =>
                                            canManageMembers ? (
                                                <MemberRow
                                                    key={membership.id}
                                                    clientId={client.id}
                                                    membership={membership}
                                                    roleOptions={roleOptions}
                                                    statusOptions={
                                                        membershipStatuses
                                                    }
                                                />
                                            ) : (
                                                <TableRow key={membership.id}>
                                                    <TableCell>
                                                        <div className="font-medium text-foreground">
                                                            {
                                                                membership.user
                                                                    .name
                                                            }
                                                        </div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {
                                                                membership.user
                                                                    .email
                                                            }
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="text-sm text-muted-foreground">
                                                        {membership.role} ·{" "}
                                                        {membership.status}
                                                        {membership.isDefault
                                                            ? " · défaut"
                                                            : ""}
                                                    </TableCell>
                                                </TableRow>
                                            ),
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Projets recents
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {client.projects.length > 0 ? (
                                <div className="mt-5 grid gap-3 md:grid-cols-2">
                                    {client.projects.map((project) => (
                                        <div
                                            key={project.id}
                                            className="rounded-md border border-border/80 p-4"
                                        >
                                            <div className="font-medium text-foreground">
                                                {project.name}
                                            </div>
                                            <div className="mt-1 text-sm text-muted-foreground">
                                                {project.slug} ·{" "}
                                                {project.status}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Aucun projet rattache pour le moment.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Metric({ label, value }: { label: string; value: string | number }) {
    return (
        <Card className="border-border/70">
            <CardContent className="p-5">
                <div className="text-sm font-medium text-muted-foreground">
                    {label}
                </div>
                <div className="mt-2 text-2xl font-semibold text-foreground">
                    {value}
                </div>
            </CardContent>
        </Card>
    );
}
