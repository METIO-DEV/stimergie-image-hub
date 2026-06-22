import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
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
import {
    CheckCircle2,
    Loader2,
    Play,
    RefreshCw,
    Square,
    XCircle,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";

type SourceFolder = {
    name: string;
    onFtp: boolean;
    onBucket: boolean;
    bucketFileCount: number;
    projectId?: number | null;
    projectName?: string | null;
    databaseImageCount: number;
    imagesWithOriginalCount: number;
    webVariantReadyCount: number;
    missingWebVariantCount: number;
    transferable: boolean;
    transferBlockedReason?: string | null;
};

type TransferJob = {
    id: number;
    status: string;
    mode: string;
    startedBy?: string | null;
    totalFolders: number;
    processedFolders: number;
    failedFolders: number;
    currentFolder?: string | null;
    folders: string[];
    completedFolders: string[];
    failedFolderDetails: Record<string, string>;
    cancelRequestedAt?: string | null;
    startedAt?: string | null;
    finishedAt?: string | null;
    createdAt?: string | null;
    syncTotals?: {
        bucketImages: number;
        created: number;
        updated: number;
        skipped: number;
    } | null;
    webVariantTotals?: {
        checked: number;
        generated: number;
        missingOriginals: number;
        failed: number;
    } | null;
    log: string;
};

type WebVariantAudit = {
    projectId: number;
    projectName: string;
    clientName?: string | null;
    sourceFolder?: string | null;
    databaseImageCount: number;
    imagesWithOriginalCount: number;
    webVariantReadyCount: number;
    missingWebVariantCount: number;
};

type Props = {
    jobs: TransferJob[];
};

const csrfToken = () =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute("content") || "";

export default function AssetTransfersIndex({ jobs: initialJobs }: Props) {
    const [folders, setFolders] = useState<SourceFolder[]>([]);
    const [webVariantAudits, setWebVariantAudits] = useState<WebVariantAudit[]>([]);
    const [jobs, setJobs] = useState<TransferJob[]>(initialJobs);
    const [selectedFolders, setSelectedFolders] = useState<string[]>([]);
    const [selectedJobId, setSelectedJobId] = useState<number | null>(
        initialJobs[0]?.id ?? null,
    );
    const [limit, setLimit] = useState("5");
    const [loadingSources, setLoadingSources] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [refreshedAt, setRefreshedAt] = useState<string | null>(null);
    const mounted = useRef(false);
    const sourcesAbortController = useRef<AbortController | null>(null);
    const jobAbortControllers = useRef<Map<number, AbortController>>(new Map());

    const selectedJob = jobs.find((job) => job.id === selectedJobId) || jobs[0];
    const activeJobs = useMemo(
        () => jobs.filter((job) => isActive(job.status)),
        [jobs],
    );
    const activeJobIds = useMemo(
        () => activeJobs.map((job) => job.id),
        [activeJobs],
    );
    const activeJobIdsKey = activeJobIds.join(",");
    const activeTransferJob = activeJobs.find((job) => job.mode === "batch-copy");
    const activeSyncJob = activeJobs.find((job) => job.mode === "bucket-db-sync");
    const activeWebVariantJob = activeJobs.find(
        (job) => job.mode === "web-variant-generation",
    );
    const missingFolders = useMemo(
        () =>
            folders.filter(
                (folder) =>
                    folder.onFtp && !folder.onBucket && isTransferableFolder(folder),
            ),
        [folders],
    );
    const selectedTransferableFolders = useMemo(() => {
        const transferableNames = new Set(
            folders.filter(isTransferableFolder).map((folder) => folder.name),
        );

        return selectedFolders.filter((folderName) =>
            transferableNames.has(folderName),
        );
    }, [folders, selectedFolders]);
    const missingWebVariantTotal = useMemo(
        () =>
            webVariantAudits.reduce(
                (total, audit) => total + audit.missingWebVariantCount,
                0,
            ),
        [webVariantAudits],
    );

    const loadSources = useCallback(async () => {
        sourcesAbortController.current?.abort();

        const controller = new AbortController();
        sourcesAbortController.current = controller;

        setLoadingSources(true);
        setError(null);

        try {
            const response = await fetch(route("asset-transfers.sources"), {
                headers: { Accept: "application/json" },
                signal: controller.signal,
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || "Actualisation impossible.");
            }

            if (!mounted.current) {
                return;
            }

            setFolders(payload.folders || []);
            setWebVariantAudits(payload.webVariantAudits || []);
            setRefreshedAt(payload.refreshedAt || null);
        } catch (exception) {
            if (exception instanceof DOMException && exception.name === "AbortError") {
                return;
            }

            if (!mounted.current) {
                return;
            }

            setError(
                exception instanceof Error
                    ? exception.message
                    : "Actualisation impossible.",
            );
        } finally {
            const isLatestSourceRequest =
                sourcesAbortController.current === controller;

            if (isLatestSourceRequest) {
                sourcesAbortController.current = null;

                if (mounted.current) {
                    setLoadingSources(false);
                }
            }
        }
    }, []);

    const loadJob = useCallback(async (jobId: number) => {
        jobAbortControllers.current.get(jobId)?.abort();

        const controller = new AbortController();
        jobAbortControllers.current.set(jobId, controller);
        try {
            const response = await fetch(route("asset-transfers.show", jobId), {
                headers: { Accept: "application/json" },
                signal: controller.signal,
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || "Suivi indisponible.");
            }

            if (!mounted.current) {
                return;
            }

            let shouldRefreshSources = false;

            setJobs((currentJobs) => {
                const previousJob = currentJobs.find((job) => job.id === jobId);
                shouldRefreshSources = Boolean(
                    previousJob &&
                        isActive(previousJob.status) &&
                        !isActive(payload.job.status),
                );

                return mergeJob(currentJobs, payload.job);
            });

            if (shouldRefreshSources) {
                void loadSources();
            }
        } finally {
            if (jobAbortControllers.current.get(jobId) === controller) {
                jobAbortControllers.current.delete(jobId);
            }
        }
    }, [loadSources]);

    useEffect(() => {
        mounted.current = true;

        return () => {
            mounted.current = false;
            sourcesAbortController.current?.abort();
            jobAbortControllers.current.forEach((controller) => controller.abort());
            jobAbortControllers.current.clear();
        };
    }, []);

    useEffect(() => {
        void loadSources();
    }, [loadSources]);

    useEffect(() => {
        if (activeJobIds.length === 0) {
            return;
        }

        const pollActiveJobs = () => {
            activeJobIds.forEach((jobId) => {
                void loadJob(jobId).catch((exception) => {
                    if (
                        exception instanceof DOMException &&
                        exception.name === "AbortError"
                    ) {
                        return;
                    }

                    if (!mounted.current) {
                        return;
                    }

                    setError(
                        exception instanceof Error
                            ? exception.message
                            : "Suivi indisponible.",
                    );
                });
            });
        };

        pollActiveJobs();

        const interval = window.setInterval(() => {
            pollActiveJobs();
        }, 5000);

        return () => window.clearInterval(interval);
    }, [activeJobIdsKey, loadJob]);

    const toggleFolder = (folder: SourceFolder) => {
        if (!isTransferableFolder(folder)) {
            setError(
                folder.transferBlockedReason ||
                    "Ce dossier doit être associé à un projet avant transfert.",
            );
            return;
        }

        setSelectedFolders((current) =>
            current.includes(folder.name)
                ? current.filter((name) => name !== folder.name)
                : [...current, folder.name],
        );
    };

    const startTransfer = async (mode: "selected" | "limit") => {
        setSubmitting(true);
        setError(null);

        if (mode === "selected" && selectedTransferableFolders.length === 0) {
            setSubmitting(false);
            setSelectedFolders([]);
            setError("Sélectionnez au moins un dossier FTP associé à un projet.");
            return;
        }

        const body =
            mode === "selected"
                ? { folders: selectedTransferableFolders }
                : { limit: Number(limit) || 1 };

        try {
            const response = await fetch(route("asset-transfers.store"), {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken(),
                },
                body: JSON.stringify(body),
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || "Lancement impossible.");
            }

            setJobs((currentJobs) => mergeJob(currentJobs, payload.job));
            setSelectedJobId(payload.job.id);
            setSelectedFolders([]);
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : "Lancement impossible.",
            );
        } finally {
            setSubmitting(false);
        }
    };

    const stopTransfer = async (job: TransferJob) => {
        setSubmitting(true);
        setError(null);

        try {
            const response = await fetch(route("asset-transfers.stop", job.id), {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken(),
                },
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || "Arrêt impossible.");
            }

            setJobs((currentJobs) => mergeJob(currentJobs, payload.job));
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : "Arrêt impossible.",
            );
        } finally {
            setSubmitting(false);
        }
    };

    const startBucketResync = async () => {
        setSubmitting(true);
        setError(null);

        try {
            const response = await fetch(route("asset-transfers.resync-bucket"), {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken(),
                },
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || "Resynchro impossible.");
            }

            setJobs((currentJobs) => mergeJob(currentJobs, payload.job));
            setSelectedJobId(payload.job.id);
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : "Resynchro impossible.",
            );
        } finally {
            setSubmitting(false);
        }
    };

    const startWebVariantGeneration = async (projectId?: number) => {
        setSubmitting(true);
        setError(null);

        try {
            const response = await fetch(
                route("asset-transfers.generate-web-variants"),
                {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrfToken(),
                    },
                    body: JSON.stringify(
                        projectId ? { project_id: projectId } : {},
                    ),
                },
            );
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || "Génération impossible.");
            }

            setJobs((currentJobs) => mergeJob(currentJobs, payload.job));
            setSelectedJobId(payload.job.id);
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : "Génération impossible.",
            );
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Transferts FTP" />

            <main className="container py-10">
                <div className="flex min-w-0 flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div className="min-w-0">
                        <h1 className="break-words text-3xl font-bold leading-tight tracking-normal md:text-4xl">
                            Transferts FTP
                        </h1>
                        <p className="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Dossiers o2switch, présence bucket, transferts rclone
                            et mise à jour automatique de la base.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => void loadSources()}
                        disabled={loadingSources}
                    >
                        <RefreshCw
                            className={cn(
                                "mr-2 h-4 w-4",
                                loadingSources && "animate-spin",
                            )}
                        />
                        Actualiser
                    </Button>
                </div>

                {error && (
                    <div className="mt-6 rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm font-medium text-destructive">
                        {error}
                    </div>
                )}

                <section className="mt-8 grid gap-4 md:grid-cols-4">
                    <Metric label="FTP" value={folders.filter((folder) => folder.onFtp).length} />
                    <Metric label="Bucket" value={folders.filter((folder) => folder.onBucket).length} />
                    <Metric label="À transférer" value={missingFolders.length} />
                    <Metric label="JPG web à générer" value={missingWebVariantTotal} />
                </section>

                <section className="mt-8 rounded-lg border bg-card p-5">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="grid gap-3 sm:grid-cols-[160px_auto] sm:items-end">
                            <label className="block text-sm font-medium">
                                <span className="mb-2 block">Nombre</span>
                                <Input
                                    type="number"
                                    min={1}
                                    max={100}
                                    value={limit}
                                    onChange={(event) =>
                                        setLimit(event.target.value)
                                    }
                                    disabled={Boolean(activeTransferJob)}
                                />
                            </label>
                            <Button
                                type="button"
                                onClick={() => void startTransfer("limit")}
                                disabled={Boolean(activeTransferJob) || submitting}
                            >
                                <Play className="mr-2 h-4 w-4" />
                                Lancer les premiers manquants
                            </Button>
                        </div>
                        <div className="flex flex-wrap gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    setSelectedFolders(
                                        missingFolders
                                            .map((folder) => folder.name)
                                    )
                                }
                                disabled={Boolean(activeTransferJob)}
                            >
                                Sélectionner les manquants
                            </Button>
                            <Button
                                type="button"
                                onClick={() => void startTransfer("selected")}
                                disabled={
                                    Boolean(activeTransferJob) ||
                                    submitting ||
                                    selectedTransferableFolders.length === 0
                                }
                            >
                                <Play className="mr-2 h-4 w-4" />
                                Lancer la sélection
                            </Button>
                        </div>
                    </div>
                    <div className="mt-5 flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <div className="text-sm text-muted-foreground">
                            Resynchronise la base depuis les objets déjà présents
                            dans Scaleway, sans copier de fichiers FTP.
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => void startBucketResync()}
                            disabled={Boolean(activeSyncJob) || submitting}
                        >
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Resynchroniser la base
                        </Button>
                    </div>
                </section>

                <section className="mt-8 overflow-hidden rounded-lg border bg-card">
                    <div className="flex flex-col gap-4 border-b p-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 className="text-xl font-semibold">Images web JPG</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Regénère les versions légères manquantes dans le dossier JPG du projet.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-3">
                            <Button
                                type="button"
                                onClick={() => void startWebVariantGeneration()}
                                disabled={
                                    Boolean(activeWebVariantJob) ||
                                    submitting
                                }
                            >
                                <Play className="mr-2 h-4 w-4" />
                                Générer les JPG web
                            </Button>
                        </div>
                    </div>
                    <div className="max-h-[420px] overflow-auto">
                        <Table>
                            <TableHeader>
                                    <TableRow>
                                        <TableHead>Projet</TableHead>
                                        <TableHead>Dossier</TableHead>
                                        <TableHead className="text-right">Originaux</TableHead>
                                        <TableHead className="text-right">JPG prêts</TableHead>
                                        <TableHead className="text-right">JPG à générer</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                            </TableHeader>
                            <TableBody>
                                {webVariantAudits.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="py-10 text-center text-sm text-muted-foreground"
                                        >
                                            {loadingSources
                                                ? "Chargement..."
                                                : "Toutes les images avec original ont une version JPG web."}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    webVariantAudits.map((audit) => (
                                        <TableRow key={audit.projectId}>
                                            <TableCell className="min-w-[240px]">
                                                <div className="font-medium">
                                                    {audit.projectName}
                                                </div>
                                                {audit.clientName && (
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        {audit.clientName}
                                                    </div>
                                                )}
                                            </TableCell>
                                            <TableCell className="min-w-[180px] text-sm">
                                                {audit.sourceFolder || "Non renseigné"}
                                            </TableCell>
                                            <TableCell className="text-right text-sm">
                                                {audit.imagesWithOriginalCount}
                                            </TableCell>
                                            <TableCell className="text-right text-sm">
                                                {audit.webVariantReadyCount}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Badge variant="destructive">
                                                    {audit.missingWebVariantCount}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        onClick={() =>
                                                            void startWebVariantGeneration(
                                                                audit.projectId,
                                                            )
                                                        }
                                                        disabled={
                                                            Boolean(activeWebVariantJob) ||
                                                            submitting
                                                        }
                                                    >
                                                        <Play className="mr-2 h-4 w-4" />
                                                        Générer
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </section>

                <section className="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(420px,0.8fr)]">
                    <div className="overflow-hidden rounded-lg border bg-card">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b p-4">
                            <h2 className="text-xl font-semibold">
                                Dossiers disponibles
                            </h2>
                            {refreshedAt && (
                                <span className="text-xs text-muted-foreground">
                                    {formatDate(refreshedAt)}
                                </span>
                            )}
                        </div>
                        <div className="max-h-[620px] overflow-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12" />
                                        <TableHead>Dossier</TableHead>
                                        <TableHead>FTP</TableHead>
                                        <TableHead>Bucket</TableHead>
                                        <TableHead>Base</TableHead>
                                        <TableHead>Structure</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {folders.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="py-10 text-center text-sm text-muted-foreground"
                                            >
                                                {loadingSources
                                                    ? "Chargement..."
                                                    : "Aucun dossier chargé."}
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        folders.map((folder) => (
                                            <TableRow key={folder.name}>
                                                <TableCell>
                                                    <input
                                                        type="checkbox"
                                                        className="h-4 w-4 rounded border-border"
                                                        checked={selectedFolders.includes(
                                                            folder.name,
                                                        )}
                                                        disabled={
                                                            !folder.onFtp ||
                                                            !isTransferableFolder(folder) ||
                                                            Boolean(activeTransferJob)
                                                        }
                                                        onChange={() =>
                                                            toggleFolder(folder)
                                                        }
                                                        aria-label={`Sélectionner ${folder.name}`}
                                                    />
                                                </TableCell>
                                                <TableCell className="min-w-[240px]">
                                                    <div className="font-medium">
                                                        {folder.name}
                                                    </div>
                                                    {folder.projectName && (
                                                        <div className="mt-1 text-xs text-muted-foreground">
                                                            {folder.projectName}
                                                        </div>
                                                    )}
                                                    {folder.transferBlockedReason && (
                                                        <div className="mt-1 text-xs text-destructive">
                                                            {
                                                                folder.transferBlockedReason
                                                            }
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <PresenceBadge present={folder.onFtp} />
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-col gap-1">
                                                        <PresenceBadge
                                                            present={folder.onBucket}
                                                        />
                                                        {folder.onBucket && (
                                                            <span className="text-xs text-muted-foreground">
                                                                {folder.bucketFileCount} fichiers
                                                            </span>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <span className="text-sm">
                                                        {folder.databaseImageCount}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    {folder.projectId ? (
                                                        <VariantStatusBadge
                                                            missing={
                                                                folder.missingWebVariantCount
                                                            }
                                                            ready={
                                                                folder.webVariantReadyCount
                                                            }
                                                        />
                                                    ) : (
                                                        <span className="text-sm text-muted-foreground">
                                                            -
                                                        </span>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <div className="overflow-hidden rounded-lg border bg-card">
                            <div className="border-b p-4">
                                <h2 className="text-xl font-semibold">Suivi</h2>
                            </div>
                            {selectedJob ? (
                                <JobDetails
                                    job={selectedJob}
                                    submitting={submitting}
                                    onStop={() => void stopTransfer(selectedJob)}
                                />
                            ) : (
                                <div className="p-5 text-sm text-muted-foreground">
                                    Aucun transfert lancé.
                                </div>
                            )}
                        </div>

                        <div className="overflow-hidden rounded-lg border bg-card">
                            <div className="border-b p-4">
                                <h2 className="text-xl font-semibold">
                                    Historique
                                </h2>
                            </div>
                            {jobs.length === 0 ? (
                                <div className="p-5 text-sm text-muted-foreground">
                                    Aucun job enregistré.
                                </div>
                            ) : (
                                jobs.map((job) => (
                                    <button
                                        key={job.id}
                                        type="button"
                                        onClick={() => setSelectedJobId(job.id)}
                                        className={cn(
                                            "block w-full border-b p-4 text-left last:border-b-0 hover:bg-muted/40",
                                            selectedJob?.id === job.id &&
                                                "bg-muted/50",
                                        )}
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="font-semibold">
                                                {jobLabel(job)} #{job.id}
                                            </span>
                                            <StatusBadge status={job.status} />
                                        </div>
                                        <div className="mt-2 text-sm text-muted-foreground">
                                            {job.processedFolders}/{job.totalFolders}{" "}
                                            {jobUnitLabel(job)}
                                        </div>
                                    </button>
                                ))
                            )}
                        </div>
                    </div>
                </section>
            </main>
        </AuthenticatedLayout>
    );
}

function JobDetails({
    job,
    submitting,
    onStop,
}: {
    job: TransferJob;
    submitting: boolean;
    onStop: () => void;
}) {
    const checkedImages = job.webVariantTotals?.checked ?? job.processedFolders;
    const progress =
        job.mode === "web-variant-generation" && job.totalFolders > 0
            ? Math.min(100, Math.round((checkedImages / job.totalFolders) * 100))
            : job.totalFolders > 0
            ? Math.min(
                  100,
                  Math.round(
                      ((job.processedFolders + job.failedFolders) /
                          job.totalFolders) *
                          100,
                  ),
              )
            : 0;

    return (
        <div className="p-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div className="text-lg font-semibold">
                        {jobLabel(job)} #{job.id}
                    </div>
                    <div className="mt-1 text-sm text-muted-foreground">
                        {job.currentFolder || formatDate(job.createdAt)}
                    </div>
                </div>
                <StatusBadge status={job.status} />
            </div>

            <div className="mt-5">
                <div className="mb-2 flex justify-between text-xs text-muted-foreground">
                    <span>{progress}%</span>
                    <span>
                        {job.mode === "web-variant-generation"
                            ? checkedImages
                            : job.processedFolders + job.failedFolders}
                        /
                        {job.totalFolders}
                    </span>
                </div>
                <div className="h-2 overflow-hidden rounded-full bg-muted">
                    <div
                        className="h-full bg-primary transition-all"
                        style={{ width: `${progress}%` }}
                    />
                </div>
            </div>

            <div className="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                <SmallMetric
                    label={
                        job.mode === "bucket-db-sync"
                            ? "Projets"
                            : job.mode === "web-variant-generation"
                              ? "Vérifiées"
                              : "Copiés"
                    }
                    value={
                        job.mode === "web-variant-generation"
                            ? checkedImages
                            : job.processedFolders
                    }
                />
                <SmallMetric label="Erreurs" value={job.failedFolders} />
                <SmallMetric label="Total" value={job.totalFolders} />
            </div>

            {job.syncTotals && (
                <div className="mt-4 grid gap-3 text-sm sm:grid-cols-4">
                    <SmallMetric
                        label="Bucket"
                        value={job.syncTotals.bucketImages}
                    />
                    <SmallMetric label="Créées" value={job.syncTotals.created} />
                    <SmallMetric label="Maj" value={job.syncTotals.updated} />
                    <SmallMetric
                        label="Ignorées"
                        value={job.syncTotals.skipped}
                    />
                </div>
            )}

            {job.webVariantTotals && (
                <div className="mt-4 grid gap-3 text-sm sm:grid-cols-4">
                    <SmallMetric
                        label="Vérifiées"
                        value={job.webVariantTotals.checked}
                    />
                    <SmallMetric
                        label="Générées"
                        value={job.webVariantTotals.generated}
                    />
                    <SmallMetric
                        label="Originaux absents"
                        value={job.webVariantTotals.missingOriginals}
                    />
                    <SmallMetric
                        label="Échecs"
                        value={job.webVariantTotals.failed}
                    />
                </div>
            )}

            {isActive(job.status) && (
                <Button
                    type="button"
                    variant="destructive"
                    className="mt-5"
                    onClick={onStop}
                    disabled={
                        submitting ||
                        Boolean(job.cancelRequestedAt) ||
                        job.status === "cancelling"
                    }
                >
                    <Square className="mr-2 h-4 w-4" />
                    {job.cancelRequestedAt || job.status === "cancelling"
                        ? "Arrêt demandé"
                        : "Stopper"}
                </Button>
            )}

            <pre className="mt-5 max-h-[420px] overflow-auto rounded-md bg-foreground p-4 text-xs leading-5 text-background">
                {job.log || "Log en attente..."}
            </pre>
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

function PresenceBadge({
    present,
    label,
}: {
    present: boolean;
    label?: string;
}) {
    return present ? (
        <Badge variant="secondary">
            <CheckCircle2 className="mr-1 h-3 w-3" />
            {label || "Oui"}
        </Badge>
    ) : (
        <Badge variant="outline">
            <XCircle className="mr-1 h-3 w-3" />
            {label || "Non"}
        </Badge>
    );
}

function VariantStatusBadge({ missing, ready }: { missing: number; ready: number }) {
    if (missing > 0) {
        return (
            <div className="flex flex-col gap-1">
                <Badge variant="destructive">{missing} à traiter</Badge>
                <span className="text-xs text-muted-foreground">
                    {ready} conforme(s)
                </span>
            </div>
        );
    }

    return (
        <Badge variant="secondary">
            <CheckCircle2 className="mr-1 h-3 w-3" />
            OK
        </Badge>
    );
}

function StatusBadge({ status }: { status: string }) {
    const variant =
        status === "failed"
            ? "destructive"
            : ["completed", "cancelled"].includes(status)
              ? "secondary"
              : "default";

    return (
        <Badge variant={variant}>
            {["running", "cancelling"].includes(status) && (
                <Loader2 className="mr-1 h-3 w-3 animate-spin" />
            )}
            {status === "completed" && (
                <CheckCircle2 className="mr-1 h-3 w-3" />
            )}
            {statusLabel(status)}
        </Badge>
    );
}

function statusLabel(status: string): string {
    return (
        {
            pending: "En attente",
            running: "En cours",
            cancelling: "Arrêt demandé",
            completed: "Terminé",
            failed: "Erreur",
            cancelled: "Annulé",
        }[status] || status
    );
}

function jobLabel(job: TransferJob): string {
    if (job.mode === "bucket-db-sync") {
        return "Resynchro DB";
    }

    if (job.mode === "web-variant-generation") {
        return "Génération JPG web";
    }

    return "Transfert";
}

function jobUnitLabel(job: TransferJob): string {
    if (job.mode === "bucket-db-sync") {
        return "projet(s)";
    }

    if (job.mode === "web-variant-generation") {
        return "image(s)";
    }

    return "dossier(s)";
}

function isActive(status: string): boolean {
    return ["pending", "running", "cancelling"].includes(status);
}

function mergeJob(jobs: TransferJob[], job: TransferJob): TransferJob[] {
    const exists = jobs.some((currentJob) => currentJob.id === job.id);
    const merged = exists
        ? jobs.map((currentJob) => (currentJob.id === job.id ? job : currentJob))
        : [job, ...jobs];

    return merged.sort((a, b) => b.id - a.id).slice(0, 10);
}

function isTransferableFolder(folder: SourceFolder): boolean {
    return (
        folder.onFtp &&
        folder.transferable &&
        isTransferableFolderName(folder.name)
    );
}

function isTransferableFolderName(folderName: string): boolean {
    const value = folderName.trim();

    return (
        value.length > 0 &&
        value.length <= 255 &&
        !value.includes(":") &&
        !/[\u0000-\u001f\u007f]/.test(value) &&
        value.toLowerCase() !== "assets"
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
