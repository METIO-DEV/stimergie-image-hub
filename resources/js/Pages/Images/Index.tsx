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
import { Head } from "@inertiajs/react";
import { Pencil, Plus } from "lucide-react";
import { useMemo, useState } from "react";

type FilterOption = {
    id: number;
    name: string;
    clientId?: number;
};

type Props = {
    images: LegacyImage[];
    canManageImages: boolean;
    filters: {
        clients: FilterOption[];
        projects: FilterOption[];
    };
};

const PAGE_SIZE = 20;

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
                action={
                    <div className="flex items-center gap-4">
                        <ViewToggle
                            currentView={viewMode}
                            onViewChange={setViewMode}
                        />
                        {canManageImages && (
                            <Button
                                onClick={() => {
                                    setEditingImage(null);
                                    setImageModalOpen(true);
                                }}
                            >
                                <Plus size={16} className="mr-2" />
                                Ajouter une image
                            </Button>
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
                        allLabel="Tous les clients"
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
                        <TableHead>Client</TableHead>
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
