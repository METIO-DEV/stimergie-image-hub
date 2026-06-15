import { Button } from "@/Components/ui/button";
import {
    LegacySelect,
    LegacyUserCard,
    SectionHeader,
    ViewMode,
    ViewToggle,
    roleDisplay,
} from "@/Components/Legacy/LegacyDesign";
import { UserEditModal } from "@/Components/Legacy/LegacyModals";
import { Badge } from "@/Components/ui/badge";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, router } from "@inertiajs/react";
import {
    Building2,
    Mail,
    Pencil,
    PlusCircle,
    Shield,
    Trash2,
    UserRound,
    Users,
} from "lucide-react";
import { useMemo, useState } from "react";

type UserRow = {
    id: number;
    name: string;
    email: string;
    status: string;
    platformRole: string | null;
    role: string;
    clients: Array<{
        clientId: number | null;
        clientName: string | null;
        role: string;
        status: string;
    }>;
    clientIds: number[];
};

type ClientOption = {
    id: number;
    name: string;
};

type RoleOption = {
    value: string;
    label: string;
};

type Props = {
    users: UserRow[];
    clients: ClientOption[];
    roles: RoleOption[];
};

export default function UsersIndex({ users, clients, roles }: Props) {
    const [clientId, setClientId] = useState("");
    const [role, setRole] = useState("");
    const [viewMode, setViewMode] = useState<ViewMode>("card");
    const [editingUser, setEditingUser] = useState<UserRow | null>(null);
    const [userModalOpen, setUserModalOpen] = useState(false);

    const deleteUser = (user: UserRow) => {
        if (
            !window.confirm(
                `Supprimer l'utilisateur "${user.name || user.email}" ? Cette action est définitive.`,
            )
        ) {
            return;
        }

        router.delete(route("users.destroy", user.id), {
            preserveScroll: true,
        });
    };

    const filteredUsers = useMemo(
        () =>
            users.filter((user) => {
                const matchesClient =
                    !clientId ||
                    user.clientIds.some((id) => String(id) === clientId);
                const matchesRole = !role || user.role === role;

                return matchesClient && matchesRole;
            }),
        [clientId, role, users],
    );

    return (
        <AuthenticatedLayout>
            <Head title="Utilisateurs" />

            <SectionHeader
                icon={<Users className="h-8 w-8 text-primary" />}
                title="Utilisateurs"
                description="Créez les comptes, attribuez leurs statuts et rattachez-les aux entreprises."
                action={
                    <Button
                        className="h-auto gap-2 whitespace-normal text-left"
                        onClick={() => {
                            setEditingUser(null);
                            setUserModalOpen(true);
                        }}
                    >
                        <PlusCircle size={18} />
                        Ajouter un utilisateur
                    </Button>
                }
            />

            <main className="mx-auto w-full max-w-7xl px-6 py-12">
                <div className="mb-8 flex flex-col gap-4 md:flex-row">
                    <div className="grid flex-grow grid-cols-1 gap-4 md:grid-cols-2">
                        <LegacySelect
                            label="Filtrer par entreprise"
                            value={clientId}
                            onChange={setClientId}
                            allLabel="Toutes les entreprises"
                            options={clients}
                        />
                        <LegacySelect
                            label="Filtrer par rôle"
                            value={role}
                            onChange={setRole}
                            allLabel="Tous les rôles"
                            options={roles.map((item) => ({
                                id: item.value,
                                name: item.label,
                            }))}
                        />
                    </div>
                    <div className="flex items-end justify-end md:pl-4">
                        <ViewToggle
                            currentView={viewMode}
                            onViewChange={setViewMode}
                        />
                    </div>
                </div>

                {viewMode === "card" ? (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        {filteredUsers.map((user) => (
                            <LegacyUserCard
                                key={user.id}
                                name={user.name}
                                email={user.email}
                                role={user.role}
                                clients={
                                    user.clients
                                        .map((client) => client.clientName)
                                        .filter(Boolean) as string[]
                                }
                                onEdit={() => {
                                    setEditingUser(user);
                                    setUserModalOpen(true);
                                }}
                                onDelete={() => deleteUser(user)}
                            />
                        ))}
                    </div>
                ) : (
                    <UsersTable
                        users={filteredUsers}
                        onEdit={(user) => {
                            setEditingUser(user);
                            setUserModalOpen(true);
                        }}
                        onDelete={deleteUser}
                    />
                )}
            </main>
            <UserEditModal
                user={editingUser}
                open={userModalOpen}
                clients={clients}
                roles={roles}
                onOpenChange={(open) => {
                    setUserModalOpen(open);
                    if (!open) {
                        setEditingUser(null);
                    }
                }}
            />
        </AuthenticatedLayout>
    );
}

function UsersTable({
    users,
    onEdit,
    onDelete,
}: {
    users: UserRow[];
    onEdit: (user: UserRow) => void;
    onDelete: (user: UserRow) => void;
}) {
    return (
        <div className="w-full overflow-hidden rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Nom</TableHead>
                        <TableHead>Email</TableHead>
                        <TableHead>Rôle</TableHead>
                        <TableHead>Entreprise</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {users.map((user) => (
                        <TableRow key={user.id}>
                            <TableCell className="font-medium">
                                <div className="flex items-center gap-2">
                                    <UserRound
                                        size={16}
                                        className="text-muted-foreground"
                                    />
                                    {user.name}
                                </div>
                            </TableCell>
                            <TableCell>
                                <div className="flex items-center gap-2">
                                    <Mail
                                        size={16}
                                        className="text-muted-foreground"
                                    />
                                    {user.email}
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    variant="outline"
                                    className={roleDisplay(user.role).color}
                                >
                                    <Shield className="mr-1 h-3 w-3" />
                                    {roleDisplay(user.role).label}
                                </Badge>
                            </TableCell>
                            <TableCell>
                                <div className="flex flex-wrap gap-1">
                                    {user.clients.length > 0 ? (
                                        user.clients.map((client) => (
                                            <Badge
                                                key={`${user.id}-${client.clientId}`}
                                                variant="secondary"
                                                className="text-xs font-semibold uppercase"
                                            >
                                                <Building2 className="mr-1 h-3 w-3" />
                                                {client.clientName}
                                            </Badge>
                                        ))
                                    ) : (
                                        <span className="text-sm text-muted-foreground">
                                            Non spécifié
                                        </span>
                                    )}
                                </div>
                            </TableCell>
                            <TableCell className="text-right">
                                <div className="flex justify-end gap-2">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title="Modifier"
                                        onClick={() => onEdit(user)}
                                    >
                                        <Pencil size={16} />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title="Supprimer"
                                        className="text-destructive hover:text-destructive/90"
                                        onClick={() => onDelete(user)}
                                    >
                                        <Trash2 size={16} />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
