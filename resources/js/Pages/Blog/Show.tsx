import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent } from "@/Components/ui/card";
import { Head, Link } from "@inertiajs/react";
import { ArrowLeft, ExternalLink, Pencil } from "lucide-react";
import PublicBlogLayout from "./PublicLayout";
import { BlogPost } from "./types";

type Props = {
    post: BlogPost;
    canEdit: boolean;
};

export default function Show({ post, canEdit }: Props) {
    const backRoute =
        post.contentType === "blog" ? "blog.index" : "blog.resources";

    return (
        <PublicBlogLayout
            section={post.contentType === "blog" ? "blog" : "resources"}
        >
            <Head title={post.title} />

            <article className="container py-8 md:py-12">
                <div className="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route(backRoute)}>
                            <ArrowLeft className="h-4 w-4" />
                            Retour
                        </Link>
                    </Button>
                    {canEdit && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route("blog.edit", post.id)}>
                                <Pencil className="h-4 w-4" />
                                Modifier
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="mx-auto max-w-4xl">
                    <div className="mb-6 flex flex-wrap items-center gap-2">
                        <Badge variant="secondary">
                            {post.contentTypeLabel}
                        </Badge>
                        {post.categoryLabel && (
                            <Badge variant="outline">
                                {post.categoryLabel}
                            </Badge>
                        )}
                        {post.clientName && (
                            <Badge variant="outline">{post.clientName}</Badge>
                        )}
                    </div>

                    <h1 className="break-words text-3xl font-bold leading-tight tracking-normal text-foreground md:text-5xl">
                        {post.title}
                    </h1>
                    <p className="mt-4 text-sm text-muted-foreground">
                        Publié le {formatDate(post.publishedAt || post.createdAt)}
                    </p>

                    {post.featuredImageUrl && (
                        <div className="mt-8 overflow-hidden rounded-lg border bg-muted">
                            <img
                                src={post.featuredImageUrl}
                                alt=""
                                className="max-h-[520px] w-full object-cover"
                            />
                        </div>
                    )}

                    <Card className="mt-8">
                        <CardContent className="p-6 md:p-8">
                            <div className="whitespace-pre-line text-base leading-8 text-foreground">
                                {post.content}
                            </div>
                        </CardContent>
                    </Card>

                    {post.externalLinks.length > 0 && (
                        <section className="mt-8 rounded-lg border bg-card p-6 md:p-8">
                            <h2 className="text-lg font-semibold">
                                Supports externes
                            </h2>
                            <div className="mt-4 grid gap-3">
                                {post.externalLinks.map((link) => (
                                    <a
                                        key={link.url}
                                        href={link.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="flex min-w-0 items-center justify-between gap-4 rounded-md border px-4 py-3 text-sm font-medium transition-colors hover:border-primary hover:text-primary"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate">
                                                {link.label}
                                            </span>
                                            {link.host && (
                                                <span className="mt-1 block truncate text-xs font-normal text-muted-foreground">
                                                    {link.host}
                                                </span>
                                            )}
                                        </span>
                                        <ExternalLink className="h-4 w-4 shrink-0" />
                                    </a>
                                ))}
                            </div>
                        </section>
                    )}
                </div>
            </article>
        </PublicBlogLayout>
    );
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "long",
        year: "numeric",
    }).format(new Date(value));
}
