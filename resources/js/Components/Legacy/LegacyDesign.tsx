import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from "@/Components/ui/sheet";
import { cn } from "@/lib/utils";
import {
    Building2,
    Check,
    ChevronFirst,
    ChevronLast,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    Download,
    Folder,
    Grid2X2,
    ImageIcon,
    List,
    Mail,
    Pencil,
    Search,
    Shield,
    Trash2,
    UserRound,
    Users,
    RotateCcw,
    ZoomIn,
    ZoomOut,
} from "lucide-react";
import {
    ReactNode,
    memo,
    useEffect,
    useMemo,
    useRef,
    useState,
} from "react";

export type ViewMode = "card" | "list";

export type LegacyImage = {
    id: number | string;
    title: string;
    description?: string | null;
    orientation?: string | null;
    status?: string;
    clientId?: number | string | null;
    clientName?: string | null;
    client?: LegacyClientInfo | null;
    projectId?: number | string | null;
    projectName?: string | null;
    thumbUrl?: string | null;
    imageUrl?: string | null;
    downloadUrl?: string | null;
    webDownloadUrl?: string | null;
    hdDownloadUrl?: string | null;
    width?: number | null;
    height?: number | null;
    rightsStartsAt?: string | null;
    rightsEndsAt?: string | null;
    rightsStatus?: "unlimited" | "active" | "expiring_soon" | "expired";
    rightsStatusLabel?: string;
    rightsExtensionRequestedAt?: string | null;
    canRequestRightsExtension?: boolean;
    rightsExtensionRequestUrl?: string;
    tags?: string[];
    sharedClients?: Array<{
        id: number;
        name: string;
        expiresAt?: string | null;
    }>;
    createdAt?: string;
    canManage?: boolean;
};

export type LegacyClientInfo = {
    id?: number | string | null;
    name: string;
    slug?: string | null;
    logo?: string | null;
    status?: string | null;
    projectsCount?: number | null;
    imagesCount?: number | null;
    membersCount?: number | null;
};

type Option = {
    id: number | string;
    name: string;
    clientId?: number | string | null;
};

export function ViewToggle({
    currentView,
    onViewChange,
}: {
    currentView: ViewMode;
    onViewChange: (view: ViewMode) => void;
}) {
    return (
        <div className="flex space-x-1 rounded-md border p-1">
            <Button
                variant={currentView === "card" ? "default" : "ghost"}
                size="icon"
                className="h-8 w-8"
                onClick={() => onViewChange("card")}
                title="Vue en cartes"
            >
                <Grid2X2 size={16} />
            </Button>
            <Button
                variant={currentView === "list" ? "default" : "ghost"}
                size="icon"
                className="h-8 w-8"
                onClick={() => onViewChange("list")}
                title="Vue en liste"
            >
                <List size={16} />
            </Button>
        </div>
    );
}

export function LegacySelect({
    label,
    value,
    onChange,
    options,
    allLabel,
    className,
}: {
    label?: string;
    value: string;
    onChange: (value: string) => void;
    options: Option[];
    allLabel: string;
    className?: string;
}) {
    return (
        <div className={className}>
            {label && (
                <label className="mb-2 block text-sm font-medium">
                    {label}
                </label>
            )}
            <div className="relative min-w-0">
                <select
                    className="h-11 w-full truncate rounded-md border border-input bg-card px-3 pr-10 text-base outline-none appearance-none focus:ring-2 focus:ring-primary/30"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                >
                    <option value="">{allLabel}</option>
                    {options.map((option) => (
                        <option key={option.id} value={String(option.id)}>
                            {option.name}
                        </option>
                    ))}
                </select>
                <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            </div>
        </div>
    );
}

