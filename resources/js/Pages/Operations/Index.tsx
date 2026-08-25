import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import { Input } from "@/Components/ui/input";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from "@/Components/ui/tabs";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { cn } from "@/lib/utils";
import { Head, Link, router } from "@inertiajs/react";
import {
    Download,
    ExternalLink,
    Image as ImageIcon,
    RefreshCw,
    Search,
    ShieldCheck,
    ShieldQuestion,
    TimerReset,
    UserRound,
} from "lucide-react";
import { FormEvent, ReactNode, useMemo, useState } from "react";

type Option = {
    id: number;
    name: string;
    clientId?: number;
    clientName?: string | null;
    email?: string;
};

type SelectOption = {
    value: string;
    label: string;
};

type Summary = {
    downloadsLast30Days: number;
    downloadedImagesLast30Days: number;
    openExtensionRequests: number;
    expiredRights: number;
    expiringRights: number;
    activeAccessPeriods: number;
    expiringAccessPeriods: number;
};

type ImageChip = {
    id: number;
    title: string;
    clientName: string | null;
    clientId: number;
    projectName: string | null;
    projectId: number;
    thumbUrl: string | null;
    imageUrl: string | null;
    rightsEndsAt: string | null;
    rightsStatus: string;
};

type DownloadRow = {
    id: number;
    createdAt: string | null;
    processedAt: string | null;
    expiresAt: string | null;
    title: string;
    status: string;
    statusLabel: string;
    format: string;
    imageCount: number;
    actorName: string | null;
    actorId: number;
    clientName: string | null;
    clientIds: number[];
    projectName: string | null;
    projectIds: number[];
    images: ImageChip[];
    skippedImages: Array<{ id: number; title: string }>;
    errorDetails: string | null;
    targetUrl: string;
};

type RightsRow = {
    id: number;
    title: string;
    clientId: number;
    clientName: string | null;
    projectId: number;
    projectName: string | null;
    thumbUrl: string | null;
    imageUrl: string | null;
    startsAt: string | null;
    endsAt: string | null;
    status: string;
    statusLabel: string;
    latestRequest: {
        id: number;
        status: string;
        statusLabel: string;
        requestedBy: string | null;
        resolvedBy: string | null;
        createdAt: string | null;
        resolvedAt: string | null;
        extendedRightsEndsAt: string | null;
    } | null;
    targetUrl: string;
};

type ExtensionRequestRow = {
    id: number;
    createdAt: string | null;
    resolvedAt: string | null;
    imageTitle: string | null;
    imageId: number;
    thumbUrl: string | null;
    imageUrl: string | null;
    clientName: string | null;
    clientId: number;
    projectName: string | null;
    projectId: number;
    requestedBy: string | null;
    resolvedBy: string | null;
    status: string;
    statusLabel: string;
    rightsEndsAt: string | null;
    extendedRightsEndsAt: string | null;
    targetUrl: string;
};

type AccessPeriodRow = {
    id: number;
    clientId: number;
    clientName: string | null;
    projectId: number;
    projectName: string | null;
    startsAt: string | null;
    endsAt: string | null;
    isActive: boolean;
    status: string;
    statusLabel: string;
    imagesCount: number;
    images: ImageChip[];
    createdAt: string | null;
    updatedAt: string | null;
    targetUrl: string;
};

type Dataset<T> = {
    items: T[];
    total: number;
};

type ActiveFilters = {
    search: string;
    clientId: string;
    projectId: string;
    userId: string;
    status: string;
    dateFrom: string;
    dateTo: string;
};

type TabValue =
    | "overview"
    | "downloads"
    | "rights"
    | "extensions"
    | "access";

type DetailItem =
    | { type: "download"; item: DownloadRow }
    | { type: "rights"; item: RightsRow }
    | { type: "extension"; item: ExtensionRequestRow }
    | { type: "access"; item: AccessPeriodRow }
    | null;

