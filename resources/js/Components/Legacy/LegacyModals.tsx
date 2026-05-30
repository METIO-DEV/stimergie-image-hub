import InputError from "@/Components/InputError";
import { Button } from "@/Components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/Components/ui/dialog";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Textarea } from "@/Components/ui/textarea";
import { LegacyImage } from "@/Components/Legacy/LegacyDesign";
import { useForm, usePage } from "@inertiajs/react";
import { ImagePlus, Mail, Upload } from "lucide-react";
import { FormEvent, useEffect, useState } from "react";

type ProjectForEdit = {
    id: number;
    name: string;
    type: string | null;
    clientName: string;
    sourceFolder: string | null;
};

type UserForEdit = {
    id: number;
    name: string;
    email: string;
    role: string;
    clients: Array<{ clientName: string | null }>;
};

export function ContactModal({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const user = usePage().props.auth.user;
    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        recentlySuccessful,
    } = useForm({
        subject: "",
        message: "",
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        post(route("contact.send"), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
            },
        });
    };

    useEffect(() => {
        if (recentlySuccessful) {
            const timeout = window.setTimeout(() => onOpenChange(false), 900);
            return () => window.clearTimeout(timeout);
        }
    }, [onOpenChange, recentlySuccessful]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="bg-[#c8bfac] sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Mail className="h-5 w-5" />
                        Formulaire de contact
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="contact-first-name">Prénom</Label>
                            <Input
                                id="contact-first-name"
                                className="mt-2"
                                value={user.name.split(" ")[0] || ""}
                                readOnly
                            />
                        </div>
                        <div>
                            <Label htmlFor="contact-last-name">Nom</Label>
                            <Input
                                id="contact-last-name"
                                className="mt-2"
                                value={
                                    user.name.split(" ").slice(1).join(" ") ||
                                    user.name
                                }
                                readOnly
                            />
                        </div>
                    </div>
                    <div>
                        <Label htmlFor="contact-email">Adresse e-mail</Label>
                        <Input
                            id="contact-email"
                            type="email"
                            className="mt-2"
                            value={user.email}
                            readOnly
                        />
                    </div>
                    <div>
                        <Label htmlFor="contact-subject">Objet</Label>
                        <Input
                            id="contact-subject"
                            className="mt-2"
                            value={data.subject}
                            placeholder="Sujet de votre message"
                            onChange={(event) =>
                                setData("subject", event.target.value)
                            }
                        />
                        <InputError message={errors.subject} className="mt-2" />
                    </div>
                    <div>
                        <Label htmlFor="contact-message">Message</Label>
                        <Textarea
                            id="contact-message"
                            className="mt-2 min-h-[120px]"
                            value={data.message}
                            placeholder="Votre message..."
                            onChange={(event) =>
                                setData("message", event.target.value)
                            }
                        />
                        <InputError message={errors.message} className="mt-2" />
                    </div>

                    <DialogFooter className="pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Annuler
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? "Envoi..." : "Envoyer"}
                        </Button>
                    </DialogFooter>
                    {recentlySuccessful && (
                        <p className="text-sm text-muted-foreground">
                            Message envoyé avec succès !
                        </p>
                    )}
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function ProjectEditModal({
    project,
    open,
    onOpenChange,
}: {
    project: ProjectForEdit | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {project ? "Modifier le projet" : "Ajouter un projet"}
                    </DialogTitle>
                </DialogHeader>
                <div className="mx-auto w-full max-w-2xl rounded-lg border bg-card p-6">
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label>Nom du projet</Label>
                            <Input defaultValue={project?.name || ""} />
                        </div>
                        <div className="space-y-2">
                            <Label>Client</Label>
                            <Input defaultValue={project?.clientName || ""} />
                        </div>
                        <div className="space-y-2">
                            <Label>Type de projet</Label>
                            <Input defaultValue={project?.type || ""} />
                        </div>
                        <div className="space-y-2">
                            <Label>Nom du dossier</Label>
                            <Input defaultValue={project?.sourceFolder || ""} />
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Annuler
                    </Button>
                    <Button onClick={() => onOpenChange(false)}>
                        Mettre à jour
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function ImageEditModal({
    image,
    open,
    onOpenChange,
}: {
    image: LegacyImage | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [preview, setPreview] = useState<string | null>(null);

    useEffect(() => {
        setPreview(image?.thumbUrl || image?.imageUrl || null);
    }, [image]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-xl overflow-hidden">
                <DialogHeader>
                    <DialogTitle>
                        {image
                            ? "Modifier l'image"
                            : "Ajouter une nouvelle image"}
                    </DialogTitle>
                    <DialogDescription>
                        {image
                            ? "Remplacez le visuel et ajustez ses informations."
                            : "Téléchargez une image et ajoutez les informations nécessaires."}
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-[calc(90vh-180px)] space-y-5 overflow-y-auto pr-2">
                    <div>
                        {preview ? (
                            <img
                                src={preview}
                                alt={image?.title || "Aperçu"}
                                className="max-h-[300px] w-full rounded-lg bg-muted/30 object-contain"
                            />
                        ) : (
                            <label className="flex cursor-pointer flex-col items-center rounded-lg border-2 border-dashed p-12 text-center transition-colors hover:bg-muted/50">
                                <ImagePlus className="h-10 w-10 text-muted-foreground" />
                                <span className="mt-2 text-sm text-muted-foreground">
                                    Cliquez ou glissez-déposez une image
                                </span>
                                <input type="file" className="hidden" />
                            </label>
                        )}
                        <Button variant="outline" className="mt-3 gap-2">
                            <Upload className="h-4 w-4" />
                            Changer l'image
                        </Button>
                    </div>

                    <div className="space-y-2">
                        <Label>Titre</Label>
                        <Input defaultValue={image?.title || ""} />
                    </div>
                    <div className="space-y-2">
                        <Label>Description</Label>
                        <Textarea defaultValue={image?.description || ""} />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Client</Label>
                            <Input defaultValue={image?.clientName || ""} />
                        </div>
                        <div className="space-y-2">
                            <Label>Projet</Label>
                            <Input defaultValue={image?.projectName || ""} />
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Orientation</Label>
                            <Input defaultValue={image?.orientation || ""} />
                        </div>
                        <div className="space-y-2">
                            <Label>Tags</Label>
                            <Input
                                defaultValue={image?.tags?.join(", ") || ""}
                            />
                        </div>
                    </div>
                </div>

                <DialogFooter className="border-t pt-4">
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Annuler
                    </Button>
                    <Button onClick={() => onOpenChange(false)}>
                        Enregistrer
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function UserEditModal({
    user,
    open,
    onOpenChange,
}: {
    user: UserForEdit | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {user
                            ? "Modifier l'utilisateur"
                            : "Ajouter un utilisateur"}
                    </DialogTitle>
                </DialogHeader>

                <div className="rounded-lg bg-white p-6 shadow-md">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Email</Label>
                            <Input defaultValue={user?.email || ""} />
                        </div>
                        <div className="space-y-2">
                            <Label>Rôle</Label>
                            <select
                                defaultValue={user?.role || "user"}
                                className="h-10 w-full rounded-md border border-input bg-background px-3 py-2"
                            >
                                <option value="user">Utilisateur</option>
                                <option value="admin_client">
                                    Admin Client
                                </option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                        <div className="space-y-2">
                            <Label>Prénom</Label>
                            <Input defaultValue={firstName(user?.name)} />
                        </div>
                        <div className="space-y-2">
                            <Label>Nom</Label>
                            <Input defaultValue={lastName(user?.name)} />
                        </div>
                        <div className="space-y-2 md:col-span-2">
                            <Label>Clients</Label>
                            <Input
                                defaultValue={
                                    user?.clients
                                        .map((client) => client.clientName)
                                        .filter(Boolean)
                                        .join(", ") || ""
                                }
                            />
                        </div>
                        <div className="space-y-2 md:col-span-2">
                            <Label>Mot de passe</Label>
                            <Input type="password" placeholder="Optionnel" />
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Annuler
                    </Button>
                    <Button onClick={() => onOpenChange(false)}>
                        Mettre à jour
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function firstName(name?: string) {
    return name?.split(" ")[0] || "";
}

function lastName(name?: string) {
    return name?.split(" ").slice(1).join(" ") || "";
}