export function LegacySearch({
    value,
    onChange,
    placeholder = "Recherchez des images...",
    className,
    onFocusChange,
    onSubmit,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
    onFocusChange?: (focused: boolean) => void;
    onSubmit?: (value: string) => void;
}) {
    return (
        <div className={cn("relative w-full", className)}>
            <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <input
                value={value}
                onChange={(event) => onChange(event.target.value)}
                onFocus={() => onFocusChange?.(true)}
                onBlur={() => onFocusChange?.(false)}
                placeholder={placeholder}
                autoComplete="off"
                onKeyDown={(event) => {
                    if (event.key === "Enter") {
                        event.preventDefault();
                        onSubmit?.(value);
                    }
                }}
                className="h-11 w-full rounded-full border border-border bg-muted px-11 text-base outline-none focus:ring-2 focus:ring-primary/30 sm:text-sm"
            />
            <Button
                type="button"
                size="icon"
                className="absolute right-1 top-1/2 h-9 w-9 -translate-y-1/2 rounded-full"
                onClick={() => onSubmit?.(value)}
                title="Rechercher"
            >
                <Search className="h-4 w-4" />
            </Button>
        </div>
    );
}

export function MasonryGrid({
    images,
    selectedIds,
    onToggle,
    onImageClick,
    loadingSlots = false,
}: {
    images: LegacyImage[];
    selectedIds?: Array<string | number>;
    onToggle?: (id: string | number) => void;
    onImageClick?: (image: LegacyImage) => void;
    loadingSlots?: boolean;
}) {
    const [hoveredId, setHoveredId] = useState<string | number | null>(null);
    const columns = useMemo(() => distributeImages(images, 5), [images]);

    if (loadingSlots) {
        return (
            <div className="grid grid-cols-2 gap-0.5 px-0.5 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                {Array.from({ length: 10 }).map((_, index) => (
                    <div
                        key={index}
                        className={cn(
                            "relative bg-muted/60",
                            index % 3 === 0
                                ? "aspect-[3/4]"
                                : index % 3 === 1
                                  ? "aspect-[4/3]"
                                  : "aspect-square",
                        )}
                    >
                        <span className="absolute left-3 top-3 h-8 w-8 rounded-full border-2 border-white/80 bg-white/60" />
                    </div>
                ))}
            </div>
        );
    }

    return (
        <div className="grid grid-cols-2 gap-0.5 px-0.5 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
            {columns.map((column, columnIndex) => (
                <div key={columnIndex} className="flex flex-col gap-0.5">
                    {column.map((image) => {
                        const imageId = image.id;
                        const isSelected = selectedIds?.includes(imageId);
                        const src = image.thumbUrl || image.imageUrl;
                        const rightsExpired =
                            image.rightsStatus === "expired";
                        const rightsWarning =
                            image.rightsStatus === "expiring_soon";

                        return (
                            <div
                                key={imageId}
                                className={cn(
                                    "group relative overflow-hidden bg-card",
                                    onImageClick && "cursor-pointer",
                                    rightsExpired && "bg-muted",
                                    isSelected &&
                                        "ring-2 ring-primary ring-offset-1",
                                )}
                                onClick={() => onImageClick?.(image)}
                                onMouseEnter={() => setHoveredId(imageId)}
                                onMouseLeave={() => setHoveredId(null)}
                            >
                                <button
                                    type="button"
                                    className={cn(
                                        "absolute left-3 top-3 z-10 flex h-8 w-8 items-center justify-center rounded-full border-2 border-white/80 transition",
                                        isSelected
                                            ? "scale-110 bg-primary text-white"
                                            : "bg-white/60 group-hover:bg-white/90",
                                    )}
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        onToggle?.(imageId);
                                    }}
                                    aria-label="Sélectionner l'image"
                                >
                                    {isSelected && (
                                        <Check className="h-4 w-4" />
                                    )}
                                </button>

                                {src ? (
                                    <LazyImage
                                        src={src}
                                        alt={image.title}
                                        aspectRatio={
                                            image.width && image.height
                                                ? image.width / image.height
                                                : undefined
                                        }
                                        className={cn(
                                            "w-full object-cover",
                                            imageClassName(image),
                                            rightsExpired &&
                                                "grayscale opacity-45",
                                        )}
                                    />
                                ) : (
                                    <div
                                        className={cn(
                                            "w-full bg-muted",
                                            imageClassName(image),
                                            rightsExpired && "opacity-45",
                                        )}
                                    />
                                )}

                                {(rightsExpired || rightsWarning) && (
                                    <div
                                        className={cn(
                                            "pointer-events-none absolute left-3 right-3 top-14 z-10 rounded-md px-2 py-1 text-xs font-semibold shadow-sm",
                                            rightsExpired
                                                ? "bg-destructive text-destructive-foreground"
                                                : "bg-amber-100 text-amber-900",
                                        )}
                                    >
                                        {image.rightsStatusLabel}
                                    </div>
                                )}

                                <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent p-4 opacity-0 transition-opacity group-hover:opacity-100">
                                    <h3 className="truncate font-semibold text-white">
                                        {image.title}
                                    </h3>
                                    {(image.client || image.clientName) && (
                                        <div className="mt-1 inline-flex items-center gap-1.5 text-xs text-white/85">
                                            <Building2 className="h-3 w-3" />
                                            {image.clientName ||
                                                image.client?.name}
                                        </div>
                                    )}
                                </div>

                                {image.downloadUrl && (
                                    <a
                                        href={image.downloadUrl}
                                        className={cn(
                                            "absolute right-3 top-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-foreground shadow-md transition",
                                            hoveredId === imageId
                                                ? "translate-y-0 opacity-100"
                                                : "-translate-y-2 opacity-0",
                                        )}
                                        onClick={(event) =>
                                            event.stopPropagation()
                                        }
                                        title="Télécharger"
                                    >
                                        <Download className="h-4 w-4" />
                                    </a>
                                )}
                            </div>
                        );
                    })}
                </div>
            ))}
        </div>
    );
}

