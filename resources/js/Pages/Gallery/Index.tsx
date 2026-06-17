import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from "@/Components/ui/sheet";
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
    Crop,
    Download,
    FolderInput,
    Images,
    Infinity,
    Info,
    Plus,
    Share2,
    ShoppingBasket,
    SquareCheck,
    Trash2,
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

type SelectionSnapshot = {
    id: string;
    title: string;
    thumbUrl?: string | null;
    imageUrl?: string | null;
    clientName?: string | null;
    projectName?: string | null;
    rightsStatus?: LegacyImage["rightsStatus"];
    rightsStatusLabel?: string;
    canManage?: boolean;
};

type CropPresetKey = "square" | "story" | "magazine" | "web_banner";

type CropPreset = {
    key: CropPresetKey;
    label: string;
    ratioLabel: string;
    width: number;
    height: number;
};

type CropSetting = {
    focusX: number;
    focusY: number;
    zoom: number;
};

type CropSource = "web" | "hd";

const PAGE_SIZE = 60;
const FILTER_DEBOUNCE_MS = 350;
const GALLERY_SELECTION_EVENT = "stimergie:gallery-selection";
const MOBILE_COLUMNS_STORAGE_KEY = "stimergie.gallery.mobileColumns";
const DEFAULT_CROP_SETTING: CropSetting = {
    focusX: 0.5,
    focusY: 0.5,
    zoom: 1,
};
const EXPORT_PRESETS: CropPreset[] = [
    {
        key: "square",
        label: "Carré",
        ratioLabel: "1:1",
        width: 1,
        height: 1,
    },
    {
        key: "story",
        label: "Story",
        ratioLabel: "9:16",
        width: 9,
        height: 16,
    },
    {
        key: "magazine",
        label: "Magazine",
        ratioLabel: "4:3",
        width: 4,
        height: 3,
    },
    {
        key: "web_banner",
        label: "Bandeau web",
        ratioLabel: "3:1",
        width: 3,
        height: 1,
    },
];

const normalizeImageId = (id: string | number) => String(id);

const imageToSelectionSnapshot = (image: LegacyImage): SelectionSnapshot => ({
    id: normalizeImageId(image.id),
    title: image.title,
    thumbUrl: image.thumbUrl,
    imageUrl: image.imageUrl,
    clientName: image.clientName || image.client?.name || null,
    projectName: image.projectName || null,
    rightsStatus: image.rightsStatus,
    rightsStatusLabel: image.rightsStatusLabel,
    canManage: image.canManage,
});