export default function OperationsIndex({
    summary,
    downloads,
    rights,
    extensionRequests,
    accessPeriods,
    filters,
    activeFilters,
    limits,
}: {
    summary: Summary;
    downloads: Dataset<DownloadRow>;
    rights: Dataset<RightsRow>;
    extensionRequests: Dataset<ExtensionRequestRow>;
    accessPeriods: Dataset<AccessPeriodRow>;
    filters: {
        clients: Option[];
        projects: Option[];
        users: Option[];
        downloadStatuses: SelectOption[];
        rightsStatuses: SelectOption[];
        extensionStatuses: SelectOption[];
        accessStatuses: SelectOption[];
    };
    activeFilters: ActiveFilters;
    limits: {
        maxRows: number;
    };
}) {
    const [tab, setTab] = useState<TabValue>("overview");
    const [form, setForm] = useState(activeFilters);
    const [detail, setDetail] = useState<DetailItem>(null);
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
        visit(form);
    };

    const resetFilters = () => {
        const reset = {
            search: "",
            clientId: "",
            projectId: "",
            userId: "",
            status: "",
            dateFrom: "",
            dateTo: "",
        };

        setForm(reset);
        visit(reset);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Suivi opérationnel" />

            <main className="mx-auto max-w-7xl px-4 py-8">
                <div className="space-y-8">
                    <div className="flex min-w-0 flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div className="min-w-0">
                            <h1 className="break-words text-2xl font-bold leading-tight sm:text-3xl">
                                Suivi opérationnel
                            </h1>
                        </div>
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <Button
                                asChild
                                variant="outline"
                                className="gap-2"
                            >
                                <Link href={route("access-periods.index")}>
                                    <ShieldCheck className="h-4 w-4" />
                                    Gérer les droits
                                </Link>
                            </Button>
                            <Button
                                variant="outline"
                                className="gap-2"
                                onClick={() => router.reload()}
                            >
                                <RefreshCw className="h-4 w-4" />
                                Actualiser
                            </Button>
                        </div>
                    </div>

                    <SummaryGrid summary={summary} />

                    <form
                        onSubmit={applyFilters}
                        className="grid grid-cols-2 gap-3 border-y bg-background py-5 md:grid-cols-[minmax(220px,1.6fr)_repeat(4,minmax(130px,1fr))] xl:grid-cols-[minmax(240px,1.7fr)_repeat(4,minmax(135px,1fr))_145px_145px_auto]"
                    >
                        <div className="relative col-span-2 md:col-span-1">
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
                                placeholder="Image, compte, entreprise, projet..."
                            />
                        </div>
                        <SelectControl
                            value={form.clientId}
                            onChange={(clientId) =>
                                setForm({ ...form, clientId, projectId: "" })
                            }
                            placeholder="Toutes entreprises"
                            options={filters.clients.map((client) => ({
                                value: String(client.id),
                                label: client.name,
                            }))}
                        />
                        <SelectControl
                            value={form.projectId}
                            onChange={(projectId) =>
                                setForm({ ...form, projectId })
                            }
                            placeholder="Tous projets"
                            options={visibleProjects.map((project) => ({
                                value: String(project.id),
                                label: project.name,
                            }))}
                        />
                        <SelectControl
                            value={form.userId}
                            onChange={(userId) => setForm({ ...form, userId })}
                            placeholder="Tous comptes"
                            options={filters.users.map((user) => ({
                                value: String(user.id),
                                label: user.name || user.email || `#${user.id}`,
                            }))}
                        />
                        <StatusSelect
                            tab={tab}
                            value={form.status}
                            onChange={(status) => setForm({ ...form, status })}
                            filters={filters}
                        />
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
                        <div className="col-span-2 flex gap-3 md:col-span-5 xl:col-span-1 xl:justify-end">
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

                    <Tabs
                        value={tab}
                        onValueChange={(value) => {
                            setTab(value as TabValue);
                            if (form.status) {
                                const nextForm = { ...form, status: "" };

                                setForm(nextForm);
                                visit(nextForm);
                            }
                        }}
                    >
                        <TabsList>
                            <TabsTrigger value="overview">Vue d’ensemble</TabsTrigger>
                            <TabsTrigger value="downloads">
                                Téléchargements
                            </TabsTrigger>
                            <TabsTrigger value="rights">Cessions</TabsTrigger>
                            <TabsTrigger value="extensions">
                                Demandes d’extension
                            </TabsTrigger>
                            <TabsTrigger value="access">
                                Droits d’accès
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="overview">
                            <OverviewSection
                                downloads={downloads.items.slice(0, 6)}
                                rights={rights.items.slice(0, 6)}
                                extensionRequests={extensionRequests.items.slice(
                                    0,
                                    6,
                                )}
                                accessPeriods={accessPeriods.items.slice(0, 6)}
                                onOpenDetail={setDetail}
                            />
                        </TabsContent>
                        <TabsContent value="downloads">
                            <SectionHeader
                                title="Téléchargements demandés"
                                total={downloads.total}
                                shown={downloads.items.length}
                                maxRows={limits.maxRows}
                            />
                            <DownloadsTable
                                rows={downloads.items}
                                onOpen={(item) =>
                                    setDetail({ type: "download", item })
                                }
                            />
                        </TabsContent>
                        <TabsContent value="rights">
                            <SectionHeader
                                title="Cessions images"
                                total={rights.total}
                                shown={rights.items.length}
                                maxRows={limits.maxRows}
                            />
                            <RightsTable
                                rows={rights.items}
                                onOpen={(item) =>
                                    setDetail({ type: "rights", item })
                                }
                            />
                        </TabsContent>
                        <TabsContent value="extensions">
                            <SectionHeader
                                title="Demandes d’extension de cession"
                                total={extensionRequests.total}
                                shown={extensionRequests.items.length}
                                maxRows={limits.maxRows}
                            />
                            <ExtensionRequestsTable
                                rows={extensionRequests.items}
                                onOpen={(item) =>
                                    setDetail({ type: "extension", item })
                                }
                            />
                        </TabsContent>
                        <TabsContent value="access">
                            <SectionHeader
                                title="Droits d’accès projet"
                                total={accessPeriods.total}
                                shown={accessPeriods.items.length}
                                maxRows={limits.maxRows}
                            />
                            <AccessPeriodsTable
                                rows={accessPeriods.items}
                                onOpen={(item) =>
                                    setDetail({ type: "access", item })
                                }
                            />
                        </TabsContent>
                    </Tabs>
                </div>
            </main>

            <DetailDialog detail={detail} onOpenChange={setDetail} />
        </AuthenticatedLayout>
    );
}

