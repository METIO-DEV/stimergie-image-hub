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
import { Link } from "@inertiajs/react";
import { FormEventHandler } from "react";

export type ClientFormData = {
    name: string;
    slug: string;
    status: string;
    logo: File | null;
};

type StatusOption = {
    value: string;
    label: string;
};

type Props = {
    data: ClientFormData;
    errors: Partial<Record<keyof ClientFormData, string>>;
    processing: boolean;
    statuses: StatusOption[];
    submitLabel: string;
    currentLogo?: string | null;
    onSubmit: FormEventHandler;
    setData: <K extends keyof ClientFormData>(
        key: K,
        value: ClientFormData[K],
    ) => void;
};

export default function ClientForm({
    data,
    errors,
    processing,
    statuses,
    submitLabel,
    currentLogo,
    onSubmit,
    setData,
}: Props) {
    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <div>
                <Label htmlFor="name">Nom de l'entreprise</Label>
                <Input
                    id="name"
                    className="mt-2"
                    value={data.name}
                    onChange={(event) => setData("name", event.target.value)}
                    required
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <Label htmlFor="slug">Identifiant URL</Label>
                <Input
                    id="slug"
                    className="mt-2"
                    value={data.slug}
                    onChange={(event) => setData("slug", event.target.value)}
                    placeholder="genere depuis le nom si vide"
                />
                <InputError message={errors.slug} className="mt-2" />
            </div>

            <div>
                <Label htmlFor="status">Statut</Label>
                <Select
                    value={data.status}
                    onValueChange={(value) => setData("status", value)}
                    required
                >
                    <SelectTrigger id="status" className="mt-2">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {statuses.map((status) => (
                            <SelectItem key={status.value} value={status.value}>
                                {status.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.status} className="mt-2" />
            </div>

            <div>
                <Label htmlFor="logo">Logo</Label>
                {currentLogo && (
                    <div className="mt-2 flex h-24 w-24 items-center justify-center overflow-hidden rounded-md border bg-card">
                        <img
                            src={currentLogo}
                            alt="Logo actuel"
                            className="h-full w-full object-contain"
                        />
                    </div>
                )}
                <Input
                    id="logo"
                    type="file"
                    accept="image/*"
                    className="mt-2"
                    onChange={(event) =>
                        setData("logo", event.target.files?.[0] ?? null)
                    }
                />
                <InputError message={errors.logo} className="mt-2" />
            </div>

            <div className="flex items-center gap-3">
                <Button type="submit" disabled={processing}>
                    {submitLabel}
                </Button>
                <Button type="button" variant="ghost" asChild>
                    <Link href={route("clients.index")}>Annuler</Link>
                </Button>
            </div>
        </form>
    );
}
