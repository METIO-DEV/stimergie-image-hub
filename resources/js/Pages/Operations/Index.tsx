import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
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
    CheckCircle2,
    Clock,
    DatabaseZap,
    ExternalLink,
    FileWarning,
    History,
    RefreshCw,
    Search,
    ServerCrash,
    ShieldAlert,
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

type OperationView =
    | ""
    | "audit_traces"
    | "open_rights_requests"
    | "failed_downloads"
    | "failed_imports"
    | "failed_transfers"
    | "failed_jobs";

type OperationEvent = {
    id: string;
    sourceId: number;
    type:
        | "audit"
        | "telechargement"
        | "import"
        | "transfert"
        | "job"
        | "demande_extension";
    typeLabel: string;
    date: string | null;
    status: string;
    statusLabel: string;
    severity: "info" | "warning" | "error";
    title: string;
    description: string;
    clientName: string | null;
    projectName: string | null;
    actorName: string | null;
    targetUrl: string | null;
    metadata: Record<string, string | number | boolean | null>;
};

type OperationStats = {
    auditLogs: number;
    openRightsRequests: number;
    failedDownloads: number;
    failedImports: number;
    failedTransfers: number;
    failedJobs: number;
};

type ActiveFilters = {
    search: string;
    clientId: string;
    projectId: string;
    type: string;
    status: string;
    view: OperationView;
    dateFrom: string;
    dateTo: string;
    perPage: string;
};

type Pagination = {
    currentPage: number;
    perPage: number;
    total: number;
    lastPage: number;
};

