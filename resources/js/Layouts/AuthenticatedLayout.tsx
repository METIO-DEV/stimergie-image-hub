import { Avatar, AvatarFallback } from "@/Components/ui/avatar";
import { Button } from "@/Components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/Components/ui/dropdown-menu";
import { Sheet, SheetContent, SheetTrigger } from "@/Components/ui/sheet";
import { cn } from "@/lib/utils";
import { Link, usePage } from "@inertiajs/react";
import {
    Download,
    FolderOpen,
    Image,
    LogOut,
    Mail,
    Menu,
    Settings,
    Shield,
    User,
    Users,
} from "lucide-react";
import { PropsWithChildren, ReactNode, useMemo, useState } from "react";

type MenuItem = {
    href: string;
    label: string;
    icon: typeof Image;
    active?: boolean;
    disabled?: boolean;
};

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user;
    const [mobileOpen, setMobileOpen] = useState(false);

    const isSuperAdmin = user.platform_role === "super_admin";
    const initials = useMemo(
        () =>
            user.name
                .split(" ")
                .map((part) => part.charAt(0))
                .join("")
                .slice(0, 2)
                .toUpperCase() || user.email.charAt(0).toUpperCase(),
        [user.email, user.name],
    );

    const primaryNav: MenuItem[] = [
        {
            href: "#",
            label: "Banque d'images",
            icon: Image,
            disabled: true,
        },
        {
            href: "#",
            label: "Contact",
            icon: Mail,
            disabled: true,
        },
    ];

    const userMenu: MenuItem[] = [
        { href: "#", label: "Galerie", icon: Image, disabled: true },
        { href: "#", label: "Projets", icon: FolderOpen, disabled: true },
        {
            href: "#",
            label: "Vos téléchargements",
            icon: Download,
            disabled: true,
        },
        {
            href: route("profile.edit"),
            label: "Profil",
            icon: User,
            active: route().current("profile.edit"),
        },
    ];

    const adminMenu: MenuItem[] = [
        { href: "#", label: "Gestion des images", icon: Image, disabled: true },
        {
            href: route("clients.index"),
            label: "Gestion des clients",
            icon: Settings,
            active: route().current("clients.*"),
        },
        {
            href: "#",
            label: "Droits d'accès",
            icon: Shield,
            disabled: true,
        },
        {
            href: "#",
            label: "Gestion des utilisateurs",
            icon: Users,
            disabled: true,
        },
    ];

    const visibleAdminMenu = isSuperAdmin ? adminMenu : adminMenu.slice(0, 1);

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <header className="sticky top-0 z-50 w-full border-b border-border/80 bg-[#F2F0F0]/95 backdrop-blur">
                <div className="container flex h-16 items-center">
                    <div className="mr-8 hidden items-center md:flex">
                        <Link
                            href={route("dashboard")}
                            className="mr-8 flex items-center"
                        >
                            <img
                                src="/logo_stimergie_header.png"
                                alt="Stimergie"
                                className="h-8 w-auto"
                            />
                        </Link>

                        <nav className="flex items-center gap-7">
                            {primaryNav.map((item) => {
                                const Icon = item.icon;

                                return (
                                    <Link
                                        key={item.label}
                                        href={item.href}
                                        className={cn(
                                            "inline-flex items-center gap-3 text-base font-semibold transition-colors hover:text-primary",
                                            item.active
                                                ? "text-primary"
                                                : "text-foreground",
                                            item.disabled &&
                                                "pointer-events-none",
                                        )}
                                    >
                                        {item.label === "Contact" && (
                                            <Icon className="h-5 w-5" />
                                        )}
                                        <span>{item.label}</span>
                                    </Link>
                                );
                            })}
                        </nav>
                    </div>

                    <Sheet open={mobileOpen} onOpenChange={setMobileOpen}>
                        <SheetTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="mr-2 md:hidden"
                            >
                                <Menu className="h-5 w-5" />
                                <span className="sr-only">Ouvrir le menu</span>
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" className="w-80 bg-[#f7f8f8]">
                            <div className="mb-6 border-b pb-4">
                                <img
                                    src="/logo_stimergie_header.png"
                                    alt="Stimergie"
                                    className="h-8 w-auto"
                                />
                            </div>
                            <nav className="space-y-1">
                                {[
                                    ...primaryNav,
                                    ...userMenu,
                                    ...visibleAdminMenu,
                                ].map((item) => {
                                    const Icon = item.icon;

                                    return (
                                        <Link
                                            key={item.label}
                                            href={item.href}
                                            onClick={() => setMobileOpen(false)}
                                            className={cn(
                                                "flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors",
                                                item.active
                                                    ? "bg-primary/10 text-primary"
                                                    : "text-foreground hover:bg-primary/5 hover:text-primary",
                                                item.disabled &&
                                                    "pointer-events-none text-muted-foreground",
                                            )}
                                        >
                                            <Icon className="h-4 w-4" />
                                            {item.label}
                                        </Link>
                                    );
                                })}
                            </nav>
                        </SheetContent>
                    </Sheet>

                    <div className="flex flex-1 items-center justify-between md:justify-end">
                        <Link
                            href={route("dashboard")}
                            className="flex items-center md:hidden"
                        >
                            <img
                                src="/logo_stimergie_header.png"
                                alt="Stimergie"
                                className="h-7 w-auto"
                            />
                        </Link>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="rounded-full hover:bg-white/60"
                                >
                                    <Avatar className="h-9 w-9 border bg-background">
                                        <AvatarFallback className="bg-background text-sm font-semibold text-foreground">
                                            {initials}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent
                                className="w-72 overflow-hidden rounded-xl p-0 shadow-lg"
                                align="end"
                            >
                                <DropdownMenuLabel className="p-4">
                                    <div className="flex flex-col gap-1">
                                        <span className="text-base font-semibold leading-none">
                                            {user.name}
                                        </span>
                                        <span className="text-sm leading-none text-muted-foreground">
                                            {user.email}
                                        </span>
                                        <span className="mt-1 inline-flex w-full rounded-lg bg-muted px-3 py-1.5 text-sm font-semibold text-primary">
                                            {isSuperAdmin
                                                ? "Administrateur"
                                                : "Utilisateur"}
                                        </span>
                                    </div>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                {userMenu.map((item) => (
                                    <UserMenuItem
                                        key={item.label}
                                        item={item}
                                    />
                                ))}
                                <DropdownMenuSeparator />
                                {visibleAdminMenu.map((item) => (
                                    <UserMenuItem
                                        key={item.label}
                                        item={item}
                                    />
                                ))}
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={route("logout")}
                                        method="post"
                                        as="button"
                                        className="flex w-full items-center gap-3 px-4 py-3 text-base"
                                    >
                                        <LogOut className="h-5 w-5" />
                                        Déconnexion
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </header>

            <main className="flex-1 bg-background">
                {header && <div className="container pt-9">{header}</div>}
                {children}
            </main>

            <footer className="border-t border-border/80 bg-[#F2F0F0]">
                <div className="container grid gap-10 py-14 md:grid-cols-[1.4fr_1fr_1fr]">
                    <div>
                        <h2 className="text-2xl font-bold">Stimergie</h2>
                        <p className="mt-8 max-w-sm text-lg font-medium leading-7 text-muted-foreground">
                            Stimergie centralise, organise et valorise vos
                            contenus de marque. Un espace unique, simple et
                            souverain pour gagner du temps, retrouver vos
                            visuels facilement et les utiliser.
                        </p>
                    </div>
                    <FooterColumn
                        title="NAVIGATION"
                        items={["Accueil", "Banque d'images", "À propos"]}
                    />
                    <FooterColumn
                        title="LÉGAL"
                        items={[
                            "Conditions d'utilisation",
                            "Politique de confidentialité",
                            "Licences",
                            "Contact",
                        ]}
                    />
                </div>
            </footer>
        </div>
    );
}

function UserMenuItem({ item }: { item: MenuItem }) {
    const Icon = item.icon;

    return (
        <DropdownMenuItem asChild>
            <Link
                href={item.href}
                className={cn(
                    "flex w-full items-center gap-3 px-4 py-3 text-base",
                    item.disabled && "pointer-events-none",
                )}
            >
                <Icon className="h-5 w-5" />
                {item.label}
            </Link>
        </DropdownMenuItem>
    );
}

function FooterColumn({ title, items }: { title: string; items: string[] }) {
    return (
        <div>
            <h3 className="text-sm font-bold tracking-wide text-muted-foreground">
                {title}
            </h3>
            <div className="mt-7 space-y-5">
                {items.map((item) => (
                    <div key={item} className="text-lg text-foreground">
                        {item}
                    </div>
                ))}
            </div>
        </div>
    );
}
