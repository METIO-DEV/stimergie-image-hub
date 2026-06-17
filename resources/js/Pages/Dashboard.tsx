import { Card, CardContent } from "@/Components/ui/card";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link } from "@inertiajs/react";
import {
    ArrowRight,
    BookOpenText,
    Building2,
    CalendarRange,
    FolderOpen,
    FolderUp,
    Image,
    Users,
    ServerCog,
} from "lucide-react";

type DashboardStats = {
    clients: number;
    projects: number;
    images: number;
    imports: number;
    activeImports: number;
};

type RecentImport = {
    id: number;
    status: string;
    clientName?: string | null;
    projectName?: string | null;
    totalItems: number;
    uploadedItems: number;
    processedItems: number;
    failedItems: number;
    duplicateItems: number;
    startedAt?: string | null;
};

export default function Dashboard({
    stats,
    recentImports,
}: {
    stats: DashboardStats;
    recentImports: RecentImport[];
}) {
    return (
        <AuthenticatedLayout>
            <Head title="Tableau de bord administrateur" />

            <div className="container py-10">
                <div className="flex min-w-0 flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div className="min-w-0">
                        <h1 className="break-words text-3xl font-bold leading-tight tracking-normal text-foreground md:text-4xl">
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

                <div className="mt-10 grid gap-7 md:grid-cols-2 xl:grid-cols-4">
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
                    <MetricCard
                        label="Imports actifs"
                        value={stats.activeImports}
                        description={`${stats.imports} imports dossiers au total`}
                        icon={FolderUp}
                        href={route("imports.index")}
                    />
                </div>

                <div className="mt-12 grid gap-7 lg:grid-cols-[1.1fr_0.9fr]">
                    <div>
                        <h2 className="break-words text-2xl font-bold leading-tight tracking-normal text-foreground md:text-3xl">
                            Imports dossiers
                        </h2>
                        <p className="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground">
                            Suivez les derniers imports d'images et accédez à la
                            gestion des images pour lancer un nouveau dossier.
                        </p>
                    </div>
                    <Link
                        href={route("imports.index")}
                        className="inline-flex items-center justify-start gap-2 self-end text-sm font-semibold text-primary transition-colors hover:text-primary/80 lg:justify-end"
                    >
                        Ouvrir le suivi des imports
                        <ArrowRight className="h-4 w-4" />
                    </Link>
                </div>

                <div className="mt-6 overflow-hidden rounded-lg border bg-card">
                    {recentImports.length === 0 ? (
                        <div className="p-7 text-sm text-muted-foreground">
                            Aucun import dossier n'a encore été lancé.
                        </div>
                    ) : (
                        recentImports.map((importBatch) => (
                            <ImportRow
                                key={importBatch.id}
                                importBatch={importBatch}
                            />
                        ))
                    )}
                </div>

                <h2 className="mt-12 break-words text-2xl font-bold leading-tight tracking-normal text-foreground md:text-3xl">
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
                        title="Transferts FTP"
                        subtitle="Piloter les dossiers o2switch vers Scaleway"
                        description="Suivi rclone et synchronisation de la base"
                        href={route("asset-transfers.index")}
                        icon={ServerCog}
                    />
                    <FeatureCard
                        title="Blog et ressources"
                        subtitle="Publier les contenus éditoriaux"
                        description="Créer des ressources, articles Ensemble et brouillons"
                        href={route("blog.admin.index")}
                        icon={BookOpenText}
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

function ImportRow({ importBatch }: { importBatch: RecentImport }) {
    const terminal =
        importBatch.status === "completed" || importBatch.status === "failed";
    const done = importBatch.processedItems + importBatch.failedItems;
    const total = importBatch.totalItems || 1;
    const progress = terminal
        ? 100
        : Math.min(100, Math.round((done / total) * 100));

    return (
        <Link
            href={route("imports.index")}
            className="grid gap-4 border-b p-5 transition-colors last:border-b-0 hover:bg-muted/30 md:grid-cols-[1fr_220px]"
        >
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-3">
                    <span className="font-semibold">
                        Import #{importBatch.id}
                    </span>
                    <span className="rounded-full bg-muted px-2 py-1 text-xs text-muted-foreground">
                        {statusLabel(importBatch.status)}
                    </span>
                </div>
                <div className="mt-2 truncate text-sm text-muted-foreground">
                    {importBatch.clientName || "Entreprise inconnue"} ·{" "}
                    {importBatch.projectName || "Projet inconnu"}
                </div>
                <div className="mt-3 h-2 overflow-hidden rounded-full bg-muted">
                    <div
                        className="h-full bg-primary"
                        style={{ width: `${progress}%` }}
                    />
                </div>
            </div>
            <div className="grid grid-cols-2 gap-3 text-sm">
                <DashboardImportMetric
                    label="Traitées"
                    value={importBatch.processedItems}
                />
                <DashboardImportMetric
                    label="Erreurs"
                    value={importBatch.failedItems}
                />
                <DashboardImportMetric
                    label="Doublons"
                    value={importBatch.duplicateItems}
                />
                <DashboardImportMetric
                    label="Total"
                    value={importBatch.totalItems}
                />
            </div>
        </Link>
    );
}

function DashboardImportMetric({
    label,
    value,
}: {
    label: string;
    value: number;
}) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="font-semibold">{value}</div>
        </div>
    );
}

function statusLabel(status: string): string {
    return (
        {
            pending: "En attente",
            processing: "Traitement",
            completed: "Terminé",
            failed: "Avec erreurs",
        }[status] || status
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
