import { Badge } from "@/Components/ui/badge";
import { Card, CardContent } from "@/Components/ui/card";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { cn } from "@/lib/utils";
import { Head } from "@inertiajs/react";
import { AlertTriangle, CheckCircle2, Clock, FileClock } from "lucide-react";

type RightsExtensionRequest = {
    id: number | string;
    status: string;
    statusLabel: string;
    imageId: number;
    imageTitle?: string | null;
    clientName?: string | null;
    projectName?: string | null;
    rightsEndsAt?: string | null;
    requestedBy?: string | null;
    requestedAt?: string | null;
    resolvedBy?: string | null;
    resolvedAt?: string | null;
    isLegacy?: boolean;
};

export default function RightsExtensionsIndex({
    requests,
}: {
    requests: RightsExtensionRequest[];
}) {
    const activeCount = requests.filter((request) =>
        ["demande", "en_cours"].includes(request.status),
    ).length;

    return (
        <AuthenticatedLayout>
            <Head title="Suivi des demandes de cession" />

            <main className="container mx-auto max-w-7xl px-4 py-8">
                <div className="space-y-8">
                    <div className="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div className="min-w-0">
                            <h1 className="break-words text-2xl font-bold leading-tight sm:text-3xl">
                                Suivi des demandes de cession
                            </h1>
                            <p className="mt-2 max-w-3xl text-muted-foreground">
                                Retrouvez l'avancement des demandes d'extension
                                de cession liées à vos visuels.
                            </p>
                        </div>
                        <Badge variant="secondary" className="w-fit">
                            {activeCount} active(s)
                        </Badge>
                    </div>

                    {requests.length === 0 ? (
                        <Card className="rounded-lg">
                            <CardContent className="flex flex-col items-center justify-center gap-3 py-12 text-center text-muted-foreground">
                                <FileClock className="h-10 w-10" />
                                <p>Aucune demande d'extension à suivre.</p>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="mobile-card-table-wrapper overflow-hidden rounded-md border bg-card">
                            <Table className="mobile-card-table">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Image</TableHead>
                                        <TableHead>Client</TableHead>
                                        <TableHead>Projet</TableHead>
                                        <TableHead>Fin de cession</TableHead>
                                        <TableHead>Demandée le</TableHead>
                                        <TableHead>Statut</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {requests.map((request) => (
                                        <TableRow key={request.id}>
                                            <TableCell data-label="Image">
                                                <div className="font-medium">
                                                    {request.imageTitle || "Image"}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    #{request.imageId}
                                                </div>
                                            </TableCell>
                                            <TableCell data-label="Client">
                                                {request.clientName || "-"}
                                            </TableCell>
                                            <TableCell data-label="Projet">
                                                {request.projectName || "-"}
                                            </TableCell>
                                            <TableCell data-label="Fin de cession">
                                                {formatDate(request.rightsEndsAt)}
                                            </TableCell>
                                            <TableCell data-label="Demandée le">
                                                {formatDateTime(
                                                    request.requestedAt,
                                                )}
                                                {request.requestedBy && (
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        {request.requestedBy}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell data-label="Statut">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <StatusBadge
                                                        status={request.status}
                                                        label={
                                                            request.statusLabel
                                                        }
                                                    />
                                                    {request.isLegacy && (
                                                        <Badge variant="outline">
                                                            Historique
                                                        </Badge>
                                                    )}
                                                    {request.resolvedAt && (
                                                        <div className="w-full text-xs text-muted-foreground">
                                                            Résolue le{" "}
                                                            {formatDateTime(
                                                                request.resolvedAt,
                                                            )}
                                                            {request.resolvedBy
                                                                ? ` par ${request.resolvedBy}`
                                                                : ""}
                                                        </div>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </div>
            </main>
        </AuthenticatedLayout>
    );
}

function StatusBadge({ status, label }: { status: string; label: string }) {
    const tone =
        status === "refuse"
            ? "danger"
            : status === "demande" || status === "en_cours"
              ? "warning"
              : status === "accepte"
                ? "success"
                : "neutral";

    return (
        <Badge
            variant="secondary"
            className={cn(
                "w-fit gap-1",
                tone === "danger" && "bg-red-100 text-red-800",
                tone === "warning" && "bg-amber-100 text-amber-900",
                tone === "success" && "bg-emerald-100 text-emerald-800",
                tone === "neutral" && "bg-slate-100 text-slate-800",
            )}
        >
            {tone === "success" ? (
                <CheckCircle2 className="h-3 w-3" />
            ) : tone === "danger" ? (
                <AlertTriangle className="h-3 w-3" />
            ) : (
                <Clock className="h-3 w-3" />
            )}
            {label}
        </Badge>
    );
}

function formatDate(value?: string | null) {
    if (!value) {
        return "-";
    }

    return new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(new Date(value));
}

function formatDateTime(value?: string | null) {
    if (!value) {
        return "-";
    }

    return new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(value));
}
