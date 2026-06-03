import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import {
    LegacyImage,
    LegacyPagination,
    LegacySelect,
    ClientInfoSheet,
    MasonryGrid,
    SectionHeader,
    ViewMode,
    ViewToggle,
} from "@/Components/Legacy/LegacyDesign";
import { ImageEditModal } from "@/Components/Legacy/LegacyModals";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router } from "@inertiajs/react";
import { FolderUp, Pencil, Plus, RotateCcw, Upload } from "lucide-react";
import { FormEvent, useEffect, useMemo, useState } from "react";

type FilterOption = {
    id: number;
    name: string;
    clientId?: number;
    clientName?: string;
    sourceFolder?: string | null;
};

type Props = {
    images: LegacyImage[];
    canManageImages: boolean;
    filters: {
        clients: FilterOption[];
        projects: FilterOption[];
    };
};

type ImportSummary = {
    id: number;
    status: string;
    projectName?: string | null;
    clientName?: string | null;
    totalItems: number;
    uploadedItems: number;
    processedItems: number;
    failedItems: number;
    duplicateItems: number;
    items: Array<{
        id: number;
        filename: string;
        relativePath?: string | null;
        status: string;
        error?: string | null;
    }>;
};

type ImportProjectMode = "existing" | "new";

const PAGE_SIZE = 20;

const folderSegment = (value: string, fallback: string) => {
    const normalized = value
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");

    return normalized || fallback;
};

const generatedProjectFolder = (clientName: string, projectName: string) =>
    `${folderSegment(clientName, "entreprise")}_${folderSegment(projectName, "projet")}`;

export default function ImagesIndex({
    images,
    canManageImages,
    filters,
}: Props) {
    const [viewMode, setViewMode] = useState<ViewMode>("list");
    const [orientation, setOrientation] = useState("");
    const [clientId, setClientId] = useState("");
    const [search, setSearch] = useState("");
    const [tag, setTag] = useState("");
    const [currentPage, setCurrentPage] = useState(1);
    const [editingImage, setEditingImage] = useState<LegacyImage | null>(null);
    const [imageModalOpen, setImageModalOpen] = useState(false);
    const [importModalOpen, setImportModalOpen] = useState(false);
    const [selectedClientImage, setSelectedClientImage] =
        useState<LegacyImage | null>(null);

    const filteredImages = useMemo(() => {
        const query = search.trim().toLowerCase();
        const tagQuery = tag.trim().toLowerCase();

        return images.filter((image) => {
            const matchesSearch =
                !query || image.title.toLowerCase().includes(query);
            const matchesTag =
                !tagQuery ||
                image.tags?.some((tagName) =>
                    tagName.toLowerCase().includes(tagQuery),
                );
            const matchesOrientation =
                !orientation || image.orientation === orientation;
            const matchesClient =
                !clientId || String(image.clientId) === clientId;

            return (
                matchesSearch &&
                matchesTag &&
                matchesOrientation &&
                matchesClient
            );
        });
    }, [clientId, images, orientation, search, tag]);

    const paginatedImages = filteredImages.slice(
        (currentPage - 1) * PAGE_SIZE,
        currentPage * PAGE_SIZE,
    );

    return (
        <AuthenticatedLayout>
            <Head title="Images" />

            <SectionHeader
                title="Images"
                description="Gérez les images, leurs métadonnées et leur rattachement aux projets accessibles."
                action={
                    <div className="flex items-center gap-4">
                        <ViewToggle
                            currentView={viewMode}
                            onViewChange={setViewMode}
                        />
                        {canManageImages && (
                            <div className="flex items-center gap-3">
                                <Button
                                    variant="outline"
                                    onClick={() => setImportModalOpen(true)}
                                >
                                    <FolderUp size={16} className="mr-2" />
                                    Importer un dossier
                                </Button>
                                <Button
                                    onClick={() => {
                                        setEditingImage(null);
                                        setImageModalOpen(true);
                                    }}
                                >
                                    <Plus size={16} className="mr-2" />
                                    Ajouter une image
                                </Button>
                            </div>
                        )}
                    </div>
                }
            />

            <main className="mx-auto max-w-7xl px-6 py-12">
                <div className="mb-6 flex flex-wrap items-center gap-4">
                    <LegacySelect
                        value={clientId}
                        onChange={(value) => {
                            setClientId(value);
                            setCurrentPage(1);
                        }}
                        allLabel="Toutes les entreprises"
                        options={filters.clients}
                        className="w-full sm:w-64"
                    />
                    <LegacySelect
                        value={orientation}
                        onChange={(value) => {
                            setOrientation(value);
                            setCurrentPage(1);
                        }}
                        allLabel="Toutes les orientations"
                        options={[
                            { id: "landscape", name: "Paysage" },
                            { id: "portrait", name: "Portrait" },
                            { id: "square", name: "Carré" },
                        ]}
                        className="w-full sm:w-64"
                    />
                    <Input
                        value={search}
                        onChange={(event) => {
                            setSearch(event.target.value);
                            setCurrentPage(1);
                        }}
                        placeholder="Rechercher par titre..."
                        className="min-w-[200px] flex-1"
                    />
                    <Input
                        value={tag}
                        onChange={(event) => {
                            setTag(event.target.value);
                            setCurrentPage(1);
                        }}
                        placeholder="Filtrer par tag..."
                        className="w-full sm:w-64"
                    />
                </div>

                {viewMode === "card" ? (
                    <MasonryGrid
                        images={paginatedImages}
                        onImageClick={
                            canManageImages
                                ? (image) => {
                                      setEditingImage(image);
                                      setImageModalOpen(true);
                                  }
                                : undefined
                        }
                    />
                ) : (
                    <ImagesTable
                        images={paginatedImages}
                        canManageImages={canManageImages}
                        onEdit={(image) => {
                            setEditingImage(image);
                            setImageModalOpen(true);
                        }}
                        onClientOpen={setSelectedClientImage}
                    />
                )}

                <LegacyPagination
                    totalCount={filteredImages.length}
                    currentPage={currentPage}
                    onPageChange={setCurrentPage}
                    pageSize={PAGE_SIZE}
                />

            </main>
            <ImageEditModal
                image={editingImage}
                open={imageModalOpen}
                projects={filters.projects}
                onOpenChange={(open) => {
                    setImageModalOpen(open);
                    if (!open) {
                        setEditingImage(null);
                    }
                }}
            />
            <FolderImportModal
                open={importModalOpen}
                clients={filters.clients}
                projects={filters.projects}
                onOpenChange={setImportModalOpen}
            />
            <ClientInfoSheet
                client={
                    selectedClientImage?.client ||
                    (selectedClientImage?.clientName
                        ? {
                              id: selectedClientImage.clientId,
                              name: selectedClientImage.clientName,
                          }
                        : null)
                }
                onClose={() => setSelectedClientImage(null)}
            />
        </AuthenticatedLayout>
    );
}

