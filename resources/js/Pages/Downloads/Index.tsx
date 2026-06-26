import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card } from "@/Components/ui/card";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import {
    AlertCircle,
    Clock,
    Download,
    Eye,
    Image as ImageIcon,
    RefreshCw,
} from "lucide-react";
import { useState } from "react";

type DownloadImage = {
    id: number;
    title: string;
    clientName: string | null;
    projectName: string | null;
    thumbUrl: string | null;
};

type DownloadRow = {
    id: number;
    title: string;
    status: string;
    isHd: boolean;
    imageCount: number;
    clientName: string | null;
    processedAt: string | null;
    expiresAt: string | null;
    createdAt: string;
    downloadUrl: string | null;
    images: DownloadImage[];
};

export default function DownloadsIndex({
    downloads,
}: {
    downloads: DownloadRow[];
}) {
    const [isRefreshing, setIsRefreshing] = useState(false);

    const refresh = () => {
        setIsRefreshing(true);
        window.location.reload();
    };

    return (
        <AuthenticatedLayout>
            <Head title="Vos téléchargements" />

            <main className="container mx-auto max-w-7xl px-4 py-8">
                <div className="flex flex-col space-y-8">
                    <div className="flex min-w-0 flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="min-w-0">
                            <h1 className="break-words text-2xl font-bold leading-tight sm:text-3xl">
                                Vos téléchargements
                            </h1>
                            <p className="mt-2 text-muted-foreground">
                                Retrouvez l'état de vos archives demandées et
                                récupérez les fichiers prêts avant expiration.
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            onClick={refresh}
                            className="flex w-full items-center gap-2 sm:w-auto"
                            disabled={isRefreshing}
                        >
                            <RefreshCw
                                className={`h-4 w-4 ${
                                    isRefreshing ? "animate-spin" : ""
                                }`}
                            />
                            {isRefreshing ? "Actualisation..." : "Actualiser"}
                        </Button>
                    </div>

                    <Card className="rounded-lg border bg-card p-4 sm:p-6">
                        <h2 className="mb-4 text-xl font-semibold">
                            Historique des demandes
                        </h2>
                        <DownloadsTable
                            downloads={downloads}
                            onRefresh={refresh}
                        />
                    </Card>

                    <div className="text-sm text-muted-foreground">
                        Les liens de téléchargement expirent automatiquement.
                        Relancez une demande depuis la banque d'images si un
                        fichier n'est plus disponible.
                    </div>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}

function DownloadsTable({
    downloads,
    onRefresh,
}: {
    downloads: DownloadRow[];
    onRefresh: () => void;
}) {
    const [selectedDownload, setSelectedDownload] =
        useState<DownloadRow | null>(null);

    return (
        <>
            <div className="mobile-card-table-wrapper w-full overflow-auto">
                <Table className="mobile-card-table">
                    <TableCaption>
                        {downloads.length === 0
                            ? "Aucune demande de téléchargement pour le moment"
                            : `${downloads.length} demande(s) de téléchargement`}
                    </TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Date de demande</TableHead>
                            <TableHead>Contenu</TableHead>
                            <TableHead>Statut</TableHead>
                            <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {downloads.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={4}
                                    className="py-8 text-center"
                                >
                                    <div className="flex flex-col items-center space-y-2">
                                        <AlertCircle className="h-12 w-12 text-muted-foreground" />
                                        <p className="text-muted-foreground">
                                            Aucune demande de téléchargement pour
                                            le moment
                                        </p>
                                        <Button
                                            variant="outline"
                                            onClick={onRefresh}
                                        >
                                            <RefreshCw className="mr-2 h-4 w-4" />
                                            Actualiser
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ) : (
                            downloads.map((download) => (
                                <TableRow key={download.id}>
                                    <TableCell data-label="Demandé le">
                                        {formatDate(download.createdAt)}
                                    </TableCell>
                                    <TableCell
                                        data-label="Contenu"
                                        className="max-w-[260px] truncate"
                                    >
                                        {download.title}
                                        {download.isHd && (
                                            <Badge
                                                variant="outline"
                                                className="ml-2 bg-blue-50"
                                            >
                                                HD
                                            </Badge>
                                        )}
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            {download.clientName || "Global"} ·{" "}
                                            {download.imageCount} image
                                            {download.imageCount > 1 ? "s" : ""}
                                        </div>
                                    </TableCell>
                                    <TableCell data-label="Statut">
                                        <StatusBadge status={download.status} />
                                        <div className="mt-1 text-xs text-muted-foreground">
                                            ID: {download.id}
                                        </div>
                                    </TableCell>
                                    <TableCell
                                        data-label="Actions"
                                        className="text-right"
                                    >
                                        <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="w-full py-4 sm:w-auto"
                                                onClick={() =>
                                                    setSelectedDownload(download)
                                                }
                                            >
                                                <Eye className="mr-2 h-4 w-4" />
                                                Détail
                                            </Button>
                                            <Button
                                                asChild={
                                                    download.status === "ready"
                                                }
                                                variant="outline"
                                                size="sm"
                                                className="w-full py-4 sm:w-auto"
                                                disabled={
                                                    download.status !== "ready"
                                                }
                                            >
                                                {download.status === "ready" &&
                                                download.downloadUrl ? (
                                                    <a
                                                        href={
                                                            download.downloadUrl
                                                        }
                                                    >
                                                        <Download className="mr-2 h-4 w-4" />
                                                        Télécharger
                                                    </a>
                                                ) : (
                                                    <>
                                                        <Clock className="mr-2 h-4 w-4" />
                                                        En cours
                                                    </>
                                                )}
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            <DownloadDetailDialog
                download={selectedDownload}
                onClose={() => setSelectedDownload(null)}
            />
        </>
    );
}

function DownloadDetailDialog({
    download,
    onClose,
}: {
    download: DownloadRow | null;
    onClose: () => void;
}) {
    return (
        <Dialog open={download !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                {download && (
                    <>
                        <DialogHeader>
                            <DialogTitle>Téléchargement #{download.id}</DialogTitle>
                            <DialogDescription>
                                {formatDate(download.createdAt)} ·{" "}
                                {download.imageCount} image
                                {download.imageCount > 1 ? "s" : ""}
                            </DialogDescription>
                        </DialogHeader>

                        <dl className="grid gap-3 sm:grid-cols-2">
                            <DetailItem label="Contenu" value={download.title} />
                            <DetailItem
                                label="Statut"
                                value={statusLabel(download.status)}
                            />
                            <DetailItem
                                label="Entreprise"
                                value={download.clientName || "Global"}
                            />
                            <DetailItem
                                label="Expiration"
                                value={
                                    download.expiresAt
                                        ? formatDate(download.expiresAt)
                                        : "-"
                                }
                            />
                        </dl>

                        <section>
                            <h3 className="mb-3 text-sm font-semibold">
                                Images concernées ({download.images.length})
                            </h3>
                            {download.images.length > 0 ? (
                                <div className="max-h-80 space-y-2 overflow-y-auto pr-1">
                                    {download.images.map((image) => (
                                        <div
                                            key={image.id}
                                            className="grid gap-3 rounded-md border bg-background p-3 text-sm sm:grid-cols-[72px_1fr]"
                                        >
                                            <div className="h-[72px] w-[72px] overflow-hidden rounded-md border bg-muted">
                                                {image.thumbUrl ? (
                                                    <img
                                                        src={image.thumbUrl}
                                                        alt=""
                                                        className="h-full w-full object-cover"
                                                        loading="lazy"
                                                    />
                                                ) : (
                                                    <div className="flex h-full w-full items-center justify-center text-muted-foreground">
                                                        <ImageIcon className="h-5 w-5" />
                                                    </div>
                                                )}
                                            </div>
                                            <div className="min-w-0">
                                                <div className="font-medium">
                                                    #{image.id} {image.title}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {image.clientName || "-"} ·{" "}
                                                    {image.projectName || "-"}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="rounded-md border bg-muted/30 p-4 text-sm text-muted-foreground">
                                    Aucune image détaillée enregistrée pour cette
                                    demande.
                                </div>
                            )}
                        </section>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

function DetailItem({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-md border bg-muted/30 p-3">
            <dt className="text-xs font-semibold uppercase text-muted-foreground">
                {label}
            </dt>
            <dd className="mt-1 text-sm">{value}</dd>
        </div>
    );
}

function StatusBadge({ status }: { status: string }) {
    if (status === "ready") {
        return (
            <Badge variant="secondary" className="bg-green-100 text-green-800">
                Prêt à télécharger
            </Badge>
        );
    }

    if (status === "failed") {
        return (
            <Badge variant="secondary" className="bg-red-100 text-red-800">
                <AlertCircle className="mr-1 h-3 w-3" />
                Échec
            </Badge>
        );
    }

    return (
        <Badge
            variant="secondary"
            className="w-fit bg-amber-100 text-amber-800"
        >
            <Clock className="mr-1 h-3 w-3" />
            En cours de préparation
        </Badge>
    );
}

function statusLabel(status: string) {
    if (status === "ready") {
        return "Prêt à télécharger";
    }

    if (status === "failed") {
        return "Échec";
    }

    if (status === "expired") {
        return "Expiré";
    }

    return "En cours de préparation";
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(value));
}
