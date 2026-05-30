import { Card, CardContent } from "@/Components/ui/card";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import { FolderOpen, Image, Users } from "lucide-react";

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
                <h1 className="text-4xl font-bold tracking-normal text-foreground">
                    Tableau de bord administrateur
                </h1>

                <div className="mt-10 grid gap-7 lg:grid-cols-3">
                    <MetricCard
                        label="Clients"
                        value={stats.clients}
                        description="Nombre total de clients"
                        icon={Users}
                    />
                    <MetricCard
                        label="Projets"
                        value={stats.projects}
                        description="Nombre total de projets"
                        icon={FolderOpen}
                    />
                    <MetricCard
                        label="Images"
                        value={stats.images}
                        description="Nombre total d'images"
                        icon={Image}
                    />
                </div>

                <h2 className="mt-12 text-3xl font-bold tracking-normal text-foreground">
                    Fonctionnalités
                </h2>

                <div className="mt-10 grid gap-7 lg:grid-cols-3">
                    <FeatureCard
                        title="Clients"
                        subtitle="Gérer les clients et leurs informations"
                        description="Ajouter, modifier ou supprimer des clients"
                    />
                    <FeatureCard
                        title="Projets"
                        subtitle="Gérer les projets de tous les clients"
                        description="Créer et gérer des projets pour chaque client"
                    />
                    <FeatureCard
                        title="Banque d'images"
                        subtitle="Visualiser toutes les images"
                        description="Interface de visualisation des images"
                    />
                    <FeatureCard
                        title="Utilisateurs"
                        subtitle="Gérer les comptes utilisateurs"
                        description="Configurer les accès et les permissions"
                    />
                    <FeatureCard
                        title="Blog"
                        subtitle="Gérer les articles du blog"
                        description="Créer et publier des articles de blog"
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
}: {
    label: string;
    value: number;
    description: string;
    icon: typeof Image;
}) {
    return (
        <Card className="min-h-[150px] rounded-xl border-border/80 bg-card shadow-sm">
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
                <Icon className="mt-2 h-6 w-6 text-muted-foreground" />
            </CardContent>
        </Card>
    );
}

function FeatureCard({
    title,
    subtitle,
    description,
}: {
    title: string;
    subtitle: string;
    description: string;
}) {
    return (
        <Card className="min-h-[164px] rounded-xl border-border/80 bg-card shadow-sm">
            <CardContent className="p-7">
                <h3 className="text-3xl font-bold tracking-normal text-foreground">
                    {title}
                </h3>
                <p className="mt-2 text-lg text-muted-foreground">{subtitle}</p>
                <p className="mt-8 text-lg text-muted-foreground">
                    {description}
                </p>
            </CardContent>
        </Card>
    );
}