function SummaryGrid({ summary }: { summary: Summary }) {
    return (
        <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <MetricCard
                title="Téléchargements"
                value={summary.downloadsLast30Days}
                detail={`${summary.downloadedImagesLast30Days} images sur 30 jours`}
                icon={<Download className="h-4 w-4" />}
            />
            <MetricCard
                title="Extensions ouvertes"
                value={summary.openExtensionRequests}
                detail="Demandes à traiter ou en cours"
                icon={<ShieldQuestion className="h-4 w-4" />}
                tone="warning"
            />
            <MetricCard
                title="Cessions à surveiller"
                value={summary.expiredRights + summary.expiringRights}
                detail={`${summary.expiredRights} expirées, ${summary.expiringRights} bientôt`}
                icon={<TimerReset className="h-4 w-4" />}
                tone="danger"
            />
            <MetricCard
                title="Accès actifs"
                value={summary.activeAccessPeriods}
                detail={`${summary.expiringAccessPeriods} expirent sous 30 jours`}
                icon={<ShieldCheck className="h-4 w-4" />}
                tone="success"
            />
        </section>
    );
}

function MetricCard({
    title,
    value,
    detail,
    icon,
    tone = "neutral",
}: {
    title: string;
    value: number;
    detail: string;
    icon: ReactNode;
    tone?: "neutral" | "warning" | "danger" | "success";
}) {
    return (
        <div className="rounded-md border bg-card p-5 shadow-sm">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-sm font-medium text-muted-foreground">
                        {title}
                    </p>
                    <p className="mt-2 text-2xl font-semibold leading-none">
                        {value}
                    </p>
                </div>
                <span
                    className={cn(
                        "rounded-md border p-2",
                        tone === "neutral" &&
                            "border-slate-200 bg-slate-50 text-slate-700",
                        tone === "warning" &&
                            "border-amber-200 bg-amber-50 text-amber-700",
                        tone === "danger" &&
                            "border-red-200 bg-red-50 text-red-700",
                        tone === "success" &&
                            "border-emerald-200 bg-emerald-50 text-emerald-700",
                    )}
                >
                    {icon}
                </span>
            </div>
            <p className="mt-3 text-sm text-muted-foreground">{detail}</p>
        </div>
    );
}

