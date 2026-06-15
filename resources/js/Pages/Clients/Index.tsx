import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import {
    SectionHeader,
    ViewMode,
    ViewToggle,
} from "@/Components/Legacy/LegacyDesign";
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
import {
    Building2,
    FileText,
    ImageIcon,
    Mail,
    Pencil,
    Phone,
    PlusCircle,
    Trash2,
    UserRound,
} from "lucide-react";
import { useMemo, useState } from "react";

type ClientSummary = {
    id: number;
    name: string;
    slug: string;
    status: string;
    logo: string | null;
    projectsCount: number;
    imagesCount: number;
    membersCount: number;
    canUpdate: boolean;
    canDelete: boolean;
    canManageMembers: boolean;
};

type Props = {
    clients: ClientSummary[];
    canCreateClient: boolean;
};

export default function ClientsIndex({ clients, canCreateClient }: Props) {
    const [viewMode, setViewMode] = useState<ViewMode>("card");
    const [search, setSearch] = useState("");

    const filteredClients = useMemo(() => {
        const query = search.trim().toLowerCase();

        return clients
            .filter(
                (client) =>
                    !query ||
                    client.name.toLowerCase().includes(query) ||
                    client.slug.toLowerCase().includes(query),
            )
            .sort((a, b) =>
                a.name.localeCompare(b.name, undefined, {
                    sensitivity: "base",
                }),
            );
    }, [clients, search]);

    const deleteClient = (client: ClientSummary) => {
        if (
            !window.confirm(
                `Supprimer l'entreprise "${client.name}", ses projets et toutes ses images ? Cette action est définitive.`,
            )
        ) {
            return;
        }

        router.delete(route("clients.destroy", client.id), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Entreprises" />

            <SectionHeader
                icon={<Building2 className="h-8 w-8 text-primary" />}
                title="Entreprises"
                description="Consultez et administrez les entreprises auxquelles vous avez un rôle de gestion."
                action={
                    canCreateClient && (
                        <Button asChild className="h-auto gap-2 whitespace-normal text-left">
                            <Link href={route("clients.create")}>
                                <PlusCircle size={18} />
                                Ajouter une entreprise
                            </Link>
                        </Button>
                    )
                }
            />

            <main className="mx-auto max-w-7xl px-6 py-8">
                <div className="mb-6 flex flex-col justify-between gap-4 md:flex-row">
                    <div className="w-full md:w-2/3">
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Rechercher une entreprise..."
                            className="h-11"
                        />
                    </div>
                    <div className="flex justify-end">
                        <ViewToggle
                            currentView={viewMode}
                            onViewChange={setViewMode}
                        />
                    </div>
                </div>

                {viewMode === "card" ? (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {filteredClients.map((client) => (
                            <ClientCard
                                key={client.id}
                                client={client}
                                onDelete={
                                    client.canDelete ? deleteClient : undefined
                                }
                            />
                        ))}
                    </div>
                ) : (
                    <ClientsTable
                        clients={filteredClients}
                        onDelete={deleteClient}
                    />
                )}
            </main>
        </AuthenticatedLayout>
    );
}

function ClientCard({
    client,
    onDelete,
}: {
    client: ClientSummary;
    onDelete?: (client: ClientSummary) => void;
}) {
    return (
        <Card className="transition-shadow hover:shadow-md">
            <CardHeader className="pb-2">
                <div className="flex items-start justify-between">
                    <CardTitle className="flex items-center gap-2 text-lg">
                        <Building2
                            size={18}
                            className="text-muted-foreground"
                        />
                        {client.name}
                    </CardTitle>
                    <div className="flex gap-2">
                        <Button variant="ghost" size="icon" asChild>
                            <Link
                                href={route("clients.show", client.id)}
                                title={client.canUpdate ? "Modifier" : "Voir"}
                            >
                                <Pencil size={16} />
                            </Link>
                        </Button>
                        {onDelete && (
                            <Button
                                variant="ghost"
                                size="icon"
                                title="Supprimer"
                                onClick={() => onDelete(client)}
                            >
                                <Trash2 size={16} />
                            </Button>
                        )}
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                {client.logo && (
                    <div className="mb-4 flex justify-center">
                        <div className="h-32 w-32 overflow-hidden rounded-md border">
                            <img
                                src={client.logo}
                                alt={`Logo de ${client.name}`}
                                className="h-full w-full object-contain"
                            />
                        </div>
                    </div>
                )}

                <div className="space-y-3 text-sm">
                    <p className="flex items-center gap-2">
                        <Mail size={16} className="text-muted-foreground" />
                        {client.slug}
                    </p>
                    <p className="flex items-center gap-2">
                        <Phone size={16} className="text-muted-foreground" />
                        {client.membersCount} membre
                        {client.membersCount > 1 ? "s" : ""}
                    </p>
                    <div className="mt-2 border-t border-border pt-2">
                        <p className="flex items-start gap-2">
                            <FileText
                                size={16}
                                className="mt-0.5 shrink-0 text-muted-foreground"
                            />
                            <span>
                                {client.projectsCount} projets ·{" "}
                                {client.imagesCount} images
                            </span>
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function ClientsTable({
    clients,
    onDelete,
}: {
    clients: ClientSummary[];
    onDelete: (client: ClientSummary) => void;
}) {
    return (
        <div className="w-full overflow-hidden rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nom</TableHead>
                        <TableHead>Identifiant</TableHead>
                        <TableHead>Projets</TableHead>
                        <TableHead>Images</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {clients.map((client) => (
                        <TableRow key={client.id}>
                            <TableCell className="font-medium">
                                <div className="flex items-center gap-2">
                                    {client.logo ? (
                                        <div className="h-8 w-8 overflow-hidden rounded-full border">
                                            <img
                                                src={client.logo}
                                                alt={client.name}
                                                className="h-full w-full object-cover"
                                            />
                                        </div>
                                    ) : (
                                        <Building2
                                            size={16}
                                            className="text-muted-foreground"
                                        />
                                    )}
                                    {client.name}
                                </div>
                            </TableCell>
                            <TableCell>{client.slug}</TableCell>
                            <TableCell>{client.projectsCount}</TableCell>
                            <TableCell>
                                <div className="flex items-center gap-2">
                                    <ImageIcon className="h-4 w-4 text-muted-foreground" />
                                    {client.imagesCount}
                                </div>
                            </TableCell>
                            <TableCell className="text-right">
                                <Button variant="ghost" size="icon" asChild>
                                    <Link
                                        href={route("clients.show", client.id)}
                                        title={
                                            client.canUpdate
                                                ? "Modifier"
                                                : "Voir"
                                        }
                                    >
                                        <Pencil size={16} />
                                    </Link>
                                </Button>
                                {client.canDelete && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title="Supprimer"
                                        onClick={() => onDelete(client)}
                                    >
                                        <Trash2 size={16} />
                                    </Button>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
