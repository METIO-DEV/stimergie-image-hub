import { Head, Link } from "@inertiajs/react";

export default function LegalShow({
    title,
    paragraphs,
}: {
    title: string;
    paragraphs: string[];
}) {
    return (
        <div className="min-h-screen bg-background text-foreground">
            <Head title={title} />
            <header className="border-b border-border/80 bg-[#F2F0F0]">
                <div className="container flex h-16 items-center justify-between">
                    <Link href={route("gallery.index")}>
                        <img
                            src="/logo_stimergie_header.png"
                            alt="Stimergie"
                            className="h-8 w-auto"
                        />
                    </Link>
                    <Link
                        href={route("gallery.index")}
                        className="text-sm font-semibold text-primary"
                    >
                        Banque d'images
                    </Link>
                </div>
            </header>
            <main className="container max-w-3xl py-14">
                <h1 className="text-3xl font-bold">{title}</h1>
                <div className="mt-8 space-y-5 text-base leading-7 text-muted-foreground">
                    {paragraphs.map((paragraph) => (
                        <p key={paragraph}>{paragraph}</p>
                    ))}
                </div>
            </main>
        </div>
    );
}