export function ImageInfoSheet({
    image,
    onClose,
    onTagClick,
    onEditTags,
    onRightsExtensionRequest,
}: {
    image: LegacyImage | null;
    onClose: () => void;
    onTagClick?: (tag: string) => void;
    onEditTags?: (image: LegacyImage) => void;
    onRightsExtensionRequest?: (image: LegacyImage) => void;
}) {
    const imageSrc = image?.imageUrl || image?.thumbUrl || null;
    const rightsExpired = image?.rightsStatus === "expired";
    const rightsWarning = image?.rightsStatus === "expiring_soon";
    const [zoom, setZoom] = useState(1);

    useEffect(() => {
        setZoom(1);
    }, [image?.id]);

    const zoomOut = () => setZoom((current) => Math.max(1, current - 0.25));
    const zoomIn = () => setZoom((current) => Math.min(3, current + 0.25));

    return (
        <Sheet
            open={Boolean(image)}
            onOpenChange={(open) => !open && onClose()}
        >
            <SheetContent
                side="right"
                className="h-screen w-full max-w-none overflow-y-auto p-0 sm:w-[85%] md:w-[75%] lg:w-[60%] xl:w-[50%]"
            >
                <div className="p-6">
                    <SheetHeader className="text-left">
                        <SheetTitle>{image?.title || "Image"}</SheetTitle>
                        <SheetDescription>
                            Informations et métadonnées du visuel.
                        </SheetDescription>
                    </SheetHeader>

                    {image && (
                        <div className="mx-auto mt-8 max-w-6xl space-y-6">
                            {imageSrc ? (
                                <div className="space-y-2">
                                    <div className="flex justify-end gap-1">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="h-8 w-8"
                                            onClick={zoomOut}
                                            disabled={zoom <= 1}
                                            title="Réduire le zoom"
                                            aria-label="Réduire le zoom"
                                        >
                                            <ZoomOut className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="h-8 w-8"
                                            onClick={() => setZoom(1)}
                                            disabled={zoom === 1}
                                            title="Réinitialiser le zoom"
                                            aria-label="Réinitialiser le zoom"
                                        >
                                            <RotateCcw className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            className="h-8 w-8"
                                            onClick={zoomIn}
                                            disabled={zoom >= 3}
                                            title="Agrandir l'image"
                                            aria-label="Agrandir l'image"
                                        >
                                            <ZoomIn className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <div className="max-h-[70vh] overflow-auto rounded-md bg-muted">
                                        <img
                                            src={imageSrc}
                                            alt={image.title}
                                            className={cn(
                                                "mx-auto block h-auto max-w-none object-contain transition-[width]",
                                                rightsExpired &&
                                                    "grayscale opacity-60",
                                            )}
                                            style={{
                                                width: `${zoom * 100}%`,
                                            }}
                                        />
                                    </div>
                                </div>
                            ) : (
                                <div className="flex h-72 items-center justify-center rounded-md bg-muted">
                                    <ImageIcon className="h-12 w-12 text-muted-foreground" />
                                </div>
                            )}

                            <div className="flex flex-wrap gap-2">
                                {image.webDownloadUrl && (
                                    <Button asChild variant="outline">
                                        <a href={image.webDownloadUrl}>
                                            <Download className="mr-2 h-4 w-4" />
                                            Web
                                        </a>
                                    </Button>
                                )}
                                {(image.hdDownloadUrl || image.downloadUrl) && (
                                    <Button asChild>
                                        <a
                                            href={
                                                image.hdDownloadUrl ||
                                                image.downloadUrl ||
                                                "#"
                                            }
                                        >
                                            <Download className="mr-2 h-4 w-4" />
                                            HD
                                        </a>
                                    </Button>
                                )}
                                {image.clientName && (
                                    <Badge
                                        variant="secondary"
                                        className="gap-1.5 px-3 py-1.5"
                                    >
                                        <Building2 className="h-3.5 w-3.5" />
                                        {image.clientName}
                                    </Badge>
                                )}
                                {image.projectName && (
                                    <Badge
                                        variant="outline"
                                        className="gap-1.5 px-3 py-1.5"
                                    >
                                        <Folder className="h-3.5 w-3.5" />
                                        {image.projectName}
                                    </Badge>
                                )}
                                {image.rightsStatusLabel && (
                                    <Badge
                                        variant={
                                            rightsExpired
                                                ? "destructive"
                                                : rightsWarning
                                                  ? "secondary"
                                                  : "outline"
                                        }
                                        className="gap-1.5 px-3 py-1.5"
                                    >
                                        <Shield className="h-3.5 w-3.5" />
                                        {image.rightsStatusLabel}
                                    </Badge>
                                )}
                            </div>

                            {(rightsExpired || rightsWarning) && (
                                <div
                                    className={cn(
                                        "rounded-md border p-4 text-sm",
                                        rightsExpired
                                            ? "border-destructive/40 bg-destructive/10 text-destructive"
                                            : "border-amber-200 bg-amber-50 text-amber-900",
                                    )}
                                >
                                    <div className="font-semibold">
                                        {rightsExpired
                                            ? "La cession de droits de cette image est dépassée."
                                            : "La cession de droits de cette image arrive bientôt à expiration."}
                                    </div>
                                    <p className="mt-1">
                                        {rightsExpired
                                            ? "Le téléchargement est bloqué jusqu'à extension de la cession."
                                            : "Vous pouvez demander une extension si l'image doit rester exploitable au-delà de la date prévue."}
                                    </p>
                                    {image.rightsExtensionRequestedAt ? (
                                        <p className="mt-3 font-medium">
                                            Demande d'extension déjà envoyée le{" "}
                                            {formatLegacyDate(
                                                image.rightsExtensionRequestedAt,
                                            )}
                                            .
                                        </p>
                                    ) : image.canRequestRightsExtension &&
                                      onRightsExtensionRequest ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            className="mt-3"
                                            onClick={() =>
                                                onRightsExtensionRequest(image)
                                            }
                                        >
                                            Étendre la cession de droits
                                        </Button>
                                    ) : null}
                                </div>
                            )}

                            <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                                <InfoBlock label="Titre" value={image.title} />
                                <InfoBlock
                                    label="Orientation"
                                    value={image.orientation || "-"}
                                />
                                <InfoBlock
                                    label="Dimensions"
                                    value={
                                        image.width && image.height
                                            ? `${image.width} × ${image.height}`
                                            : "-"
                                    }
                                />
                                <InfoBlock
                                    label="Date d'ajout"
                                    value={formatLegacyDate(image.createdAt)}
                                />
                                <InfoBlock
                                    label="Début de cession"
                                    value={formatLegacyDate(
                                        image.rightsStartsAt || undefined,
                                    )}
                                />
                                <InfoBlock
                                    label="Fin de cession"
                                    value={formatLegacyDate(
                                        image.rightsEndsAt || undefined,
                                    )}
                                />
                            </div>

                            {image.description && (
                                <InfoBlock
                                    label="Description"
                                    value={image.description}
                                />
                            )}

                            {image.tags && image.tags.length > 0 && (
                                <div>
                                    <div className="flex items-center justify-between gap-3">
                                        <div className="font-medium text-foreground">
                                            Tags
                                        </div>
                                        {image.canManage && onEditTags && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    onEditTags(image)
                                                }
                                            >
                                                <Pencil className="mr-2 h-4 w-4" />
                                                Modifier les tags
                                            </Button>
                                        )}
                                    </div>
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {image.tags.map((tag) => (
                                            onTagClick ? (
                                                <button
                                                    key={tag}
                                                    type="button"
                                                    onClick={() =>
                                                        onTagClick(tag)
                                                    }
                                                    className="rounded-full outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                                                    title={`Filtrer par ${tag}`}
                                                >
                                                    <Badge variant="secondary">
                                                        #{tag}
                                                    </Badge>
                                                </button>
                                            ) : (
                                                <Badge
                                                    key={tag}
                                                    variant="secondary"
                                                >
                                                    #{tag}
                                                </Badge>
                                            )
                                        ))}
                                    </div>
                                </div>
                            )}

                            {(!image.tags || image.tags.length === 0) &&
                                image.canManage &&
                                onEditTags && (
                                    <div className="rounded-md border border-dashed p-4">
                                        <div className="font-medium text-foreground">
                                            Tags
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Aucun tag n'est encore associé à
                                            cette image.
                                        </p>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="mt-3"
                                            onClick={() => onEditTags(image)}
                                        >
                                            <Pencil className="mr-2 h-4 w-4" />
                                            Ajouter des tags
                                        </Button>
                                    </div>
                                )}
                        </div>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

export const LazyImage = memo(function LazyImage({
    src,
    alt,
    className,
    aspectRatio,
    onLoad,
    onError,
}: {
    src: string;
    alt: string;
    className?: string;
    aspectRatio?: number;
    onLoad?: () => void;
    onError?: () => void;
}) {
    const [isLoaded, setIsLoaded] = useState(false);
    const [isInView, setIsInView] = useState(false);
    const [hasError, setHasError] = useState(false);
    const imgRef = useRef<HTMLDivElement>(null);
    const observerRef = useRef<IntersectionObserver | null>(null);

    useEffect(() => {
        if (!imgRef.current) {
            return;
        }

        observerRef.current = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        setIsInView(true);
                        observerRef.current?.disconnect();
                    }
                });
            },
            {
                rootMargin: "50px",
                threshold: 0.01,
            },
        );

        observerRef.current.observe(imgRef.current);

        return () => observerRef.current?.disconnect();
    }, []);

    return (
        <div
            ref={imgRef}
            className={cn("relative overflow-hidden bg-muted", className)}
            style={aspectRatio ? { aspectRatio } : undefined}
        >
            {!isLoaded && !hasError && (
                <div className="absolute inset-0 scale-110 animate-pulse bg-gradient-to-br from-muted to-muted/50 blur-md" />
            )}

            {isInView && !hasError && (
                <img
                    src={src}
                    alt={alt}
                    className={cn(
                        "absolute inset-0 h-full w-full object-cover transition-opacity duration-500",
                        isLoaded ? "opacity-100" : "opacity-0",
                    )}
                    loading="lazy"
                    onLoad={() => {
                        setIsLoaded(true);
                        onLoad?.();
                    }}
                    onError={() => {
                        setHasError(true);
                        onError?.();
                    }}
                />
            )}

            {hasError && (
                <div className="absolute inset-0 bg-muted" aria-label={alt} />
            )}

            {!isLoaded && !hasError && isInView && (
                <div className="absolute inset-0 flex items-center justify-center">
                    <div className="h-8 w-8 animate-spin rounded-full border-2 border-primary border-t-transparent" />
                </div>
            )}
        </div>
    );
});

