import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { cn } from "@/lib/utils";
import { Head, Link, router } from "@inertiajs/react";
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    Clock,
    ExternalLink,
    FileClock,
    History,
    Link2,
    RefreshCw,
    Search,
    ShieldCheck,
} from "lucide-react";
import { FormEvent, ReactNode, useMemo, useState } from "react";

type Option = {
    id: number;
    name: string;
    clientId?: number;
    clientName?: string | null;
};

type SelectOption = {
    value: string;
    label: string;
};

type OperationEvent = {
    id: string;
    sourceId: number;
    type:
        | "cession"
        | "demande_extension"
        | "droits_acces"
        | "telechargement"
        | "partage"
        | "audit";
    typeLabel: string;
    date: string | null;
    status: string;
    statusLabel: string;
    title: string;
    description: string;
    clientName: string | null;
    projectName: string | null;
    imageTitle: string | null;
    actorName: string | null;
    targetUrl: string | null;
    metadata: Record<string, string | number | boolean | null>;
};

type OperationStats = {
    rightsExpired: number;
    rightsExpiringSoon: number;
    openRightsRequests: number;
    accessEndingSoon: number;
    failedDownloads: number;
    activeSharedAlbums: number;
};

type ActiveFilters = {
    search: string;
    clientId: string;
    projectId: string;
    type: string;
    status: string;
    dateFrom: string;
    dateTo: string;
};

export default function OperationsIndex({
    events,
    stats,
    filters,
    activeFilters,
    canViewSensitiveAuditData,
}: {
    events: OperationEvent[];
    stats: OperationStats;
    filters: {
        clients: Option[];
        projects: Option[];
        types: SelectOption[];
        statuses: SelectOption[];
    };
    activeFilters: ActiveFilters;
    canViewSensitiveAuditData: boolean;
}) {
    const [form, setForm] = useState(activeFilters);
    const visibleProjects = useMemo(
        () =>
            form.clientId
                ? filters.projects.filter(
                      (project) => String(project.clientId) === form.clientId,
                  )
                : filters.projects,
        [filters.projects, form.clientId],
    );

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();

        router.get(
            route("operations.index"),
            {
                search: form.search || undefined,
                client_id: form.clientId || undefined,
                project_id: form.projectId || undefined,
                type: form.type || undefined,
                status: form.status || undefined,
                date_from: form.dateFrom || undefined,
                date_to: form.dateTo || undefined,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const resetFilters = () => {
        router.get(route("operations.index"), {}, { replace: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Suivi opérationnel" />

            <main className="container mx-auto max-w-7xl px-4 py-8">
                <div className="space-y-8">
                    <div className="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="min-w-0">
                            <h1 className="break-words text-2xl font-bold leading-tight sm:text-3xl">
                                Suivi opérationnel
                            </h1>
                            <p className="mt-2 max-w-3xl text-muted-foreground">
                                Echéances de droits, demandes, accès,
                                téléchargements, partages et traces d'audit.
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            className="w-full gap-2 sm:w-auto"
                            onClick={() => router.reload()}
                        >
                            <RefreshCw className="h-4 w-4" />
                            Actualiser
                        </Button>
                    </div>

                    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <MetricCard
                            title="Cessions expirées"
                            value={stats.rightsExpired}
                            icon={<AlertTriangle className="h-4 w-4" />}
                            tone="danger"
                        />
                        <MetricCard
                            title="Cessions à 30 jours"
                            value={stats.rightsExpiringSoon}
                            icon={<CalendarClock className="h-4 w-4" />}
                            tone="warning"
                        />
                        <MetricCard
                            title="Demandes ouvertes"
                            value={stats.openRightsRequests}
                            icon={<FileClock className="h-4 w-4" />}
                        />
                        <MetricCard
                            title="Accès à échéance"
                            value={stats.accessEndingSoon}
                            icon={<ShieldCheck className="h-4 w-4" />}
                        />
                        <MetricCard
                            title="Téléchargements en échec"
                            value={stats.failedDownloads}
                            icon={<AlertTriangle className="h-4 w-4" />}
                            tone="danger"
                        />
                        <MetricCard
                            title="Liens partagés actifs"
                            value={stats.activeSharedAlbums}
                            icon={<Link2 className="h-4 w-4" />}
                            tone="success"
                        />
                    </section>

                    <form
                        onSubmit={applyFilters}
                        className="grid gap-3 rounded-md border bg-card p-4 lg:grid-cols-[1fr_180px_180px_180px_180px] xl:grid-cols-[1.2fr_180px_180px_170px_170px_150px_150px_auto]"
                    >
                        <div className="relative">
                            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={form.search}
                                onChange={(event) =>
                                    setForm({
                                        ...form,
                                        search: event.target.value,
                                    })
                                }
                                className="pl-9"
                                placeholder="Rechercher..."
                            />
                        </div>
                        <select
                            value={form.clientId}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    clientId: event.target.value,
                                    projectId: "",
                                })
                            }
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="">Tous clients</option>
                            {filters.clients.map((client) => (
                                <option key={client.id} value={client.id}>
                                    {client.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={form.projectId}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    projectId: event.target.value,
                                })
                            }
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="">Tous projets</option>
                            {visibleProjects.map((project) => (
                                <option key={project.id} value={project.id}>
                                    {project.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={form.type}
                            onChange={(event) =>
                                setForm({ ...form, type: event.target.value })
                            }
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="">Tous types</option>
                            {filters.types.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </select>
                        <select
                            value={form.status}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    status: event.target.value,
                                })
                            }
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="">Tous statuts</option>
                            {filters.statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                        <Input
                            type="date"
                            value={form.dateFrom}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    dateFrom: event.target.value,
                                })
                            }
                        />
                        <Input
                            type="date"
                            value={form.dateTo}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    dateTo: event.target.value,
                                })
                            }
                        />
                        <div className="flex gap-2 lg:col-span-5 xl:col-span-1">
                            <Button type="submit" className="flex-1 xl:flex-none">
                                Filtrer
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={resetFilters}
                                className="flex-1 xl:flex-none"
                            >
                                Effacer
                            </Button>
                        </div>
                    </form>

                    <section className="rounded-md border bg-card">
                        <div className="flex flex-col gap-2 border-b p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 className="text-lg font-semibold">
                                    Timeline
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {events.length} événement
                                    {events.length > 1 ? "s" : ""} chargé
                                    {events.length >= 120
                                        ? "s, affinez les filtres pour élargir la recherche."
                                        : "."}
                                </p>
                            </div>
                        </div>
                        <OperationsTable
                            events={events}
                            canViewSensitiveAuditData={
                                canViewSensitiveAuditData
                            }
                        />
                    </section>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}

