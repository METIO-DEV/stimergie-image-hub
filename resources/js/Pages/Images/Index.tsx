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
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from "@/Components/ui/tabs";
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
import { Head, router } from "@inertiajs/react";
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Pencil,
    Plus,
    RotateCcw,
    Sparkles,
    Square,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";

type FilterOption = {
    id: number;
    name: string;
    clientId?: number;
    clientName?: string;
    sourceFolder?: string | null;
};

type Props = {
    images: LegacyImage[];
    imports: ImportBatch[];
    stats: ImportStats;
    canManageImages: boolean;
    filters: {
        clients: FilterOption[];
        projects: FilterOption[];
    };
};

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

type ImportStats = {
    total: number;
    active: number;
    failed: number;
    completed: number;
};

type ImagesTab = "library" | "imports" | "ai-tags";

type TagAnalysisDashboard = {
    stats: {
        total: number;
        withTags: number;
        withoutTags: number;
    };
    run: TagAnalysisRun | null;
};

type TagAnalysisRun = {
    id: number;
    status: string;
    mode: string;
    totalImages: number;
    processedImages: number;
    failedImages: number;
    currentImage?: {
        id: number;
        title: string;
    } | null;
    startedAt?: string | null;
    finishedAt?: string | null;
};

const PAGE_SIZE = 20;

