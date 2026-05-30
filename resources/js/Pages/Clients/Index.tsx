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
import { Head, Link } from "@inertiajs/react";

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
                    <div>
                        <p className="text-sm font-medium text-muted-foreground">
                            Administration
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight">
                            Clients
                        </h1>
                    </div>
                    {canCreateClient && (
                        <Button asChild>
                            <Link href={route("clients.create")}>
                                Nouveau client
                            </Link>
                        </Button>
                    )}
                </div>
            }
        >
            <Head title="Clients" />

            <div className="py-8">
                <div className="container space-y-6">
                    <div>
                        <h2 className="text-2xl font-semibold text-foreground">
                            Comptes clients
                        </h2>
                        <p className="mt-1 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Les clients deviennent les espaces metier. Les
                            membres, projets, images et droits seront
                            administres depuis cet ecran.
                        </p>
                    </div>

                    <Card className="border-border/70">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Espaces clients
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Client</TableHead>
                                        <TableHead>Statut</TableHead>
                                        <TableHead className="text-right">
                                            Membres
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Projets
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Images
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {clients.length > 0 ? (
                                        clients.map((client) => (
                                            <TableRow key={client.id}>
                                                <TableCell>
                                                    <div className="font-medium text-foreground">
                                                        {client.name}
                                                    </div>
                                                    <div className="text-sm text-muted-foreground">
                                                        {client.slug}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {client.status}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {client.membersCount}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {client.projectsCount}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {client.imagesCount}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        variant="ghost"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={route(
                                                                "clients.show",
                                                                client.id,
                                                            )}
                                                        >
                                                            Ouvrir
                                                        </Link>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    ) : (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="h-28 text-center text-sm text-muted-foreground"
                                            >
                                                Aucun client disponible pour cet
                                                utilisateur.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
