import AppFooter from "@/Components/AppFooter";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent } from "@/Components/ui/card";
import { Head } from "@inertiajs/react";
import { Download } from "lucide-react";

type SharedImage = {
    id: number;
    title: string;
    description: string | null;
    clientName: string | null;
    projectName: string | null;
    thumbUrl: string | null;
    imageUrl: string | null;
    tags: string[];
};

export default function SharedAlbumShow({
    album,
}: {
    album: {
        name: string;
        description: string | null;
        message: string | null;
        expiresAt: string | null;
        downloadUrl: string;
        images: SharedImage[];
    };
}) {
    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <Head title={album.name} />
            <header className="border-b border-border/80 bg-[#F2F0F0]">
                <div className="container flex h-16 items-center justify-between">
                    <img
                        src="/logo_stimergie_header.png"
                        alt="Stimergie"
                        className="h-8 w-auto"
                    />
                    <Button asChild>
                        <a href={album.downloadUrl}>
                            <Download className="mr-2 h-4 w-4" />
                            Télécharger l'album
                        </a>
                    </Button>
                </div>
            </header>
            <main className="container flex-1 py-10">
                <div className="mb-8 max-w-3xl">
                    <h1 className="break-words text-2xl font-bold leading-tight sm:text-3xl">
                        {album.name}
                    </h1>
                    {album.description && (
                        <p className="mt-3 text-muted-foreground">
                            {album.description}
                        </p>
                    )}
                    {album.message && (
                        <p className="mt-4 rounded-md border bg-card p-4 text-sm">
                            {album.message}
                        </p>
                    )}
                    {album.expiresAt && (
                        <p className="mt-3 text-sm text-muted-foreground">
                            Disponible jusqu'au {formatDate(album.expiresAt)}.
                        </p>
                    )}
                </div>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {album.images.map((image) => (
                        <Card key={image.id} className="overflow-hidden">
                            <div className="aspect-square bg-muted">
                                {image.imageUrl || image.thumbUrl ? (
                                    <img
                                        src={image.imageUrl || image.thumbUrl || ""}
                                        alt={image.title}
                                        className="h-full w-full object-cover"
                                    />
                                ) : null}
                            </div>
                            <CardContent className="space-y-3 p-4">
                                <div>
                                    <h2 className="font-semibold">
                                        {image.title}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {[image.clientName, image.projectName]
                                            .filter(Boolean)
                                            .join(" - ")}
                                    </p>
                                </div>
                                {image.tags.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {image.tags.map((tag) => (
                                            <Badge key={tag} variant="secondary">
                                                #{tag}
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </main>
            <AppFooter />
        </div>
    );
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
    }).format(new Date(value));
}
