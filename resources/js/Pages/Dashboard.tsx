import { Card, CardContent } from "@/Components/ui/card";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link } from "@inertiajs/react";
import {
    ArrowRight,
    Building2,
    CalendarRange,
    FolderOpen,
    Image,
    Users,
} from "lucide-react";

type DashboardStats = {
    clients: number;
    projects: number;
    images: number;
};

export default function Dashboard({ stats }: { stats: DashboardStats }) {
    return (
        <AuthenticatedLayout>
            <Head title="Tableau de bord administrateur" />

            <div className="container py-10">
                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-4xl font-bold tracking-normal text-foreground">
                            Tableau de bord administrateur
                        </h1>
                        <p className="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground">
                            Suivez les volumes globaux et accédez rapidement aux
                            espaces d'administration de la plateforme.
                        </p>
                    </div>
                    <Link
                        href={route("gallery.index")}
                        className="inline-flex items-center gap-2 text-sm font-semibold text-primary transition-colors hover:text-primary/80"
                    >
                        Ouvrir la galerie
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>

                <div className="mt-10 grid gap-7 lg:grid-cols-3">
                    <MetricCard
                        label="Entreprises"
                        value={stats.clients}
                        description="Nombre total d'entreprises"
                        icon={Building2}
                        href={route("clients.index")}
                    />
                    <MetricCard
                        label="Projets"
                        value={stats.projects}
                        description="Nombre total de projets"
                        icon={FolderOpen}
                        href={route("projects.index")}
                    />
                    <MetricCard
                        label="Images"
                        value={stats.images}
                        description="Nombre total d'images"
                        icon={Image}
                        href={route("images.index")}
                    />
                </div>

                <h2 className="mt-12 text-3xl font-bold tracking-normal text-foreground">
                    Fonctionnalités
                </h2>

                <div className="mt-10 grid gap-7 lg:grid-cols-3">
                    <FeatureCard
                        title="Entreprises"
                        subtitle="Gérer les entreprises et leurs informations"
                        description="Ajouter, modifier ou supprimer des entreprises"
                        href={route("clients.index")}
                        icon={Building2}
                    />
                    <FeatureCard
                        title="Projets"
                        subtitle="Gérer les projets de toutes les entreprises"
                        description="Créer et gérer des projets pour chaque entreprise"
                        href={route("projects.index")}
                        icon={FolderOpen}
                    />
                    <FeatureCard
                        title="Banque d'images"
                        subtitle="Visualiser toutes les images"
                        description="Interface de visualisation des images"
                        href={route("gallery.index")}
                        icon={Image}
                    />
                    <FeatureCard
                        title="Utilisateurs"
                        subtitle="Gérer les comptes utilisateurs"
                        description="Configurer les accès et les permissions"
                        href={route("users.index")}
                        icon={Users}
                    />
                    <FeatureCard
                        title="Droits d'accès"
                        subtitle="Suivre les périodes d'accès aux projets"
                        description="Consulter les fenêtres d'accès par entreprise"
                        href={route("access-periods.index")}
                        icon={CalendarRange}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function MetricCard({
    label,
    value,
    description,
    icon: Icon,
    href,
}: {
    label: string;
    value: number;
    description: string;
    icon: typeof Image;
    href: string;
}) {
    return (
        <Link href={href} className="block h-full">
            <Card className="min-h-[150px] rounded-xl border-border/80 bg-card shadow-sm transition-colors hover:border-primary/50 hover:bg-muted/30">
                <CardContent className="flex h-full items-start justify-between p-7">
                    <div>
                        <div className="text-base font-semibold text-foreground">
                            {label}
                        </div>
                        <div className="mt-5 text-4xl font-bold leading-none text-foreground">
                            {value}
                        </div>
                        <div className="mt-2 text-base text-muted-foreground">
                            {description}
                        </div>
                    </div>
                    <div className="flex flex-col items-end gap-8">
                        <Icon className="mt-2 h-6 w-6 text-muted-foreground" />
                        <ArrowRight className="h-4 w-4 text-primary" />
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}

function FeatureCard({
    title,
    subtitle,
    description,
    href,
    icon: Icon,
}: {
    title: string;
    subtitle: string;
    description: string;
    href: string;
    icon: typeof Image;
}) {
    return (
        <Link href={href} className="block h-full">
            <Card className="min-h-[164px] rounded-xl border-border/80 bg-card shadow-sm transition-colors hover:border-primary/50 hover:bg-muted/30">
                <CardContent className="flex h-full flex-col p-7">
                    <div className="flex items-start justify-between gap-4">
                        <Icon className="h-6 w-6 text-primary" />
                        <ArrowRight className="h-4 w-4 text-muted-foreground" />
                    </div>
                    <h3 className="mt-6 text-2xl font-bold tracking-normal text-foreground">
                        {title}
                    </h3>
                    <p className="mt-2 text-base text-muted-foreground">
                        {subtitle}
                    </p>
                    <p className="mt-auto pt-8 text-sm leading-6 text-muted-foreground">
                        {description}
                    </p>
                </CardContent>
            </Card>
        </Link>
    );
}
