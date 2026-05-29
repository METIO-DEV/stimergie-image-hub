import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetTrigger } from '@/Components/ui/sheet';
import { cn } from '@/lib/utils';
import { Link, usePage } from '@inertiajs/react';
import {
    Download,
    FolderOpen,
    Image,
    LogOut,
    Menu,
    Shield,
    User,
    Users,
} from 'lucide-react';
import { PropsWithChildren, ReactNode, useMemo, useState } from 'react';

type NavItem = {
    href: string;
    label: string;
    icon: typeof Image;
    active: boolean;
    show: boolean;
};

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user;
    const [mobileOpen, setMobileOpen] = useState(false);

    const isSuperAdmin = user.platform_role === 'super_admin';
    const initials = useMemo(
        () =>
            user.name
                .split(' ')
                .map((part) => part.charAt(0))
                .join('')
                .slice(0, 2)
                .toUpperCase() || user.email.charAt(0).toUpperCase(),
        [user.email, user.name],
    );

    const items: NavItem[] = [
        {
            href: route('dashboard'),
            label: 'Tableau de bord',
            icon: Image,
            active: route().current('dashboard'),
            show: true,
        },
        {
            href: route('clients.index'),
            label: 'Clients',
            icon: Users,
            active: route().current('clients.*'),
            show: true,
        },
        {
            href: '#',
            label: 'Projets',
            icon: FolderOpen,
            active: false,
            show: true,
        },
        {
            href: '#',
            label: "Banque d'images",
            icon: Image,
            active: false,
            show: true,
        },
        {
            href: '#',
            label: 'Telechargements',
            icon: Download,
            active: false,
            show: true,
        },
        {
            href: '#',
            label: "Droits d'acces",
            icon: Shield,
            active: false,
            show: isSuperAdmin,
        },
    ];

    return (
        <div className="min-h-screen bg-background text-foreground">
            <header className="sticky top-0 z-50 w-full border-b bg-[#F2F0F0]/95 backdrop-blur">
                <div className="container flex h-14 items-center">
                    <div className="mr-4 hidden items-center md:flex">
                        <Link href={route('dashboard')} className="mr-6">
                            <img
                                src="/logo_stimergie_header.png"
                                alt="Stimergie"
                                className="h-8 w-auto"
                            />
                        </Link>

                        <nav className="flex items-center gap-6">
                            {items
                                .filter((item) => item.show)
                                .slice(1, 5)
                                .map((item) => (
                                    <Link
                                        key={item.label}
                                        href={item.href}
                                        className={cn(
                                            'text-sm font-medium transition-colors hover:text-primary',
                                            item.active
                                                ? 'text-primary'
                                                : 'text-foreground',
                                            item.href === '#' &&
                                                'pointer-events-none opacity-50',
                                        )}
                                    >
                                        {item.label}
                                    </Link>
                                ))}
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
                        <SheetContent side="left" className="w-80">
                            <div className="mb-6 border-b pb-4">
                                <img
                                    src="/logo_stimergie_header.png"
                                    alt="Stimergie"
                                    className="h-8 w-auto"
                                />
                            </div>
                            <nav className="space-y-1">
                                {items
                                    .filter((item) => item.show)
                                    .map((item) => {
                                        const Icon = item.icon;

                                        return (
                                            <Link
                                                key={item.label}
                                                href={item.href}
                                                onClick={() =>
                                                    setMobileOpen(false)
                                                }
                                                className={cn(
                                                    'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                                    item.active
                                                        ? 'bg-primary/10 text-primary'
                                                        : 'hover:bg-primary/5 hover:text-primary',
                                                    item.href === '#' &&
                                                        'pointer-events-none opacity-50',
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
                            href={route('dashboard')}
                            className="flex items-center md:hidden"
                        >
                            <img
                                src="/logo_stimergie_header.png"
                                alt="Stimergie"
                                className="h-6 w-auto"
                            />
                        </Link>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="rounded-full"
                                >
                                    <Avatar className="h-8 w-8 border">
                                        <AvatarFallback>
                                            {initials}
                                        </AvatarFallback>
                                    </Avatar>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent className="w-64" align="end">
                                <DropdownMenuLabel>
                                    <div className="flex flex-col gap-1">
                                        <span className="text-sm font-medium leading-none">
                                            {user.name}
                                        </span>
                                        <span className="text-xs leading-none text-muted-foreground">
                                            {user.email}
                                        </span>
                                        {isSuperAdmin && (
                                            <span className="mt-1 inline-flex w-fit rounded-md bg-primary/10 px-2 py-1 text-xs font-medium text-primary">
                                                Administrateur
                                            </span>
                                        )}
                                    </div>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuGroup>
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={route('profile.edit')}
                                            className="flex w-full items-center"
                                        >
                                            <User className="mr-2 h-4 w-4" />
                                            Profil
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuGroup>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={route('logout')}
                                        method="post"
                                        as="button"
                                        className="flex w-full items-center"
                                    >
                                        <LogOut className="mr-2 h-4 w-4" />
                                        Deconnexion
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </header>

            {header && (
                <div className="border-b bg-card">
                    <div className="container py-6">{header}</div>
                </div>
            )}

            <main>{children}</main>
        </div>
    );
}
