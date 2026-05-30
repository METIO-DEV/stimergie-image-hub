import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import { FolderOpen, Image, Users } from "lucide-react";

export default function Dashboard() {
    return (
        <AuthenticatedLayout
            header={
                <div>
                    <p className="text-sm font-medium text-muted-foreground">
                        Stimergie Image Hub
                    </p>
                    <h1 className="mt-1 text-2xl font-semibold tracking-tight text-foreground">
                        Tableau de bord
                    </h1>
                </div>
            }
        >
            <Head title="Tableau de bord" />

            <div className="py-8">
                <div className="container space-y-6">
                    <Card className="border-border/70 bg-card">
                        <CardHeader>
                            <CardTitle className="text-xl">
                                Bienvenue sur Stimergie
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="max-w-3xl text-sm leading-6 text-muted-foreground">
                            L'espace Laravel Inertia reprend l'identite du front
                            React initial tout en s'appuyant sur le back
                            Laravel. Les modules fonctionnels seront branches
                            ici au fur et a mesure de la migration.
                        </CardContent>
                    </Card>

                    <div className="grid gap-4 md:grid-cols-3">
                        <DashboardCard
                            icon={Users}
                            title="Clients"
                            description="Espaces clients, membres et rattachements."
                        />
                        <DashboardCard
                            icon={FolderOpen}
                            title="Projets"
                            description="Structure projet conservee pour la suite de la migration."
                        />
                        <DashboardCard
                            icon={Image}
                            title="Banque d'images"
                            description="Socle visuel aligne avec l'application React d'origine."
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function DashboardCard({
    icon: Icon,
    title,
    description,
}: {
    icon: typeof Image;
    title: string;
    description: string;
}) {
    return (
        <Card className="border-border/70">
            <CardHeader className="space-y-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-md bg-primary/10 text-primary">
                    <Icon className="h-5 w-5" />
                </div>
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent className="text-sm leading-6 text-muted-foreground">
                {description}
            </CardContent>
        </Card>
    );
}
