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
    const searchSuggestions = useMemo(
        () =>
            [
                ...filters.clients.map((client) => client.name),
                ...filters.projects.map((project) => project.name),
                ...filters.tags.map((tag) => tag.name),
            ]
                .filter(Boolean)
                .filter(
                    (value, index, values) =>
                        values.findIndex(
                            (candidate) =>
                                candidate.toLowerCase() === value.toLowerCase(),
                        ) === index,
                )
                .slice(0, 120),
        [filters.clients, filters.projects, filters.tags],
    );

    const filterParams = (
        nextPage = 1,
    ): Record<string, string | number | undefined> => ({
        search: search.trim() || undefined,
        orientation: orientation || undefined,
        client_id: clientId || undefined,
        project_id: projectId || undefined,
        tag: tag.trim() || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
        page: nextPage > 1 ? nextPage : undefined,
    });

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

        const timeout = window.setTimeout(() => {
            setCurrentPage(1);
            router.get(route("gallery.index"), filterParams(), {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ["images", "activeFilters", "pagination"],
            });
        }, 350);

        return () => window.clearTimeout(timeout);
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
                    <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
                        <div className="mb-6 text-center">
                            <h1 className="mb-3 break-words text-2xl font-bold leading-tight sm:text-3xl">
                                Banque d'images
                            </h1>
                            <p className="mx-auto max-w-2xl text-sm leading-6 text-[#150B0D]">
                                Bonjour {user?.name},
                                cette galerie vous propose l'ensemble des photos
                                créées pour vos projets. Filtrez, prévisualisez
                                et téléchargez les visuels disponibles.
                            </p>
                        </div>

                        <div className="grid min-w-0 gap-4 lg:grid-cols-[minmax(18rem,1fr)_minmax(0,2fr)] lg:items-center">
                            <LegacySearch
                                value={search}
                                onChange={(value) => {
                                    setSearch(value);
                                    setCurrentPage(1);
                                }}
                                suggestions={searchSuggestions}
                                className="min-w-0 lg:max-w-sm"
                                onFocusChange={setSearchFocused}
                            />
                            <div className="grid w-full min-w-0 grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
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
                                    className="min-w-0 sm:col-span-2 xl:col-span-1"
                                />
                                <div className="min-w-0">
                                    <label
                                        htmlFor="gallery-date-from"
                                        className="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#150B0D]/70"
                                    >
                                        Depuis
                                    </label>
                                    <input
                                        id="gallery-date-from"
                                        type="date"
                                        value={dateFrom}
                                        onChange={(event) => {
                                            setDateFrom(event.target.value);
                                            setCurrentPage(1);
                                        }}
                                        className="h-11 w-full rounded-md border border-input bg-card px-3 text-sm outline-none focus:ring-2 focus:ring-primary/30"
                                    />
                                </div>
                                <div className="min-w-0">
                                    <label
                                        htmlFor="gallery-date-to"
                                        className="mb-1 block text-xs font-semibold uppercase tracking-wide text-[#150B0D]/70"
                                    >
                                        Jusqu'au
                                    </label>
                                    <input
                                        id="gallery-date-to"
                                        type="date"
                                        value={dateTo}
                                        onChange={(event) => {
                                            setDateTo(event.target.value);
                                            setCurrentPage(1);
                                        }}
                                        className="h-11 w-full rounded-md border border-input bg-card px-3 text-sm outline-none focus:ring-2 focus:ring-primary/30"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div className="flex flex-wrap items-center justify-between gap-3 px-4 pb-2 pt-[10px]">
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setInfiniteScroll(!infiniteScroll)}
                            className="relative h-7 w-12 rounded-full bg-muted shadow-inner"
                            aria-pressed={infiniteScroll}
                        >
                            <span
                                className={`absolute top-1 h-5 w-5 rounded-full bg-white shadow transition ${
                                    infiniteScroll ? "left-6" : "left-1"
                                }`}
                            />
                        </button>
                        <Infinity className="h-4 w-4" />
                        <span className="font-semibold">Défilement infini</span>
                    </div>
                    {canAddImages && (
                        <Button
                            type="button"
                            size="sm"
                            className="gap-2"
                            onClick={() => {
                                setEditingImage(null);
                                setImageModalOpen(true);
                            }}
                        >
                            <Plus className="h-4 w-4" />
                            Ajouter une image
                        </Button>
                    )}
                </div>

                {!infiniteScroll && (
                    <LegacyPagination
                        totalCount={pagination.total}
                        currentPage={currentPage}
                        onPageChange={handlePageChange}
                        pageSize={PAGE_SIZE}
                    />
                )}

                <div className="mb-4 px-0">
                    {tag && (
                        <div className="mb-4 flex flex-wrap items-center gap-2 px-4 text-sm">
                            <span className="text-muted-foreground">
                                Filtre tag actif :
                            </span>
                            <button
                                type="button"
                                onClick={() => filterByTag("")}
                                className="inline-flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 font-medium text-primary transition hover:bg-primary/15"
                                title="Retirer le filtre tag"
                            >
                                #{tag}
                                <X className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    )}
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3 px-4">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setSelectedImages(
                                    paginatedImages.map((image) => image.id),
                                )
                            }
                            className="gap-2"
                        >
                            <SquareCheck className="h-4 w-4" />
                            Tout sélectionner
                        </Button>
                        {selectedImages.length > 0 && (
                            <div className="flex flex-1 flex-wrap items-center justify-end gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="gap-2"
                                    onClick={() => requestDownload("web")}
                                    disabled={selectionHasExpiredRights}
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
                                    onClick={() => requestDownload("hd")}
                                    disabled={selectionHasExpiredRights}
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
                                        onClick={() => setBulkProjectOpen(true)}
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
                                        disabled={selectionHasExpiredRights}
                                        title={
                                            selectionHasExpiredRights
                                                ? "Une image sélectionnée a une cession expirée"
                                                : "Créer un album partagé"
                                        }
                                        onClick={() => {
                                            setShareName(
                                                selectedImageItems.length === 1
                                                    ? selectedImageItems[0].title
                                                    : `Sélection de ${selectedImages.length} images`,
                                            );
                                            setShareOpen(true);
                                        }}
                                    >
                                        <Share2 className="h-4 w-4" />
                                        Partager
                                    </Button>
                                )}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setSelectedImages([])}
                                >
                                    Effacer la sélection (
                                    {selectedImages.length})
                                </Button>
                            </div>
                        )}
                    </div>
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
            </main>
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
