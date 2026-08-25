import InputError from "@/Components/InputError";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import { TableCell, TableRow } from "@/Components/ui/table";
import { router, useForm } from "@inertiajs/react";
import { FormEventHandler, useEffect, useState } from "react";

export type Option = {
    value: string;
    label: string;
};

export type Membership = {
    id: number;
    role: string;
    status: string;
    isDefault: boolean;
    user: {
        id: number;
        name: string;
        email: string;
        status: string;
    };
};

type MemberFormData = {
    name: string;
    email: string;
    role: string;
    status: string;
    is_default: boolean;
};

type UserSuggestion = {
    id: number;
    name: string;
    email: string;
    platformRole: string;
    status: string;
};

export function AddMemberForm({
    clientId,
    roleOptions,
    statusOptions,
}: {
    clientId: number;
    roleOptions: Option[];
    statusOptions: Option[];
}) {
    const { data, setData, post, processing, errors, reset } =
        useForm<MemberFormData>({
            name: "",
            email: "",
            role: "member",
            status: "active",
            is_default: false,
        });
    const [search, setSearch] = useState("");
    const [suggestions, setSuggestions] = useState<UserSuggestion[]>([]);
    const [searching, setSearching] = useState(false);

    useEffect(() => {
        const query = search.trim();

        if (query.length < 2) {
            setSuggestions([]);
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            setSearching(true);
            fetch(route("users.search", { q: query }), {
                signal: controller.signal,
            })
                .then((response) => (response.ok ? response.json() : []))
                .then((users: UserSuggestion[]) => setSuggestions(users))
                .catch(() => setSuggestions([]))
                .finally(() => setSearching(false));
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [search]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route("clients.members.store", clientId), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setSearch("");
                setSuggestions([]);
            },
        });
    };

    const selectUser = (user: UserSuggestion) => {
        setSearch(user.name);
        setData("name", user.name);
        setData("email", user.email);
        setSuggestions([]);
    };

    return (
        <form onSubmit={submit} className="mt-6 grid gap-4 lg:grid-cols-6">
            <div className="relative lg:col-span-2">
                <Label htmlFor="member-search">Utilisateur</Label>
                <Input
                    id="member-search"
                    className="mt-2"
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value);
                        setData("name", "");
                        setData("email", "");
                    }}
                    placeholder="Tapez les premieres lettres du nom"
                    required
                />
                {suggestions.length > 0 && (
                    <div className="absolute z-20 mt-2 w-full overflow-hidden rounded-md border bg-background shadow-lg">
                        {suggestions.map((user) => (
                            <button
                                key={user.id}
                                type="button"
                                className="block w-full px-3 py-2 text-left text-sm hover:bg-muted"
                                onClick={() => selectUser(user)}
                            >
                                <span className="block font-medium">
                                    {user.name}
                                </span>
                                <span className="text-muted-foreground">
                                    {user.email}
                                </span>
                            </button>
                        ))}
                    </div>
                )}
                {searching && (
                    <p className="mt-2 text-xs text-muted-foreground">
                        Recherche...
                    </p>
                )}
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div className="lg:col-span-2">
                <Label htmlFor="member-email">Email</Label>
                <Input
                    id="member-email"
                    type="email"
                    className="mt-2"
                    value={data.email}
                    readOnly
                    required
                />
                <InputError message={errors.email} className="mt-2" />
            </div>

            <div>
                <Label htmlFor="member-role">Role</Label>
                <Select
                    value={data.role}
                    onValueChange={(value) => setData("role", value)}
                >
                    <SelectTrigger id="member-role" className="mt-2">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {roleOptions.map((role) => (
                            <SelectItem key={role.value} value={role.value}>
                                {role.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.role} className="mt-2" />
            </div>

            <div>
                <Label htmlFor="member-status">Statut</Label>
                <Select
                    value={data.status}
                    onValueChange={(value) => setData("status", value)}
                >
                    <SelectTrigger id="member-status" className="mt-2">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {statusOptions.map((status) => (
                            <SelectItem key={status.value} value={status.value}>
                                {status.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.status} className="mt-2" />
            </div>

            <div className="flex items-center gap-3 lg:col-span-6">
                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                    <input
                        type="checkbox"
                        className="rounded border-input text-primary shadow-sm focus:ring-primary"
                        checked={data.is_default}
                        onChange={(event) =>
                            setData("is_default", event.target.checked)
                        }
                    />
                    Entreprise par défaut pour cet utilisateur
                </label>

                <Button type="submit" disabled={processing}>
                    Ajouter le membre
                </Button>
            </div>
        </form>
    );
}

export function MemberRow({
    clientId,
    membership,
    roleOptions,
    statusOptions,
}: {
    clientId: number;
    membership: Membership;
    roleOptions: Option[];
    statusOptions: Option[];
}) {
    const { data, setData, patch, processing, errors } = useForm({
        role: membership.role,
        status: membership.status,
        is_default: membership.isDefault,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        patch(route("clients.members.update", [clientId, membership.id]), {
            preserveScroll: true,
        });
    };

    const removeMember = () => {
        if (!window.confirm("Retirer cet utilisateur de l'entreprise ?")) {
            return;
        }

        router.delete(
            route("clients.members.destroy", [clientId, membership.id]),
            { preserveScroll: true },
        );
    };

    return (
        <TableRow>
            <TableCell className="align-top">
                <div className="font-medium text-foreground">
                    {membership.user.name}
                </div>
                <div className="text-sm text-muted-foreground">
                    {membership.user.email}
                </div>
            </TableCell>
            <TableCell className="align-top">
                <form onSubmit={submit} className="grid gap-3 md:grid-cols-4">
                    <div>
                        <Select
                            value={data.role}
                            onValueChange={(value) => setData("role", value)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {roleOptions.map((role) => (
                                    <SelectItem
                                        key={role.value}
                                        value={role.value}
                                    >
                                        {role.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.role} className="mt-2" />
                    </div>

                    <div>
                        <Select
                            value={data.status}
                            onValueChange={(value) => setData("status", value)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {statusOptions.map((status) => (
                                    <SelectItem
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.status} className="mt-2" />
                    </div>

                    <label className="flex items-center gap-2 text-sm text-muted-foreground">
                        <input
                            type="checkbox"
                            className="rounded border-input text-primary shadow-sm focus:ring-primary"
                            checked={data.is_default}
                            onChange={(event) =>
                                setData("is_default", event.target.checked)
                            }
                        />
                        Défaut
                    </label>

                    <div className="flex gap-2">
                        <Button
                            type="submit"
                            variant="secondary"
                            disabled={processing}
                        >
                            Sauvegarder
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={removeMember}
                            className="text-destructive hover:text-destructive"
                        >
                            Retirer
                        </Button>
                    </div>
                </form>
            </TableCell>
        </TableRow>
    );
}
