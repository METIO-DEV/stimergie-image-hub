import { ContactModal } from "@/Components/Legacy/LegacyModals";
import AppFooter from "@/Components/AppFooter";
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
    Building2,
    ChevronRight,
    BookOpenText,
    Download,
    FolderOpen,
    History,
    Image,
    LayoutDashboard,
    LogOut,
    Mail,
    Menu,
    ServerCog,
    Shield,
    ShoppingBasket,
    User,
    Users,
} from "lucide-react";
import { PropsWithChildren, ReactNode, useEffect, useMemo, useState } from "react";

type MenuItem = {
    href: string;
    label: string;
    icon?: typeof Image;
    active?: boolean;
    disabled?: boolean;
};

type BreadcrumbItem = {
    label: string;
    href?: string;
};

type BlogBreadcrumbPost = {
    title?: string;
    contentType?: "resource" | "ensemble";
};

export default function Authenticated({
    header,
    basketAction,
    navActions,
    children,
}: PropsWithChildren<{
    header?: ReactNode;
    basketAction?: ReactNode;
    navActions?: ReactNode;
}>) {
    const page = usePage();
    const { abilities, user } = page.props.auth;
    const { flash } = page.props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const [contactOpen, setContactOpen] = useState(false);
    const [basketCount, setBasketCount] = useState(0);

    const isSuperAdmin = abilities.isSuperAdmin;
    const initials = useMemo(
        () => {
            if (!user) {
                return "";
            }

            return (
            user.name
                .split(" ")
                .map((part) => part.charAt(0))
                .join("")
                .slice(0, 2)
                    .toUpperCase() || user.email.charAt(0).toUpperCase()
            );
        },
        [user],
    );

    useEffect(() => {
        if (!user) {
            return;
        }

        const readBasketCount = () => {
            try {
                const storedSelection = window.localStorage.getItem(
                    gallerySelectionStorageKey(user.id),
                );
                const parsed = storedSelection ? JSON.parse(storedSelection) : null;
                const ids = Array.isArray(parsed)
                    ? parsed
                    : Array.isArray(parsed?.ids)
                      ? parsed.ids
                      : [];

                setBasketCount(ids.length);
            } catch {
                setBasketCount(0);
            }
        };

        readBasketCount();
        window.addEventListener("storage", readBasketCount);
        window.addEventListener("stimergie:gallery-selection", readBasketCount);

        return () => {
            window.removeEventListener("storage", readBasketCount);
            window.removeEventListener(
                "stimergie:gallery-selection",
                readBasketCount,
            );
        };
    }, [user]);

    if (!user) {
        return null;
    }

    const blogPostForNav =
        "post" in page.props
            ? (page.props.post as BlogBreadcrumbPost | undefined)
            : undefined;
    const isResourceSection =
        route().current("blog.resources") ||
        route().current("blog.resources.fr") ||
        (route().current("blog.show") &&
            blogPostForNav?.contentType !== "ensemble");
    const isBlogSection =
        route().current("blog.ensemble") ||
        (route().current("blog.show") &&
            blogPostForNav?.contentType === "ensemble");

    const primaryNav: MenuItem[] = [
        {
            href: route("gallery.index"),
            label: "Banque d'images",
            icon: Image,
            active: route().current("gallery.index"),
        },
        {
            href: route("blog.resources"),
            label: "Ressources",
            icon: BookOpenText,
            active: isResourceSection,
        },
        {
            href: route("blog.ensemble"),
            label: "Blog",
            icon: BookOpenText,
            active: isBlogSection,
        },
        {
            href: route("contact.index"),
            label: "Contacter",
            icon: Mail,
            active: route().current("contact.index"),
        },
    ];

    const userMenu: MenuItem[] = [
        {
            href: route("gallery.index"),
            label: "Galerie",
            icon: Image,
            active: route().current("gallery.index"),
        },
        {
            href: route("projects.index"),
            label: "Projets",
            icon: FolderOpen,
            active: route().current("projects.index"),
        },
        {
            href: route("downloads.index"),
            label: "Vos téléchargements",
            icon: Download,
            active: route().current("downloads.index"),
        },
        {
            href: route("rights-extension-requests.index"),
            label: "Demandes de cession",
            icon: Shield,
            active: route().current("rights-extension-requests.index"),
        },
        {
            href: route("profile.edit"),
            label: "Profil",
            icon: User,
            active: route().current("profile.edit"),
        },
    ];

    const adminMenu: MenuItem[] = [
        {
            href: route("dashboard"),
            label: "Tableau de bord",
            icon: LayoutDashboard,
            active: route().current("dashboard"),
        },
        {
            href: route("images.index"),
            label: "Gestion des images",
            icon: Image,
            active: route().current("images.index"),
        },
        {
            href: route("operations.index"),
            label: "Suivi opérationnel",
            icon: History,
            active: route().current("operations.index"),
        },
        {
            href: route("asset-transfers.index"),
            label: "Transferts FTP",
            icon: ServerCog,
            active: route().current("asset-transfers.*"),
        },
        {
            href: route("blog.admin.index"),
            label: "Blog et ressources",
            icon: BookOpenText,
            active: route().current("blog.admin.*") || route().current("blog.*"),
        },
        {
            href: route("clients.index"),
            label: "Gestion des entreprises",
            icon: Building2,
            active: route().current("clients.*"),
        },
        {
            href: route("access-periods.index"),
            label: "Droits d'accès",
            icon: Shield,
            active: route().current("access-periods.index"),
        },
        {
            href: route("users.index"),
            label: "Gestion des utilisateurs",
            icon: Users,
            active: route().current("users.index"),
        },
    ];

    const visibleAdminMenu = isSuperAdmin
        ? adminMenu
        : adminMenu.filter((item) => {
              if (item.label === "Gestion des images") {
                  return abilities.canManageClientContent;
              }

              if (item.label === "Suivi opérationnel") {
                  return abilities.canViewOperationalLogs;
              }

              if (item.label === "Blog et ressources") {
                  return abilities.canManageClientContent;
              }

              if (item.label === "Gestion des entreprises") {
                  return abilities.canViewClientManagement;
              }

              if (item.label === "Droits d'accès") {
                  return abilities.canViewAccessPeriods;
              }

              if (item.label === "Gestion des utilisateurs") {
                  return abilities.canViewUsers;
              }

              return false;
          });
    const visibleUserMenu = userMenu.filter(
        (item) =>
            !(
                abilities.canManageClientContent &&
                item.href === route("rights-extension-requests.index")
            ),
    );
    const mobileNav = [
        ...primaryNav,
        ...visibleUserMenu.filter((item) => item.href !== route("gallery.index")),
        ...visibleAdminMenu,
    ];
    const breadcrumbs = breadcrumbItems(page.props);

    return (
        <div className="flex min-h-screen flex-col bg-background text-foreground">
            <header className="sticky top-0 z-50 w-full border-b border-border/80 bg-[#F2F0F0]/95 backdrop-blur">
                <div className="container flex h-16 items-center">
                    <div className="mr-8 hidden items-center md:flex">
                        <Link
                            href={route("gallery.index")}
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
                                const isContact =
                                    item.href === route("contact.index");

                                return isContact ? (
                                    <button
                                        key={item.label}
                                        type="button"
                                        onClick={() => setContactOpen(true)}
                                        className="inline-flex items-center text-base font-semibold text-foreground transition-colors hover:text-primary"
                                    >
                                        <span>{item.label}</span>
                                    </button>
                                ) : (
                                    <Link
                                        key={item.label}
                                        href={item.href}
                                        className={cn(
                                            "inline-flex items-center text-base font-semibold transition-colors hover:text-primary",
                                            item.active
                                                ? "text-primary"
                                                : "text-foreground",
                                            item.disabled &&
                                                "pointer-events-none",
                                        )}
                                    >
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
                        <SheetContent
                            side="left"
                            className="w-[min(20rem,calc(100vw-2rem))] overflow-y-auto bg-[#f7f8f8]"
                        >
                            <div className="mb-6 border-b pb-4">
                                <img
                                    src="/logo_stimergie_header.png"
                                    alt="Stimergie"
                                    className="h-8 w-auto"
                                />
                            </div>
                            <nav className="space-y-1">
                                {mobileNav.map((item) => {
                                    const Icon = item.icon;
                                    const isContact =
                                        item.href === route("contact.index");

                                    return isContact ? (
                                        <button
                                            key={item.label}
                                            type="button"
                                            onClick={() => {
                                                setMobileOpen(false);
                                                setContactOpen(true);
                                            }}
                                            className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium text-foreground transition-colors hover:bg-primary/5 hover:text-primary"
                                        >
                                            {Icon && <Icon className="h-4 w-4" />}
                                            {item.label}
                                        </button>
                                    ) : (
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
                                            {Icon && <Icon className="h-4 w-4" />}
                                            {item.label}
                                        </Link>
                                    );
                                })}
                            </nav>
                            <div className="mt-6 border-t pt-4">
                                <Link
                                    href={route("logout")}
                                    method="post"
                                    as="button"
                                    className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm font-medium text-foreground transition-colors hover:bg-primary/5 hover:text-primary"
                                >
                                    <LogOut className="h-4 w-4" />
                                    Déconnexion
                                </Link>
                            </div>
                        </SheetContent>
                    </Sheet>

                    <div className="flex flex-1 items-center justify-between gap-2 md:justify-end">
                        <Link
                            href={route("gallery.index")}
                            className="flex items-center md:hidden"
                        >
                            <img
                                src="/logo_stimergie_header.png"
                                alt="Stimergie"
                                className="h-7 w-auto"
                            />
                        </Link>

                        <div className="flex items-center gap-2">
                            {navActions && (
                                <div className="hidden items-center md:flex">
                                    {navActions}
                                </div>
                            )}

                            <div className="flex items-center">
                                {basketAction ?? (
                                    <Link
                                        href={route("gallery.index", {
                                            basket: "1",
                                        })}
                                        className="inline-flex h-9 items-center gap-2 rounded-full border border-border bg-background/80 px-3 text-sm font-medium shadow-sm transition hover:bg-white"
                                        title="Ouvrir le panier"
                                        aria-label={`Ouvrir le panier, ${basketCount} image${basketCount > 1 ? "s" : ""} sélectionnée${basketCount > 1 ? "s" : ""}`}
                                    >
                                        <span className="relative inline-flex">
                                            <ShoppingBasket className="h-4 w-4" />
                                            <span className="absolute -right-2 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[0.65rem] font-bold leading-none text-primary-foreground">
                                                {basketCount}
                                            </span>
                                        </span>
                                        <span className="hidden sm:inline">
                                            Panier
                                        </span>
                                    </Link>
                                )}
                            </div>
                        </div>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="hidden rounded-full hover:bg-white/60 md:inline-flex"
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
                                {visibleUserMenu.map((item) => (
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
                {(flash.warning || flash.error || flash.success) && (
                    <div className="container pt-6">
                        <div
                            className={cn(
                                "rounded-md border px-4 py-3 text-sm font-medium",
                                flash.error &&
                                    "border-destructive/30 bg-destructive/10 text-destructive",
                                flash.warning &&
                                    !flash.error &&
                                    "border-amber-300 bg-amber-50 text-amber-900",
                                flash.success &&
                                    !flash.error &&
                                    !flash.warning &&
                                    "border-emerald-300 bg-emerald-50 text-emerald-900",
                            )}
                        >
                            {flash.error || flash.warning || flash.success}
                        </div>
                    </div>
                )}
                {breadcrumbs.length > 0 && (
                    <nav
                        aria-label="Fil d'Ariane"
                        className="container pt-5 text-sm text-muted-foreground"
                    >
                        <ol className="flex flex-wrap items-center gap-2">
                            {breadcrumbs.map((item, index) => {
                                const isLast = index === breadcrumbs.length - 1;

                                return (
                                    <li
                                        key={`${item.label}-${index}`}
                                        className="flex items-center gap-2"
                                    >
                                        {index > 0 && (
                                            <ChevronRight className="h-4 w-4" />
                                        )}
                                        {item.href && !isLast ? (
                                            <Link
                                                href={item.href}
                                                className="font-medium text-foreground transition-colors hover:text-primary"
                                            >
                                                {item.label}
                                            </Link>
                                        ) : (
                                            <span
                                                className={cn(
                                                    isLast &&
                                                        "font-medium text-foreground",
                                                )}
                                            >
                                                {item.label}
                                            </span>
                                        )}
                                    </li>
                                );
                            })}
                        </ol>
                    </nav>
                )}
                {header && <div className="container pt-9">{header}</div>}
                {children}
            </main>

            <AppFooter />
            <ContactModal open={contactOpen} onOpenChange={setContactOpen} />
        </div>
    );
}

function gallerySelectionStorageKey(userId: number | string): string {
    return `stimergie.gallery.selection.${userId}`;
}

function breadcrumbItems(props?: object): BreadcrumbItem[] {
    const post =
        props && "post" in props
            ? (props.post as BlogBreadcrumbPost | undefined)
            : undefined;

    const home: BreadcrumbItem = {
        label: "Galerie",
        href: route("gallery.index"),
    };

    if (route().current("gallery.index")) {
        return [];
    }

    const current = route().current();

    if (route().current("dashboard")) {
        return [home, { label: "Tableau de bord" }];
    }

    if (route().current("contact.index")) {
        return [home, { label: "Contacter" }];
    }

    if (route().current("downloads.index")) {
        return [home, { label: "Téléchargements" }];
    }

    if (route().current("rights-extension-requests.index")) {
        return [home, { label: "Demandes de cession" }];
    }

    if (route().current("operations.index")) {
        return [home, { label: "Suivi opérationnel" }];
    }

    if (route().current("projects.index")) {
        return [home, { label: "Projets" }];
    }

    if (route().current("images.index")) {
        return [home, { label: "Images" }];
    }

    if (
        route().current("blog.admin.index") ||
        route().current("blog.create") ||
        route().current("blog.edit")
    ) {
        return [home, { label: "Blog et ressources" }];
    }

    if (route().current("blog.resources") || route().current("blog.resources.fr")) {
        return [home, { label: "Ressources" }];
    }

    if (route().current("blog.ensemble")) {
        return [home, { label: "Blog" }];
    }

    if (route().current("blog.show")) {
        const section =
            post?.contentType === "ensemble"
                ? { label: "Blog", href: route("blog.ensemble") }
                : { label: "Ressources", href: route("blog.resources") };

        return [home, section, { label: post?.title || "Article" }];
    }

    if (route().current("imports.index")) {
        return [home, { label: "Imports" }];
    }

    if (route().current("asset-transfers.*")) {
        return [home, { label: "Transferts FTP" }];
    }

    if (route().current("clients.create")) {
        return [
            home,
            { label: "Entreprises", href: route("clients.index") },
            { label: "Nouvelle entreprise" },
        ];
    }

    if (route().current("clients.edit")) {
        return [
            home,
            { label: "Entreprises", href: route("clients.index") },
            { label: "Modifier" },
        ];
    }

    if (route().current("clients.show")) {
        return [
            home,
            { label: "Entreprises", href: route("clients.index") },
            { label: "Détail" },
        ];
    }

    if (route().current("clients.index")) {
        return [home, { label: "Entreprises" }];
    }

    if (route().current("users.index")) {
        return [home, { label: "Utilisateurs" }];
    }

    if (route().current("access-periods.index")) {
        return [home, { label: "Droits d'accès" }];
    }

    if (route().current("profile.edit")) {
        return [home, { label: "Profil" }];
    }

    return current ? [home, { label: current }] : [];
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
                {Icon && <Icon className="h-5 w-5" />}
                {item.label}
            </Link>
        </DropdownMenuItem>
    );
}
