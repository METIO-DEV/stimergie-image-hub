import AppFooter from "@/Components/AppFooter";
import { Button } from "@/Components/ui/button";
import { Link } from "@inertiajs/react";
import { PropsWithChildren } from "react";

export default function PublicBlogLayout({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <header className="border-b border-border/80 bg-[#F2F0F0]">
                <div className="container flex h-16 items-center justify-between gap-6">
                    <Link href={route("gallery.index")}>
                        <img
                            src="/logo_stimergie_header.png"
                            alt="Stimergie"
                            className="h-8 w-auto"
                        />
                    </Link>
                    <nav className="flex items-center gap-2 text-sm font-semibold">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={route("blog.resources")}>
                                Ressources
                            </Link>
                        </Button>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={route("blog.ensemble")}>Ensemble</Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route("gallery.index")}>
                                Banque d'images
                            </Link>
                        </Button>
                    </nav>
                </div>
            </header>
            <main className="flex-1">{children}</main>
            <AppFooter />
        </div>
    );
}