export default function ImagesIndex({
    images,
    imports,
    stats,
    canManageImages,
    filters,
}: Props) {
    const [viewMode, setViewMode] = useState<ViewMode>("list");
    const [orientation, setOrientation] = useState("");
    const [clientId, setClientId] = useState("");
    const [search, setSearch] = useState("");
    const [tag, setTag] = useState("");
    const [activeTab, setActiveTab] = useState<ImagesTab>("library");
    const [currentPage, setCurrentPage] = useState(1);
    const [selectedImportId, setSelectedImportId] = useState<number | null>(
        imports[0]?.id ?? null,
    );
    const [editingImage, setEditingImage] = useState<LegacyImage | null>(null);
    const [imageModalOpen, setImageModalOpen] = useState(false);
    const [tagAnalysis, setTagAnalysis] =
        useState<TagAnalysisDashboard | null>(null);
    const [tagAnalysisLoading, setTagAnalysisLoading] = useState(false);
    const [tagAnalysisError, setTagAnalysisError] = useState<string | null>(
        null,
    );
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
    const tagAnalysisRunActive =
        tagAnalysis?.run?.status === "pending" ||
        tagAnalysis?.run?.status === "processing";
    const activeImports = useMemo(
        () =>
            imports.filter((importBatch) =>
                ["pending", "processing"].includes(importBatch.status),
            ),
        [imports],
    );
    const selectedImport =
        imports.find((importBatch) => importBatch.id === selectedImportId) ||
        imports[0] ||
        null;

    const refreshTagAnalysis = async () => {
        const response = await window.axios.get<TagAnalysisDashboard>(
            imageTagAnalysisUrl(),
        );

        setTagAnalysis(response.data);
        return response.data;
    };

    useEffect(() => {
        void refreshTagAnalysis().catch(() => undefined);
    }, []);

    useEffect(() => {
        if (activeTab !== "ai-tags") {
            return;
        }

        void refreshTagAnalysis().catch((exception) =>
            setTagAnalysisError(errorMessage(exception)),
        );
    }, [activeTab]);

    useEffect(() => {
        if (!tagAnalysisRunActive) {
            return;
        }

        const interval = window.setInterval(async () => {
            try {
                const dashboard = await refreshTagAnalysis();

                if (
                    dashboard.run &&
                    !["pending", "processing"].includes(dashboard.run.status)
                ) {
                    router.reload({ only: ["images"] });
                }
            } catch (exception) {
                setTagAnalysisError(errorMessage(exception));
            }
        }, 2500);

        return () => window.clearInterval(interval);
    }, [tagAnalysisRunActive]);

    useEffect(() => {
        if (activeTab !== "imports" || activeImports.length === 0) {
            return;
        }

        const interval = window.setInterval(() => {
            router.reload({ only: ["imports", "stats"] });
        }, 5000);

        return () => window.clearInterval(interval);
    }, [activeImports.length, activeTab]);

    const startTagAnalysis = async (payload: {
        mode?: "missing" | "all";
        image_id?: number;
    }) => {
        setTagAnalysisLoading(true);
        setTagAnalysisError(null);

        try {
            const response = await window.axios.post<TagAnalysisDashboard>(
                imageTagAnalysisUrl(),
                payload,
            );
            setTagAnalysis(response.data);
            setActiveTab("ai-tags");
        } catch (exception) {
            setTagAnalysisError(errorMessage(exception));
        } finally {
            setTagAnalysisLoading(false);
        }
    };

    const stopTagAnalysis = async () => {
        const runId = tagAnalysis?.run?.id;

        if (!runId) {
            return;
        }

        setTagAnalysisLoading(true);
        setTagAnalysisError(null);

        try {
            const response = await window.axios.post<TagAnalysisDashboard>(
                imageTagAnalysisStopUrl(runId),
            );
            setTagAnalysis(response.data);
        } catch (exception) {
            setTagAnalysisError(errorMessage(exception));
        } finally {
            setTagAnalysisLoading(false);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Images" />

            <SectionHeader
                title="Images"
                description="Gérez les images, leurs métadonnées et leur rattachement aux projets accessibles."
                action={
                    <div className="flex items-center gap-4">
                        {activeTab === "library" && (
                            <ViewToggle
                                currentView={viewMode}
                                onViewChange={setViewMode}
                            />
                        )}
                        {canManageImages && (
                            <div className="flex items-center gap-3">
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
                <Tabs
                    value={activeTab}
                    onValueChange={(value) => setActiveTab(value as ImagesTab)}
                >
                    <TabsList>
                        <TabsTrigger value="library">Bibliothèque</TabsTrigger>
                        <TabsTrigger value="imports">
                            Suivi des imports
                        </TabsTrigger>
                        <TabsTrigger value="ai-tags">Tags IA</TabsTrigger>
                    </TabsList>

                    <TabsContent value="ai-tags">
                        <TagAnalysisPanel
                            dashboard={tagAnalysis}
                            images={filteredImages}
                            loading={tagAnalysisLoading}
                            error={tagAnalysisError}
                            onAnalyzeMissing={() =>
                                startTagAnalysis({ mode: "missing" })
                            }
                            onAnalyzeImage={(image) =>
                                startTagAnalysis({
                                    image_id: Number(image.id),
                                })
                            }
                            onStop={stopTagAnalysis}
                            onRefresh={() => {
                                setTagAnalysisError(null);
                                setTagAnalysisLoading(true);
                                void refreshTagAnalysis()
                                    .catch((exception) =>
                                        setTagAnalysisError(
                                            errorMessage(exception),
                                        ),
                                    )
                                    .finally(() =>
                                        setTagAnalysisLoading(false),
                                    );
                            }}
                        />
                    </TabsContent>

                    <TabsContent value="imports">
                        <ImportTrackingPanel
                            imports={imports}
                            stats={stats}
                            activeImports={activeImports}
                            selectedImport={selectedImport}
                            onSelectImport={setSelectedImportId}
                        />
                    </TabsContent>

                    <TabsContent value="library">
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
                                onAnalyze={(image) =>
                                    startTagAnalysis({
                                        image_id: Number(image.id),
                                    })
                                }
                                analysisDisabled={
                                    tagAnalysisLoading || tagAnalysisRunActive
                                }
                                onClientOpen={setSelectedClientImage}
                            />
                        )}

                        <LegacyPagination
                            totalCount={filteredImages.length}
                            currentPage={currentPage}
                            onPageChange={setCurrentPage}
                            pageSize={PAGE_SIZE}
                        />
                    </TabsContent>
                </Tabs>
            </main>
            <ImageEditModal
                image={editingImage}
                open={imageModalOpen}
                projects={filters.projects}
                clients={filters.clients}
                onOpenChange={(open) => {
                    setImageModalOpen(open);
                    if (!open) {
                        setEditingImage(null);
                    }
                }}
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

function ImportMetric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded border px-3 py-2">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 font-semibold">{value}</div>
        </div>
    );
}

function ImportTrackingPanel({
    imports,
    stats,
    activeImports,
    selectedImport,
    onSelectImport,
}: {
    imports: ImportBatch[];
    stats: ImportStats;
    activeImports: ImportBatch[];
    selectedImport: ImportBatch | null;
    onSelectImport: (id: number) => void;
}) {
    return (
        <section className="space-y-8">
            <div className="grid gap-4 md:grid-cols-4">
                <ImportMetric label="Imports actifs" value={stats.active} />
                <ImportMetric label="Terminés" value={stats.completed} />
                <ImportMetric label="Avec erreurs" value={stats.failed} />
                <ImportMetric label="Total" value={stats.total} />
            </div>

            {activeImports.length > 0 && (
                <section>
                    <div className="mb-4 flex items-center gap-2">
                        <Clock className="h-5 w-5 text-primary" />
                        <h2 className="text-lg font-semibold">
                            Imports en cours
                        </h2>
                    </div>
                    <div className="grid gap-4 lg:grid-cols-2">
                        {activeImports.map((importBatch) => (
                            <ImportSummaryCard
                                key={importBatch.id}
                                importBatch={importBatch}
                                selected={selectedImport?.id === importBatch.id}
                                onSelect={() => onSelectImport(importBatch.id)}
                            />
                        ))}
                    </div>
                </section>
            )}

            <section className="grid gap-6 lg:grid-cols-[360px_1fr]">
                <div>
                    <h2 className="mb-4 text-lg font-semibold">
                        Historique récent
                    </h2>
                    <div className="overflow-hidden rounded-md border bg-background">
                        {imports.length === 0 ? (
                            <div className="p-5 text-sm text-muted-foreground">
                                Aucun import dossier enregistré.
                            </div>
                        ) : (
                            imports.map((importBatch) => (
                                <button
                                    key={importBatch.id}
                                    type="button"
                                    onClick={() => onSelectImport(importBatch.id)}
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
                                        <StatusBadge
                                            status={importBatch.status}
                                        />
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
                    <h2 className="mb-4 text-lg font-semibold">Détails</h2>
                    {selectedImport ? (
                        <ImportDetails importBatch={selectedImport} />
                    ) : (
                        <div className="rounded-md border bg-background p-6 text-sm text-muted-foreground">
                            Sélectionnez un import pour voir le détail.
                        </div>
                    )}
                </div>
            </section>
        </section>
    );
}

function ImportSummaryCard({
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
            className={`rounded-md border bg-background p-5 text-left transition-colors hover:bg-muted/30 ${
                selected ? "border-primary" : ""
            }`}
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <span className="font-semibold">Import #{importBatch.id}</span>
                <StatusBadge status={importBatch.status} />
            </div>
            <div className="mt-3 text-sm text-muted-foreground">
                {importBatch.clientName || "Entreprise inconnue"} ·{" "}
                {importBatch.projectName || "Projet inconnu"}
            </div>
            <ProgressBar importBatch={importBatch} />
            <div className="mt-4 grid grid-cols-4 gap-3 text-sm">
                <ImportMetric label="Env." value={importBatch.uploadedItems} />
                <ImportMetric
                    label="Trait."
                    value={importBatch.processedItems}
                />
                <ImportMetric
                    label="Doub."
                    value={importBatch.duplicateItems}
                />
                <ImportMetric label="Err." value={importBatch.failedItems} />
            </div>
        </button>
    );
}

function ImportDetails({ importBatch }: { importBatch: ImportBatch }) {
    return (
        <div className="overflow-hidden rounded-md border bg-background">
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
                    <ImportMetric label="Total" value={importBatch.totalItems} />
                    <ImportMetric
                        label="Envoyées"
                        value={importBatch.uploadedItems}
                    />
                    <ImportMetric
                        label="Traitées"
                        value={importBatch.processedItems}
                    />
                    <ImportMetric
                        label="Doublons"
                        value={importBatch.duplicateItems}
                    />
                    <ImportMetric
                        label="Erreurs"
                        value={importBatch.failedItems}
                    />
                </div>
                {importBatch.failedItems > 0 && (
                    <div className="mt-4 flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        Des fichiers sont en erreur. Ouvrez les lignes marquées
                        pour lire le message retourné par le traitement.
                    </div>
                )}
            </div>

            <div className="overflow-x-auto">
                <Table>
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
                                    <TableCell className="min-w-[280px]">
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
                                    <TableCell>
                                        <StatusBadge status={item.status} />
                                    </TableCell>
                                    <TableCell>{item.attempts}</TableCell>
                                    <TableCell>
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

function TagAnalysisPanel({
    dashboard,
    images,
    loading,
    error,
    onAnalyzeMissing,
    onAnalyzeImage,
    onStop,
    onRefresh,
}: {
    dashboard: TagAnalysisDashboard | null;
    images: LegacyImage[];
    loading: boolean;
    error: string | null;
    onAnalyzeMissing: () => void;
    onAnalyzeImage: (image: LegacyImage) => void;
    onStop: () => void;
    onRefresh: () => void;
}) {
    const [missingPage, setMissingPage] = useState(1);
    const run = dashboard?.run;
    const active = run?.status === "pending" || run?.status === "processing";
    const progress = run
        ? Math.min(
              100,
              Math.round(
                  ((run.processedImages + run.failedImages) /
                      Math.max(run.totalImages, 1)) *
                      100,
              ),
          )
        : 0;
    const missingImages = images.filter(
        (image) => !image.tags || image.tags.length === 0,
    );
    const missingPageSize = 10;
    const missingPageCount = Math.max(
        1,
        Math.ceil(missingImages.length / missingPageSize),
    );
    const paginatedMissingImages = missingImages.slice(
        (missingPage - 1) * missingPageSize,
        missingPage * missingPageSize,
    );

    useEffect(() => {
        if (missingPage > missingPageCount) {
            setMissingPage(missingPageCount);
        }
    }, [missingPage, missingPageCount]);

    return (
        <section className="mb-8 space-y-6">
            <div className="grid gap-4 md:grid-cols-3">
                <ImportMetric
                    label="Images gérées"
                    value={dashboard?.stats.total ?? images.length}
                />
                <ImportMetric
                    label="Avec tags"
                    value={
                        dashboard?.stats.withTags ??
                        images.filter((image) => image.tags?.length).length
                    }
                />
                <ImportMetric
                    label="Sans tags"
                    value={
                        dashboard?.stats.withoutTags ??
                        images.filter(
                            (image) => !image.tags || image.tags.length === 0,
                        ).length
                    }
                />
            </div>

            <div className="rounded-md border bg-background p-5">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Analyse IA des tags
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Les images sont analysées une par une via la queue.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={onRefresh}
                            disabled={loading}
                        >
                            <RotateCcw className="mr-2 h-4 w-4" />
                            Actualiser
                        </Button>
                        {active ? (
                            <Button
                                variant="outline"
                                onClick={onStop}
                                disabled={loading}
                            >
                                <Square className="mr-2 h-4 w-4" />
                                Stopper
                            </Button>
                        ) : (
                            <Button
                                onClick={onAnalyzeMissing}
                                disabled={
                                    loading ||
                                    (dashboard?.stats.withoutTags ?? 0) === 0
                                }
                            >
                                <Sparkles className="mr-2 h-4 w-4" />
                                Générer les tags
                            </Button>
                        )}
                    </div>
                </div>

                {run && (
                    <div className="mt-5 space-y-3 rounded-md border p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div className="text-sm font-semibold">
                                    Run #{run.id} · {analysisStatusLabel(run.status)}
                                </div>
                                {run.currentImage && (
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        Image en cours : {run.currentImage.title}
                                    </div>
                                )}
                            </div>
                            <Badge>{progress}%</Badge>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full bg-primary transition-all"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                        <div className="grid gap-3 text-sm sm:grid-cols-3">
                            <ImportMetric
                                label="À analyser"
                                value={run.totalImages}
                            />
                            <ImportMetric
                                label="Réussies"
                                value={run.processedImages}
                            />
                            <ImportMetric
                                label="Erreurs"
                                value={run.failedImages}
                            />
                        </div>
                    </div>
                )}

                {error && (
                    <div className="mt-4 rounded-md border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive">
                        {error}
                    </div>
                )}
            </div>

            <div className="overflow-hidden rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Images sans tags visibles</TableHead>
                            <TableHead>Entreprise</TableHead>
                            <TableHead>Projet</TableHead>
                            <TableHead className="text-right">
                                Action
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {paginatedMissingImages.length === 0 ? (
                            <TableRow>
                                <TableCell
                                    colSpan={4}
                                    className="py-8 text-center text-muted-foreground"
                                >
                                    Aucune image sans tags dans la sélection
                                    courante.
                                </TableCell>
                            </TableRow>
                        ) : (
                            paginatedMissingImages.map((image) => (
                                <TableRow key={image.id}>
                                    <TableCell className="font-medium">
                                        {image.title}
                                    </TableCell>
                                    <TableCell>{image.clientName}</TableCell>
                                    <TableCell>{image.projectName}</TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                onAnalyzeImage(image)
                                            }
                                            disabled={loading || active}
                                        >
                                            <Sparkles className="mr-2 h-4 w-4" />
                                            Analyser
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
                {missingImages.length > missingPageSize && (
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 text-sm">
                        <span className="text-muted-foreground">
                            Page {missingPage} sur {missingPageCount} ·{" "}
                            {missingImages.length} image
                            {missingImages.length > 1 ? "s" : ""} sans tags
                        </span>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={missingPage === 1}
                                onClick={() =>
                                    setMissingPage((page) =>
                                        Math.max(1, page - 1),
                                    )
                                }
                            >
                                Précédent
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={missingPage === missingPageCount}
                                onClick={() =>
                                    setMissingPage((page) =>
                                        Math.min(missingPageCount, page + 1),
                                    )
                                }
                            >
                                Suivant
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}

function imageTagAnalysisUrl(): string {
    return "/image-tag-analysis-runs";
}

function imageTagAnalysisStopUrl(runId: number): string {
    return `/image-tag-analysis-runs/${runId}/stop`;
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

function analysisStatusLabel(status: string): string {
    return (
        {
            pending: "En attente",
            processing: "Analyse en cours",
            completed: "Terminée",
            failed: "Terminée avec erreurs",
            cancelled: "Stoppée",
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
    onAnalyze,
    analysisDisabled,
    onClientOpen,
}: {
    images: LegacyImage[];
    canManageImages: boolean;
    onEdit: (image: LegacyImage) => void;
    onAnalyze: (image: LegacyImage) => void;
    analysisDisabled: boolean;
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
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                title="Régénérer les tags IA"
                                                disabled={analysisDisabled}
                                                onClick={() =>
                                                    onAnalyze(image)
                                                }
                                            >
                                                <Sparkles size={16} />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                title="Modifier"
                                                onClick={() => onEdit(image)}
                                            >
                                                <Pencil size={16} />
                                            </Button>
                                        </div>
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
