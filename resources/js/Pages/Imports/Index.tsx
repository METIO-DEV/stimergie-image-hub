import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
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
import { AlertTriangle, CheckCircle2, Clock, FolderUp, ImagePlus } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

type ImportItem = {
    id: number;
    imageId?: number | null;
    imageTitle?: string | null;
    filename: string;
    relativePath?: string | null;
    status: string;
    sizeBytes?: number | null;
    attempts: number;
    error?: string | null;
    objectKeyOriginal?: string | null;
    processedAt?: string | null;
};

type ImportBatch = {
    id: number;
    status: string;
    clientName?: string | null;
    projectName?: string | null;
    startedBy?: string | null;
    totalItems: number;
    uploadedItems: number;
    processedItems: number;
    failedItems: number;
    duplicateItems: number;
    totalBytes: number;
    startedAt?: string | null;
    finishedAt?: string | null;
    items: ImportItem[];
};

type Props = {
    imports: ImportBatch[];
    stats: {
        total: number;
        active: number;
        failed: number;
        completed: number;
    };
};

export default function ImportsIndex({ imports, stats }: Props) {
    const [selectedId, setSelectedId] = useState<number | null>(
        imports[0]?.id ?? null,
    );
    const activeImports = useMemo(
        () =>
            imports.filter((importBatch) =>
                ["pending", "processing"].includes(importBatch.status),
            ),
        [imports],
    );
    const selectedImport =
        imports.find((importBatch) => importBatch.id === selectedId) ||
        imports[0] ||
        null;

    useEffect(() => {
        if (activeImports.length === 0) {
            return;
        }

        const interval = window.setInterval(() => {
            router.reload({ only: ["imports", "stats"] });
        }, 5000);

        return () => window.clearInterval(interval);
    }, [activeImports.length]);

    return (
        <AuthenticatedLayout>
            <Head title="Suivi des imports" />

            <main className="container px-4 py-8 sm:py-10">
                <div className="flex min-w-0 flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div className="min-w-0">
                        <h1 className="break-words text-3xl font-bold leading-tight tracking-normal md:text-4xl">
                            Suivi des imports
                        </h1>
                        <p className="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Consultez les imports de dossiers, leur progression
                            et les fichiers en erreur ou en doublon.
                        </p>
                    </div>
                    <Button
                        asChild
                        className="h-auto w-full whitespace-normal text-left sm:w-auto"
                    >
                        <Link href={route("images.index")}>
                            <ImagePlus className="mr-2 h-4 w-4" />
                            Importer un dossier
                        </Link>
                    </Button>
                </div>

                <div className="mt-8 grid gap-4 md:grid-cols-4">
                    <Metric label="Imports actifs" value={stats.active} />
                    <Metric label="Terminés" value={stats.completed} />
                    <Metric label="Avec erreurs" value={stats.failed} />
                    <Metric label="Total" value={stats.total} />
                </div>

                {activeImports.length > 0 && (
                    <section className="mt-10">
                        <div className="mb-4 flex items-center gap-2">
                            <Clock className="h-5 w-5 text-primary" />
                            <h2 className="text-2xl font-semibold">
                                Imports en cours
                            </h2>
                        </div>
                        <div className="grid gap-4 lg:grid-cols-2">
                            {activeImports.map((importBatch) => (
                                <ImportSummary
                                    key={importBatch.id}
                                    importBatch={importBatch}
                                    selected={selectedImport?.id === importBatch.id}
                                    onSelect={() => setSelectedId(importBatch.id)}
                                />
                            ))}
                        </div>
                    </section>
                )}

                <section className="mt-10 grid gap-6 lg:grid-cols-[360px_1fr]">
                    <div>
                        <h2 className="mb-4 text-2xl font-semibold">
                            Historique récent
                        </h2>
                        <div className="overflow-hidden rounded-lg border bg-card">
                            {imports.length === 0 ? (
                                <div className="p-5 text-sm text-muted-foreground">
                                    Aucun import dossier enregistré.
                                </div>
                            ) : (
                                imports.map((importBatch) => (
                                    <button
                                        key={importBatch.id}
                                        type="button"
                                        onClick={() => setSelectedId(importBatch.id)}
                                        className={`block w-full border-b p-4 text-left last:border-b-0 hover:bg-muted/40 ${
                                            selectedImport?.id === importBatch.id
                                                ? "bg-muted/50"
                                                : ""
                                        }`}
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="font-semibold">
                                                Import #{importBatch.id}
                                            </span>
                                            <StatusBadge status={importBatch.status} />
                                        </div>
                                        <div className="mt-2 text-sm text-muted-foreground">
                                            {importBatch.clientName ||
                                                "Entreprise inconnue"}{" "}
                                            ·{" "}
                                            {importBatch.projectName ||
                                                "Projet inconnu"}
                                        </div>
                                        <ProgressBar importBatch={importBatch} />
                                    </button>
                                ))
                            )}
                        </div>
                    </div>

                    <div>
                        <h2 className="mb-4 text-2xl font-semibold">Détails</h2>
                        {selectedImport ? (
                            <ImportDetails importBatch={selectedImport} />
                        ) : (
                            <div className="rounded-lg border bg-card p-6 text-sm text-muted-foreground">
                                Sélectionnez un import pour voir le détail.
                            </div>
                        )}
                    </div>
                </section>
            </main>
        </AuthenticatedLayout>
    );
}