function FolderImportModal({
    open,
    clients,
    projects,
    onOpenChange,
}: {
    open: boolean;
    clients: FilterOption[];
    projects: FilterOption[];
    onOpenChange: (open: boolean) => void;
}) {
    const [projectMode, setProjectMode] =
        useState<ImportProjectMode>("existing");
    const [projectId, setProjectId] = useState("");
    const [newProject, setNewProject] = useState({
        client_id: "",
        name: "",
        type: "",
        source_folder: "",
    });
    const [sourceFolderTouched, setSourceFolderTouched] = useState(false);
    const [files, setFiles] = useState<File[]>([]);
    const [summary, setSummary] = useState<ImportSummary | null>(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const imageFiles = useMemo(
        () => files.filter((file) => file.type.startsWith("image/")),
        [files],
    );
    const ignoredCount = files.length - imageFiles.length;
    const totalBytes = imageFiles.reduce((total, file) => total + file.size, 0);
    const terminal =
        summary?.status === "completed" ||
        summary?.status === "failed" ||
        summary?.status === "cancelled";
    const progressTotal = summary?.totalItems || imageFiles.length || 1;
    const progressDone =
        (summary?.processedItems || 0) + (summary?.failedItems || 0);
    const progress = Math.min(100, Math.round((progressDone / progressTotal) * 100));
    const selectedProject = projects.find(
        (project) => String(project.id) === projectId,
    );
    const selectedClient = clients.find(
        (client) => String(client.id) === newProject.client_id,
    );

    useEffect(() => {
        if (!open || !summary || terminal) {
            return;
        }

        const interval = window.setInterval(async () => {
            const response = await window.axios.get<ImportSummary>(
                imageImportUrl(summary.id),
            );
            setSummary(response.data);

            if (response.data.status === "completed") {
                router.reload({ only: ["images"] });
            }
        }, 2500);

        return () => window.clearInterval(interval);
    }, [open, summary, terminal]);

    useEffect(() => {
        if (open) {
            return;
        }

        setProjectId("");
        setProjectMode("existing");
        setNewProject({
            client_id: "",
            name: "",
            type: "",
            source_folder: "",
        });
        setSourceFolderTouched(false);
        setFiles([]);
        setSummary(null);
        setError(null);
        setUploading(false);
    }, [open]);

    useEffect(() => {
        if (
            projectMode !== "new" ||
            sourceFolderTouched ||
            !selectedClient
        ) {
            return;
        }

        setNewProject((current) => ({
            ...current,
            source_folder: generatedProjectFolder(
                selectedClient.name,
                current.name,
            ),
        }));
    }, [projectMode, selectedClient, sourceFolderTouched, newProject.name]);

    const updateNewProject = (
        field: keyof typeof newProject,
        value: string,
    ) => {
        setNewProject((current) => ({ ...current, [field]: value }));
    };

    const submit = async (event: FormEvent) => {
        event.preventDefault();

        if (imageFiles.length === 0) {
            setError("Sélectionnez au moins une image.");
            return;
        }

        if (projectMode === "existing" && !projectId) {
            setError("Sélectionnez un projet existant.");
            return;
        }

        if (
            projectMode === "new" &&
            (!newProject.client_id || !newProject.name.trim())
        ) {
            setError("Renseignez l'entreprise et le nom du nouveau projet.");
            return;
        }

        setUploading(true);
        setError(null);

        try {
            const batchResponse = await window.axios.post<ImportSummary>(
                imageImportUrl(),
                {
                    ...(projectMode === "existing"
                        ? { project_id: Number(projectId) }
                        : {
                              new_project: {
                                  client_id: Number(newProject.client_id),
                                  name: newProject.name,
                                  type: newProject.type,
                                  source_folder: newProject.source_folder,
                              },
                          }),
                    total_items: imageFiles.length,
                    total_bytes: totalBytes,
                },
            );

            let latestSummary = batchResponse.data;
            setSummary(latestSummary);

            for (const file of imageFiles) {
                const payload = new FormData();
                payload.append("file", file);
                payload.append("relative_path", relativePath(file));

                const itemResponse = await window.axios.post<ImportSummary>(
                    imageImportItemUrl(latestSummary.id),
                    payload,
                    {
                        headers: {
                            "Content-Type": "multipart/form-data",
                        },
                    },
                );

                latestSummary = itemResponse.data;
                setSummary(latestSummary);
            }
        } catch (exception) {
            setError(errorMessage(exception));
        } finally {
            setUploading(false);
        }
    };

    const retryFailed = async () => {
        if (!summary) {
            return;
        }

        setUploading(true);
        setError(null);

        try {
            const response = await window.axios.post<ImportSummary>(
                imageImportRetryUrl(summary.id),
            );
            setSummary(response.data);
        } catch (exception) {
            setError(errorMessage(exception));
        } finally {
            setUploading(false);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(nextOpen) => {
                if (!nextOpen && uploading) {
                    return;
                }

                onOpenChange(nextOpen);
            }}
        >
            <DialogContent className="max-h-[90vh] max-w-2xl overflow-hidden">
                <DialogHeader>
                    <DialogTitle>Importer un dossier d'images</DialogTitle>
                    <DialogDescription>
                        Les images sont envoyées une par une puis traitées en
                        arrière-plan pour éviter les surcharges.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit}>
                    <div className="max-h-[calc(90vh-210px)] space-y-5 overflow-y-auto pr-2">
                        <div className="grid grid-cols-2 gap-2 rounded-md bg-muted p-1">
                            <button
                                type="button"
                                className={`rounded px-3 py-2 text-sm font-semibold transition ${
                                    projectMode === "existing"
                                        ? "bg-background text-foreground shadow-sm"
                                        : "text-muted-foreground hover:text-foreground"
                                }`}
                                disabled={uploading || Boolean(summary)}
                                onClick={() => {
                                    setProjectMode("existing");
                                    setError(null);
                                }}
                            >
                                Projet existant
                            </button>
                            <button
                                type="button"
                                className={`rounded px-3 py-2 text-sm font-semibold transition ${
                                    projectMode === "new"
                                        ? "bg-background text-foreground shadow-sm"
                                        : "text-muted-foreground hover:text-foreground"
                                }`}
                                disabled={uploading || Boolean(summary)}
                                onClick={() => {
                                    setProjectMode("new");
                                    setError(null);
                                }}
                            >
                                Nouveau projet
                            </button>
                        </div>

                        <div className="space-y-2">
                            {projectMode === "existing" ? (
                                <>
                                    <Label htmlFor="folder-import-project">
                                        Projet
                                    </Label>
                                    <select
                                        id="folder-import-project"
                                        value={projectId}
                                        onChange={(event) =>
                                            setProjectId(event.target.value)
                                        }
                                        className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                        disabled={uploading || Boolean(summary)}
                                    >
                                        <option value="">
                                            Sélectionner un projet
                                        </option>
                                        {projects.map((project) => (
                                            <option
                                                key={project.id}
                                                value={project.id}
                                            >
                                                {project.clientName
                                                    ? `${project.clientName} - ${project.name}`
                                                    : project.name}
                                            </option>
                                        ))}
                                    </select>
                                    <p className="text-xs text-muted-foreground">
                                        Les images seront ajoutées au dossier du
                                        projet sélectionné
                                        {selectedProject?.sourceFolder
                                            ? ` : ${selectedProject.sourceFolder}`
                                            : "."}
                                    </p>
                                </>
                            ) : (
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="folder-import-client">
                                            Entreprise
                                        </Label>
                                        <select
                                            id="folder-import-client"
                                            value={newProject.client_id}
                                            onChange={(event) => {
                                                updateNewProject(
                                                    "client_id",
                                                    event.target.value,
                                                );
                                                setSourceFolderTouched(false);
                                            }}
                                            className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                            disabled={
                                                uploading || Boolean(summary)
                                            }
                                        >
                                            <option value="">
                                                Sélectionner une entreprise
                                            </option>
                                            {clients.map((client) => (
                                                <option
                                                    key={client.id}
                                                    value={client.id}
                                                >
                                                    {client.name}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="folder-import-project-name">
                                            Nom du projet
                                        </Label>
                                        <Input
                                            id="folder-import-project-name"
                                            value={newProject.name}
                                            disabled={
                                                uploading || Boolean(summary)
                                            }
                                            onChange={(event) =>
                                                updateNewProject(
                                                    "name",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="folder-import-project-type">
                                            Type de projet
                                        </Label>
                                        <Input
                                            id="folder-import-project-type"
                                            value={newProject.type}
                                            disabled={
                                                uploading || Boolean(summary)
                                            }
                                            onChange={(event) =>
                                                updateNewProject(
                                                    "type",
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="folder-import-source-folder">
                                            Nom du dossier
                                        </Label>
                                        <Input
                                            id="folder-import-source-folder"
                                            value={newProject.source_folder}
                                            disabled={
                                                uploading || Boolean(summary)
                                            }
                                            onChange={(event) => {
                                                setSourceFolderTouched(true);
                                                updateNewProject(
                                                    "source_folder",
                                                    event.target.value,
                                                );
                                            }}
                                        />
                                    </div>
                                </div>
                            )}
                        </div>

                        <label className="flex cursor-pointer flex-col items-center rounded-lg border-2 border-dashed p-10 text-center transition-colors hover:bg-muted/50">
                            <Upload className="h-9 w-9 text-muted-foreground" />
                            <span className="mt-3 text-sm font-medium">
                                Choisir un dossier
                            </span>
                            <span className="mt-1 text-xs text-muted-foreground">
                                JPEG, PNG ou WebP, 100 Mo maximum par image.
                            </span>
                            <input
                                type="file"
                                accept="image/*"
                                multiple
                                className="hidden"
                                disabled={uploading || Boolean(summary)}
                                onChange={(event) =>
                                    setFiles(
                                        Array.from(event.target.files ?? []),
                                    )
                                }
                                {...folderInputAttributes()}
                            />
                        </label>

                        {files.length > 0 && (
                            <div className="rounded-md border p-4 text-sm">
                                <div className="font-medium">
                                    {imageFiles.length} image
                                    {imageFiles.length > 1 ? "s" : ""} prête
                                    {imageFiles.length > 1 ? "s" : ""} à importer
                                </div>
                                <div className="mt-1 text-muted-foreground">
                                    {formatBytes(totalBytes)}
                                    {ignoredCount > 0
                                        ? ` · ${ignoredCount} fichier${ignoredCount > 1 ? "s" : ""} ignoré${ignoredCount > 1 ? "s" : ""}`
                                        : ""}
                                </div>
                            </div>
                        )}

                        {summary && (
                            <div className="space-y-4 rounded-md border p-4">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div className="text-sm font-semibold">
                                            Import #{summary.id}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {statusLabel(summary.status)}
                                        </div>
                                    </div>
                                    <Badge>{progress}%</Badge>
                                </div>
                                <div className="h-2 overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full bg-primary transition-all"
                                        style={{ width: `${progress}%` }}
                                    />
                                </div>
                                <div className="grid gap-3 text-sm sm:grid-cols-4">
                                    <ImportMetric
                                        label="Envoyées"
                                        value={summary.uploadedItems}
                                    />
                                    <ImportMetric
                                        label="Traitées"
                                        value={summary.processedItems}
                                    />
                                    <ImportMetric
                                        label="Doublons"
                                        value={summary.duplicateItems}
                                    />
                                    <ImportMetric
                                        label="Erreurs"
                                        value={summary.failedItems}
                                    />
                                </div>
                                {summary.items.length > 0 && (
                                    <div className="max-h-44 overflow-y-auto rounded border">
                                        {summary.items.map((item) => (
                                            <div
                                                key={item.id}
                                                className="flex items-start justify-between gap-3 border-b px-3 py-2 text-xs last:border-b-0"
                                            >
                                                <div className="min-w-0">
                                                    <div className="truncate font-medium">
                                                        {item.relativePath ||
                                                            item.filename}
                                                    </div>
                                                    {item.error && (
                                                        <div className="mt-1 text-destructive">
                                                            {item.error}
                                                        </div>
                                                    )}
                                                </div>
                                                <span className="shrink-0 text-muted-foreground">
                                                    {statusLabel(item.status)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        {error && (
                            <div className="rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                                {error}
                            </div>
                        )}
                    </div>

                    <DialogFooter className="border-t pt-4">
                        {summary?.failedItems ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={retryFailed}
                                disabled={uploading}
                            >
                                <RotateCcw className="mr-2 h-4 w-4" />
                                Relancer les erreurs
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={uploading}
                        >
                            Fermer
                        </Button>
                        {summary && (
                            <Button type="button" variant="outline" asChild>
                                <Link href={route("imports.index")}>
                                    Voir le suivi
                                </Link>
                            </Button>
                        )}
                        {!summary && (
                            <Button
                                type="submit"
                                disabled={uploading}
                            >
                                {uploading
                                    ? "Import en cours..."
                                    : "Lancer l'import"}
                            </Button>
                        )}
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ImportMetric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded border px-3 py-2">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 font-semibold">{value}</div>
        </div>
    );
}

function relativePath(file: File): string {
    return (
        (file as File & { webkitRelativePath?: string }).webkitRelativePath ||
        file.name
    );
}

function folderInputAttributes() {
    return {
        webkitdirectory: "",
        directory: "",
    } as Record<string, string>;
}

function imageImportUrl(importId?: number): string {
    return importId ? `/image-imports/${importId}` : "/image-imports";
}

function imageImportItemUrl(importId: number): string {
    return `/image-imports/${importId}/items`;
}

function imageImportRetryUrl(importId: number): string {
    return `/image-imports/${importId}/retry-failed`;
}

function formatBytes(bytes: number): string {
    if (bytes === 0) {
        return "0 octet";
    }

    const units = ["octets", "Ko", "Mo", "Go"];
    const exponent = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );
    const value = bytes / 1024 ** exponent;

    return `${value.toFixed(value >= 10 || exponent === 0 ? 0 : 1)} ${units[exponent]}`;
}

function statusLabel(status: string): string {
    return (
        {
            pending: "En attente",
            uploaded: "Envoyée",
            processing: "Traitement",
            done: "Terminée",
            completed: "Terminé",
            failed: "Erreur",
            duplicate: "Doublon",
        }[status] || status
    );
}

function errorMessage(exception: unknown): string {
    if (
        typeof exception === "object" &&
        exception !== null &&
        "response" in exception
    ) {
        const response = (
            exception as {
                response?: {
                    status?: number;
                    data?: {
                        message?: string;
                        errors?: Record<string, string[]>;
                    };
                };
            }
        ).response;

        if (response?.status === 413) {
            return "Le fichier est trop volumineux pour la configuration du serveur.";
        }

        if (response?.data?.errors) {
            const firstError = Object.values(response.data.errors)
                .flat()
                .find(Boolean);

            if (firstError) {
                return firstError;
            }
        }

        if (response?.data?.message) {
            return response.data.message;
        }
    }

    if (exception instanceof Error && exception.message) {
        return exception.message;
    }

    return "L'import n'a pas pu être lancé. Réessayez dans quelques instants.";
}

function ImagesTable({
    images,
    canManageImages,
    onEdit,
    onClientOpen,
}: {
    images: LegacyImage[];
    canManageImages: boolean;
    onEdit: (image: LegacyImage) => void;
    onClientOpen: (image: LegacyImage) => void;
}) {
    return (
        <div className="overflow-hidden rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Image</TableHead>
                        <TableHead>Titre</TableHead>
                        <TableHead>Entreprise</TableHead>
                        <TableHead>Dimensions</TableHead>
                        <TableHead>Orientation</TableHead>
                        <TableHead>Tags</TableHead>
                        <TableHead>Date d'ajout</TableHead>
                        {canManageImages && (
                            <TableHead className="text-right">
                                Actions
                            </TableHead>
                        )}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {images.length === 0 ? (
                        <TableRow>
                            <TableCell
                                colSpan={canManageImages ? 8 : 7}
                                className="py-10 text-center"
                            >
                                Aucune image disponible
                            </TableCell>
                        </TableRow>
                    ) : (
                        images.map((image) => (
                            <TableRow key={image.id}>
                                <TableCell>
                                    <button
                                        type="button"
                                        className={`relative h-16 w-16 overflow-hidden rounded ${
                                            canManageImages
                                                ? "transition-opacity hover:opacity-80"
                                                : ""
                                        }`}
                                        onClick={() => onEdit(image)}
                                        disabled={!canManageImages}
                                    >
                                        {image.thumbUrl || image.imageUrl ? (
                                            <img
                                                src={
                                                    image.thumbUrl ||
                                                    image.imageUrl ||
                                                    ""
                                                }
                                                alt={image.title}
                                                className="h-full w-full object-cover"
                                                loading="lazy"
                                            />
                                        ) : (
                                            <div className="h-full w-full bg-muted" />
                                        )}
                                    </button>
                                </TableCell>
                                <TableCell className="font-medium">
                                    {image.title}
                                </TableCell>
                                <TableCell>
                                    {image.clientName ? (
                                        <button
                                            type="button"
                                            className="font-medium text-primary hover:underline"
                                            onClick={() => onClientOpen(image)}
                                        >
                                            {image.clientName}
                                        </button>
                                    ) : (
                                        "N/A"
                                    )}
                                </TableCell>
                                <TableCell>
                                    {image.width && image.height
                                        ? `${image.width} × ${image.height}`
                                        : "-"}
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        variant="outline"
                                        className="capitalize"
                                    >
                                        {labelOrientation(image.orientation)}
                                    </Badge>
                                </TableCell>
                                <TableCell>
                                    <div className="flex flex-wrap gap-1">
                                        {image.tags && image.tags.length > 0 ? (
                                            image.tags
                                                .slice(0, 3)
                                                .map((tag) => (
                                                    <Badge
                                                        key={tag}
                                                        variant="secondary"
                                                        className="text-xs"
                                                    >
                                                        #{tag}
                                                    </Badge>
                                                ))
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                Aucun tag
                                            </span>
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    {formatDate(image.createdAt)}
                                </TableCell>
                                {canManageImages && (
                                    <TableCell className="text-right">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title="Modifier"
                                            onClick={() => onEdit(image)}
                                        >
                                            <Pencil size={16} />
                                        </Button>
                                    </TableCell>
                                )}
                            </TableRow>
                        ))
                    )}
                </TableBody>
            </Table>
        </div>
    );
}

function labelOrientation(orientation?: string | null) {
    if (orientation === "landscape") {
        return "Paysage";
    }

    if (orientation === "square") {
        return "Carré";
    }

    return orientation || "-";
}

function formatDate(value?: string) {
    if (!value) {
        return "-";
    }

    return new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(new Date(value));
}