const uniqueImageIds = (ids: Array<string | number>) =>
    Array.from(new Set(ids.map(normalizeImageId)));

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
    const [selectedImages, setSelectedImages] = useState<string[]>([]);
    const [selectionSnapshots, setSelectionSnapshots] = useState<
        Record<string, SelectionSnapshot>
    >({});
    const [selectionStorageLoaded, setSelectionStorageLoaded] = useState(false);
    const [selectionReviewOpen, setSelectionReviewOpen] = useState(false);
    const [selectionDockVisible, setSelectionDockVisible] = useState(false);
    const [selectionDockClosing, setSelectionDockClosing] = useState(false);
    const [selectionDockCount, setSelectionDockCount] = useState(0);
    const [mobileColumns, setMobileColumns] = useState<3 | 4>(() => {
        if (typeof window === "undefined") {
            return 3;
        }

        return window.localStorage.getItem(MOBILE_COLUMNS_STORAGE_KEY) === "4"
            ? 4
            : 3;
    });
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
    const [cropOpen, setCropOpen] = useState(false);
    const [cropPreset, setCropPreset] = useState<CropPresetKey>("square");
    const [cropSource, setCropSource] = useState<CropSource>("web");
    const [cropImageId, setCropImageId] = useState<string | null>(null);
    const [cropSettings, setCropSettings] = useState<
        Record<string, CropSetting>
    >({});
    const pendingFilterRequest = useRef<number | null>(null);
    const submittedSearch = useRef(activeFilters.search);
    const selectionStorageKey = useMemo(
        () => `stimergie.gallery.selection.${user?.id ?? "guest"}`,
        [user?.id],
    );

    const projects = useMemo(
        () =>
            clientId
                ? filters.projects.filter(
                      (project) => String(project.clientId) === clientId,
                  )
                : filters.projects,
        [clientId, filters.projects],
    );

    const currentImagesById = useMemo(
        () =>
            new Map(
                images.map((image) => [
                    normalizeImageId(image.id),
                    image,
                ]),
            ),
        [images],
    );
    const selectedVisibleImageItems = images.filter((image) =>
        selectedImages.includes(normalizeImageId(image.id)),
    );
    const selectedSelectionItems = selectedImages
        .map((id) => currentImagesById.get(id) || selectionSnapshots[id])
        .filter((image): image is LegacyImage | SelectionSnapshot =>
            Boolean(image),
        );
    const selectionOutsideCurrentPageCount = Math.max(
        0,
        selectedImages.length - selectedVisibleImageItems.length,
    );
    const selectionHasExpiredRights = selectedSelectionItems.some(
        (image) => image.rightsStatus === "expired",
    );
    const selectionCanBeAssigned =
        selectedImages.length > 0 &&
        canBulkAssignImages &&
        selectedSelectionItems.length === selectedImages.length &&
        selectedSelectionItems.every((image) => image.canManage === true);
    const selectedImageIdsForRequest = selectedImages
        .map((id) => Number(id))
        .filter(Number.isFinite);
    const selectedCropItems = selectedImages
        .map((id) => currentImagesById.get(id) || selectionSnapshots[id])
        .filter((image): image is LegacyImage | SelectionSnapshot =>
            Boolean(image),
        );
    const paginatedImages = images;
    const hasActiveFilters =
        search.trim() !== "" ||
        orientation !== "" ||
        clientId !== "" ||
        projectId !== "" ||
        tag.trim() !== "" ||
        dateFrom !== "" ||
        dateTo !== "";
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

    useEffect(() => {
        setSelectionStorageLoaded(false);

        try {
            const storedSelection = window.localStorage.getItem(
                selectionStorageKey,
            );

            if (!storedSelection) {
                setSelectedImages([]);
                setSelectionSnapshots({});
                setSelectionStorageLoaded(true);

                return;
            }

            const parsed = JSON.parse(storedSelection);

            if (Array.isArray(parsed)) {
                setSelectedImages(uniqueImageIds(parsed));
                setSelectionSnapshots({});
                setSelectionStorageLoaded(true);

                return;
            }

            const ids = uniqueImageIds(
                Array.isArray(parsed?.ids) ? parsed.ids : [],
            );
            const snapshots =
                parsed?.snapshots && typeof parsed.snapshots === "object"
                    ? parsed.snapshots
                    : {};

            setSelectedImages(ids);
            setSelectionSnapshots(
                Object.fromEntries(
                    ids
                        .map((id) => [id, snapshots[id]])
                        .filter(([, snapshot]) => Boolean(snapshot)),
                ),
            );
        } catch {
            setSelectedImages([]);
            setSelectionSnapshots({});
        } finally {
            setSelectionStorageLoaded(true);
        }
    }, [selectionStorageKey]);

    useEffect(() => {
        if (!selectionStorageLoaded) {
            return;
        }

        if (selectedImages.length === 0) {
            window.localStorage.removeItem(selectionStorageKey);
            window.dispatchEvent(new Event(GALLERY_SELECTION_EVENT));

            return;
        }

        window.localStorage.setItem(
            selectionStorageKey,
            JSON.stringify({
                version: 1,
                ids: selectedImages,
                snapshots: selectionSnapshots,
            }),
        );
        window.dispatchEvent(new Event(GALLERY_SELECTION_EVENT));
    }, [
        selectedImages,
        selectionSnapshots,
        selectionStorageKey,
        selectionStorageLoaded,
    ]);

    useEffect(() => {
        if (selectedImages.length === 0) {
            setSelectionReviewOpen(false);
            setSelectionSnapshots({});

            return;
        }

        const selectedIdSet = new Set(selectedImages);

        setSelectionSnapshots((current) => {
            let changed = false;
            const next: Record<string, SelectionSnapshot> = {};

            selectedImages.forEach((id) => {
                const currentImage = currentImagesById.get(id);
                const snapshot = currentImage
                    ? imageToSelectionSnapshot(currentImage)
                    : current[id];

                if (snapshot) {
                    next[id] = snapshot;
                }
            });

            images.forEach((image) => {
                const id = normalizeImageId(image.id);

                if (selectedIdSet.has(id)) {
                    next[id] = imageToSelectionSnapshot(image);
                }
            });

            const currentKeys = Object.keys(current);
            const nextKeys = Object.keys(next);

            changed =
                currentKeys.length !== nextKeys.length ||
                nextKeys.some(
                    (id) =>
                        JSON.stringify(current[id]) !==
                        JSON.stringify(next[id]),
                );

            return changed ? next : current;
        });
    }, [currentImagesById, images, selectedImages]);

    const resetFilters = () => {
        clearPendingFilterRequest();
        submittedSearch.current = "";
        setSearch("");
        setOrientation("");
        setClientId("");
        setProjectId("");
        setTag("");
        setDateFrom("");
        setDateTo("");
        setCurrentPage(1);
        router.get(
            route("gallery.index"),
            filterParams(1, {
                search: "",
                orientation: "",
                clientId: "",
                projectId: "",
                tag: "",
                dateFrom: "",
                dateTo: "",
            }),
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ["images", "activeFilters", "pagination"],
            },
        );
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
        search:
            (overrides.search ?? submittedSearch.current).trim() || undefined,
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
        submittedSearch.current = nextSearch;
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
        submittedSearch.current = activeFilters.search;

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
        if (selectedImages.length > 0) {
            setSelectionDockCount(selectedImages.length);
            setSelectionDockVisible(true);
            setSelectionDockClosing(false);

            return;
        }

        if (!selectionDockVisible) {
            return;
        }

        setSelectionDockClosing(true);

        const timeout = window.setTimeout(() => {
            setSelectionDockVisible(false);
            setSelectionDockClosing(false);
            setSelectionDockCount(0);
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [selectedImages.length, selectionDockVisible]);

    useEffect(() => {
        window.localStorage.setItem(
            MOBILE_COLUMNS_STORAGE_KEY,
            String(mobileColumns),
        );
    }, [mobileColumns]);

    useEffect(() => {
        if (!didMount.current) {
            didMount.current = true;

            return;
        }

        const timeout = window.setTimeout(() => {
            pendingFilterRequest.current = null;
            setCurrentPage(1);
            router.get(route("gallery.index"), filterParams(), {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                only: ["images", "activeFilters", "pagination"],
            });
        }, FILTER_DEBOUNCE_MS);

        pendingFilterRequest.current = timeout;

        return () => {
            if (pendingFilterRequest.current === timeout) {
                pendingFilterRequest.current = null;
            }
            window.clearTimeout(timeout);
        };
    }, [clientId, dateFrom, dateTo, orientation, projectId, tag]);

    const handlePageChange = (page: number) => {
        setCurrentPage(page);
        router.get(route("gallery.index"), filterParams(page), {
            preserveScroll: true,
            preserveState: true,
            only: ["images", "activeFilters", "pagination"],
        });
    };

    const toggleSelection = (id: string | number) => {
        const normalizedId = normalizeImageId(id);
        const image = currentImagesById.get(normalizedId);

        setSelectedImages((current) =>
            current.includes(normalizedId)
                ? current.filter((selectedId) => selectedId !== normalizedId)
                : [...current, normalizedId],
        );

        setSelectionSnapshots((current) => {
            if (!image) {
                const { [normalizedId]: _removed, ...next } = current;

                return next;
            }

            if (current[normalizedId]) {
                const { [normalizedId]: _removed, ...next } = current;

                return next;
            }

            return {
                ...current,
                [normalizedId]: imageToSelectionSnapshot(image),
            };
        });
    };

    const addCurrentPageToSelection = () => {
        setSelectedImages((current) =>
            uniqueImageIds([
                ...current,
                ...paginatedImages.map((image) => image.id),
            ]),
        );
        setSelectionSnapshots((current) => ({
            ...current,
            ...Object.fromEntries(
                paginatedImages.map((image) => [
                    normalizeImageId(image.id),
                    imageToSelectionSnapshot(image),
                ]),
            ),
        }));
    };

    const removeFromSelection = (id: string | number) => {
        const normalizedId = normalizeImageId(id);

        setSelectedImages((current) =>
            current.filter((selectedId) => selectedId !== normalizedId),
        );
        setSelectionSnapshots((current) => {
            const { [normalizedId]: _removed, ...next } = current;

            return next;
        });
    };

    const clearSelection = () => {
        setSelectedImages([]);
        setSelectionSnapshots({});
        window.localStorage.removeItem(selectionStorageKey);
        window.dispatchEvent(new Event(GALLERY_SELECTION_EVENT));
    };

    const openBulkProjectDialog = () => {
        setSelectionReviewOpen(false);
        setBulkProjectOpen(true);
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
                image_ids: selectedImageIdsForRequest,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    clearSelection();
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
                image_ids: selectedImageIdsForRequest,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    clearSelection();
                },
            },
        );
    };

    const openCropDialog = () => {
        if (selectionHasExpiredRights || selectedImages.length === 0) {
            return;
        }

        setCropImageId((current) =>
            current && selectedImages.includes(current)
                ? current
                : (selectedImages[0] ?? null),
        );
        setCropSettings((current) => {
            const next = { ...current };

            selectedImages.forEach((id) => {
                next[id] = next[id] ?? DEFAULT_CROP_SETTING;
            });

            return next;
        });
        setSelectionReviewOpen(false);
        setCropOpen(true);
    };

    const updateCurrentCrop = (updates: Partial<CropSetting>) => {
        if (!cropImageId) {
            return;
        }

        setCropSettings((current) => ({
            ...current,
            [cropImageId]: {
                ...(current[cropImageId] ?? DEFAULT_CROP_SETTING),
                ...updates,
            },
        }));
    };

    const requestCroppedDownload = () => {
        if (selectionHasExpiredRights || selectedImageIdsForRequest.length === 0) {
            return;
        }

        router.post(
            route("downloads.store"),
            {
                variant: "crop",
                crop_preset: cropPreset,
                crop_source: cropSource,
                image_ids: selectedImageIdsForRequest,
                crops: selectedImages.map((id) => {
                    const setting = cropSettings[id] ?? DEFAULT_CROP_SETTING;

                    return {
                        image_id: Number(id),
                        focus_x: setting.focusX,
                        focus_y: setting.focusY,
                        zoom: setting.zoom,
                    };
                }),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    clearSelection();
                    setCropOpen(false);
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
            selectedSelectionItems.length === 1
                ? selectedSelectionItems[0].title
                : `Sélection de ${selectedImages.length} images`,
        );
        setSelectionReviewOpen(false);
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
                image_ids: selectedImageIdsForRequest,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    clearSelection();
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

    const selectionDockAnimationClass = selectionDockClosing
        ? "pointer-events-none motion-safe:animate-out motion-safe:fade-out-0 motion-safe:slide-out-to-bottom-4 motion-safe:duration-300"
        : "motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-4 motion-safe:duration-300";
    const selectionDisplayCount =
        selectedImages.length > 0 ? selectedImages.length : selectionDockCount;
    const basketButtonLabel = `Ouvrir le panier, ${selectedImages.length} image${
        selectedImages.length > 1 ? "s" : ""
    } sélectionnée${selectedImages.length > 1 ? "s" : ""}`;
    const basketButtonContent = (
        <>
            <span className="relative inline-flex">
                <ShoppingBasket className="h-5 w-5" />
                <span className="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[0.65rem] font-bold leading-none text-primary-foreground">
                    {selectedImages.length}
                </span>
            </span>
            <span>Panier</span>
        </>
    );

    return (
        <AuthenticatedLayout
            basketAction={
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="gap-2 rounded-full bg-background/80 px-4"
                    onClick={() => setSelectionReviewOpen(true)}
                    title="Ouvrir le panier"
                    aria-label={basketButtonLabel}
                >
                    {basketButtonContent}
                </Button>
            }
        >
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
                                    {pagination.total > 1 ? "s" : ""}
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
                                    onClick={addCurrentPageToSelection}
                                    className="min-h-9 h-auto flex-[1_1_13rem] gap-2 whitespace-normal px-3 text-center leading-tight sm:h-9 sm:flex-none sm:whitespace-nowrap"
                                    title="Ajouter la page à la sélection"
                                >
                                    <SquareCheck className="h-4 w-4" />
                                    Sélectionner les {paginatedImages.length}{" "}
                                    image{paginatedImages.length > 1 ? "s" : ""}
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

                        <div className="rounded-2xl bg-background/55 p-1.5 ring-1 ring-border/45 backdrop-blur-sm sm:p-2">
                            <div className="grid gap-1.5 lg:grid-cols-[minmax(16rem,1fr)_minmax(0,2fr)_auto] lg:items-center">
                                <LegacySearch
                                    value={search}
                                    onChange={setSearch}
                                    suggestions={searchSuggestions}
                                    className="min-w-0"
                                    onFocusChange={setSearchFocused}
                                    onSubmit={submitSearch}
                                />
                                <div className="grid min-w-0 grid-cols-1 gap-1.5 sm:grid-cols-2 lg:grid-cols-5">
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
                                    <div className="grid min-w-0 grid-cols-1 gap-1.5 sm:col-span-2 sm:grid-cols-2 lg:col-span-2">
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
                                                    className="h-10 w-full rounded-full border border-border/60 bg-background px-4 pl-20 text-base outline-none transition focus:border-primary/40 focus:bg-background focus:ring-2 focus:ring-primary/20 sm:text-sm"
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
                                                    className="h-10 w-full rounded-full border border-border/60 bg-background px-4 pl-24 text-base outline-none transition focus:border-primary/40 focus:bg-background focus:ring-2 focus:ring-primary/20 sm:text-sm"
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
                                        className="h-10 rounded-full border-border/60 bg-background px-4 justify-center gap-2"
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
                                        <span className="inline-flex items-center rounded-full border border-border bg-background px-3 py-1 text-sm font-medium">
                                            {selectedImages.length} image
                                            {selectedImages.length > 1
                                                ? "s"
                                                : ""}{" "}
                                            sélectionnée
                                            {selectedImages.length > 1
                                                ? "s"
                                                : ""}
                                            {selectionOutsideCurrentPageCount >
                                                0 &&
                                                `, dont ${selectionOutsideCurrentPageCount} hors page`}
                                        </span>
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
                        selectionDockVisible ? "pb-28 md:pb-24" : ""
                    }`}
                >
                    <div className="flex justify-end px-2 py-2 md:hidden">
                        <div className="inline-flex rounded-full border border-border bg-background p-1 shadow-sm">
                            {([3, 4] as const).map((columnCount) => {
                                const active = mobileColumns === columnCount;

                                return (
                                    <button
                                        key={columnCount}
                                        type="button"
                                        onClick={() =>
                                            setMobileColumns(columnCount)
                                        }
                                        className={`flex h-7 w-8 items-center justify-center rounded-full text-xs font-semibold transition ${
                                            active
                                                ? "bg-primary text-primary-foreground"
                                                : "text-muted-foreground hover:bg-muted"
                                        }`}
                                        title={`${columnCount} colonnes`}
                                        aria-label={`Afficher ${columnCount} colonnes`}
                                        aria-pressed={active}
                                    >
                                        {columnCount}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                    {paginatedImages.length > 0 ? (
                        <MasonryGrid
                            images={paginatedImages}
                            selectedIds={selectedImages}
                            onToggle={toggleSelection}
                            onImageClick={setDetailImage}
                            mobileColumns={mobileColumns}
                        />
                    ) : (
                        <MasonryGrid
                            images={[]}
                            loadingSlots
                            mobileColumns={mobileColumns}
                        />
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
            {selectionDockVisible && (
                <div
                    className={`fixed inset-x-0 bottom-0 z-50 border-t border-border bg-background/95 px-3 py-2 shadow-[0_-12px_30px_rgba(0,0,0,0.12)] backdrop-blur md:hidden ${selectionDockAnimationClass}`}
                >
                    <div className="mx-auto flex max-w-md items-center gap-2">
                        <div className="flex h-12 min-w-12 flex-col items-center justify-center rounded-md bg-primary text-primary-foreground">
                            <span className="text-base font-bold leading-none">
                                {selectionDisplayCount}
                            </span>
                            <span className="text-[0.65rem] font-medium leading-none">
                                img
                            </span>
                        </div>
                        <div className="grid flex-1 grid-cols-5 gap-1">
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
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-12 flex-col gap-1 px-1 text-[0.65rem]"
                                onClick={openCropDialog}
                                disabled={selectionHasExpiredRights}
                                title="Exporter dans un format recadré"
                            >
                                <Crop className="h-4 w-4" />
                                Formats
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
                                onClick={clearSelection}
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
                            onClick={openBulkProjectDialog}
                        >
                            <FolderInput className="h-4 w-4" />
                            Lier la sélection à un projet
                        </Button>
                    )}
                </div>
            )}
            {selectionDockVisible && (
                <div
                    className={`fixed inset-x-0 bottom-0 z-50 hidden border-t border-border bg-background/95 px-6 py-3 shadow-[0_-12px_30px_rgba(0,0,0,0.12)] backdrop-blur md:block ${selectionDockAnimationClass}`}
                >
                    <div className="mx-auto flex max-w-7xl items-center justify-between gap-4">
                        <div className="min-w-0">
                            <div className="text-sm font-semibold">
                                {selectionDisplayCount} image
                                {selectionDisplayCount > 1 ? "s" : ""}{" "}
                                sélectionnée
                                {selectionDisplayCount > 1 ? "s" : ""}
                            </div>
                            {selectionOutsideCurrentPageCount > 0 && (
                                <div className="text-xs text-muted-foreground">
                                    {selectionOutsideCurrentPageCount} image
                                    {selectionOutsideCurrentPageCount > 1
                                        ? "s"
                                        : ""}{" "}
                                    conservée
                                    {selectionOutsideCurrentPageCount > 1
                                        ? "s"
                                        : ""}{" "}
                                    hors de la page visible
                                </div>
                            )}
                        </div>
                        <div className="flex flex-wrap items-center justify-end gap-2">
                            <Button
                                type="button"
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
                                type="button"
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
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="gap-2"
                                onClick={openCropDialog}
                                disabled={selectionHasExpiredRights}
                                title={
                                    selectionHasExpiredRights
                                        ? "Une image sélectionnée a une cession expirée"
                                        : "Exporter dans un format recadré"
                                }
                            >
                                <Crop className="h-4 w-4" />
                                Formats
                            </Button>
                            {selectionCanBeAssigned && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="gap-2"
                                    onClick={openBulkProjectDialog}
                                >
                                    <FolderInput className="h-4 w-4" />
                                    Lier à un projet
                                </Button>
                            )}
                            {canCreateSharedAlbums && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="gap-2"
                                    disabled={selectionHasExpiredRights}
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
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={clearSelection}
                            >
                                Effacer la sélection
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            <Sheet
                open={selectionReviewOpen}
                onOpenChange={setSelectionReviewOpen}
            >
                <SheetContent
                    side="right"
                    className="flex h-[100dvh] max-h-[100dvh] w-full max-w-none flex-col overflow-hidden p-0 sm:w-[28rem]"
                >
                    <div className="border-b border-border p-6">
                        <SheetHeader className="pr-10 text-left">
                            <SheetTitle>Panier / lightbox</SheetTitle>
                            <SheetDescription>
                                {selectedImages.length} image
                                {selectedImages.length > 1 ? "s" : ""}{" "}
                                accumulée
                                {selectedImages.length > 1 ? "s" : ""} entre
                                les pages, filtres, recherches et tags.
                            </SheetDescription>
                        </SheetHeader>
                    </div>

                    <div className="relative min-h-0 flex-1 bg-muted/25">
                        <div className="h-full overflow-y-auto px-4 py-4 pb-12">
                        {selectionHasExpiredRights && (
                            <div className="mb-4 rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
                                Une image sélectionnée a une cession expirée.
                                Les téléchargements et partages sont bloqués
                                jusqu'à correction de la sélection.
                            </div>
                        )}

                        {selectedImages.length === 0 ? (
                            <div className="flex min-h-80 flex-col items-center justify-center rounded-md border border-dashed p-6 text-center">
                                <Images className="h-10 w-10 text-muted-foreground" />
                                <p className="mt-3 text-sm font-medium">
                                    Aucune image sélectionnée
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {selectedImages.map((id) => {
                                    const image =
                                        currentImagesById.get(id) ||
                                        selectionSnapshots[id];
                                    const imageSrc = image?.thumbUrl;
                                    const currentImage =
                                        currentImagesById.get(id);
                                    const rightsExpired =
                                        image?.rightsStatus === "expired";

                                    return (
                                        <div
                                            key={id}
                                            className="grid grid-cols-[4.5rem_minmax(0,1fr)_2.25rem] gap-3 rounded-md border border-border bg-background p-2"
                                        >
                                            <button
                                                type="button"
                                                className="h-16 overflow-hidden rounded-md bg-muted text-muted-foreground"
                                                onClick={() => {
                                                    if (currentImage) {
                                                        setDetailImage(
                                                            currentImage,
                                                        );
                                                        setSelectionReviewOpen(
                                                            false,
                                                        );
                                                    }
                                                }}
                                                disabled={!currentImage}
                                                title={
                                                    currentImage
                                                        ? "Ouvrir le détail"
                                                        : "Image conservée hors page visible"
                                                }
                                            >
                                                {imageSrc ? (
                                                    <img
                                                        src={imageSrc}
                                                        alt={
                                                            image?.title ||
                                                            "Image sélectionnée"
                                                        }
                                                        className="h-full w-full object-cover"
                                                    />
                                                ) : (
                                                    <Images className="mx-auto h-full w-6" />
                                                )}
                                            </button>
                                            <div className="min-w-0 py-0.5">
                                                <div className="truncate text-sm font-semibold">
                                                    {image?.title ||
                                                        `Image #${id}`}
                                                </div>
                                                <div className="mt-1 truncate text-xs text-muted-foreground">
                                                    {[
                                                        image?.clientName,
                                                        image?.projectName,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(" · ") ||
                                                        "Hors page visible"}
                                                </div>
                                                {image?.rightsStatusLabel && (
                                                    <div
                                                        className={`mt-2 inline-flex max-w-full truncate rounded-full px-2 py-0.5 text-xs font-medium ${
                                                            rightsExpired
                                                                ? "bg-destructive text-destructive-foreground"
                                                                : "bg-muted text-muted-foreground"
                                                        }`}
                                                    >
                                                        {
                                                            image.rightsStatusLabel
                                                        }
                                                    </div>
                                                )}
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="h-9 w-9"
                                                onClick={() =>
                                                    removeFromSelection(id)
                                                }
                                                title="Retirer du panier"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                        </div>
                        {selectedImages.length > 3 && (
                            <div className="pointer-events-none absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-background via-background/90 to-transparent" />
                        )}
                    </div>

                    {selectedImages.length > 0 && (
                        <div className="space-y-3 border-t border-border bg-background p-4 pb-[calc(1rem+max(env(safe-area-inset-bottom),1.5rem))] sm:pb-4">
                            <div className="grid grid-cols-3 gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="gap-2"
                                    onClick={() => requestDownload("web")}
                                    disabled={selectionHasExpiredRights}
                                >
                                    <Download className="h-4 w-4" />
                                    Web
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="gap-2"
                                    onClick={() => requestDownload("hd")}
                                    disabled={selectionHasExpiredRights}
                                >
                                    <Download className="h-4 w-4" />
                                    HD
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="gap-2"
                                    onClick={openCropDialog}
                                    disabled={selectionHasExpiredRights}
                                >
                                    <Crop className="h-4 w-4" />
                                    Formats
                                </Button>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {canCreateSharedAlbums && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="flex-1 gap-2"
                                        onClick={openShareDialog}
                                        disabled={selectionHasExpiredRights}
                                    >
                                        <Share2 className="h-4 w-4" />
                                        Partager
                                    </Button>
                                )}
                                {selectionCanBeAssigned && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="flex-1 gap-2"
                                        onClick={openBulkProjectDialog}
                                    >
                                        <FolderInput className="h-4 w-4" />
                                        Lier
                                    </Button>
                                )}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="flex-1"
                                    onClick={clearSelection}
                                >
                                    Vider
                                </Button>
                            </div>
                        </div>
                    )}
                </SheetContent>
            </Sheet>

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
            <CropExportDialog
                open={cropOpen}
                onOpenChange={setCropOpen}
                images={selectedCropItems}
                selectedImageId={cropImageId}
                onSelectedImageChange={setCropImageId}
                preset={cropPreset}
                onPresetChange={setCropPreset}
                source={cropSource}
                onSourceChange={setCropSource}
                settings={cropSettings}
                onSettingChange={updateCurrentCrop}
                onSubmit={requestCroppedDownload}
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

function CropExportDialog({
    open,
    onOpenChange,
    images,
    selectedImageId,
    onSelectedImageChange,
    preset,
    onPresetChange,
    source,
    onSourceChange,
    settings,
    onSettingChange,
    onSubmit,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    images: Array<LegacyImage | SelectionSnapshot>;
    selectedImageId: string | null;
    onSelectedImageChange: (id: string) => void;
    preset: CropPresetKey;
    onPresetChange: (preset: CropPresetKey) => void;
    source: CropSource;
    onSourceChange: (source: CropSource) => void;
    settings: Record<string, CropSetting>;
    onSettingChange: (updates: Partial<CropSetting>) => void;
    onSubmit: () => void;
}) {
    const selectedImage =
        images.find((image) => normalizeImageId(image.id) === selectedImageId) ??
        images[0] ??
        null;
    const selectedId = selectedImage ? normalizeImageId(selectedImage.id) : null;
    const selectedPreset =
        EXPORT_PRESETS.find((candidate) => candidate.key === preset) ??
        EXPORT_PRESETS[0];
    const setting = selectedId
        ? settings[selectedId] ?? DEFAULT_CROP_SETTING
        : DEFAULT_CROP_SETTING;
    const previewSrc = selectedImage?.imageUrl || selectedImage?.thumbUrl || null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92dvh] max-w-5xl overflow-hidden p-0">
                <DialogHeader className="border-b border-border px-5 py-4">
                    <DialogTitle>Formats d'export</DialogTitle>
                </DialogHeader>
                <div className="grid min-h-0 gap-0 md:grid-cols-[16rem_minmax(0,1fr)]">
                    <div className="max-h-[70dvh] overflow-y-auto border-b border-border p-3 md:border-b-0 md:border-r">
                        <div className="grid grid-cols-2 gap-2 md:grid-cols-1">
                            {images.map((image) => {
                                const id = normalizeImageId(image.id);
                                const src = image.thumbUrl || image.imageUrl;
                                const active = id === selectedId;

                                return (
                                    <button
                                        key={id}
                                        type="button"
                                        className={`grid grid-cols-[3rem_minmax(0,1fr)] gap-2 rounded-md border p-2 text-left transition ${
                                            active
                                                ? "border-primary bg-primary/5"
                                                : "border-border hover:bg-muted"
                                        }`}
                                        onClick={() => onSelectedImageChange(id)}
                                    >
                                        <span className="h-12 overflow-hidden rounded bg-muted">
                                            {src ? (
                                                <img
                                                    src={src}
                                                    alt={image.title}
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : (
                                                <Images className="mx-auto h-full w-5 text-muted-foreground" />
                                            )}
                                        </span>
                                        <span className="min-w-0 self-center truncate text-sm font-medium">
                                            {image.title}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <div className="max-h-[70dvh] overflow-y-auto p-5">
                        <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                            <div className="space-y-4">
                                <div
                                    className="relative mx-auto w-full max-w-2xl overflow-hidden rounded-md bg-muted"
                                    style={{
                                        aspectRatio: `${selectedPreset.width} / ${selectedPreset.height}`,
                                    }}
                                >
                                    {previewSrc ? (
                                        <img
                                            src={previewSrc}
                                            alt={selectedImage?.title ?? "Aperçu"}
                                            className="h-full w-full object-cover"
                                            style={{
                                                objectPosition: `${setting.focusX * 100}% ${setting.focusY * 100}%`,
                                                transform: `scale(${setting.zoom})`,
                                                transformOrigin: `${setting.focusX * 100}% ${setting.focusY * 100}%`,
                                            }}
                                        />
                                    ) : (
                                        <div className="flex h-full items-center justify-center text-muted-foreground">
                                            <Images className="h-10 w-10" />
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-5">
                                <div className="grid grid-cols-2 gap-2">
                                    {EXPORT_PRESETS.map((candidate) => (
                                        <button
                                            key={candidate.key}
                                            type="button"
                                            className={`rounded-md border px-3 py-2 text-left text-sm transition ${
                                                candidate.key === preset
                                                    ? "border-primary bg-primary text-primary-foreground"
                                                    : "border-border hover:bg-muted"
                                            }`}
                                            onClick={() =>
                                                onPresetChange(candidate.key)
                                            }
                                        >
                                            <span className="block font-semibold">
                                                {candidate.label}
                                            </span>
                                            <span className="text-xs opacity-80">
                                                {candidate.ratioLabel}
                                            </span>
                                        </button>
                                    ))}
                                </div>

                                <div className="grid grid-cols-2 gap-2">
                                    {(["web", "hd"] as const).map((candidate) => (
                                        <button
                                            key={candidate}
                                            type="button"
                                            className={`rounded-md border px-3 py-2 text-left text-sm transition ${
                                                candidate === source
                                                    ? "border-primary bg-primary text-primary-foreground"
                                                    : "border-border hover:bg-muted"
                                            }`}
                                            onClick={() =>
                                                onSourceChange(candidate)
                                            }
                                        >
                                            <span className="block font-semibold">
                                                {candidate === "web"
                                                    ? "Web"
                                                    : "HD"}
                                            </span>
                                            <span className="text-xs opacity-80">
                                                {candidate === "web"
                                                    ? "Plus rapide"
                                                    : "Source originale"}
                                            </span>
                                        </button>
                                    ))}
                                </div>

                                <div className="space-y-4">
                                    <CropRange
                                        label="Horizontal"
                                        min={0}
                                        max={100}
                                        step={1}
                                        value={Math.round(setting.focusX * 100)}
                                        onChange={(value) =>
                                            onSettingChange({
                                                focusX: value / 100,
                                            })
                                        }
                                    />
                                    <CropRange
                                        label="Vertical"
                                        min={0}
                                        max={100}
                                        step={1}
                                        value={Math.round(setting.focusY * 100)}
                                        onChange={(value) =>
                                            onSettingChange({
                                                focusY: value / 100,
                                            })
                                        }
                                    />
                                    <CropRange
                                        label="Zoom"
                                        min={1}
                                        max={4}
                                        step={0.05}
                                        value={setting.zoom}
                                        onChange={(value) =>
                                            onSettingChange({ zoom: value })
                                        }
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <DialogFooter className="border-t border-border px-5 py-4">
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Annuler
                    </Button>
                    <Button disabled={images.length === 0} onClick={onSubmit}>
                        <Download className="mr-2 h-4 w-4" />
                        Exporter
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function CropRange({
    label,
    min,
    max,
    step,
    value,
    onChange,
}: {
    label: string;
    min: number;
    max: number;
    step: number;
    value: number;
    onChange: (value: number) => void;
}) {
    return (
        <label className="block space-y-2 text-sm">
            <span className="flex items-center justify-between gap-3 font-medium">
                <span>{label}</span>
                <span className="text-xs text-muted-foreground">
                    {label === "Zoom" ? `${value.toFixed(2)}x` : `${value}%`}
                </span>
            </span>
            <input
                type="range"
                min={min}
                max={max}
                step={step}
                value={value}
                onChange={(event) => onChange(Number(event.target.value))}
                className="w-full accent-primary"
            />
        </label>
    );
}
