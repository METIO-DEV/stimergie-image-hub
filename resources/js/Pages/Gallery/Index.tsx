import { Button } from "@/Components/ui/button";
import {
    LegacyImage,
    LegacyPagination,
    LegacySearch,
    LegacySelect,
    MasonryGrid,
} from "@/Components/Legacy/LegacyDesign";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, router, usePage } from "@inertiajs/react";
import { Infinity, SquareCheck } from "lucide-react";
import { useMemo, useState } from "react";

type FilterOption = {
    id: number;
    name: string;
    clientId?: number;
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
    pagination: {
        currentPage: number;
        perPage: number;
        total: number;
    };
};

const PAGE_SIZE = 100;

export default function GalleryIndex({
    images,
    stats,
    filters,
    pagination,
}: Props) {
    const user = usePage().props.auth.user;
    const [search, setSearch] = useState("");
    const [orientation, setOrientation] = useState("");
    const [clientId, setClientId] = useState("");
    const [projectId, setProjectId] = useState("");
    const [currentPage, setCurrentPage] = useState(pagination.currentPage);
    const [infiniteScroll, setInfiniteScroll] = useState(false);
    const [selectedImages, setSelectedImages] = useState<
        Array<string | number>
    >([]);

    const projects = useMemo(
        () =>
            clientId
                ? filters.projects.filter(
                      (project) => String(project.clientId) === clientId,
                  )
                : filters.projects,
        [clientId, filters.projects],
    );

    const filteredImages = useMemo(() => {
        const query = search.trim().toLowerCase();

        return images.filter((image) => {
            const matchesSearch =
                !query ||
                image.title.toLowerCase().includes(query) ||
                image.tags?.some((tag) => tag.toLowerCase().includes(query));
            const matchesOrientation =
                !orientation || image.orientation === orientation;
            const matchesClient =
                !clientId || String(image.clientId) === clientId;
            const matchesProject =
                !projectId || String(image.projectId) === projectId;

            return (
                matchesSearch &&
                matchesOrientation &&
                matchesClient &&
                matchesProject
            );
        });
    }, [clientId, images, orientation, projectId, search]);

    const hasLocalFilters = Boolean(
        search || orientation || clientId || projectId,
    );
    const paginatedImages =
        infiniteScroll || !hasLocalFilters
            ? filteredImages
            : filteredImages.slice(
                  (currentPage - 1) * PAGE_SIZE,
                  currentPage * PAGE_SIZE,
              );

    const handlePageChange = (page: number) => {
        setCurrentPage(page);

        if (!hasLocalFilters) {
            router.get(
                route("gallery.index"),
                { page },
                { preserveScroll: true, preserveState: false },
            );
        }
    };

    const toggleSelection = (id: string | number) => {
        setSelectedImages((current) =>
            current.includes(id)
                ? current.filter((selectedId) => selectedId !== id)
                : [...current, id],
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Banque d'images" />

            <main className="w-screen flex-grow px-0">
                <section className="border-b border-border bg-[#dcd0bb]">
                    <div className="mx-auto max-w-7xl px-6 py-16">
                        <div className="mb-10 text-center">
                            <h1 className="mb-6 text-3xl font-bold">
                                Banque d'images
                            </h1>
                            <p className="mx-auto max-w-3xl text-[#150B0D]">
                                Bonjour {user.name},
                                <br />
                                <br />
                                Cette galerie vous propose l'ensemble des photos
                                créées par Imprononçable pour vos projets. Vous
                                pouvez les filtrer par catégorie, par type de
                                droits, puis les prévisualiser et les
                                télécharger.
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
                                    allLabel="Tous les clients"
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
                        totalCount={
                            hasLocalFilters
                                ? filteredImages.length
                                : stats.images
                        }
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
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setSelectedImages([])}
                            >
                                Effacer la sélection ({selectedImages.length})
                            </Button>
                        )}
                    </div>
                    {paginatedImages.length > 0 ? (
                        <MasonryGrid
                            images={paginatedImages}
                            selectedIds={selectedImages}
                            onToggle={toggleSelection}
                        />
                    ) : (
                        <MasonryGrid images={[]} loadingSlots />
                    )}
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
