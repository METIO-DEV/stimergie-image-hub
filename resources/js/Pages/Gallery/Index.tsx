import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import {
    LegacyImage,
    ImageInfoSheet,
    LegacyPagination,
    LegacySearch,
    LegacySelect,
    MasonryGrid,
} from "@/Components/Legacy/LegacyDesign";
import { ImageEditModal } from "@/Components/Legacy/LegacyModals";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, router, usePage } from "@inertiajs/react";
import {
    Download,
    FolderInput,
    Infinity,
    Info,
    Plus,
    Share2,
    SquareCheck,
    X,
} from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

type FilterOption = {
    id: number;
    name: string;
    clientId?: number;
    clientName?: string;
};

type Props = {
    images: LegacyImage[];
    stats: {
        images: number;
        clients: number;
        projects: number;
    };
    filters: {
        clients: FilterOption[];
        projects: FilterOption[];
        tags: FilterOption[];
    };
    activeFilters: {
        search: string;
        orientation: string;
        clientId: string;
        projectId: string;
        tag: string;
        dateFrom: string;
        dateTo: string;
    };
    bulkProjects: FilterOption[];
    canAddImages: boolean;
    canBulkAssignImages: boolean;
    canCreateSharedAlbums: boolean;
    pagination: {
        currentPage: number;
        perPage: number;
        total: number;
    };
};

const PAGE_SIZE = 100;
const SEARCH_DEBOUNCE_MS = 700;
const FILTER_DEBOUNCE_MS = 350;