function OverviewSection({
    downloads,
    rights,
    extensionRequests,
    accessPeriods,
    onOpenDetail,
}: {
    downloads: DownloadRow[];
    rights: RightsRow[];
    extensionRequests: ExtensionRequestRow[];
    accessPeriods: AccessPeriodRow[];
    onOpenDetail: (detail: DetailItem) => void;
}) {
    return (
        <div className="grid gap-6 xl:grid-cols-2">
            <OverviewList
                title="Derniers téléchargements"
                emptyLabel="Aucun téléchargement dans les filtres."
                items={downloads}
                renderItem={(item) => (
                    <OverviewButton
                        key={item.id}
                        title={item.title}
                        meta={`${item.actorName || "Compte inconnu"} · ${item.format}`}
                        right={<StatusBadge status={item.status} label={item.statusLabel} />}
                        onClick={() => onOpenDetail({ type: "download", item })}
                    />
                )}
            />
            <OverviewList
                title="Cessions à suivre"
                emptyLabel="Aucune cession dans les filtres."
                items={rights}
                renderItem={(item) => (
                    <OverviewButton
                        key={item.id}
                        title={item.title}
                        meta={`${item.clientName || "-"} · ${item.projectName || "-"}`}
                        right={<StatusBadge status={item.status} label={item.statusLabel} />}
                        onClick={() => onOpenDetail({ type: "rights", item })}
                    />
                )}
            />
            <OverviewList
                title="Demandes d’extension"
                emptyLabel="Aucune demande dans les filtres."
                items={extensionRequests}
                renderItem={(item) => (
                    <OverviewButton
                        key={item.id}
                        title={item.imageTitle || `Demande #${item.id}`}
                        meta={`${item.requestedBy || "Compte inconnu"} · ${formatDate(item.createdAt)}`}
                        right={<StatusBadge status={item.status} label={item.statusLabel} />}
                        onClick={() =>
                            onOpenDetail({ type: "extension", item })
                        }
                    />
                )}
            />
            <OverviewList
                title="Droits d’accès projet"
                emptyLabel="Aucun droit d’accès dans les filtres."
                items={accessPeriods}
                renderItem={(item) => (
                    <OverviewButton
                        key={item.id}
                        title={`${item.clientName || "-"} / ${item.projectName || "-"}`}
                        meta={`${item.imagesCount} image${item.imagesCount > 1 ? "s" : ""} concernée${item.imagesCount > 1 ? "s" : ""}`}
                        right={<StatusBadge status={item.status} label={item.statusLabel} />}
                        onClick={() => onOpenDetail({ type: "access", item })}
                    />
                )}
            />
        </div>
    );
}

function OverviewList<T>({
    title,
    emptyLabel,
    items,
    renderItem,
}: {
    title: string;
    emptyLabel: string;
    items: T[];
    renderItem: (item: T) => ReactNode;
}) {
    return (
        <section className="rounded-md border bg-card">
            <div className="border-b px-4 py-3">
                <h2 className="font-semibold">{title}</h2>
            </div>
            <div className="divide-y">
                {items.length > 0 ? (
                    items.map(renderItem)
                ) : (
                    <p className="px-4 py-6 text-sm text-muted-foreground">
                        {emptyLabel}
                    </p>
                )}
            </div>
        </section>
    );
}

function OverviewButton({
    title,
    meta,
    right,
    onClick,
}: {
    title: string;
    meta: string;
    right: ReactNode;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="grid w-full grid-cols-[1fr_auto] items-center gap-3 px-4 py-3 text-left transition hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            <span className="min-w-0">
                <span className="block truncate text-sm font-medium">
                    {title}
                </span>
                <span className="mt-1 block truncate text-xs text-muted-foreground">
                    {meta}
                </span>
            </span>
            {right}
        </button>
    );
}

function SectionHeader({
    title,
    total,
    shown,
    maxRows,
}: {
    title: string;
    total: number;
    shown: number;
    maxRows: number;
}) {
    return (
        <div className="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 className="text-lg font-semibold">{title}</h2>
                <p className="text-sm text-muted-foreground">
                    {shown} ligne{shown > 1 ? "s" : ""} affichée
                    {shown > 1 ? "s" : ""} sur {total}.
                </p>
            </div>
            {total > maxRows && (
                <p className="text-sm text-muted-foreground">
                    Affichage limité aux {maxRows} premiers résultats.
                </p>
            )}
        </div>
    );
}