function MetricCard({
    title,
    value,
    icon,
    tone = "neutral",
}: {
    title: string;
    value: number;
    icon: ReactNode;
    tone?: "neutral" | "danger" | "warning" | "success";
}) {
    return (
        <Card className="rounded-lg">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {title}
                </CardTitle>
                <span
                    className={cn(
                        "rounded-md border p-2",
                        tone === "danger" &&
                            "border-red-200 bg-red-50 text-red-700",
                        tone === "warning" &&
                            "border-amber-200 bg-amber-50 text-amber-700",
                        tone === "success" &&
                            "border-emerald-200 bg-emerald-50 text-emerald-700",
                        tone === "neutral" &&
                            "border-slate-200 bg-slate-50 text-slate-700",
                    )}
                >
                    {icon}
                </span>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold">{value}</div>
            </CardContent>
        </Card>
    );
}

function OperationsTable({
    events,
    canViewSensitiveAuditData,
}: {
    events: OperationEvent[];
    canViewSensitiveAuditData: boolean;
}) {
    if (events.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center text-muted-foreground">
                <History className="h-10 w-10" />
                <p>Aucun événement ne correspond aux filtres.</p>
            </div>
        );
    }

    return (
        <div className="mobile-card-table-wrapper overflow-x-auto">
            <Table className="mobile-card-table">
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Objet</TableHead>
                        <TableHead>Contexte</TableHead>
                        <TableHead>Statut</TableHead>
                        <TableHead className="text-right">Lien</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {events.map((event) => (
                        <TableRow key={event.id}>
                            <TableCell
                                data-label="Date"
                                className="min-w-[160px] align-top"
                            >
                                {event.date ? formatDate(event.date) : "Sans date"}
                            </TableCell>
                            <TableCell data-label="Type" className="align-top">
                                <Badge variant="outline">
                                    {event.typeLabel}
                                </Badge>
                            </TableCell>
                            <TableCell
                                data-label="Objet"
                                className="min-w-[260px] align-top"
                            >
                                <div className="font-medium">{event.title}</div>
                                <div className="mt-1 text-sm text-muted-foreground">
                                    {event.description}
                                </div>
                                <EventMetadata
                                    event={event}
                                    canViewSensitiveAuditData={
                                        canViewSensitiveAuditData
                                    }
                                />
                            </TableCell>
                            <TableCell
                                data-label="Contexte"
                                className="min-w-[220px] align-top"
                            >
                                <div className="space-y-1 text-sm">
                                    {event.clientName && (
                                        <div>{event.clientName}</div>
                                    )}
                                    {event.projectName && (
                                        <div className="text-muted-foreground">
                                            {event.projectName}
                                        </div>
                                    )}
                                    {event.actorName && (
                                        <div className="text-muted-foreground">
                                            {event.actorName}
                                        </div>
                                    )}
                                    {!event.clientName &&
                                        !event.projectName &&
                                        !event.actorName && (
                                            <span className="text-muted-foreground">
                                                Global
                                            </span>
                                        )}
                                </div>
                            </TableCell>
                            <TableCell data-label="Statut" className="align-top">
                                <StatusBadge status={event.status}>
                                    {event.statusLabel}
                                </StatusBadge>
                            </TableCell>
                            <TableCell
                                data-label="Lien"
                                className="text-right align-top"
                            >
                                {event.targetUrl ? (
                                    <Button asChild variant="outline" size="sm">
                                        <Link
                                            href={event.targetUrl}
                                            className="gap-2"
                                        >
                                            Ouvrir
                                            <ExternalLink className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                ) : (
                                    <span className="text-sm text-muted-foreground">
                                        Trace
                                    </span>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}

function EventMetadata({
    event,
    canViewSensitiveAuditData,
}: {
    event: OperationEvent;
    canViewSensitiveAuditData: boolean;
}) {
    const rows = Object.entries(event.metadata).filter(([key, value]) => {
        if (value === null || value === "" || typeof value === "undefined") {
            return false;
        }

        if (
            !canViewSensitiveAuditData &&
            ["ipAddress", "userAgent"].includes(key)
        ) {
            return false;
        }

        return true;
    });

    if (rows.length === 0) {
        return null;
    }

    return (
        <dl className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
            {rows.map(([key, value]) => (
                <div key={key} className="flex gap-1">
                    <dt>{metadataLabel(key)}:</dt>
                    <dd>{formatMetadataValue(value)}</dd>
                </div>
            ))}
        </dl>
    );
}

function StatusBadge({
    status,
    children,
}: {
    status: string;
    children: ReactNode;
}) {
    const tone =
        status === "expired" || status === "failed" || status === "refuse"
            ? "danger"
            : status === "expiring_soon" ||
                status === "pending" ||
                status === "demande" ||
                status === "en_cours" ||
                status === "upcoming" ||
                status === "processing"
              ? "warning"
              : status === "active" ||
                  status === "ready" ||
                  status === "accepte"
                ? "success"
                : "neutral";

    return (
        <Badge
            variant="secondary"
            className={cn(
                "w-fit gap-1",
                tone === "danger" && "bg-red-100 text-red-800",
                tone === "warning" && "bg-amber-100 text-amber-900",
                tone === "success" && "bg-emerald-100 text-emerald-800",
                tone === "neutral" && "bg-slate-100 text-slate-800",
            )}
        >
            {tone === "success" ? (
                <CheckCircle2 className="h-3 w-3" />
            ) : tone === "danger" ? (
                <AlertTriangle className="h-3 w-3" />
            ) : (
                <Clock className="h-3 w-3" />
            )}
            {children}
        </Badge>
    );
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(value));
}

function metadataLabel(key: string) {
    const labels: Record<string, string> = {
        rightsStartsAt: "Début cession",
        rightsEndsAt: "Fin cession",
        resolvedAt: "Résolu le",
        resolvedBy: "Résolu par",
        startsAt: "Début",
        endsAt: "Fin",
        isActive: "Actif",
        imageCount: "Images",
        isHd: "HD",
        processedAt: "Traité le",
        expiresAt: "Expire le",
        subjectType: "Sujet",
        subjectId: "ID sujet",
        ipAddress: "IP",
        userAgent: "Navigateur",
    };

    return labels[key] ?? key;
}

function formatMetadataValue(value: string | number | boolean | null) {
    if (typeof value === "boolean") {
        return value ? "oui" : "non";
    }

    if (typeof value === "string" && /^\d{4}-\d{2}-\d{2}T/.test(value)) {
        return formatDate(value);
    }

    return value;
}
