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
    Link2,
    Loader2,
    Play,
    RefreshCw,
    Square,
    Wand2,
    XCircle,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";

type SourceFolder = {
    name: string;
    onFtp: boolean;
    onBucket: boolean;
    bucketFileCount: number;
    projectId?: number | null;
    projectName?: string | null;
    databaseImageCount: number;
};

type ProjectOption = {
    id: number;
    name: string;
    clientName?: string | null;
    sourceFolder?: string | null;
    imagesCount?: number | null;
};

type FolderMatch = {
    folder: string;
    status: "unmatched" | "suggested" | "mapped" | "exact" | "ignored";
    onFtp: boolean;
    onBucket: boolean;
    bucketFileCount: number;
    mappedProject?: ProjectOption | null;
    exactProject?: ProjectOption | null;
    suggestion?: (ProjectOption & {
        score: number;
        source?: string | null;
        autoMappable: boolean;
    }) | null;
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
    log: string;
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
    const [folderMatches, setFolderMatches] = useState<FolderMatch[]>([]);
    const [projectOptions, setProjectOptions] = useState<ProjectOption[]>([]);
    const [mappingSelections, setMappingSelections] = useState<
        Record<string, string>
    >({});
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

    const selectedJob = jobs.find((job) => job.id === selectedJobId) || jobs[0];
    const activeJobs = jobs.filter((job) => isActive(job.status));
    const activeTransferJob = activeJobs.find((job) => job.mode === "batch-copy");
    const activeSyncJob = activeJobs.find((job) => job.mode === "bucket-db-sync");
    const missingFolders = useMemo(
        () => folders.filter((folder) => folder.onFtp && !folder.onBucket),
        [folders],
    );
    const unresolvedFolderMatches = useMemo(
        () =>
            folderMatches.filter((match) =>
                ["unmatched", "suggested"].includes(match.status),
            ),
        [folderMatches],
    );

    const loadSources = useCallback(async () => {
        setLoadingSources(true);
        setError(null);

        try {
            const response = await fetch(route("asset-transfers.sources"), {
                headers: { Accept: "application/json" },
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || "Actualisation impossible.");
            }

            setFolders(payload.folders || []);
            const nextMatches: FolderMatch[] = payload.folderMatches || [];
            setFolderMatches(nextMatches);
            setProjectOptions(payload.projectOptions || []);
            setMappingSelections((currentSelections) => {
                const nextSelections = { ...currentSelections };

                nextMatches.forEach((match) => {
                    if (nextSelections[match.folder]) {
                        return;
                    }

                    const projectId =
                        match.mappedProject?.id ||
                        match.exactProject?.id ||
                        match.suggestion?.id;

                    if (projectId) {
                        nextSelections[match.folder] = String(projectId);
                    }
                });

                return nextSelections;
            });
            setRefreshedAt(payload.refreshedAt || null);
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : "Actualisation impossible.",
            );
        } finally {
            setLoadingSources(false);
        }
    }, []);

    const loadJob = useCallback(async (jobId: number) => {
        const response = await fetch(route("asset-transfers.show", jobId), {
            headers: { Accept: "application/json" },
        });
        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.message || "Suivi indisponible.");
        }

        setJobs((currentJobs) => mergeJob(currentJobs, payload.job));
    }, []);

    useEffect(() => {
        void loadSources();
    }, [loadSources]);

    useEffect(() => {
        const interval = window.setInterval(() => {
            void loadSources();
        }, 15000);

        return () => window.clearInterval(interval);
    }, [loadSources]);

    useEffect(() => {
        if (activeJobs.length === 0) {
            return;
        }

        const interval = window.setInterval(() => {
            activeJobs.forEach((job) => {
                void loadJob(job.id).catch((exception) => {
                    setError(
                        exception instanceof Error
                            ? exception.message
                            : "Suivi indisponible.",
                    );
                });
            });
        }, 2000);

        return () => window.clearInterval(interval);
    }, [activeJobs, loadJob]);

    const toggleFolder = (folderName: string) => {
        setSelectedFolders((current) =>
            current.includes(folderName)
                ? current.filter((name) => name !== folderName)
                : [...current, folderName],
        );
    };

    const startTransfer = async (mode: "selected" | "limit") => {
        setSubmitting(true);
        setError(null);

        const body =
            mode === "selected"
                ? { folders: selectedFolders }
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

    const mapFolder = async (folder: string, projectId?: string) => {
        if (!projectId) {
            setError("Sélectionnez un projet avant d'associer le dossier.");
            return;
        }

        setSubmitting(true);
        setError(null);

        try {
            const response = await fetch(
                route("asset-transfers.folder-mappings.store"),
                {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrfToken(),
                    },
                    body: JSON.stringify({
                        folder,
                        project_id: Number(projectId),
                    }),
                },
            );
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || "Association impossible.");
            }

            await loadSources();
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : "Association impossible.",
            );
        } finally {
            setSubmitting(false);
        }
    };

    const ignoreFolder = async (folder: string) => {
        setSubmitting(true);
        setError(null);

        try {
            const response = await fetch(
                route("asset-transfers.folder-mappings.ignore"),
                {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrfToken(),
                    },
                    body: JSON.stringify({ folder }),
                },
            );
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || "Action impossible.");
            }

            await loadSources();
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : "Action impossible.",
            );
        } finally {
            setSubmitting(false);
        }
    };

    const autoMapFolders = async () => {
        setSubmitting(true);
        setError(null);

        try {
            const response = await fetch(
                route("asset-transfers.folder-mappings.auto"),
                {
                    method: "POST",
                    headers: {
                        Accept: "application/json",
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": csrfToken(),
                    },
                },
            );
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || "Auto-match impossible.");
            }

            await loadSources();
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : "Auto-match impossible.",
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
                    <Metric label="À rapprocher" value={unresolvedFolderMatches.length} />
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
                                        missingFolders.map((folder) => folder.name),
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
                                    selectedFolders.length === 0
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
                    <div className="flex flex-col gap-4 border-b p-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 className="text-xl font-semibold">
                                Rapprochement dossiers / projets
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Associez les dossiers FTP ou bucket aux projets avant de resynchroniser la base.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => void autoMapFolders()}
                            disabled={submitting || folderMatches.length === 0}
                        >
                            <Wand2 className="mr-2 h-4 w-4" />
                            Auto-match confiance élevée
                        </Button>
                    </div>
                    <div className="max-h-[520px] overflow-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Dossier</TableHead>
                                    <TableHead>Statut</TableHead>
                                    <TableHead>Suggestion</TableHead>
                                    <TableHead>Projet</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {folderMatches.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="py-10 text-center text-sm text-muted-foreground"
                                        >
                                            {loadingSources
                                                ? "Chargement..."
                                                : "Aucun rapprochement chargé."}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    folderMatches.map((match) => {
                                        const selectedProjectId =
                                            mappingSelections[match.folder] || "";

                                        return (
                                            <TableRow key={match.folder}>
                                                <TableCell className="min-w-[260px]">
                                                    <div className="font-medium">
                                                        {match.folder}
                                                    </div>
                                                    <div className="mt-2 flex flex-wrap gap-2">
                                                        <PresenceBadge present={match.onFtp} label="FTP" />
                                                        <PresenceBadge present={match.onBucket} label="Bucket" />
                                                        {match.onBucket && (
                                                            <span className="text-xs text-muted-foreground">
                                                                {match.bucketFileCount} fichiers
                                                            </span>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <FolderMatchBadge status={match.status} />
                                                </TableCell>
                                                <TableCell className="min-w-[220px]">
                                                    {match.mappedProject ? (
                                                        <ProjectSummary
                                                            project={match.mappedProject}
                                                            prefix="Associé"
                                                        />
                                                    ) : match.exactProject ? (
                                                        <ProjectSummary
                                                            project={match.exactProject}
                                                            prefix="Exact"
                                                        />
                                                    ) : match.suggestion ? (
                                                        <div>
                                                            <ProjectSummary
                                                                project={match.suggestion}
                                                                prefix={`${match.suggestion.score}%`}
                                                            />
                                                            {match.suggestion.source && (
                                                                <div className="mt-1 text-xs text-muted-foreground">
                                                                    via {match.suggestion.source}
                                                                </div>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <span className="text-sm text-muted-foreground">
                                                            Aucune suggestion fiable
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="min-w-[260px]">
                                                    <select
                                                        className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                                        value={selectedProjectId}
                                                        onChange={(event) =>
                                                            setMappingSelections(
                                                                (current) => ({
                                                                    ...current,
                                                                    [match.folder]:
                                                                        event.target.value,
                                                                }),
                                                            )
                                                        }
                                                        disabled={submitting}
                                                    >
                                                        <option value="">
                                                            Sélectionner un projet
                                                        </option>
                                                        {projectOptions.map((project) => (
                                                            <option
                                                                key={project.id}
                                                                value={project.id}
                                                            >
                                                                {project.clientName
                                                                    ? `${project.clientName} · `
                                                                    : ""}
                                                                {project.name}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            onClick={() =>
                                                                void mapFolder(
                                                                    match.folder,
                                                                    selectedProjectId,
                                                                )
                                                            }
                                                            disabled={
                                                                submitting ||
                                                                !selectedProjectId
                                                            }
                                                        >
                                                            <Link2 className="mr-2 h-4 w-4" />
                                                            Associer
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                void ignoreFolder(match.folder)
                                                            }
                                                            disabled={submitting}
                                                        >
                                                            Ignorer
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
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
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {folders.length === 0 ? (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
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
                                                            Boolean(activeTransferJob)
                                                        }
                                                        onChange={() =>
                                                            toggleFolder(folder.name)
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
                                            {job.mode === "bucket-db-sync"
                                                ? "projet(s)"
                                                : "dossier(s)"}
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
    const progress =
        job.totalFolders > 0
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
                        {job.processedFolders + job.failedFolders}/
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
                    label={job.mode === "bucket-db-sync" ? "Projets" : "Copiés"}
                    value={job.processedFolders}
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

function ProjectSummary({
    project,
    prefix,
}: {
    project: ProjectOption;
    prefix: string;
}) {
    return (
        <div className="text-sm">
            <div className="font-medium">
                {prefix} · {project.name}
            </div>
            <div className="mt-1 text-xs text-muted-foreground">
                {[
                    project.clientName,
                    project.sourceFolder,
                    typeof project.imagesCount === "number"
                        ? `${project.imagesCount} images`
                        : null,
                ]
                    .filter(Boolean)
                    .join(" · ")}
            </div>
        </div>
    );
}

function FolderMatchBadge({ status }: { status: FolderMatch["status"] }) {
    const variant =
        status === "unmatched"
            ? "destructive"
            : ["mapped", "exact"].includes(status)
              ? "secondary"
              : "outline";

    return (
        <Badge variant={variant}>
            {
                {
                    unmatched: "À traiter",
                    suggested: "Suggestion",
                    mapped: "Associé",
                    exact: "Exact",
                    ignored: "Ignoré",
                }[status]
            }
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
    return job.mode === "bucket-db-sync" ? "Resynchro DB" : "Transfert";
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
