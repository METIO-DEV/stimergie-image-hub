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
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, router, usePage } from "@inertiajs/react";
import { Download, FolderInput, Infinity, SquareCheck } from "lucide-react";
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
    };
    activeFilters: {
        search: string;
        orientation: string;
        clientId: string;
        projectId: string;
    };
    bulkProjects: FilterOption[];
    canBulkAssignImages: boolean;
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
    canBulkAssignImages,
    pagination,
}: Props) {
    const user = usePage().props.auth.user;
    const [search, setSearch] = useState(activeFilters.search);
    const [orientation, setOrientation] = useState(activeFilters.orientation);
    const [clientId, setClientId] = useState(activeFilters.clientId);
    const [projectId, setProjectId] = useState(activeFilters.projectId);
    const [currentPage, setCurrentPage] = useState(pagination.currentPage);
    const [infiniteScroll, setInfiniteScroll] = useState(false);
    const didMount = useRef(false);
    const [selectedImages, setSelectedImages] = useState<
        Array<string | number>
    >([]);
    const [detailImage, setDetailImage] = useState<LegacyImage | null>(null);
    const [bulkProjectOpen, setBulkProjectOpen] = useState(false);
    const [bulkProjectId, setBulkProjectId] = useState("");

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
    const selectionCanBeAssigned =
        canBulkAssignImages &&
        selectedImageItems.length === selectedImages.length &&
        selectedImageItems.every((image) => image.canManage);
    const paginatedImages = images;

    const filterParams = (
        nextPage = 1,
    ): Record<string, string | number | undefined> => ({
        search: search.trim() || undefined,
        orientation: orientation || undefined,
        client_id: clientId || undefined,
        project_id: projectId || undefined,
        page: nextPage > 1 ? nextPage : undefined,
    });

    useEffect(() => {
        setSearch(activeFilters.search);
        setOrientation(activeFilters.orientation);
        setClientId(activeFilters.clientId);
        setProjectId(activeFilters.projectId);
        setCurrentPage(pagination.currentPage);
    }, [
        activeFilters.clientId,
        activeFilters.orientation,
        activeFilters.projectId,
        activeFilters.search,
        pagination.currentPage,
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
            });
        }, 350);

        return () => window.clearTimeout(timeout);
    }, [clientId, orientation, projectId, search]);

    const handlePageChange = (page: number) => {
        setCurrentPage(page);
        router.get(route("gallery.index"), filterParams(page), {
            preserveScroll: true,
            preserveState: false,
        });
    };

    const toggleSelection = (id: string | number) => {
        setSelectedImages((current) =>
            current.includes(id)
                ? current.filter((selectedId) => selectedId !== id)
                : [...current, id],
        );
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

    return (
        <AuthenticatedLayout>
            <Head title="Banque d'images" />

            <main className="w-screen flex-grow px-0">
                <section className="border-b border-border bg-[#dcd0bb]">
                    <div className="mx-auto max-w-7xl px-6 py-10">
                        <div className="mb-6 text-center">
                            <h1 className="mb-3 text-3xl font-bold">
                                Banque d'images
                            </h1>
                            <p className="mx-auto max-w-2xl text-sm leading-6 text-[#150B0D]">
                                Bonjour {user?.name},
                                cette galerie vous propose l'ensemble des photos
                                créées pour vos projets. Filtrez, prévisualisez
                                et téléchargez les visuels disponibles.
                            </p>
                        </div>

                        <div className="flex flex-col gap-4 md:flex-row md:items-center">
                            <LegacySearch
                                value={search}
                                onChange={(value) => {
                                    setSearch(value);
                                    setCurrentPage(1);
                                }}
                                className="md:max-w-sm"
                            />
                            <div className="flex w-full flex-col gap-4 md:ml-auto md:flex-row">
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
                                    className="w-full md:w-64"
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
                                    className="w-full md:w-64"
                                />
                                <LegacySelect
                                    value={projectId}
                                    onChange={(value) => {
                                        setProjectId(value);
                                        setCurrentPage(1);
                                    }}
                                    allLabel="Tous les projets"
                                    options={projects}
                                    className="w-full md:w-64"
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <div className="flex items-center justify-between px-4 pb-2 pt-[10px]">
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
                    <div className="mb-4 flex items-center justify-between px-0">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setSelectedImages(
                                    paginatedImages.map((image) => image.id),
                                )
                            }
                            className="gap-2 rounded-r-md rounded-l-none"
                        >
                            <SquareCheck className="h-4 w-4" />
                            Tout sélectionner
                        </Button>
                        {selectedImages.length > 0 && (
                            <div className="flex flex-wrap items-center justify-end gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="gap-2"
                                    onClick={() => requestDownload("web")}
                                >
                                    <Download className="h-4 w-4" />
                                    Version web
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="gap-2"
                                    onClick={() => requestDownload("hd")}
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
        </AuthenticatedLayout>
    );
}