export function ClientInfoSheet({
    client,
    onClose,
}: {
    client: LegacyClientInfo | null;
    onClose: () => void;
}) {
    return (
        <Sheet
            open={Boolean(client)}
            onOpenChange={(open) => !open && onClose()}
        >
            <SheetContent
                side="right"
                className="h-screen w-full max-w-none overflow-y-auto p-0 sm:w-[420px]"
            >
                <div className="p-6">
                    <SheetHeader className="text-left">
                        <SheetTitle>Informations entreprise</SheetTitle>
                        <SheetDescription>
                            Données associées à l'entreprise de cette image.
                        </SheetDescription>
                    </SheetHeader>

                    {client && (
                        <div className="mt-8 space-y-6">
                            <div className="flex items-center gap-4">
                                <div className="flex h-16 w-16 items-center justify-center overflow-hidden rounded-lg border bg-card">
                                    {client.logo ? (
                                        <img
                                            src={client.logo}
                                            alt={client.name}
                                            className="h-full w-full object-contain"
                                        />
                                    ) : (
                                        <Building2 className="h-8 w-8 text-muted-foreground" />
                                    )}
                                </div>
                                <div>
                                    <h2 className="text-2xl font-bold">
                                        {client.name}
                                    </h2>
                                    {client.slug && (
                                        <p className="text-sm text-muted-foreground">
                                            {client.slug}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-3">
                                <ClientMetric
                                    icon={<Folder className="h-4 w-4" />}
                                    label="Projets"
                                    value={client.projectsCount}
                                />
                                <ClientMetric
                                    icon={<ImageIcon className="h-4 w-4" />}
                                    label="Images"
                                    value={client.imagesCount}
                                />
                                <ClientMetric
                                    icon={<Users className="h-4 w-4" />}
                                    label="Membres"
                                    value={client.membersCount}
                                />
                            </div>

                            <div className="rounded-lg border bg-card p-4">
                                <div className="text-sm font-medium text-muted-foreground">
                                    Statut
                                </div>
                                <div className="mt-2 text-base font-semibold capitalize">
                                    {client.status || "Non renseigné"}
                                </div>
                            </div>

                            <div className="rounded-lg border bg-card p-4">
                                <div className="text-sm font-medium text-muted-foreground">
                                    Note migration
                                </div>
                                <p className="mt-2 text-sm leading-6">
                                    Ce panneau remplace la navigation directe
                                    vers un filtre entreprise. Les actions métier
                                    restent à brancher sur la fiche entreprise
                                    Laravel complète.
                                </p>
                            </div>
                        </div>
                    )}
                </div>
            </SheetContent>
        </Sheet>
    );
}

function ClientMetric({
    icon,
    label,
    value,
}: {
    icon: ReactNode;
    label: string;
    value?: number | null;
}) {
    return (
        <div className="rounded-lg border bg-card p-3">
            <div className="text-muted-foreground">{icon}</div>
            <div className="mt-2 text-xl font-bold">{value ?? "-"}</div>
            <div className="text-xs text-muted-foreground">{label}</div>
        </div>
    );
}

function InfoBlock({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="rounded-lg border bg-card p-4">
            <div className="text-sm font-medium text-muted-foreground">
                {label}
            </div>
            <div className="mt-2 text-base text-foreground">{value}</div>
        </div>
    );
}

function formatLegacyDate(value?: string) {
    if (!value) {
        return "-";
    }

    return new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(new Date(value));
}

export function LegacyPagination({
    totalCount,
    currentPage,
    onPageChange,
    pageSize = 100,
}: {
    totalCount: number;
    currentPage: number;
    onPageChange: (page: number) => void;
    pageSize?: number;
}) {
    const totalPages = Math.max(1, Math.ceil(totalCount / pageSize));
    const start = totalCount === 0 ? 0 : (currentPage - 1) * pageSize + 1;
    const end = Math.min(currentPage * pageSize, totalCount);

    if (totalPages <= 1 && totalCount <= pageSize) {
        return null;
    }

    const pages = visiblePages(currentPage, totalPages);

    return (
        <div className="flex flex-col items-center gap-6 px-4 py-8">
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <span className="font-medium text-foreground">
                    {start} - {end}
                </span>
                <span>sur</span>
                <span className="font-medium text-foreground">
                    {totalCount}
                </span>
                <span>images</span>
                <span className="ml-2 text-xs">
                    (Page {currentPage}/{totalPages})
                </span>
            </div>

            <div className="flex items-center gap-1">
                <PageButton
                    disabled={currentPage === 1}
                    onClick={() => onPageChange(1)}
                >
                    <ChevronFirst className="h-4 w-4" />
                </PageButton>
                {currentPage > 1 && (
                    <PageButton onClick={() => onPageChange(currentPage - 1)}>
                        <ChevronLeft className="h-4 w-4" />
                    </PageButton>
                )}
                {pages.map((page, index) =>
                    page === "ellipsis" ? (
                        <span key={`${page}-${index}`} className="px-3">
                            ...
                        </span>
                    ) : (
                        <Button
                            key={page}
                            variant={page === currentPage ? "default" : "ghost"}
                            className="h-10 w-10"
                            onClick={() => onPageChange(page)}
                        >
                            {page}
                        </Button>
                    ),
                )}
                {currentPage < totalPages && (
                    <PageButton onClick={() => onPageChange(currentPage + 1)}>
                        <ChevronRight className="h-4 w-4" />
                    </PageButton>
                )}
                <PageButton
                    disabled={currentPage === totalPages}
                    onClick={() => onPageChange(totalPages)}
                >
                    <ChevronLast className="h-4 w-4" />
                </PageButton>
            </div>
        </div>
    );
}

export function LegacyUserCard({
    name,
    email,
    role,
    clients,
    onEdit,
    onDelete,
}: {
    name: string;
    email: string;
    role: string;
    clients: string[];
    onEdit?: () => void;
    onDelete?: () => void;
}) {
    return (
        <Card className="transition-shadow hover:shadow-md">
            <CardHeader className="pb-2">
                <div className="flex items-start justify-between">
                    <CardTitle className="flex items-center gap-2 text-lg">
                        <UserRound
                            size={18}
                            className="text-muted-foreground"
                        />
                        {name || (
                            <span className="italic text-muted-foreground">
                                Nom non defini
                            </span>
                        )}
                    </CardTitle>
                    <div className="flex gap-2">
                        <Button
                            variant="ghost"
                            size="icon"
                            title="Modifier"
                            onClick={onEdit}
                        >
                            <Pencil size={16} />
                        </Button>
                        {onDelete && (
                            <Button
                                variant="ghost"
                                size="icon"
                                title="Supprimer"
                                className="text-destructive hover:text-destructive/90"
                                onClick={onDelete}
                            >
                                <Trash2 size={16} />
                            </Button>
                        )}
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div className="space-y-3 text-sm">
                    <p className="flex items-center gap-2">
                        <Mail size={16} className="text-muted-foreground" />
                        {email}
                    </p>
                    <div className="flex items-center gap-2">
                        <Shield size={16} className="text-muted-foreground" />
                        <Badge
                            variant="outline"
                            className={roleDisplay(role).color}
                        >
                            {roleDisplay(role).label}
                        </Badge>
                    </div>
                    {clients.length > 0 && (
                        <div className="flex items-start gap-2">
                            <Building2
                                size={16}
                                className="mt-1 text-muted-foreground"
                            />
                            <div className="flex flex-wrap gap-1">
                                {clients.map((client) => (
                                    <Badge
                                        key={client}
                                        variant="secondary"
                                        className="text-xs font-semibold uppercase"
                                    >
                                        {client}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

export function SectionHeader({
    icon,
    title,
    description,
    action,
}: {
    icon?: ReactNode;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="border-b border-border bg-muted/30">
            <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-16">
                <div className="flex min-w-0 flex-col gap-6 md:flex-row md:items-center md:justify-between">
                    <div className="min-w-0">
                        <div className="flex min-w-0 items-center gap-3">
                            {icon}
                            <h1 className="min-w-0 break-words text-2xl font-bold leading-tight sm:text-3xl">
                                {title}
                            </h1>
                        </div>
                        {description && (
                            <p className="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {action && (
                        <div className="flex w-full flex-wrap items-center gap-3 md:w-auto md:justify-end">
                            {action}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export function roleDisplay(role: string | null | undefined) {
    switch (role) {
        case "admin":
            return {
                label: "Administrateur",
                color: "bg-destructive/10 text-destructive border-destructive/20",
            };
        case "admin_client":
            return {
                label: "Admin Client",
                color: "bg-primary/10 text-primary border-primary/20",
            };
        default:
            return {
                label: "Utilisateur",
                color: "bg-muted text-muted-foreground border-border",
            };
    }
}

function PageButton({
    children,
    disabled,
    onClick,
}: {
    children: ReactNode;
    disabled?: boolean;
    onClick: () => void;
}) {
    return (
        <Button
            variant="outline"
            size="icon"
            disabled={disabled}
            className="h-10 w-10"
            onClick={onClick}
        >
            {children}
        </Button>
    );
}

function distributeImages(images: LegacyImage[], count: number) {
    const columns = Array.from({ length: count }, () => [] as LegacyImage[]);
    const heights = Array.from({ length: count }, () => 0);

    images.forEach((image) => {
        const index = heights.indexOf(Math.min(...heights));
        columns[index].push(image);
        heights[index] += imageHeightFactor(image);
    });

    return columns;
}

function imageHeightFactor(image: LegacyImage) {
    if (image.width && image.height) {
        return image.height / image.width;
    }

    if (image.orientation === "portrait") {
        return 1.33;
    }

    if (image.orientation === "square" || image.orientation === "carré") {
        return 1;
    }

    return 0.75;
}

function imageClassName(image: LegacyImage) {
    if (image.width && image.height) {
        return "";
    }

    if (image.orientation === "portrait") {
        return "aspect-[3/4]";
    }

    if (image.orientation === "square" || image.orientation === "carré") {
        return "aspect-square";
    }

    return "aspect-[4/3]";
}

function visiblePages(currentPage: number, totalPages: number) {
    if (totalPages <= 7) {
        return Array.from({ length: totalPages }, (_, index) => index + 1);
    }

    if (currentPage <= 3) {
        return [1, 2, 3, 4, "ellipsis", totalPages] as const;
    }

    if (currentPage >= totalPages - 2) {
        return [
            1,
            "ellipsis",
            totalPages - 3,
            totalPages - 2,
            totalPages - 1,
            totalPages,
        ] as const;
    }

    return [
        1,
        "ellipsis",
        currentPage - 1,
        currentPage,
        currentPage + 1,
        "ellipsis",
        totalPages,
    ] as const;
}
