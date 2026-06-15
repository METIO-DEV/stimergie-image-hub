import AppFooter from "@/Components/AppFooter";
import { Button } from "@/Components/ui/button";
import { Link } from "@inertiajs/react";
import { PropsWithChildren } from "react";

export default function PublicBlogLayout({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <header className="border-b border-border/80 bg-[#F2F0F0]">
                <div className="container flex min-h-16 items-center justify-between gap-3 py-3">
                    <Link href={route("gallery.index")} className="shrink-0">
                        <img
                            src="/logo_stimergie_header.png"
                            alt="Stimergie"
                            className="h-7 w-auto sm:h-8"
                        />
                    </Link>
                    <nav className="flex min-w-0 flex-wrap items-center justify-end gap-1 text-sm font-semibold sm:gap-2">
                        <Button
                            variant="ghost"
                            size="sm"
                            asChild
                            className="h-auto px-2 py-1.5"
                        >
                            <Link href={route("blog.resources")}>
                                Ressources
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            asChild
                            className="h-auto px-2 py-1.5"
                        >
                            <Link href={route("blog.ensemble")}>Ensemble</Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            asChild
                            className="h-auto whitespace-normal px-2 py-1.5 text-center"
                        >
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
