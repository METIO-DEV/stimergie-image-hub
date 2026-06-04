import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent } from "@/Components/ui/card";
import { Head, Link } from "@inertiajs/react";
import { ArrowRight } from "lucide-react";
import PublicBlogLayout from "./PublicLayout";
import { BlogPost } from "./types";

type Props = {
    posts: BlogPost[];
    title: string;
    description: string;
    contentType: "resource" | "ensemble";
};

export default function PublicIndex({ posts, title, description }: Props) {
    return (
        <PublicBlogLayout>
            <Head title={title} />

            <section className="border-b bg-muted/30">
                <div className="container py-12 md:py-16">
                    <div className="max-w-3xl">
                        <h1 className="text-4xl font-bold tracking-normal text-foreground">
                            {title}
                        </h1>
                        <p className="mt-4 text-base leading-7 text-muted-foreground">
                            {description}
                        </p>
                    </div>
                </div>
            </section>

            <section className="container py-10 md:py-14">
                {posts.length === 0 ? (
                    <div className="rounded-lg border bg-card p-8 text-sm text-muted-foreground">
                        Aucun contenu publié pour le moment.
                    </div>
                ) : (
                    <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        {posts.map((post) => (
                            <ArticleCard key={post.id} post={post} />
                        ))}
                    </div>
                )}
            </section>
        </PublicBlogLayout>
    );
}

function ArticleCard({ post }: { post: BlogPost }) {
    return (
        <Card className="flex h-full overflow-hidden">
            <div className="flex w-full flex-col">
                {post.featuredImageUrl && (
                    <Link
                        href={route("blog.show", post.slug)}
                        className="block aspect-[16/9] overflow-hidden bg-muted"
                    >
                        <img
                            src={post.featuredImageUrl}
                            alt=""
                            className="h-full w-full object-cover transition-transform duration-200 hover:scale-[1.02]"
                        />
                    </Link>
                )}
                <CardContent className="flex flex-1 flex-col p-6">
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <Badge variant="secondary">
                            {post.contentTypeLabel}
                        </Badge>
                        {post.categoryLabel && (
                            <Badge variant="outline">
                                {post.categoryLabel}
                            </Badge>
                        )}
                    </div>
                    <h2 className="text-xl font-semibold leading-tight">
                        <Link
                            href={route("blog.show", post.slug)}
                            className="transition-colors hover:text-primary"
                        >
                            {post.title}
                        </Link>
                    </h2>
                    <p className="mt-3 line-clamp-3 text-sm leading-6 text-muted-foreground">
                        {post.excerpt}
                    </p>
                    <div className="mt-auto pt-6">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route("blog.show", post.slug)}>
                                Lire l'article
                                <ArrowRight className="h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </div>
        </Card>
    );
}