function DownloadsTable({
    rows,
    onOpen,
}: {
    rows: DownloadRow[];
    onOpen: (row: DownloadRow) => void;
}) {
    return (
        <DataTable
            emptyLabel="Aucun téléchargement ne correspond aux filtres."
            headers={[
                "Date",
                "Compte",
                "Entreprise / projet",
                "Format",
                "Images",
                "Statut",
                "",
            ]}
            rows={rows}
            renderRow={(row) => [
                formatDate(row.createdAt),
                <PersonCell name={row.actorName} />,
                <ContextCell primary={row.clientName} secondary={row.projectName} />,
                row.format,
                `${row.imageCount} image${row.imageCount > 1 ? "s" : ""}`,
                <StatusBadge status={row.status} label={row.statusLabel} />,
                <RowActions onOpen={() => onOpen(row)} href={row.targetUrl} />,
            ]}
        />
    );
}

function RightsTable({
    rows,
    onOpen,
}: {
    rows: RightsRow[];
    onOpen: (row: RightsRow) => void;
}) {
    return (
        <DataTable
            emptyLabel="Aucune cession ne correspond aux filtres."
            headers={[
                "Image",
                "Entreprise / projet",
                "Début",
                "Fin",
                "État",
                "Demande liée",
                "",
            ]}
            rows={rows}
            renderRow={(row) => [
                <StrongCell title={row.title} subtitle={`#${row.id}`} />,
                <ContextCell primary={row.clientName} secondary={row.projectName} />,
                formatPlainDate(row.startsAt),
                formatPlainDate(row.endsAt) || "Illimitée",
                <StatusBadge status={row.status} label={row.statusLabel} />,
                row.latestRequest ? row.latestRequest.statusLabel : "-",
                <RowActions onOpen={() => onOpen(row)} href={row.targetUrl} />,
            ]}
        />
    );
}

function ExtensionRequestsTable({
    rows,
    onOpen,
}: {
    rows: ExtensionRequestRow[];
    onOpen: (row: ExtensionRequestRow) => void;
}) {
    return (
        <DataTable
            emptyLabel="Aucune demande d’extension ne correspond aux filtres."
            headers={[
                "Date demande",
                "Image",
                "Entreprise / projet",
                "Demandé par",
                "Fin actuelle",
                "Statut",
                "",
            ]}
            rows={rows}
            renderRow={(row) => [
                formatDate(row.createdAt),
                <StrongCell title={row.imageTitle || `#${row.imageId}`} />,
                <ContextCell primary={row.clientName} secondary={row.projectName} />,
                <PersonCell name={row.requestedBy} />,
                formatPlainDate(row.rightsEndsAt),
                <StatusBadge status={row.status} label={row.statusLabel} />,
                <RowActions onOpen={() => onOpen(row)} href={row.targetUrl} />,
            ]}
        />
    );
}

function AccessPeriodsTable({
    rows,
    onOpen,
}: {
    rows: AccessPeriodRow[];
    onOpen: (row: AccessPeriodRow) => void;
}) {
    return (
        <DataTable
            emptyLabel="Aucun droit d’accès ne correspond aux filtres."
            headers={[
                "Entreprise",
                "Projet",
                "Début",
                "Fin",
                "Images",
                "Statut",
                "",
            ]}
            rows={rows}
            renderRow={(row) => [
                row.clientName || "-",
                row.projectName || "-",
                formatPlainDate(row.startsAt) || "Immédiat",
                formatPlainDate(row.endsAt) || "Sans limite",
                row.imagesCount,
                <StatusBadge status={row.status} label={row.statusLabel} />,
                <RowActions onOpen={() => onOpen(row)} href={row.targetUrl} />,
            ]}
        />
    );
}

