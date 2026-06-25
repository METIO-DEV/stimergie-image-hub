import AppFooter from "@/Components/AppFooter";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { PropsWithChildren } from "react";

type Props = PropsWithChildren<{
    section?: "resources" | "blog";
}>;

export default function PublicBlogLayout({ children, section }: Props) {
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
                        <PublicNavLink
                            href={route("blog.resources")}
                            active={section === "resources"}
                        >
                            Ressources
                        </PublicNavLink>
                        <PublicNavLink
                            href={route("blog.ensemble")}
                            active={section === "blog"}
                        >
                            Blog
                        </PublicNavLink>
                        <PublicNavLink href={route("gallery.index")}>
                            Banque d'images
                        </PublicNavLink>
                    </nav>
                </div>
            </header>
            <main className="flex-1">{children}</main>
            <AppFooter />
        </div>
    );
}

function PublicNavLink({
    href,
    active = false,
    children,
}: PropsWithChildren<{ href: string; active?: boolean }>) {
    return (
        <Link
            href={href}
            aria-current={active ? "page" : undefined}
            className={cn(
                "rounded-md px-3 py-2 text-sm transition-colors",
                active
                    ? "bg-[#150B0D] text-white shadow-sm"
                    : "text-[#150B0D]/70 hover:bg-white/70 hover:text-[#150B0D]",
            )}
        >
            {children}
        </Link>
    );
}
