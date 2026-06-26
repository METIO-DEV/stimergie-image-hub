import InputError from "@/Components/InputError";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Textarea } from "@/Components/ui/textarea";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { cn } from "@/lib/utils";
import { Head, Link, useForm } from "@inertiajs/react";
import {
    ArrowLeft,
    Check,
    ImageIcon,
    Plus,
    Save,
    Trash2,
    X,
} from "lucide-react";
import { FormEvent, useMemo } from "react";
import {
    BlogClientOption,
    BlogExternalLinkFormData,
    BlogFormData,
    BlogImageOption,
    BlogPost,
} from "./types";

type Props = {
    post: BlogPost | null;
    clients: BlogClientOption[];
    imageOptions: BlogImageOption[];
    canCreateGlobalPost: boolean;
};

export default function Edit({
    post: existingPost,
    clients,
    imageOptions,
    canCreateGlobalPost,
}: Props) {
    const initialFeaturedImageId = useMemo(() => {
        if (!existingPost?.featuredImageObjectKey) {
            return null;
        }

        return (
            imageOptions.find(
                (image) => image.objectKey === existingPost.featuredImageObjectKey,
            )?.id ?? null
        );
    }, [existingPost, imageOptions]);

    const initialContentType = existingPost?.contentType ?? "resource";
    const fallbackClientId =
        initialContentType === "blog"
            ? null
            : existingPost?.clientId ?? clients[0]?.id ?? null;

    const { data, setData, post, patch, processing, errors } =
        useForm<BlogFormData>({
            title: existingPost?.title ?? "",
            content: existingPost?.content ?? "",
            client_id: fallbackClientId,
            content_type: initialContentType,
            category:
                (existingPost?.category as BlogFormData["category"]) ?? null,
            featured_image_id: initialFeaturedImageId,
            remove_featured_image: false,
            external_links:
                existingPost?.externalLinks.map((link) => ({
                    label: link.label,
                    url: link.url,
                })) ?? [],
            is_published: existingPost?.isPublished ?? false,
        });

    const selectedImage = imageOptions.find(
        (image) => image.id === data.featured_image_id,
    );
    const currentImageUrl =
        selectedImage?.thumbUrl ||
        (!data.remove_featured_image ? existingPost?.featuredImageUrl : null);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
        };

        if (existingPost) {
            patch(route("blog.update", existingPost.id), options);
        } else {
            post(route("blog.store"), options);
        }
    };

    const setContentType = (contentType: BlogFormData["content_type"]) => {
        setData((values) => ({
            ...values,
            content_type: contentType,
            category: contentType === "blog" ? values.category : null,
            client_id:
                contentType === "blog"
                    ? null
                    : values.client_id ?? clients[0]?.id ?? null,
        }));
    };

    const addExternalLink = () => {
        setData("external_links", [
            ...data.external_links,
            { label: "", url: "" },
        ]);
    };

    const updateExternalLink = (
        index: number,
        field: keyof BlogExternalLinkFormData,
        value: string,
    ) => {
        setData(
            "external_links",
            data.external_links.map((link, linkIndex) =>
                linkIndex === index ? { ...link, [field]: value } : link,
            ),
        );
    };

    const removeExternalLink = (index: number) => {
        setData(
            "external_links",
            data.external_links.filter((_, linkIndex) => linkIndex !== index),
        );
    };

    const fieldErrors = errors as Record<string, string | undefined>;

    return (
        <AuthenticatedLayout>
            <Head title={existingPost ? "Modifier l'article" : "Nouvel article"} />

            <main className="mx-auto max-w-5xl px-4 py-10 pb-20">
                <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route("blog.admin.index")}>
                            <ArrowLeft className="h-4 w-4" />
                            Retour au blog
                        </Link>
                    </Button>
                    {existingPost?.isPublished && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route("blog.show", existingPost.slug)}>
                                Voir l'article
                            </Link>
                        </Button>
                    )}
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {existingPost
                                    ? "Modifier l'article"
                                    : "Nouvel article"}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="grid gap-5 md:grid-cols-2">
                                <div className="space-y-2 md:col-span-2">
                                    <Label htmlFor="blog-title">Titre</Label>
                                    <Input
                                        id="blog-title"
                                        value={data.title}
                                        onChange={(event) =>
                                            setData("title", event.target.value)
                                        }
                                        placeholder="Titre de l'article"
                                    />
                                    <InputError message={errors.title} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="blog-type">
                                        Type de contenu
                                    </Label>
                                    <select
                                        id="blog-type"
                                        value={data.content_type}
                                        onChange={(event) =>
                                            setContentType(
                                                event.target
                                                    .value as BlogFormData["content_type"],
                                            )
                                        }
                                        className="h-11 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary/30"
                                    >
                                        <option value="resource">Ressource</option>
                                        {canCreateGlobalPost && (
                                            <option value="blog">Blog</option>
                                        )}
                                    </select>
                                    <InputError message={errors.content_type} />
                                </div>

                                {data.content_type === "resource" && (
                                    <div className="space-y-2">
                                        <Label htmlFor="blog-client">
                                            Client associé
                                        </Label>
                                        <select
                                            id="blog-client"
                                            value={data.client_id ?? ""}
                                            onChange={(event) =>
                                                setData(
                                                    "client_id",
                                                    event.target.value
                                                        ? Number(
                                                              event.target.value,
                                                          )
                                                        : null,
                                                )
                                            }
                                            className="h-11 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary/30"
                                        >
                                            {clients.map((client) => (
                                                <option
                                                    key={client.id}
                                                    value={client.id}
                                                >
                                                    {client.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.client_id} />
                                    </div>
                                )}

                                {data.content_type === "blog" && (
                                    <div className="space-y-2">
                                        <Label htmlFor="blog-category">
                                            Catégorie
                                        </Label>
                                        <select
                                            id="blog-category"
                                            value={data.category ?? ""}
                                            onChange={(event) =>
                                                setData(
                                                    "category",
                                                    event.target.value
                                                        ? (event.target
                                                              .value as BlogFormData["category"])
                                                        : null,
                                                )
                                            }
                                            className="h-11 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-primary/30"
                                        >
                                            <option value="">
                                                Sans catégorie
                                            </option>
                                            <option value="actualites">
                                                Actualités
                                            </option>
                                            <option value="projets">
                                                Projets
                                            </option>
                                            <option value="conseils">
                                                Conseils
                                            </option>
                                        </select>
                                        <InputError message={errors.category} />
                                    </div>
                                )}

                                <label className="flex items-center gap-3 rounded-md border p-3 text-sm font-medium">
                                    <input
                                        type="checkbox"
                                        checked={data.is_published}
                                        onChange={(event) =>
                                            setData(
                                                "is_published",
                                                event.target.checked,
                                            )
                                        }
                                        className="h-4 w-4 rounded border-input"
                                    />
                                    Publier l'article
                                </label>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="blog-content">Contenu</Label>
                                <Textarea
                                    id="blog-content"
                                    value={data.content}
                                    onChange={(event) =>
                                        setData("content", event.target.value)
                                    }
                                    className="min-h-[340px]"
                                    placeholder="Rédigez le contenu de l'article..."
                                />
                                <InputError message={errors.content} />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <CardTitle>Liens externes</CardTitle>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addExternalLink}
                            >
                                <Plus className="h-4 w-4" />
                                Ajouter un lien
                            </Button>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {data.external_links.length === 0 ? (
                                <div className="rounded-md border bg-muted/40 p-4 text-sm text-muted-foreground">
                                    Aucun lien externe ajouté.
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {data.external_links.map((link, index) => (
                                        <div
                                            key={index}
                                            className="grid gap-3 rounded-md border p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto]"
                                        >
                                            <div className="space-y-2">
                                                <Label
                                                    htmlFor={`external-link-label-${index}`}
                                                >
                                                    Libellé
                                                </Label>
                                                <Input
                                                    id={`external-link-label-${index}`}
                                                    value={link.label}
                                                    onChange={(event) =>
                                                        updateExternalLink(
                                                            index,
                                                            "label",
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="Google Slides, Canva..."
                                                />
                                                <InputError
                                                    message={
                                                        fieldErrors[
                                                            `external_links.${index}.label`
                                                        ]
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label
                                                    htmlFor={`external-link-url-${index}`}
                                                >
                                                    URL
                                                </Label>
                                                <Input
                                                    id={`external-link-url-${index}`}
                                                    value={link.url}
                                                    onChange={(event) =>
                                                        updateExternalLink(
                                                            index,
                                                            "url",
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="https://..."
                                                />
                                                <InputError
                                                    message={
                                                        fieldErrors[
                                                            `external_links.${index}.url`
                                                        ]
                                                    }
                                                />
                                            </div>
                                            <div className="flex items-end justify-end">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    title="Retirer le lien"
                                                    onClick={() =>
                                                        removeExternalLink(index)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                            <InputError message={errors.external_links} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Image à la une</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            {currentImageUrl ? (
                                <div className="flex flex-col gap-4 md:flex-row md:items-center">
                                    <div className="h-28 w-44 overflow-hidden rounded-md border bg-muted">
                                        <img
                                            src={currentImageUrl}
                                            alt=""
                                            className="h-full w-full object-cover"
                                        />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            setData((values) => ({
                                                ...values,
                                                featured_image_id: null,
                                                remove_featured_image: true,
                                            }));
                                        }}
                                    >
                                        <X className="h-4 w-4" />
                                        Retirer l'image
                                    </Button>
                                </div>
                            ) : (
                                <div className="rounded-md border bg-muted/40 p-4 text-sm text-muted-foreground">
                                    Aucune image sélectionnée.
                                </div>
                            )}

                            {imageOptions.length > 0 && (
                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    {imageOptions.map((image) => {
                                        const selected =
                                            image.id === data.featured_image_id;

                                        return (
                                            <button
                                                key={image.id}
                                                type="button"
                                                className={cn(
                                                    "overflow-hidden rounded-md border bg-card text-left transition-colors hover:border-primary",
                                                    selected &&
                                                        "border-primary ring-2 ring-primary/20",
                                                )}
                                                onClick={() =>
                                                    setData((values) => ({
                                                        ...values,
                                                        featured_image_id:
                                                            image.id,
                                                        remove_featured_image:
                                                            false,
                                                    }))
                                                }
                                            >
                                                <div className="flex aspect-[4/3] items-center justify-center bg-muted">
                                                    {image.thumbUrl ? (
                                                        <img
                                                            src={image.thumbUrl}
                                                            alt=""
                                                            className="h-full w-full object-cover"
                                                        />
                                                    ) : (
                                                        <ImageIcon className="h-6 w-6 text-muted-foreground" />
                                                    )}
                                                </div>
                                                <div className="space-y-2 p-3">
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="line-clamp-2 text-sm font-semibold leading-5">
                                                            {image.title}
                                                        </div>
                                                        {selected && (
                                                            <Check className="mt-0.5 h-4 w-4 text-primary" />
                                                        )}
                                                    </div>
                                                    <div className="text-xs leading-5 text-muted-foreground">
                                                        {image.clientName ||
                                                            "Client inconnu"}
                                                        {image.projectName &&
                                                            ` · ${image.projectName}`}
                                                    </div>
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                            <InputError message={errors.featured_image_id} />
                        </CardContent>
                    </Card>

                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <Button variant="outline" type="button" asChild>
                            <Link href={route("blog.admin.index")}>Annuler</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <Save className="h-4 w-4" />
                            {processing ? "Enregistrement..." : "Enregistrer"}
                        </Button>
                    </div>

                    <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                        <Badge variant={data.is_published ? "default" : "outline"}>
                            {data.is_published ? "Publié" : "Brouillon"}
                        </Badge>
                        <span>
                            Les brouillons restent visibles uniquement dans
                            l'administration.
                        </span>
                    </div>
                </form>
            </main>
        </AuthenticatedLayout>
    );
}