function DataTable<T>({
    headers,
    rows,
    renderRow,
    emptyLabel,
}: {
    headers: string[];
    rows: T[];
    renderRow: (row: T) => ReactNode[];
    emptyLabel: string;
}) {
    if (rows.length === 0) {
        return (
            <div className="rounded-md border px-4 py-10 text-center text-sm text-muted-foreground">
                {emptyLabel}
            </div>
        );
    }

    return (
        <div className="mobile-card-table-wrapper overflow-x-auto rounded-md border">
            <Table className="mobile-card-table">
                <TableHeader>
                    <TableRow>
                        {headers.map((header) => (
                            <TableHead key={header}>{header}</TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row, rowIndex) => {
                        const cells = renderRow(row);

                        return (
                            <TableRow key={rowIndex}>
                                {cells.map((cell, cellIndex) => (
                                    <TableCell
                                        key={`${rowIndex}-${headers[cellIndex]}`}
                                        data-label={headers[cellIndex]}
                                        className={cn(
                                            "align-top",
                                            cellIndex === 0 && "min-w-[160px]",
                                            cellIndex === cells.length - 1 &&
                                                "w-[120px] text-right",
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

function RowActions({ onOpen, href }: { onOpen: () => void; href?: string }) {
    return (
        <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" size="sm" onClick={onOpen}>
                Détail
            </Button>
            {href && (
                <Button asChild variant="ghost" size="sm">
                    <Link href={href}>
                        <ExternalLink className="h-4 w-4" />
                    </Link>
                </Button>
            )}
        </div>
    );
}

function DetailDialog({
    detail,
    onOpenChange,
}: {
    detail: DetailItem;
    onOpenChange: (detail: DetailItem) => void;
}) {
    return (
        <Dialog open={detail !== null} onOpenChange={(open) => !open && onOpenChange(null)}>
            <DialogContent className="max-h-[85vh] max-w-3xl overflow-y-auto">
                {detail && (
                    <>
                        <DialogHeader>
                            <DialogTitle>{detailTitle(detail)}</DialogTitle>
                            <DialogDescription>
                                Données actuellement enregistrées par
                                l’application.
                            </DialogDescription>
                        </DialogHeader>
                        <DetailBody detail={detail} />
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

function DetailBody({ detail }: { detail: NonNullable<DetailItem> }) {
    if (detail.type === "download") {
        const item = detail.item;

        return (
            <div className="space-y-5">
                <DetailGrid
                    rows={[
                        ["Compte", item.actorName || "-"],
                        ["Date", formatDate(item.createdAt)],
                        ["Format", item.format],
                        ["Statut", item.statusLabel],
                        ["Traitement", formatDate(item.processedAt)],
                        ["Expiration archive", formatDate(item.expiresAt)],
                    ]}
                />
                <ImageList images={item.images} />
                {item.skippedImages.length > 0 && (
                    <DetailSection title="Images ignorées">
                        <ul className="space-y-1 text-sm">
                            {item.skippedImages.map((image) => (
                                <li key={`${image.id}-${image.title}`}>
                                    #{image.id || "-"} {image.title}
                                </li>
                            ))}
                        </ul>
                    </DetailSection>
                )}
                {item.errorDetails && (
                    <DetailSection title="Erreur">
                        <p className="whitespace-pre-wrap text-sm text-red-700">
                            {item.errorDetails}
                        </p>
                    </DetailSection>
                )}
            </div>
        );
    }

    if (detail.type === "rights") {
        const item = detail.item;

        return (
            <div className="space-y-5">
                <DetailGrid
                    rows={[
                        ["Image", item.title],
                        ["Entreprise", item.clientName || "-"],
                        ["Projet", item.projectName || "-"],
                        ["Début cession", formatPlainDate(item.startsAt) || "-"],
                        ["Fin cession", formatPlainDate(item.endsAt) || "Illimitée"],
                        ["État", item.statusLabel],
                    ]}
                />
                <ImageList images={[rightsToImageChip(item)]} />
                {item.latestRequest && (
                    <DetailSection title="Dernière demande d’extension">
                        <DetailGrid
                            rows={[
                                ["Statut", item.latestRequest.statusLabel],
                                ["Demandé par", item.latestRequest.requestedBy || "-"],
                                ["Demandé le", formatDate(item.latestRequest.createdAt)],
                                ["Résolu par", item.latestRequest.resolvedBy || "-"],
                                ["Résolu le", formatDate(item.latestRequest.resolvedAt)],
                                [
                                    "Nouvelle fin",
                                    formatPlainDate(
                                        item.latestRequest.extendedRightsEndsAt,
                                    ) || "-",
                                ],
                            ]}
                        />
                    </DetailSection>
                )}
            </div>
        );
    }

    if (detail.type === "extension") {
        const item = detail.item;

        return (
            <div className="space-y-5">
                <DetailGrid
                    rows={[
                        ["Image", item.imageTitle || `#${item.imageId}`],
                        ["Entreprise", item.clientName || "-"],
                        ["Projet", item.projectName || "-"],
                        ["Demandé par", item.requestedBy || "-"],
                        ["Demandé le", formatDate(item.createdAt)],
                        ["Fin actuelle", formatPlainDate(item.rightsEndsAt) || "-"],
                        ["Statut", item.statusLabel],
                        ["Résolu par", item.resolvedBy || "-"],
                        ["Résolu le", formatDate(item.resolvedAt)],
                        [
                            "Nouvelle fin",
                            formatPlainDate(item.extendedRightsEndsAt) || "-",
                        ],
                    ]}
                />
                <ImageList images={[extensionToImageChip(item)]} />
            </div>
        );
    }

    const item = detail.item;

    return (
        <div className="space-y-5">
            <DetailGrid
                rows={[
                    ["Entreprise", item.clientName || "-"],
                    ["Projet", item.projectName || "-"],
                    ["Début", formatPlainDate(item.startsAt) || "Immédiat"],
                    ["Fin", formatPlainDate(item.endsAt) || "Sans limite"],
                    ["Statut", item.statusLabel],
                    ["Images du projet", String(item.imagesCount)],
                    ["Créé le", formatDate(item.createdAt)],
                    ["Mis à jour le", formatDate(item.updatedAt)],
                ]}
            />
            <ImageList images={item.images} />
        </div>
    );
}

function DetailGrid({ rows }: { rows: Array<[string, ReactNode]> }) {
    return (
        <dl className="grid gap-3 sm:grid-cols-2">
            {rows.map(([label, value]) => (
                <div key={label} className="rounded-md border bg-muted/30 p-3">
                    <dt className="text-xs font-medium uppercase text-muted-foreground">
                        {label}
                    </dt>
                    <dd className="mt-1 break-words text-sm">{value || "-"}</dd>
                </div>
            ))}
        </dl>
    );
}

function DetailSection({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section>
            <h3 className="mb-2 text-sm font-semibold">{title}</h3>
            <div className="rounded-md border bg-muted/30 p-3">{children}</div>
        </section>
    );
}

function ImageList({ images }: { images: ImageChip[] }) {
    return (
        <DetailSection title={`Images concernées (${images.length})`}>
            {images.length > 0 ? (
                <div className="max-h-72 space-y-2 overflow-y-auto pr-1">
                    {images.map((image) => {
                        const previewUrl = image.thumbUrl || image.imageUrl;

                        return (
                            <div
                                key={image.id}
                                className="grid gap-3 rounded-md border bg-background p-3 text-sm sm:grid-cols-[72px_1fr_auto]"
                            >
                                <div className="h-[72px] w-[72px] overflow-hidden rounded-md border bg-muted">
                                    {previewUrl ? (
                                        <img
                                            src={previewUrl}
                                            alt=""
                                            className="h-full w-full object-cover"
                                            loading="lazy"
                                        />
                                    ) : (
                                        <div className="flex h-full w-full items-center justify-center text-muted-foreground">
                                            <ImageIcon className="h-5 w-5" />
                                        </div>
                                    )}
                                </div>
                                <div className="min-w-0">
                                    <div className="font-medium">
                                        #{image.id} {image.title}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {image.clientName || "-"} ·{" "}
                                        {image.projectName || "-"}
                                    </div>
                                </div>
                                <div className="sm:text-right">
                                    <StatusBadge
                                        status={image.rightsStatus}
                                        label={rightsStatusLabel(
                                            image.rightsStatus,
                                        )}
                                    />
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {formatPlainDate(image.rightsEndsAt) ||
                                            "Illimitée"}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    La liste détaillée des images n’est pas disponible pour cette
                    demande.
                </p>
            )}
        </DetailSection>
    );
}

function StatusSelect({
    tab,
    value,
    onChange,
    filters,
}: {
    tab: TabValue;
    value: string;
    onChange: (value: string) => void;
    filters: {
        downloadStatuses: SelectOption[];
        rightsStatuses: SelectOption[];
        extensionStatuses: SelectOption[];
        accessStatuses: SelectOption[];
    };
}) {
    const options =
        tab === "rights"
            ? filters.rightsStatuses
            : tab === "extensions"
              ? filters.extensionStatuses
              : tab === "access"
                ? filters.accessStatuses
                : filters.downloadStatuses;

    return (
        <SelectControl
            value={value}
            onChange={onChange}
            placeholder="Tous statuts"
            options={options}
        />
    );
}

function SelectControl({
    value,
    onChange,
    placeholder,
    options,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    options: SelectOption[];
}) {
    return (
        <select
            value={value}
            onChange={(event) => onChange(event.target.value)}
            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
        >
            <option value="">{placeholder}</option>
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    );
}

function StrongCell({
    title,
    subtitle,
}: {
    title: string;
    subtitle?: string;
}) {
    return (
        <div>
            <div className="font-medium">{title}</div>
            {subtitle && (
                <div className="mt-1 text-xs text-muted-foreground">
                    {subtitle}
                </div>
            )}
        </div>
    );
}

function ContextCell({
    primary,
    secondary,
}: {
    primary?: string | null;
    secondary?: string | null;
}) {
    return (
        <div>
            <div>{primary || "-"}</div>
            {secondary && (
                <div className="mt-1 text-xs text-muted-foreground">
                    {secondary}
                </div>
            )}
        </div>
    );
}

function PersonCell({ name }: { name?: string | null }) {
    return (
        <div className="inline-flex max-w-full items-center gap-2">
            <UserRound className="h-4 w-4 text-muted-foreground" />
            <span className="truncate">{name || "Compte inconnu"}</span>
        </div>
    );
}

function StatusBadge({ status, label }: { status: string; label: string }) {
    const tone =
        status === "failed" ||
        status === "expired" ||
        status === "refuse" ||
        status === "inactive"
            ? "danger"
            : status === "pending" ||
                status === "processing" ||
                status === "demande" ||
                status === "en_cours" ||
                status === "expiring_soon" ||
                status === "upcoming"
              ? "warning"
              : status === "ready" ||
                  status === "completed" ||
                  status === "accepte" ||
                  status === "active"
                ? "success"
                : "neutral";

    return (
        <Badge
            variant="secondary"
            className={cn(
                "w-fit",
                tone === "danger" && "bg-red-100 text-red-800",
                tone === "warning" && "bg-amber-100 text-amber-900",
                tone === "success" && "bg-emerald-100 text-emerald-800",
                tone === "neutral" && "bg-slate-100 text-slate-800",
            )}
        >
            {label}
        </Badge>
    );
}

function visit(form: ActiveFilters) {
    router.get(
        route("operations.index"),
        {
            search: form.search || undefined,
            client_id: form.clientId || undefined,
            project_id: form.projectId || undefined,
            user_id: form.userId || undefined,
            status: form.status || undefined,
            date_from: form.dateFrom || undefined,
            date_to: form.dateTo || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    );
}

function detailTitle(detail: NonNullable<DetailItem>) {
    if (detail.type === "download") {
        return `Téléchargement #${detail.item.id}`;
    }

    if (detail.type === "rights") {
        return `Cession image #${detail.item.id}`;
    }

    if (detail.type === "extension") {
        return `Demande d’extension #${detail.item.id}`;
    }

    return `Droit d’accès #${detail.item.id}`;
}

function rightsToImageChip(item: RightsRow): ImageChip {
    return {
        id: item.id,
        title: item.title,
        clientName: item.clientName,
        clientId: item.clientId,
        projectName: item.projectName,
        projectId: item.projectId,
        thumbUrl: item.thumbUrl,
        imageUrl: item.imageUrl,
        rightsEndsAt: item.endsAt,
        rightsStatus: item.status,
    };
}

function extensionToImageChip(item: ExtensionRequestRow): ImageChip {
    return {
        id: item.imageId,
        title: item.imageTitle || `Image #${item.imageId}`,
        clientName: item.clientName,
        clientId: item.clientId,
        projectName: item.projectName,
        projectId: item.projectId,
        thumbUrl: item.thumbUrl,
        imageUrl: item.imageUrl,
        rightsEndsAt: item.rightsEndsAt,
        rightsStatus: "active",
    };
}

function formatDate(value?: string | null) {
    if (!value) {
        return "-";
    }

    return new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    }).format(new Date(value));
}

function formatPlainDate(value?: string | null) {
    if (!value) {
        return "";
    }

    return new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(new Date(`${value}T00:00:00`));
}

function rightsStatusLabel(status: string) {
    if (status === "expired") {
        return "Cession expirée";
    }

    if (status === "expiring_soon") {
        return "Expire bientôt";
    }

    if (status === "unlimited") {
        return "Illimitée";
    }

    return "Active";
}
