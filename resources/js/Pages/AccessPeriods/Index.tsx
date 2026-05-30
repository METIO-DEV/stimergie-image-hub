import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";
import { Calendar, Clock, MoreHorizontal, Plus, Users } from "lucide-react";
import { useMemo, useState } from "react";

type AccessPeriod = {
    id: number;
    clientName: string;
    projectName: string;
    isActive: boolean;
    startsAt: string | null;
    endsAt: string | null;
};

export default function AccessPeriodsIndex({
    periods,
}: {
    periods: AccessPeriod[];
}) {
    const [search, setSearch] = useState("");
    const [status, setStatus] = useState("");

    const filteredPeriods = useMemo(() => {
        const query = search.trim().toLowerCase();

        return periods.filter((period) => {
            const matchesSearch =
                !query ||
                period.clientName.toLowerCase().includes(query) ||
                period.projectName.toLowerCase().includes(query);
            const matchesStatus =
                !status ||
                (status === "active" && period.isActive) ||
                (status === "inactive" && !period.isActive);

            return matchesSearch && matchesStatus;
        });
    }, [periods, search, status]);

    return (
        <AuthenticatedLayout>
            <Head title="Gestion des droits d'accès" />

            <main className="flex-grow">
                <div className="mx-auto max-w-7xl px-4 py-8">
                    <div className="space-y-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-2xl font-bold">
                                    Gestion des droits d'accès
                                </h1>
                                <p className="text-muted-foreground">
                                    Gérez les périodes d'accès aux projets pour
                                    les clients
                                </p>
                            </div>
                            <Button className="flex items-center gap-2">
                                <Plus className="h-4 w-4" />
                                Nouvelle période
                            </Button>
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
                                        (period) => period.isActive,
                                    ).length
                                }
                                icon={<Clock className="h-4 w-4" />}
                            />
                            <MetricCard
                                title="Clients concernés"
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

                        <div className="grid gap-4 rounded-lg border bg-card p-4 md:grid-cols-[1fr_220px_auto]">
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Rechercher par client, projet ou date..."
                            />
                            <select
                                value={status}
                                onChange={(event) =>
                                    setStatus(event.target.value)
                                }
                                className="rounded-md border border-input bg-card px-3 py-2"
                            >
                                <option value="">Tous les statuts</option>
                                <option value="active">Actives</option>
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
                                    />
                                ))
                            ) : (
                                <Card>
                                    <CardContent className="flex flex-col items-center justify-center py-8">
                                        <p className="text-center text-muted-foreground">
                                            Aucune période d'accès configurée
                                        </p>
                                        <p className="mt-1 text-center text-sm text-muted-foreground">
                                            Créez une nouvelle période pour
                                            commencer
                                        </p>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </div>
                </div>
            </main>
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
    icon: React.ReactNode;
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

function AccessPeriodCard({ period }: { period: AccessPeriod }) {
    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                    <div className="space-y-1">
                        <CardTitle className="text-lg">
                            {period.projectName || "Projet sans nom"}
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Client: {period.clientName || "Client inconnu"}
                        </p>
                    </div>
                    <div className="flex items-center space-x-2">
                        <Badge
                            variant={period.isActive ? "default" : "secondary"}
                        >
                            {period.isActive ? "Active" : "Inactive"}
                        </Badge>
                        <Button variant="ghost" size="sm">
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
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
                        <p>Statut {period.isActive ? "actif" : "inactif"}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
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