function ImportSummary({
    importBatch,
    selected,
    onSelect,
}: {
    importBatch: ImportBatch;
    selected: boolean;
    onSelect: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onSelect}
            className={`rounded-lg border bg-card p-5 text-left transition-colors hover:bg-muted/30 ${
                selected ? "border-primary" : ""
            }`}
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <FolderUp className="h-5 w-5 text-primary" />
                    <span className="font-semibold">Import #{importBatch.id}</span>
                </div>
                <StatusBadge status={importBatch.status} />
            </div>
            <div className="mt-3 text-sm text-muted-foreground">
                {importBatch.clientName || "Entreprise inconnue"} ·{" "}
                {importBatch.projectName || "Projet inconnu"}
            </div>
            <ProgressBar importBatch={importBatch} />
            <div className="mt-4 grid grid-cols-4 gap-3 text-sm">
                <SmallMetric label="Env." value={importBatch.uploadedItems} />
                <SmallMetric label="Trait." value={importBatch.processedItems} />
                <SmallMetric label="Doub." value={importBatch.duplicateItems} />
                <SmallMetric label="Err." value={importBatch.failedItems} />
            </div>
        </button>
    );
}

function ImportDetails({ importBatch }: { importBatch: ImportBatch }) {
    return (
        <div className="overflow-hidden rounded-lg border bg-card">
            <div className="border-b p-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div className="text-lg font-semibold">
                            Import #{importBatch.id}
                        </div>
                        <div className="mt-1 text-sm text-muted-foreground">
                            {importBatch.startedBy || "Utilisateur inconnu"} ·{" "}
                            {formatDate(importBatch.startedAt)}
                        </div>
                    </div>
                    <StatusBadge status={importBatch.status} />
                </div>
                <ProgressBar importBatch={importBatch} />
                <div className="mt-5 grid gap-3 text-sm sm:grid-cols-5">
                    <SmallMetric label="Total" value={importBatch.totalItems} />
                    <SmallMetric label="Envoyées" value={importBatch.uploadedItems} />
                    <SmallMetric label="Traitées" value={importBatch.processedItems} />
                    <SmallMetric label="Doublons" value={importBatch.duplicateItems} />
                    <SmallMetric label="Erreurs" value={importBatch.failedItems} />
                </div>
                {importBatch.failedItems > 0 && (
                    <div className="mt-4 flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        Des fichiers sont en erreur. Ouvrez les lignes marquées
                        pour lire le message retourné par le traitement.
                    </div>
                )}
            </div>

            <div className="mobile-card-table-wrapper overflow-x-auto">
                <Table className="mobile-card-table">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Fichier</TableHead>
                            <TableHead>Statut</TableHead>
                            <TableHead>Essais</TableHead>
                            <TableHead>Traitement</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {importBatch.items.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={4}
                                    className="py-8 text-center text-sm text-muted-foreground"
                                >
                                    Aucun fichier enregistré pour cet import.
                                </TableCell>
                            </TableRow>
                        ) : (
                            importBatch.items.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell
                                        data-label="Fichier"
                                        className="min-w-[280px]"
                                    >
                                        <div className="font-medium">
                                            {item.relativePath || item.filename}
                                        </div>
                                        {item.imageTitle && (
                                            <div className="mt-1 text-xs text-muted-foreground">
                                                Image créée : {item.imageTitle}
                                            </div>
                                        )}
                                        {item.error && (
                                            <div className="mt-2 max-w-xl text-xs text-destructive">
                                                {item.error}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell data-label="Statut">
                                        <StatusBadge status={item.status} />
                                    </TableCell>
                                    <TableCell data-label="Essais">
                                        {item.attempts}
                                    </TableCell>
                                    <TableCell data-label="Traitement">
                                        {formatDate(item.processedAt)}
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg border bg-card p-5">
            <div className="text-sm text-muted-foreground">{label}</div>
            <div className="mt-2 text-3xl font-semibold">{value}</div>
        </div>
    );
}

function SmallMetric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded border px-3 py-2">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 font-semibold">{value}</div>
        </div>
    );
}

function ProgressBar({ importBatch }: { importBatch: ImportBatch }) {
    const progress = importProgress(importBatch);

    return (
        <div className="mt-4">
            <div className="mb-1 flex justify-between text-xs text-muted-foreground">
                <span>{progress}%</span>
                <span>
                    {importBatch.processedItems + importBatch.failedItems}/
                    {importBatch.totalItems}
                </span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-muted">
                <div
                    className="h-full bg-primary transition-all"
                    style={{ width: `${progress}%` }}
                />
            </div>
        </div>
    );
}

function StatusBadge({ status }: { status: string }) {
    const variant =
        status === "failed"
            ? "destructive"
            : status === "completed" || status === "done"
              ? "secondary"
              : "default";

    return (
        <Badge variant={variant}>
            {(status === "completed" || status === "done") && (
                <CheckCircle2 className="mr-1 h-3 w-3" />
            )}
            {statusLabel(status)}
        </Badge>
    );
}

function importProgress(importBatch: ImportBatch): number {
    if (["completed", "failed"].includes(importBatch.status)) {
        return 100;
    }

    const total = importBatch.totalItems || 1;
    const done = importBatch.processedItems + importBatch.failedItems;

    return Math.min(100, Math.round((done / total) * 100));
}

function statusLabel(status: string): string {
    return (
        {
            pending: "En attente",
            uploaded: "Envoyé",
            processing: "Traitement",
            done: "Terminé",
            completed: "Terminé",
            failed: "Erreur",
            duplicate: "Doublon",
        }[status] || status
    );
}

function formatDate(value?: string | null): string {
    if (!value) {
        return "Non renseigné";
    }

    return new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(value));
}
