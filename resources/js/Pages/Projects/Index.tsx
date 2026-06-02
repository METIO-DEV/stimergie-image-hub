import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import {
    LegacySelect,
    SectionHeader,
    ViewMode,
    ViewToggle,
} from "@/Components/Legacy/LegacyDesign";
import { ProjectEditModal } from "@/Components/Legacy/LegacyModals";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import {
    Building2,
    Calendar,
    Folder,
    HardDrive,
    Pencil,
    Plus,
} from "lucide-react";
import { useMemo, useState } from "react";

type Project = {
    id: number;
    name: string;
    slug: string;
    status: string;
    type: string | null;
    clientId: number;
    clientName: string;
    clientLogo: string | null;
    sourceFolder: string | null;
    imagesCount: number;
    createdAt: string;
    canUpdate: boolean;
};

type Props = {
    projects: Project[];
    filters: {
        clients: Array<{ id: number; name: string }>;
    };
    manageableClients: Array<{ id: number; name: string }>;
    canCreateProject: boolean;
};

export default function ProjectsIndex({
    projects,
    filters,
    manageableClients,
    canCreateProject,
}: Props) {
    const [viewMode, setViewMode] = useState<ViewMode>("card");
    const [clientFilter, setClientFilter] = useState("");
    const [search, setSearch] = useState("");
    const [editingProject, setEditingProject] = useState<Project | null>(null);
    const [projectModalOpen, setProjectModalOpen] = useState(false);

    const filteredProjects = useMemo(() => {
        const query = search.trim().toLowerCase();

        return projects
            .filter((project) => {
                const matchesSearch =
                    !query ||
                    project.name.toLowerCase().includes(query) ||
                    project.clientName.toLowerCase().includes(query);
                const matchesClient =
                    !clientFilter ||
                    filters.clients.find(
                        (client) => String(client.id) === clientFilter,
                    )?.name === project.clientName;

                return matchesSearch && matchesClient;
            })
            .sort((a, b) =>
                `${a.clientName}-${a.name}`.localeCompare(
                    `${b.clientName}-${b.name}`,
                ),
            );
    }, [clientFilter, filters.clients, projects, search]);

    return (
        <AuthenticatedLayout>
            <Head title="Projets" />

            <SectionHeader
                title="Projets"
                action={
                    <div className="flex items-center gap-4">
                        <ViewToggle
                            currentView={viewMode}
                            onViewChange={setViewMode}
                        />
                        {canCreateProject && (
                            <Button
                                onClick={() => {
                                    setEditingProject(null);
                                    setProjectModalOpen(true);
                                }}
                            >
                                <Plus size={16} className="mr-2" />
                                Ajouter un projet
                            </Button>
                        )}
                    </div>
                }
            />

            <main className="mx-auto max-w-7xl px-4 pb-20">
                <div className="mb-6 mt-8 grid gap-4 md:grid-cols-[280px_1fr]">
                    <LegacySelect
                        value={clientFilter}
                        onChange={setClientFilter}
                        allLabel="Tous les clients"
                        options={filters.clients}
                    />
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Rechercher un projet..."
                    />
                </div>

                {viewMode === "card" ? (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {filteredProjects.map((project) => (
                            <ProjectCard
                                key={project.id}
                                project={project}
                                onEdit={
                                    project.canUpdate
                                        ? (project) => {
                                              setEditingProject(project);
                                              setProjectModalOpen(true);
                                          }
                                        : undefined
                                }
                            />
                        ))}
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-md border bg-card">
                        {filteredProjects.map((project) => (
                            <div
                                key={project.id}
                                className="grid gap-4 border-b p-5 last:border-b-0 md:grid-cols-[1fr_220px_120px_96px]"
                            >
                                <div className="font-semibold">
                                    {project.name}
                                    <div className="mt-1 text-sm font-normal text-muted-foreground">
                                        {project.clientName}
                                    </div>
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {project.sourceFolder || project.slug}
                                </div>
                                <div className="text-sm">
                                    {project.imagesCount} images
                                </div>
                                <div className="flex justify-end gap-2">
                                    {project.canUpdate && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            title="Modifier"
                                            onClick={() => {
                                                setEditingProject(project);
                                                setProjectModalOpen(true);
                                            }}
                                        >
                                            <Pencil size={16} />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </main>
            <ProjectEditModal
                project={editingProject}
                open={projectModalOpen}
                clients={manageableClients}
                onOpenChange={(open) => {
                    setProjectModalOpen(open);
                    if (!open) {
                        setEditingProject(null);
                    }
                }}
            />
        </AuthenticatedLayout>
    );
}

function ProjectCard({
    project,
    onEdit,
}: {
    project: Project;
    onEdit?: (project: Project) => void;
}) {
    return (
        <Card className="flex h-full flex-col transition-shadow hover:shadow-md">
            <CardHeader className="pb-2">
                <div className="flex items-start justify-between">
                    <CardTitle className="flex items-start gap-2 text-lg">
                        <Folder
                            size={18}
                            className="mt-1 flex-shrink-0 text-primary"
                        />
                        <span className="break-words">{project.name}</span>
                    </CardTitle>
                    <div className="flex flex-shrink-0 gap-2">
                        {onEdit && (
                            <Button
                                variant="ghost"
                                size="icon"
                                title="Modifier"
                                onClick={() => onEdit(project)}
                            >
                                <Pencil size={16} />
                            </Button>
                        )}
                    </div>
                </div>
            </CardHeader>

            <CardContent className="flex flex-grow flex-col justify-between">
                <div className="space-y-3 text-sm">
                    <p className="flex items-center gap-2">
                        {project.clientLogo ? (
                            <span className="flex h-10 w-10 flex-shrink-0 items-center justify-center overflow-hidden rounded-full bg-card">
                                <img
                                    src={project.clientLogo}
                                    alt={project.clientName}
                                    className="h-full w-full object-contain"
                                />
                            </span>
                        ) : (
                            <Building2
                                size={16}
                                className="flex-shrink-0 text-muted-foreground"
                            />
                        )}
                        <span className="truncate">{project.clientName}</span>
                    </p>

                    {project.type && (
                        <p className="flex items-center gap-2">
                            <span className="inline-block h-4 w-4 flex-shrink-0 rounded-full bg-primary/10" />
                            <span className="truncate">{project.type}</span>
                        </p>
                    )}

                    {project.sourceFolder && (
                        <p className="flex items-center gap-2">
                            <HardDrive
                                size={16}
                                className="flex-shrink-0 text-muted-foreground"
                            />
                            <span className="truncate">
                                Dossier: {project.sourceFolder}
                            </span>
                        </p>
                    )}
                </div>

                <p className="mt-3 flex items-center gap-2 border-t border-border pt-3 text-muted-foreground">
                    <Calendar size={16} className="flex-shrink-0" />
                    <span className="truncate">
                        Créé le {formatDate(project.createdAt)} ·{" "}
                        {project.imagesCount} images
                    </span>
                </p>
            </CardContent>
        </Card>
    );
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "long",
        year: "numeric",
    }).format(new Date(value));
}
