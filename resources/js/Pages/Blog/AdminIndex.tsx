import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import { LegacySelect, SectionHeader } from "@/Components/Legacy/LegacyDesign";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, router } from "@inertiajs/react";
import { Edit, Eye, FileText, Plus, Trash2 } from "lucide-react";
import { useMemo, useState } from "react";
import { BlogClientOption, BlogPost } from "./types";

type Props = {
    posts: BlogPost[];
    filters: {
        clients: BlogClientOption[];
    };
    canCreatePost: boolean;
};

export default function AdminIndex({
    posts,
    filters,
    canCreatePost,
}: Props) {
    const [search, setSearch] = useState("");
    const [clientFilter, setClientFilter] = useState("");
    const [typeFilter, setTypeFilter] = useState("");

    const filteredPosts = useMemo(() => {
        const query = search.trim().toLowerCase();

        return posts.filter((post) => {
            const matchesSearch =
                !query ||
                post.title.toLowerCase().includes(query) ||
                post.excerpt.toLowerCase().includes(query) ||
                (post.clientName || "").toLowerCase().includes(query);
            const matchesClient =
                !clientFilter || String(post.clientId || "") === clientFilter;
            const matchesType = !typeFilter || post.contentType === typeFilter;

            return matchesSearch && matchesClient && matchesType;
        });
    }, [clientFilter, posts, search, typeFilter]);

    const deletePost = (post: BlogPost) => {
        if (!window.confirm(`Supprimer l'article "${post.title}" ?`)) {
            return;
        }

        router.delete(route("blog.destroy", post.id), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Blog et ressources" />

            <SectionHeader
                title="Blog et ressources"
                description="Gérez les contenus éditoriaux publiés dans les espaces Ressources et Blog."
                action={
                    canCreatePost && (
                        <Button asChild className="h-auto whitespace-normal text-left">
                            <Link href={route("blog.create")}>
                                <Plus className="h-4 w-4" />
                                Nouvel article
                            </Link>
                        </Button>
                    )
                }
            />

            <main className="mx-auto max-w-7xl px-4 pb-20">
                <div className="mb-6 mt-8 grid gap-4 lg:grid-cols-[1fr_240px_240px]">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Rechercher un article..."
                    />
                    <LegacySelect
                        value={clientFilter}
                        onChange={setClientFilter}
                        options={filters.clients}
                        allLabel="Tous les clients"
                    />
                    <select
                        value={typeFilter}
                        onChange={(event) => setTypeFilter(event.target.value)}
                        className="h-11 w-full rounded-md border border-input bg-card px-3 text-base outline-none focus:ring-2 focus:ring-primary/30"
                    >
                        <option value="">Tous les types</option>
                        <option value="resource">Ressource</option>
                        <option value="ensemble">Blog</option>
                    </select>
                </div>

                {filteredPosts.length === 0 ? (
                    <div className="rounded-lg border bg-card p-8 text-sm text-muted-foreground">
                        Aucun article ne correspond aux filtres.
                    </div>
                ) : (
                    <div className="space-y-4">
                        {filteredPosts.map((post) => (
                            <PostRow
                                key={post.id}
                                post={post}
                                onDelete={deletePost}
                            />
                        ))}
                    </div>
                )}
            </main>
        </AuthenticatedLayout>
    );
}

function PostRow({
    post,
    onDelete,
}: {
    post: BlogPost;
    onDelete: (post: BlogPost) => void;
}) {
    return (
        <Card>
            <CardContent className="grid gap-4 p-5 md:grid-cols-[96px_minmax(0,1fr)_220px] md:items-center">
                <div className="flex h-20 w-24 items-center justify-center overflow-hidden rounded-md bg-muted">
                    {post.featuredImageUrl ? (
                        <img
                            src={post.featuredImageUrl}
                            alt=""
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <FileText className="h-7 w-7 text-muted-foreground" />
                    )}
                </div>

                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant={post.isPublished ? "default" : "outline"}>
                            {post.isPublished ? "Publié" : "Brouillon"}
                        </Badge>
                        <Badge variant="secondary">{post.contentTypeLabel}</Badge>
                        {post.categoryLabel && (
                            <Badge variant="outline">
                                {post.categoryLabel}
                            </Badge>
                        )}
                    </div>
                    <h2 className="mt-3 truncate text-lg font-semibold">
                        {post.title}
                    </h2>
                    <p className="mt-1 line-clamp-2 text-sm leading-6 text-muted-foreground">
                        {post.excerpt}
                    </p>
                    <div className="mt-2 text-xs text-muted-foreground">
                        {post.clientName || "Contenu global"} · Dernière mise à
                        jour le {formatDate(post.updatedAt)}
                    </div>
                </div>

                <div className="flex justify-end gap-2">
                    {post.isPublished && (
                        <Button variant="outline" size="icon" asChild>
                            <Link
                                href={route("blog.show", post.slug)}
                                title="Voir"
                            >
                                <Eye className="h-4 w-4" />
                            </Link>
                        </Button>
                    )}
                    <Button variant="outline" size="icon" asChild>
                        <Link href={route("blog.edit", post.id)} title="Modifier">
                            <Edit className="h-4 w-4" />
                        </Link>
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        title="Supprimer"
                        onClick={() => onDelete(post)}
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(new Date(value));
}