export default function OperationsIndex({
    events,
    pagination,
    stats,
    filters,
    activeFilters,
    canViewSensitiveAuditData,
}: {
    events: OperationEvent[];
    pagination: Pagination;
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

    const visit = (page = 1) => {
        router.get(
            route("operations.index"),
            queryParams(form, page),
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const selectView = (view: OperationView) => {
        const nextForm = {
            ...form,
            type: "",
            status: "",
            view,
        };

        setForm(nextForm);
        router.get(route("operations.index"), queryParams(nextForm, 1), {
            preserveState: true,
            replace: true,
        });
    };

    const applyFilters = (event: FormEvent) => {
        event.preventDefault();
        visit(1);
    };

    const resetFilters = () => {
        router.get(route("operations.index"), {}, { replace: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Suivi technique" />

            <main className="container mx-auto max-w-7xl px-4 py-8">
                <div className="space-y-8">
                    <div className="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="min-w-0">
                            <h1 className="break-words text-2xl font-bold leading-tight sm:text-3xl">
                                Suivi technique
                            </h1>
                            <p className="mt-2 max-w-3xl text-muted-foreground">
                                Journal paginé des actions, erreurs de traitement,
                                téléchargements, imports, jobs et demandes de
                                cession.
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
                            title="Traces audit"
                            value={stats.auditLogs}
                            icon={<History className="h-4 w-4" />}
                            active={form.view === "audit_traces"}
                            onClick={() => selectView("audit_traces")}
                        />
                        <MetricCard
                            title="Demandes ouvertes"
                            value={stats.openRightsRequests}
                            icon={<ShieldAlert className="h-4 w-4" />}
                            tone="warning"
                            active={form.view === "open_rights_requests"}
                            onClick={() => selectView("open_rights_requests")}
                        />
                        <MetricCard
                            title="Téléchargements en échec"
                            value={stats.failedDownloads}
                            icon={<AlertTriangle className="h-4 w-4" />}
                            tone="danger"
                            active={form.view === "failed_downloads"}
                            onClick={() => selectView("failed_downloads")}
                        />
                        <MetricCard
                            title="Imports avec erreurs"
                            value={stats.failedImports}
                            icon={<FileWarning className="h-4 w-4" />}
                            tone="danger"
                            active={form.view === "failed_imports"}
                            onClick={() => selectView("failed_imports")}
                        />
                        <MetricCard
                            title="Transferts avec erreurs"
                            value={stats.failedTransfers}
                            icon={<DatabaseZap className="h-4 w-4" />}
                            tone="danger"
                            active={form.view === "failed_transfers"}
                            onClick={() => selectView("failed_transfers")}
                        />
                        <MetricCard
                            title="Jobs échoués"
                            value={stats.failedJobs}
                            icon={<ServerCrash className="h-4 w-4" />}
                            tone="danger"
                            active={form.view === "failed_jobs"}
                            onClick={() => selectView("failed_jobs")}
                        />
                    </section>

                    <form
                        onSubmit={applyFilters}
                        className="grid gap-3 rounded-md border bg-card p-4 lg:grid-cols-[1fr_180px_180px_180px_180px] xl:grid-cols-[1.2fr_180px_180px_170px_170px_150px_150px_110px_auto]"
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
                                placeholder="Action, erreur, objet..."
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
                                setForm({
                                    ...form,
                                    type: event.target.value,
                                    view: "",
                                })
                            }
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="">Toutes sources</option>
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
                                    view: "",
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
                        <select
                            value={form.perPage}
                            onChange={(event) =>
                                setForm({
                                    ...form,
                                    perPage: event.target.value,
                                })
                            }
                            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        >
                            <option value="25">25 / page</option>
                            <option value="50">50 / page</option>
                            <option value="100">100 / page</option>
                        </select>
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
                                    {viewTitle(form.view)}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {pagination.total} élément
                                    {pagination.total > 1 ? "s" : ""} au total,
                                    page {pagination.currentPage} sur{" "}
                                    {pagination.lastPage || 1}.
                                </p>
                            </div>
                            <PaginationControls
                                pagination={pagination}
                                onPageChange={visit}
                            />
                        </div>
                        <OperationsTable
                            events={events}
                            view={form.view}
                            canViewSensitiveAuditData={
                                canViewSensitiveAuditData
                            }
                        />
                        <div className="border-t p-4">
                            <PaginationControls
                                pagination={pagination}
                                onPageChange={visit}
                                alignEnd
                            />
                        </div>
                    </section>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}

function queryParams(form: ActiveFilters, page: number) {
    return {
        search: form.search || undefined,
        client_id: form.clientId || undefined,
        project_id: form.projectId || undefined,
        type: form.type || undefined,
        status: form.status || undefined,
        view: form.view || undefined,
        date_from: form.dateFrom || undefined,
        date_to: form.dateTo || undefined,
        per_page: form.perPage || undefined,
        page: page > 1 ? page : undefined,
    };
}

function viewTitle(view: OperationView) {
    const titles: Record<Exclude<OperationView, "">, string> = {
        audit_traces: "Traces audit",
        open_rights_requests: "Demandes de cession ouvertes",
        failed_downloads: "Téléchargements en échec",
        failed_imports: "Imports avec erreurs",
        failed_transfers: "Transferts avec erreurs",
        failed_jobs: "Jobs échoués",
    };

    return view ? titles[view] : "Traces techniques";
}

function PaginationControls({
    pagination,
    onPageChange,
    alignEnd = false,
}: {
    pagination: Pagination;
    onPageChange: (page: number) => void;
    alignEnd?: boolean;
}) {
    if (pagination.total === 0) {
        return null;
    }

    return (
        <div
            className={cn(
                "flex flex-wrap items-center gap-2",
                alignEnd && "justify-end",
            )}
        >
            <span className="text-sm text-muted-foreground">
                {(pagination.currentPage - 1) * pagination.perPage + 1}-
                {Math.min(
                    pagination.currentPage * pagination.perPage,
                    pagination.total,
                )}{" "}
                / {pagination.total}
            </span>
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={pagination.currentPage <= 1}
                onClick={() => onPageChange(pagination.currentPage - 1)}
            >
                Précédent
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={pagination.currentPage >= pagination.lastPage}
                onClick={() => onPageChange(pagination.currentPage + 1)}
            >
                Suivant
            </Button>
        </div>
    );
}

function MetricCard({
    title,
    value,
    icon,
    tone = "neutral",
    active = false,
    onClick,
}: {
    title: string;
    value: number;
    icon: ReactNode;
    tone?: "neutral" | "danger" | "warning" | "success";
    active?: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                "rounded-lg border bg-card p-6 text-left shadow-sm transition hover:border-primary/50 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring",
                active && "border-primary ring-2 ring-primary/20",
            )}
        >
            <div className="flex flex-row items-center justify-between space-y-0 pb-2">
                <div className="text-sm font-medium text-muted-foreground">
                    {title}
                </div>
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
            </div>
            <div className="text-2xl font-bold">{value}</div>
        </button>
    );
}

function OperationsTable({
    events,
    view,
    canViewSensitiveAuditData,
}: {
    events: OperationEvent[];
    view: OperationView;
    canViewSensitiveAuditData: boolean;
}) {
    if (events.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center text-muted-foreground">
                <History className="h-10 w-10" />
                <p>Aucune trace ne correspond aux filtres.</p>
            </div>
        );
    }

    if (view === "open_rights_requests") {
        return (
            <SpecializedTable
                headers={[
                    "Date demande",
                    "Image",
                    "Client / projet",
                    "Demandé par",
                    "Fin cession",
                    "Statut",
                    "Lien",
                ]}
                events={events}
                renderRow={(event) => [
                    event.date ? formatDate(event.date) : "Sans date",
                    event.title,
                    <ContextCell event={event} hideActor />,
                    event.actorName || "-",
                    metadataValue(event, "rightsEndsAt") || "-",
                    <StatusBadge status={event.status}>
                        {event.statusLabel}
                    </StatusBadge>,
                    <EventLink event={event} />,
                ]}
            />
        );
    }

    if (view === "failed_downloads") {
        return (
            <SpecializedTable
                headers={[
                    "Date",
                    "Téléchargement",
                    "Images",
                    "Format",
                    "Demandé par",
                    "Erreur",
                    "Lien",
                ]}
                events={events}
                renderRow={(event) => [
                    event.date ? formatDate(event.date) : "Sans date",
                    <TitleStatusCell event={event} />,
                    metadataValue(event, "requestedImages") ||
                        metadataValue(event, "skippedImages") ||
                        `${metadataValue(event, "imageCount") || 0} image(s)`,
                    metadataValue(event, "format") || "-",
                    <ContextCell event={event} hideProject />,
                    metadataValue(event, "errorDetails") || event.description,
                    <EventLink event={event} />,
                ]}
            />
        );
    }

    if (view === "failed_imports") {
        return (
            <SpecializedTable
                headers={[
                    "Date",
                    "Import",
                    "Client / projet",
                    "Progression",
                    "Échecs",
                    "Détails",
                    "Lien",
                ]}
                events={events}
                renderRow={(event) => [
                    event.date ? formatDate(event.date) : "Sans date",
                    <TitleStatusCell event={event} />,
                    <ContextCell event={event} hideActor />,
                    `${metadataValue(event, "processedItems") || 0}/${metadataValue(event, "totalItems") || 0}`,
                    metadataValue(event, "failedItems") || 0,
                    metadataValue(event, "failedItemDetails") ||
                        metadataValue(event, "metadata") ||
                        "-",
                    <EventLink event={event} />,
                ]}
            />
        );
    }

    if (view === "failed_transfers") {
        return (
            <SpecializedTable
                headers={[
                    "Date",
                    "Transfert",
                    "Mode",
                    "Lancé par",
                    "Progression",
                    "Dossiers en erreur",
                    "Lien",
                ]}
                events={events}
                renderRow={(event) => [
                    event.date ? formatDate(event.date) : "Sans date",
                    <TitleStatusCell event={event} />,
                    metadataValue(event, "mode") || "-",
                    event.actorName || "Global",
                    `${metadataValue(event, "processedFolders") || 0}/${metadataValue(event, "totalFolders") || 0}`,
                    metadataValue(event, "failedFolderDetails") ||
                        metadataValue(event, "failedFolders") ||
                        "-",
                    <EventLink event={event} />,
                ]}
            />
        );
    }

    if (view === "failed_jobs") {
        return (
            <SpecializedTable
                headers={["Date", "Queue", "Connexion", "UUID", "Exception"]}
                events={events}
                renderRow={(event) => [
                    event.date ? formatDate(event.date) : "Sans date",
                    metadataValue(event, "queue") || event.title,
                    metadataValue(event, "connection") || "-",
                    metadataValue(event, "uuid") || "-",
                    metadataValue(event, "exception") || event.description,
                ]}
            />
        );
    }

    return (
        <div className="mobile-card-table-wrapper overflow-x-auto">
            <Table className="mobile-card-table">
                <TableHeader>
                    <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Source</TableHead>
                        <TableHead>Gravité</TableHead>
                        <TableHead>Message</TableHead>
                        <TableHead>Contexte</TableHead>
                        <TableHead>Détails</TableHead>
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
                            <TableCell data-label="Source" className="align-top">
                                <Badge variant="outline">
                                    {event.typeLabel}
                                </Badge>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    #{event.sourceId}
                                </div>
                            </TableCell>
                            <TableCell
                                data-label="Gravité"
                                className="align-top"
                            >
                                <SeverityBadge severity={event.severity} />
                            </TableCell>
                            <TableCell
                                data-label="Message"
                                className="min-w-[260px] align-top"
                            >
                                <div className="font-medium">{event.title}</div>
                                <div className="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">
                                    {event.description}
                                </div>
                                <StatusBadge status={event.status}>
                                    {event.statusLabel}
                                </StatusBadge>
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
                            <TableCell
                                data-label="Détails"
                                className="min-w-[300px] max-w-[420px] align-top"
                            >
                                <EventMetadata
                                    event={event}
                                    canViewSensitiveAuditData={
                                        canViewSensitiveAuditData
                                    }
                                />
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

function SpecializedTable({
    headers,
    events,
    renderRow,
}: {
    headers: string[];
    events: OperationEvent[];
    renderRow: (event: OperationEvent) => ReactNode[];
}) {
    return (
        <div className="mobile-card-table-wrapper overflow-x-auto">
            <Table className="mobile-card-table">
                <TableHeader>
                    <TableRow>
                        {headers.map((header) => (
                            <TableHead key={header}>{header}</TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {events.map((event) => {
                        const cells = renderRow(event);

                        return (
                            <TableRow key={event.id}>
                                {cells.map((cell, index) => (
                                    <TableCell
                                        key={`${event.id}-${headers[index]}`}
                                        data-label={headers[index]}
                                        className={cn(
                                            "align-top",
                                            index === 1 && "min-w-[220px]",
                                            index === cells.length - 1 &&
                                                "max-w-[420px]",
                                        )}
                                    >
                                        <div className="break-words text-sm">
                                            {cell}
                                        </div>
                                    </TableCell>
                                ))}
                            </TableRow>
                        );
                    })}
                </TableBody>
            </Table>
        </div>
    );
}

function TitleStatusCell({ event }: { event: OperationEvent }) {
    return (
        <div>
            <div className="font-medium">{event.title}</div>
            <div className="mt-1 text-xs text-muted-foreground">
                #{event.sourceId}
            </div>
            <StatusBadge status={event.status}>{event.statusLabel}</StatusBadge>
        </div>
    );
}

function ContextCell({
    event,
    hideActor = false,
    hideProject = false,
}: {
    event: OperationEvent;
    hideActor?: boolean;
    hideProject?: boolean;
}) {
    return (
        <div className="space-y-1">
            {event.clientName && <div>{event.clientName}</div>}
            {!hideProject && event.projectName && (
                <div className="text-muted-foreground">{event.projectName}</div>
            )}
            {!hideActor && event.actorName && (
                <div className="text-muted-foreground">{event.actorName}</div>
            )}
            {!event.clientName &&
                (hideProject || !event.projectName) &&
                (hideActor || !event.actorName) && (
                    <span className="text-muted-foreground">Global</span>
                )}
        </div>
    );
}

function EventLink({ event }: { event: OperationEvent }) {
    if (!event.targetUrl) {
        return <span className="text-sm text-muted-foreground">Trace</span>;
    }

    return (
        <Button asChild variant="outline" size="sm">
            <Link href={event.targetUrl} className="gap-2">
                Ouvrir
                <ExternalLink className="h-4 w-4" />
            </Link>
        </Button>
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
        return <span className="text-sm text-muted-foreground">-</span>;
    }

    return (
        <dl className="space-y-1 text-xs">
            {rows.map(([key, value]) => (
                <div key={key} className="grid gap-1">
                    <dt className="font-medium text-muted-foreground">
                        {metadataLabel(key)}
                    </dt>
                    <dd className="break-words font-mono text-[0.72rem] leading-relaxed">
                        {formatMetadataValue(value)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function SeverityBadge({
    severity,
}: {
    severity: OperationEvent["severity"];
}) {
    const label =
        severity === "error"
            ? "Erreur"
            : severity === "warning"
              ? "Attention"
              : "Info";

    return (
        <Badge
            variant="secondary"
            className={cn(
                "w-fit gap-1",
                severity === "error" && "bg-red-100 text-red-800",
                severity === "warning" && "bg-amber-100 text-amber-900",
                severity === "info" && "bg-slate-100 text-slate-800",
            )}
        >
            {severity === "error" ? (
                <AlertTriangle className="h-3 w-3" />
            ) : severity === "warning" ? (
                <Clock className="h-3 w-3" />
            ) : (
                <CheckCircle2 className="h-3 w-3" />
            )}
            {label}
        </Badge>
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
        status === "failed" || status === "refuse"
            ? "danger"
            : status === "pending" ||
                status === "demande" ||
                status === "en_cours" ||
                status === "running" ||
                status === "processing"
              ? "warning"
              : status === "ready" ||
                  status === "completed" ||
                  status === "accepte"
                ? "success"
                : "neutral";

    return (
        <Badge
            variant="secondary"
            className={cn(
                "mt-2 w-fit gap-1",
                tone === "danger" && "bg-red-100 text-red-800",
                tone === "warning" && "bg-amber-100 text-amber-900",
                tone === "success" && "bg-emerald-100 text-emerald-800",
                tone === "neutral" && "bg-slate-100 text-slate-800",
            )}
        >
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
        subjectType: "Sujet",
        subjectId: "ID sujet",
        properties: "Propriétés",
        ipAddress: "IP",
        userAgent: "Navigateur",
        imageCount: "Images",
        isHd: "HD",
        processedAt: "Traité le",
        expiresAt: "Expire le",
        errorDetails: "Erreur",
        source: "Source",
        totalItems: "Total",
        uploadedItems: "Envoyés",
        processedItems: "Traités",
        failedItems: "Échecs",
        duplicateItems: "Doublons",
        failedItemDetails: "Fichiers en erreur",
        startedAt: "Début",
        finishedAt: "Fin",
        metadata: "Métadonnées",
        format: "Format",
        requestedImages: "Images demandées",
        skippedImages: "Images ignorées",
        variant: "Variante",
        cropPreset: "Format export",
        cropSource: "Source recadrage",
        mode: "Mode",
        currentFolder: "Dossier courant",
        totalFolders: "Dossiers",
        processedFolders: "Traités",
        failedFolders: "Dossiers échoués",
        failedFolderDetails: "Détails dossiers",
        logFile: "Fichier log",
        uuid: "UUID",
        connection: "Connexion",
        queue: "Queue",
        exception: "Exception",
        rightsEndsAt: "Fin cession",
        resolvedAt: "Résolu le",
        resolvedBy: "Résolu par",
        requestMetadata: "Métadonnées demande",
    };

    return labels[key] ?? key;
}

function metadataValue(event: OperationEvent, key: string) {
    const value = event.metadata[key];

    if (value === null || value === "" || typeof value === "undefined") {
        return null;
    }

    return formatMetadataValue(value);
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
