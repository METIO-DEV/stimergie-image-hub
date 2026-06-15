import InputError from "@/Components/InputError";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, router, useForm } from "@inertiajs/react";
import { Calendar, Clock, Pencil, Plus, Trash2, Users } from "lucide-react";
import { FormEvent, ReactNode, useEffect, useMemo, useState } from "react";

type AccessPeriod = {
    id: number;
    clientId: number;
    clientName: string;
    projectId: number;
    projectName: string;
    isActive: boolean;
    startsAt: string | null;
    endsAt: string | null;
    status: "active" | "upcoming" | "expired" | "inactive";
    canUpdate: boolean;
    canDelete: boolean;
};

type Option = {
    id: number;
    name: string;
    clientId?: number;
    clientName?: string;
};

export default function AccessPeriodsIndex({
    periods,
    clients,
    projects,
    canManageAccessPeriods,
}: {
    periods: AccessPeriod[];
    clients: Option[];
    projects: Option[];
    canManageAccessPeriods: boolean;
}) {
    const [search, setSearch] = useState("");
    const [status, setStatus] = useState("");
    const [editingPeriod, setEditingPeriod] = useState<AccessPeriod | null>(
        null,
    );
    const [periodModalOpen, setPeriodModalOpen] = useState(false);

    const filteredPeriods = useMemo(() => {
        const query = search.trim().toLowerCase();

        return periods.filter((period) => {
            const dateText = `${period.startsAt || ""} ${period.endsAt || ""}`;
            const matchesSearch =
                !query ||
                period.clientName.toLowerCase().includes(query) ||
                period.projectName.toLowerCase().includes(query) ||
                dateText.includes(query);
            const matchesStatus = !status || period.status === status;

            return matchesSearch && matchesStatus;
        });
    }, [periods, search, status]);
    const searchSuggestions = useMemo(
        () =>
            [
                ...periods.map((period) => period.clientName),
                ...periods.map((period) => period.projectName),
                ...periods.flatMap((period) => [
                    period.startsAt || "",
                    period.endsAt || "",
                ]),
            ]
                .filter(Boolean)
                .filter(
                    (value, index, values) =>
                        values.findIndex(
                            (candidate) =>
                                candidate.toLowerCase() === value.toLowerCase(),
                        ) === index,
                ),
        [periods],
    );

    const deletePeriod = (period: AccessPeriod) => {
        if (
            !window.confirm(
                `Supprimer la période "${period.clientName} / ${period.projectName}" ?`,
            )
        ) {
            return;
        }

        router.delete(route("access-periods.destroy", period.id), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Gestion des droits d'accès" />

            <main className="flex-grow">
                <div className="mx-auto max-w-7xl px-4 py-8">
                    <div className="space-y-6">
                        <div className="flex min-w-0 flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div className="min-w-0">
                                <h1 className="break-words text-2xl font-bold leading-tight">
                                    Gestion des droits d'accès
                                </h1>
                                <p className="text-muted-foreground">
                                    Gérez les périodes d'accès aux projets pour
                                    les entreprises.
                                </p>
                            </div>
                            {canManageAccessPeriods && (
                                <Button
                                    className="flex h-auto items-center gap-2 whitespace-normal text-left"
                                    onClick={() => {
                                        setEditingPeriod(null);
                                        setPeriodModalOpen(true);
                                    }}
                                >
                                    <Plus className="h-4 w-4" />
                                    Nouvelle période
                                </Button>
                            )}
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <MetricCard
                                title="Total des périodes"
                                value={filteredPeriods.length}
                                detail={
                                    filteredPeriods.length !== periods.length
                                        ? `sur ${periods.length} au total`
                                        : undefined
                                }
                                icon={<Calendar className="h-4 w-4" />}
                            />
                            <MetricCard
                                title="Périodes actives"
                                value={
                                    filteredPeriods.filter(
                                        (period) => period.status === "active",
                                    ).length
                                }
                                icon={<Clock className="h-4 w-4" />}
                            />
                            <MetricCard
                                title="Entreprises concernées"
                                value={
                                    new Set(
                                        filteredPeriods.map(
                                            (period) => period.clientName,
                                        ),
                                    ).size
                                }
                                icon={<Users className="h-4 w-4" />}
                            />
                        </div>

                        <div className="grid gap-4 rounded-md border bg-card p-4 md:grid-cols-[1fr_220px_auto]">
                            <div>
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    list="access-period-search-suggestions"
                                    placeholder="Rechercher par entreprise, projet ou date..."
                                />
                                <datalist id="access-period-search-suggestions">
                                    {searchSuggestions.map((suggestion) => (
                                        <option
                                            key={suggestion}
                                            value={suggestion}
                                        />
                                    ))}
                                </datalist>
                            </div>
                            <select
                                value={status}
                                onChange={(event) =>
                                    setStatus(event.target.value)
                                }
                                className="rounded-md border border-input bg-card px-3 py-2"
                            >
                                <option value="">Tous les statuts</option>
                                <option value="active">Actives</option>
                                <option value="upcoming">À venir</option>
                                <option value="expired">Expirées</option>
                                <option value="inactive">Inactives</option>
                            </select>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setSearch("");
                                    setStatus("");
                                }}
                            >
                                Effacer les filtres
                            </Button>
                        </div>

                        <div className="space-y-4">
                            {filteredPeriods.length > 0 ? (
                                filteredPeriods.map((period) => (
                                    <AccessPeriodCard
                                        key={period.id}
                                        period={period}
                                        onEdit={
                                            period.canUpdate
                                                ? () => {
                                                      setEditingPeriod(period);
                                                      setPeriodModalOpen(true);
                                                  }
                                                : undefined
                                        }
                                        onDelete={
                                            period.canDelete
                                                ? () => deletePeriod(period)
                                                : undefined
                                        }
                                    />
                                ))
                            ) : (
                                <Card>
                                    <CardContent className="flex flex-col items-center justify-center py-8">
                                        <p className="text-center text-muted-foreground">
                                            Aucune période d'accès configurée
                                        </p>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </div>
                </div>
            </main>
            <AccessPeriodModal
                period={editingPeriod}
                clients={clients}
                projects={projects}
                open={periodModalOpen}
                onOpenChange={(open) => {
                    setPeriodModalOpen(open);
                    if (!open) {
                        setEditingPeriod(null);
                    }
                }}
            />
        </AuthenticatedLayout>
    );
}

function MetricCard({
    title,
    value,
    detail,
    icon,
}: {
    title: string;
    value: number;
    detail?: string;
    icon: ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{title}</CardTitle>
                <span className="text-muted-foreground">{icon}</span>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold">{value}</div>
                {detail && (
                    <p className="text-xs text-muted-foreground">{detail}</p>
                )}
            </CardContent>
        </Card>
    );
}

function AccessPeriodCard({
    period,
    onEdit,
    onDelete,
}: {
    period: AccessPeriod;
    onEdit?: () => void;
    onDelete?: () => void;
}) {
    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-1">
                        <CardTitle className="text-lg">
                            {period.projectName || "Projet sans nom"}
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Entreprise:{" "}
                            {period.clientName || "Entreprise inconnue"}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant={periodBadgeVariant(period.status)}>
                            {periodStatusLabel(period.status)}
                        </Badge>
                        {onEdit && (
                            <Button
                                variant="ghost"
                                size="icon"
                                title="Modifier"
                                onClick={onEdit}
                            >
                                <Pencil className="h-4 w-4" />
                            </Button>
                        )}
                        {onDelete && (
                            <Button
                                variant="ghost"
                                size="icon"
                                title="Supprimer"
                                onClick={onDelete}
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                    <div>
                        <p className="font-medium text-muted-foreground">
                            Période d'accès
                        </p>
                        <p>Du {formatDate(period.startsAt)}</p>
                        <p>Au {formatDate(period.endsAt)}</p>
                    </div>
                    <div>
                        <p className="font-medium text-muted-foreground">
                            Informations
                        </p>
                        <p>
                            {period.isActive
                                ? "Période activée"
                                : "Période désactivée"}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function AccessPeriodModal({
    period,
    clients,
    projects,
    open,
    onOpenChange,
}: {
    period: AccessPeriod | null;
    clients: Option[];
    projects: Option[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { data, setData, post, patch, processing, errors, reset } = useForm({
        client_id: "",
        project_id: "",
        starts_at: "",
        ends_at: "",
        is_active: true,
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        setData({
            client_id: period ? String(period.clientId) : "",
            project_id: period ? String(period.projectId) : "",
            starts_at: period?.startsAt || "",
            ends_at: period?.endsAt || "",
            is_active: period?.isActive ?? true,
        });
    }, [open, period, setData]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };

        if (period) {
            patch(route("access-periods.update", period.id), options);
            return;
        }

        post(route("access-periods.store"), options);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {period
                            ? "Modifier la période"
                            : "Créer une période"}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="access-client">Entreprise</Label>
                            <select
                                id="access-client"
                                value={data.client_id}
                                onChange={(event) =>
                                    setData("client_id", event.target.value)
                                }
                                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">
                                    Sélectionner une entreprise
                                </option>
                                {clients.map((client) => (
                                    <option key={client.id} value={client.id}>
                                        {client.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.client_id} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="access-project">Projet</Label>
                            <select
                                id="access-project"
                                value={data.project_id}
                                onChange={(event) =>
                                    setData("project_id", event.target.value)
                                }
                                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Sélectionner un projet</option>
                                {projects.map((project) => (
                                    <option key={project.id} value={project.id}>
                                        {project.clientName
                                            ? `${project.clientName} - ${project.name}`
                                            : project.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.project_id} />
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="access-starts">Début</Label>
                            <Input
                                id="access-starts"
                                type="date"
                                value={data.starts_at}
                                onChange={(event) =>
                                    setData("starts_at", event.target.value)
                                }
                            />
                            <InputError message={errors.starts_at} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="access-ends">Fin</Label>
                            <Input
                                id="access-ends"
                                type="date"
                                value={data.ends_at}
                                onChange={(event) =>
                                    setData("ends_at", event.target.value)
                                }
                            />
                            <InputError message={errors.ends_at} />
                        </div>
                    </div>
                    <label className="flex items-center gap-3 text-sm font-medium">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(event) =>
                                setData("is_active", event.target.checked)
                            }
                            className="h-4 w-4 rounded border-input"
                        />
                        Période active
                    </label>
                    <InputError message={errors.is_active} />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Annuler
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {period ? "Mettre à jour" : "Créer"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function periodStatusLabel(status: AccessPeriod["status"]) {
    return {
        active: "Active",
        upcoming: "À venir",
        expired: "Expirée",
        inactive: "Inactive",
    }[status];
}

function periodBadgeVariant(status: AccessPeriod["status"]) {
    return status === "active" ? "default" : "secondary";
}

function formatDate(value: string | null) {
    if (!value) {
        return "-";
    }

    return new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
    }).format(new Date(value));
}