export default function GalleryIndex({
    images,
    filters,
    activeFilters,
    bulkProjects,
    canAddImages,
    canBulkAssignImages,
    canCreateSharedAlbums,
    pagination,
}: Props) {
    const user = usePage().props.auth.user;
    const [search, setSearch] = useState(activeFilters.search);
    const [orientation, setOrientation] = useState(activeFilters.orientation);
    const [clientId, setClientId] = useState(activeFilters.clientId);
    const [projectId, setProjectId] = useState(activeFilters.projectId);
    const [tag, setTag] = useState(activeFilters.tag);
    const [dateFrom, setDateFrom] = useState(activeFilters.dateFrom);
    const [dateTo, setDateTo] = useState(activeFilters.dateTo);
    const [currentPage, setCurrentPage] = useState(pagination.currentPage);
    const [searchFocused, setSearchFocused] = useState(false);
    const [infiniteScroll, setInfiniteScroll] = useState(false);
    const didMount = useRef(false);
    const [selectedImages, setSelectedImages] = useState<
        Array<string | number>
    >([]);
    const [detailImage, setDetailImage] = useState<LegacyImage | null>(null);
    const [editingImage, setEditingImage] = useState<LegacyImage | null>(null);
    const [imageModalOpen, setImageModalOpen] = useState(false);
    const [bulkProjectOpen, setBulkProjectOpen] = useState(false);
    const [bulkProjectId, setBulkProjectId] = useState("");
    const [shareOpen, setShareOpen] = useState(false);
    const [shareName, setShareName] = useState("");
    const [shareDescription, setShareDescription] = useState("");
    const [shareRecipients, setShareRecipients] = useState("");
    const [shareMessage, setShareMessage] = useState("");
    const [shareStartsAt, setShareStartsAt] = useState("");
    const [shareExpiresAt, setShareExpiresAt] = useState("");
    const pendingFilterRequest = useRef<number | null>(null);
    const previousFilterState = useRef({
        search,
        orientation,
        clientId,
        projectId,
        tag,
        dateFrom,
        dateTo,
    });

    const projects = useMemo(
        () =>
            clientId
                ? filters.projects.filter(
                      (project) => String(project.clientId) === clientId,
                  )
                : filters.projects,
        [clientId, filters.projects],
    );

    const selectedImageItems = images.filter((image) =>
        selectedImages.includes(image.id),
    );
    const selectionHasExpiredRights = selectedImageItems.some(
        (image) => image.rightsStatus === "expired",
    );
    const selectionCanBeAssigned =
        canBulkAssignImages &&
        selectedImageItems.length === selectedImages.length &&
        selectedImageItems.every((image) => image.canManage);
    const paginatedImages = images;
    const hasActiveFilters =
        search.trim() !== "" ||
        orientation !== "" ||
        clientId !== "" ||
        projectId !== "" ||
        tag.trim() !== "" ||
        dateFrom !== "" ||
        dateTo !== "";
    const resetFilters = () => {
        setSearch("");
        setOrientation("");
        setClientId("");
        setProjectId("");
        setTag("");
        setDateFrom("");
        setDateTo("");
        setCurrentPage(1);
    };

    const filterParams = (
        nextPage = 1,
        overrides: Partial<{
            search: string;
            orientation: string;
            clientId: string;
            projectId: string;
            tag: string;
            dateFrom: string;
            dateTo: string;
        }> = {},
    ): Record<string, string | number | undefined> => ({
        search: (overrides.search ?? search).trim() || undefined,
        orientation: (overrides.orientation ?? orientation) || undefined,
        client_id: (overrides.clientId ?? clientId) || undefined,
        project_id: (overrides.projectId ?? projectId) || undefined,
        tag: (overrides.tag ?? tag).trim() || undefined,
        date_from: (overrides.dateFrom ?? dateFrom) || undefined,
        date_to: (overrides.dateTo ?? dateTo) || undefined,
        page: nextPage > 1 ? nextPage : undefined,
    });

    const clearPendingFilterRequest = () => {
        if (pendingFilterRequest.current !== null) {
            window.clearTimeout(pendingFilterRequest.current);
            pendingFilterRequest.current = null;
        }
    };

    const submitSearch = (nextSearch = search) => {
        clearPendingFilterRequest();
        setSearch(nextSearch);
        setCurrentPage(1);
        router.get(
            route("gallery.index"),
            filterParams(1, { search: nextSearch }),
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ["images", "activeFilters", "pagination"],
            },
        );
    };

    useEffect(() => {
        if (!searchFocused) {
            setSearch(activeFilters.search);
        }
        setOrientation(activeFilters.orientation);
        setClientId(activeFilters.clientId);
        setProjectId(activeFilters.projectId);
        setTag(activeFilters.tag);
        setDateFrom(activeFilters.dateFrom);
        setDateTo(activeFilters.dateTo);
        setCurrentPage(pagination.currentPage);
    }, [
        activeFilters.clientId,
        activeFilters.dateFrom,
        activeFilters.dateTo,
        activeFilters.orientation,
        activeFilters.projectId,
        activeFilters.search,
        activeFilters.tag,
        pagination.currentPage,
        searchFocused,
    ]);

    useEffect(() => {
        if (!didMount.current) {
            didMount.current = true;

            return;
        }

        const nextFilterState = {
            search,
            orientation,
            clientId,
            projectId,
            tag,
            dateFrom,
            dateTo,
        };
        const searchChanged =
            previousFilterState.current.search !== nextFilterState.search;

        previousFilterState.current = nextFilterState;

        const timeout = window.setTimeout(() => {
            pendingFilterRequest.current = null;
            setCurrentPage(1);
            router.get(route("gallery.index"), filterParams(), {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ["images", "activeFilters", "pagination"],
            });
        }, searchChanged ? SEARCH_DEBOUNCE_MS : FILTER_DEBOUNCE_MS);

        pendingFilterRequest.current = timeout;

        return () => {
            if (pendingFilterRequest.current === timeout) {
                pendingFilterRequest.current = null;
            }
            window.clearTimeout(timeout);
        };
    }, [clientId, dateFrom, dateTo, orientation, projectId, search, tag]);

    const handlePageChange = (page: number) => {
        setCurrentPage(page);
        router.get(route("gallery.index"), filterParams(page), {
            preserveScroll: true,
            preserveState: false,
            only: ["images", "activeFilters", "pagination"],
        });
    };

    const toggleSelection = (id: string | number) => {
        setSelectedImages((current) =>
            current.includes(id)
                ? current.filter((selectedId) => selectedId !== id)
                : [...current, id],
        );
    };

    const filterByTag = (nextTag: string) => {
        setTag(nextTag);
        setCurrentPage(1);
        setDetailImage(null);
    };

    const assignSelectionToProject = () => {
        router.patch(
            route("images.bulk-project"),
            {
                project_id: bulkProjectId,
                image_ids: selectedImages.map((id) => Number(id)),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedImages([]);
                    setBulkProjectId("");
                    setBulkProjectOpen(false);
                },
            },
        );
    };

    const requestDownload = (variant: "web" | "hd") => {
        if (selectionHasExpiredRights) {
            return;
        }

        router.post(
            route("downloads.store"),
            {
                variant,
                image_ids: selectedImages.map((id) => Number(id)),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedImages([]);
                },
            },
        );
    };

    const requestRightsExtension = (image: LegacyImage) => {
        if (!image.rightsExtensionRequestUrl) {
            return;
        }

        router.post(
            image.rightsExtensionRequestUrl,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setDetailImage((current) =>
                        current?.id === image.id
                            ? {
                                  ...current,
                                  rightsExtensionRequestedAt:
                                      new Date().toISOString(),
                                  canRequestRightsExtension: false,
                              }
                            : current,
                    );
                    router.reload({
                        only: ["images"],
                    });
                },
            },
        );
    };

    const editImageTags = (image: LegacyImage) => {
        if (!image.canManage) {
            return;
        }

        setEditingImage(image);
        setImageModalOpen(true);
    };

    const openShareDialog = () => {
        setShareName(
            selectedImageItems.length === 1
                ? selectedImageItems[0].title
                : `Sélection de ${selectedImages.length} images`,
        );
        setShareOpen(true);
    };

    const createSharedAlbum = () => {
        router.post(
            route("shared-albums.store"),
            {
                name: shareName,
                description: shareDescription,
                recipients: shareRecipients,
                message: shareMessage,
                starts_at: shareStartsAt || null,
                expires_at: shareExpiresAt || null,
                image_ids: selectedImages.map((id) => Number(id)),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedImages([]);
                    setShareOpen(false);
                    setShareName("");
                    setShareDescription("");
                    setShareRecipients("");
                    setShareMessage("");
                    setShareStartsAt("");
                    setShareExpiresAt("");
                },
            },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Banque d'images" />

            <main className="min-w-0 flex-grow overflow-x-hidden px-0">
                <section className="border-b border-border bg-[#dcd0bb]">
                    <div className="mx-auto max-w-7xl px-4 py-4 sm:px-6">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <div className="min-w-0">
                                <div className="flex min-w-0 items-center gap-2">
                                    <h1 className="break-words text-xl font-bold leading-tight sm:text-2xl">
                                        Banque d'images
                                    </h1>
                                    <details className="group relative shrink-0">
                                        <summary
                                            className="flex h-5 w-5 cursor-pointer list-none items-center justify-center rounded-full text-[#150B0D]/45 transition hover:bg-[#150B0D]/5 hover:text-[#150B0D]/75 focus:outline-none focus:ring-2 focus:ring-primary/25 [&::-webkit-details-marker]:hidden"
                                            aria-label="Aide sur la banque d'images"
                                        >
                                            <Info className="h-3.5 w-3.5" />
                                        </summary>
                                        <div className="absolute left-1/2 z-30 mt-2 w-[min(20rem,calc(100vw-2rem))] max-w-[calc(100vw-2rem)] origin-top -translate-x-1/2 break-words rounded-md border border-border bg-background p-3 text-sm leading-6 text-foreground shadow-lg motion-safe:group-open:animate-in motion-safe:group-open:fade-in-0 motion-safe:group-open:zoom-in-95 motion-safe:group-open:slide-in-from-top-1 motion-safe:group-open:duration-150 sm:left-auto sm:right-0 sm:translate-x-0">
                                            Bonjour {user?.name}, cette galerie
                                            vous propose l'ensemble des photos
                                            créées pour vos projets. Filtrez,
                                            prévisualisez et téléchargez les
                                            visuels disponibles.
                                        </div>
                                    </details>
                                </div>
                                <p className="mt-1 text-sm text-[#150B0D]/75">
                                    {pagination.total} image
                                    {pagination.total > 1 ? "s" : ""} visible
                                    {pagination.total > 1 ? "s" : ""} pour vos
                                    projets
                                </p>
                            </div>
                            <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
                                <button
                                    type="button"
                                    onClick={() =>
                                        setInfiniteScroll(!infiniteScroll)
                                    }
                                    className="inline-flex h-9 flex-1 items-center justify-center gap-2 rounded-md border border-border bg-background px-3 text-sm font-medium shadow-sm transition hover:bg-muted sm:flex-none"
                                    aria-pressed={infiniteScroll}
                                >
                                    <span
                                        className={`relative h-5 w-9 rounded-full shadow-inner transition ${
                                            infiniteScroll
                                                ? "bg-primary"
                                                : "bg-muted"
                                        }`}
                                    >
                                        <span
                                            className={`absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition ${
                                                infiniteScroll
                                                    ? "left-4"
                                                    : "left-0.5"
                                            }`}
                                        />
                                    </span>
                                    <Infinity className="h-4 w-4" />
                                    <span className="hidden sm:inline">
                                        Défilement infini
                                    </span>
                                </button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={paginatedImages.length === 0}
                                    onClick={() =>
                                        setSelectedImages(
                                            paginatedImages.map(
                                                (image) => image.id,
                                            ),
                                        )
                                    }
                                    className="h-9 flex-1 gap-2 sm:flex-none"
                                    title="Tout sélectionner"
                                >
                                    <SquareCheck className="h-4 w-4" />
                                    <span className="sm:hidden">
                                        Sélection
                                    </span>
                                    <span className="hidden sm:inline">
                                        Tout sélectionner
                                    </span>
                                </Button>
                                {canAddImages && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="h-9 flex-1 gap-2 sm:flex-none"
                                        onClick={() => {
                                            setEditingImage(null);
                                            setImageModalOpen(true);
                                        }}
                                    >
                                        <Plus className="h-4 w-4" />
                                        Ajouter
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="rounded-md border border-border/70 bg-background/80 p-2 shadow-sm sm:p-3">
                            <div className="grid gap-2 lg:grid-cols-[minmax(16rem,1fr)_minmax(0,2fr)_auto] lg:items-center">
                                <LegacySearch
                                    value={search}
                                    onChange={(value) => {
                                        setSearch(value);
                                        setCurrentPage(1);
                                    }}
                                    className="min-w-0"
                                    onFocusChange={setSearchFocused}
                                    onSubmit={submitSearch}
                                />
                                <div className="grid min-w-0 grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-5">
                                    <LegacySelect
                                        value={orientation}
                                        onChange={(value) => {
                                            setOrientation(value);
                                            setCurrentPage(1);
                                        }}
                                        allLabel="Toutes les orientations"
                                        options={[
                                            {
                                                id: "landscape",
                                                name: "Paysage",
                                            },
                                            {
                                                id: "portrait",
                                                name: "Portrait",
                                            },
                                            { id: "square", name: "Carré" },
                                        ]}
                                        className="min-w-0"
                                    />
                                    <LegacySelect
                                        value={clientId}
                                        onChange={(value) => {
                                            setClientId(value);
                                            setProjectId("");
                                            setCurrentPage(1);
                                        }}
                                        allLabel="Toutes les entreprises"
                                        options={filters.clients}
                                        className="min-w-0"
                                    />
                                    <LegacySelect
                                        value={projectId}
                                        onChange={(value) => {
                                            setProjectId(value);
                                            setCurrentPage(1);
                                        }}
                                        allLabel="Tous les projets"
                                        options={projects}
                                        className="min-w-0 sm:col-span-2 lg:col-span-1"
                                    />
                                    <div className="grid min-w-0 grid-cols-1 gap-2 sm:col-span-2 sm:grid-cols-2 lg:col-span-2">
                                        <div className="min-w-0">
                                            <label
                                                htmlFor="gallery-date-from"
                                                className="sr-only"
                                            >
                                                Depuis
                                            </label>
                                            <div className="relative">
                                                <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[0.65rem] font-semibold uppercase tracking-wide text-muted-foreground">
                                                    Depuis
                                                </span>
                                                <input
                                                    id="gallery-date-from"
                                                    type="date"
                                                    aria-label="Date de début"
                                                    title="Date de début"
                                                    value={dateFrom}
                                                    onChange={(event) => {
                                                        setDateFrom(
                                                            event.target.value,
                                                        );
                                                        setCurrentPage(1);
                                                    }}
                                                    className="h-11 w-full rounded-md border border-input bg-card px-3 pl-20 text-sm outline-none focus:ring-2 focus:ring-primary/30"
                                                />
                                            </div>
                                        </div>
                                        <div className="min-w-0">
                                            <label
                                                htmlFor="gallery-date-to"
                                                className="sr-only"
                                            >
                                                Jusqu'au
                                            </label>
                                            <div className="relative">
                                                <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[0.65rem] font-semibold uppercase tracking-wide text-muted-foreground">
                                                    Jusqu'au
                                                </span>
                                                <input
                                                    id="gallery-date-to"
                                                    type="date"
                                                    aria-label="Date de fin"
                                                    title="Date de fin"
                                                    value={dateTo}
                                                    onChange={(event) => {
                                                        setDateTo(
                                                            event.target.value,
                                                        );
                                                        setCurrentPage(1);
                                                    }}
                                                    className="h-11 w-full rounded-md border border-input bg-card px-3 pl-24 text-sm outline-none focus:ring-2 focus:ring-primary/30"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {hasActiveFilters && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-11 justify-center gap-2"
                                        onClick={resetFilters}
                                        title="Réinitialiser les filtres"
                                    >
                                        <X className="h-4 w-4" />
                                        <span className="lg:sr-only">
                                            Réinitialiser
                                        </span>
                                    </Button>
                                )}
                            </div>
                            {(tag || selectedImages.length > 0) && (
                                <div className="mt-2 flex flex-wrap items-center gap-2 border-t border-border/60 pt-2">
                                    {tag && (
                                        <button
                                            type="button"
                                            onClick={() => filterByTag("")}
                                            className="inline-flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-sm font-medium text-primary transition hover:bg-primary/15"
                                            title="Retirer le filtre tag"
                                        >
                                            #{tag}
                                            <X className="h-3.5 w-3.5" />
                                        </button>
                                    )}
                                    {selectedImages.length > 0 && (
                                        <span className="inline-flex items-center rounded-full border border-border bg-background px-3 py-1 text-sm font-medium md:hidden">
                                            {selectedImages.length} image
                                            {selectedImages.length > 1
                                                ? "s"
                                                : ""}{" "}
                                            sélectionnée
                                            {selectedImages.length > 1
                                                ? "s"
                                                : ""}
                                        </span>
                                    )}
                                    {selectedImages.length > 0 && (
                                        <div className="ml-auto hidden flex-wrap items-center justify-end gap-2 md:flex">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="gap-2"
                                                onClick={() =>
                                                    requestDownload("web")
                                                }
                                                disabled={
                                                    selectionHasExpiredRights
                                                }
                                                title={
                                                    selectionHasExpiredRights
                                                        ? "Une image sélectionnée a une cession expirée"
                                                        : "Télécharger la sélection en version web"
                                                }
                                            >
                                                <Download className="h-4 w-4" />
                                                Version web
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="gap-2"
                                                onClick={() =>
                                                    requestDownload("hd")
                                                }
                                                disabled={
                                                    selectionHasExpiredRights
                                                }
                                                title={
                                                    selectionHasExpiredRights
                                                        ? "Une image sélectionnée a une cession expirée"
                                                        : "Télécharger la sélection en HD"
                                                }
                                            >
                                                <Download className="h-4 w-4" />
                                                HD impression
                                            </Button>
                                            {selectionCanBeAssigned && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="gap-2"
                                                    onClick={() =>
                                                        setBulkProjectOpen(true)
                                                    }
                                                >
                                                    <FolderInput className="h-4 w-4" />
                                                    Lier à un projet
                                                </Button>
                                            )}
                                            {canCreateSharedAlbums && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="gap-2"
                                                    disabled={
                                                        selectionHasExpiredRights
                                                    }
                                                    title={
                                                        selectionHasExpiredRights
                                                            ? "Une image sélectionnée a une cession expirée"
                                                            : "Créer un album partagé"
                                                    }
                                                    onClick={openShareDialog}
                                                >
                                                    <Share2 className="h-4 w-4" />
                                                    Partager
                                                </Button>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    setSelectedImages([])
                                                }
                                            >
                                                Effacer (
                                                {selectedImages.length})
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                {!infiniteScroll && (
                    <LegacyPagination
                        totalCount={pagination.total}
                        currentPage={currentPage}
                        onPageChange={handlePageChange}
                        pageSize={PAGE_SIZE}
                    />
                )}

                <div
                    className={`mb-4 px-0 ${
                        selectedImages.length > 0 ? "pb-28 md:pb-0" : ""
                    }`}
                >
                    {paginatedImages.length > 0 ? (
                        <MasonryGrid
                            images={paginatedImages}
                            selectedIds={selectedImages}
                            onToggle={toggleSelection}
                            onImageClick={setDetailImage}
                        />
                    ) : (
                        <MasonryGrid images={[]} loadingSlots />
                    )}
                </div>

                {!infiniteScroll && (
                    <div className="pb-8">
                        <LegacyPagination
                            totalCount={pagination.total}
                            currentPage={currentPage}
                            onPageChange={handlePageChange}
                            pageSize={PAGE_SIZE}
                        />
                    </div>
                )}
            </main>
            {selectedImages.length > 0 && (
                <div className="fixed inset-x-0 bottom-0 z-50 border-t border-border bg-background/95 px-3 py-2 shadow-[0_-12px_30px_rgba(0,0,0,0.12)] backdrop-blur md:hidden">
                    <div className="mx-auto flex max-w-md items-center gap-2">
                        <div className="flex h-12 min-w-12 flex-col items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <span className="text-base font-bold leading-none">
                                {selectedImages.length}
                            </span>
                            <span className="text-[0.65rem] font-medium leading-none">
                                img
                            </span>
                        </div>
                        <div className="grid flex-1 grid-cols-4 gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-12 flex-col gap-1 px-1 text-[0.65rem]"
                                onClick={() => requestDownload("web")}
                                disabled={selectionHasExpiredRights}
                                title="Télécharger la sélection en version web"
                            >
                                <Download className="h-4 w-4" />
                                Web
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-12 flex-col gap-1 px-1 text-[0.65rem]"
                                onClick={() => requestDownload("hd")}
                                disabled={selectionHasExpiredRights}
                                title="Télécharger la sélection en HD"
                            >
                                <Download className="h-4 w-4" />
                                HD
                            </Button>
                            {canCreateSharedAlbums ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="h-12 flex-col gap-1 px-1 text-[0.65rem]"
                                    onClick={openShareDialog}
                                    disabled={selectionHasExpiredRights}
                                    title="Créer un album partagé"
                                >
                                    <Share2 className="h-4 w-4" />
                                    Partage
                                </Button>
                            ) : (
                                <span />
                            )}
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-12 flex-col gap-1 px-1 text-[0.65rem]"
                                onClick={() => setSelectedImages([])}
                                title="Effacer la sélection"
                            >
                                <X className="h-4 w-4" />
                                Fermer
                            </Button>
                        </div>
                    </div>
                    {selectionCanBeAssigned && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="mx-auto mt-2 flex h-9 w-full max-w-md gap-2"
                            onClick={() => setBulkProjectOpen(true)}
                        >
                            <FolderInput className="h-4 w-4" />
                            Lier la sélection à un projet
                        </Button>
                    )}
                </div>
            )}

            <ImageInfoSheet
                image={detailImage}
                onClose={() => setDetailImage(null)}
                onTagClick={filterByTag}
                onEditTags={editImageTags}
                onRightsExtensionRequest={requestRightsExtension}
            />
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
            <Dialog open={bulkProjectOpen} onOpenChange={setBulkProjectOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Lier la sélection à un projet</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            {selectedImages.length} image
                            {selectedImages.length > 1 ? "s" : ""} sélectionnée
                            {selectedImages.length > 1 ? "s" : ""}
                        </p>
                        <select
                            value={bulkProjectId}
                            onChange={(event) =>
                                setBulkProjectId(event.target.value)
                            }
                            className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Sélectionner un projet</option>
                            {bulkProjects.map((project) => (
                                <option key={project.id} value={project.id}>
                                    {project.clientName
                                        ? `${project.clientName} - ${project.name}`
                                        : project.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setBulkProjectOpen(false)}
                        >
                            Annuler
                        </Button>
                        <Button
                            disabled={!bulkProjectId}
                            onClick={assignSelectionToProject}
                        >
                            Lier au projet
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <Dialog open={shareOpen} onOpenChange={setShareOpen}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Créer un album partagé</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="text-sm text-muted-foreground">
                            {selectedImages.length} image
                            {selectedImages.length > 1 ? "s" : ""} dans
                            l'album.
                        </div>
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Nom de l'album
                            </label>
                            <input
                                value={shareName}
                                onChange={(event) =>
                                    setShareName(event.target.value)
                                }
                                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Description
                            </label>
                            <textarea
                                value={shareDescription}
                                onChange={(event) =>
                                    setShareDescription(event.target.value)
                                }
                                className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Destinataires
                            </label>
                            <input
                                value={shareRecipients}
                                onChange={(event) =>
                                    setShareRecipients(event.target.value)
                                }
                                placeholder="email@exemple.fr, autre@exemple.fr"
                                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            />
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Début
                                </label>
                                <input
                                    type="date"
                                    value={shareStartsAt}
                                    onChange={(event) =>
                                        setShareStartsAt(event.target.value)
                                    }
                                    className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">
                                    Expiration
                                </label>
                                <input
                                    type="date"
                                    value={shareExpiresAt}
                                    onChange={(event) =>
                                        setShareExpiresAt(event.target.value)
                                    }
                                    className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <label className="text-sm font-medium">
                                Message
                            </label>
                            <textarea
                                value={shareMessage}
                                onChange={(event) =>
                                    setShareMessage(event.target.value)
                                }
                                className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShareOpen(false)}
                        >
                            Annuler
                        </Button>
                        <Button
                            disabled={!shareName.trim()}
                            onClick={createSharedAlbum}
                        >
                            Créer le partage
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
