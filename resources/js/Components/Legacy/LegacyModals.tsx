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
    clientId: number;
    clientName: string;
    sourceFolder: string | null;
    status: string;
};

type UserForEdit = {
    id: number;
    name: string;
    email: string;
    role: string;
    status: string;
    clientIds: number[];
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
    clients,
    onOpenChange,
}: {
    project: ProjectForEdit | null;
    open: boolean;
    clients: Array<{ id: number; name: string }>;
    onOpenChange: (open: boolean) => void;
}) {
    const {
        data,
        setData,
        post,
        patch,
        processing,
        errors,
        reset,
        recentlySuccessful,
    } = useForm({
        name: "",
        client_id: "",
        type: "",
        source_folder: "",
        status: "active",
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        setData({
            name: project?.name || "",
            client_id: project?.clientId ? String(project.clientId) : "",
            type: project?.type || "",
            source_folder: project?.sourceFolder || "",
            status: project?.status || "active",
        });
    }, [open, project, setData]);

    useEffect(() => {
        if (recentlySuccessful) {
            onOpenChange(false);
            reset();
        }
    }, [onOpenChange, recentlySuccessful, reset]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (project) {
            patch(route("projects.update", project.id), {
                preserveScroll: true,
            });
            return;
        }

        post(route("projects.store"), {
            preserveScroll: true,
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {project ? "Modifier le projet" : "Ajouter un projet"}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-6">
                <div className="mx-auto w-full max-w-2xl rounded-lg border bg-card p-6">
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="project-name">Nom du projet</Label>
                            <Input
                                id="project-name"
                                value={data.name}
                                onChange={(event) =>
                                    setData("name", event.target.value)
                                }
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="project-client">Entreprise</Label>
                            <select
                                id="project-client"
                                value={data.client_id}
                                onChange={(event) =>
                                    setData("client_id", event.target.value)
                                }
                                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Sélectionner une entreprise</option>
                                {clients.map((client) => (
                                    <option key={client.id} value={client.id}>
                                        {client.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.client_id} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="project-type">
                                Type de projet
                            </Label>
                            <Input
                                id="project-type"
                                value={data.type}
                                onChange={(event) =>
                                    setData("type", event.target.value)
                                }
                            />
                            <InputError message={errors.type} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="project-folder">
                                Nom du dossier
                            </Label>
                            <Input
                                id="project-folder"
                                value={data.source_folder}
                                onChange={(event) =>
                                    setData("source_folder", event.target.value)
                                }
                            />
                            <InputError message={errors.source_folder} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="project-status">Statut</Label>
                            <select
                                id="project-status"
                                value={data.status}
                                onChange={(event) =>
                                    setData("status", event.target.value)
                                }
                                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="active">Actif</option>
                                <option value="paused">En pause</option>
                                <option value="archived">Archive</option>
                            </select>
                            <InputError message={errors.status} />
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
                    <Button type="submit" disabled={processing}>
                        {processing
                            ? "Enregistrement..."
                            : project
                              ? "Mettre à jour"
                              : "Créer le projet"}
                    </Button>
                </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function ImageEditModal({
    image,
    open,
    projects,
    onOpenChange,
}: {
    image: LegacyImage | null;
    open: boolean;
    projects: Array<{ id: number; name: string; clientName?: string }>;
    onOpenChange: (open: boolean) => void;
}) {
    const [preview, setPreview] = useState<string | null>(null);
    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        recentlySuccessful,
    } = useForm({
        project_id: "",
        title: "",
        description: "",
        orientation: "",
        status: "ready",
        tags: "",
        file: null as File | null,
    });

    useEffect(() => {
        setPreview(image?.thumbUrl || image?.imageUrl || null);
        if (!open) {
            return;
        }

        setData({
            project_id: image?.projectId ? String(image.projectId) : "",
            title: image?.title || "",
            description: image?.description || "",
            orientation: image?.orientation || "",
            status: image?.status || "ready",
            tags: image?.tags?.join(", ") || "",
            file: null,
        });
    }, [image, open, setData]);

    useEffect(() => {
        if (recentlySuccessful) {
            onOpenChange(false);
            reset();
            setPreview(null);
        }
    }, [onOpenChange, recentlySuccessful, reset]);

    const selectedProject = projects.find(
        (project) => String(project.id) === data.project_id,
    );

    const handleFileChange = (file: File | null) => {
        setData("file", file);

        if (file) {
            setPreview(URL.createObjectURL(file));
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        post(image ? route("images.update", image.id) : route("images.store"), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

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

                <form onSubmit={submit}>
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
                                <input
                                    type="file"
                                    accept="image/*"
                                    className="hidden"
                                    onChange={(event) =>
                                        handleFileChange(
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            </label>
                        )}
                        <label className="mt-3 inline-flex h-10 cursor-pointer items-center justify-center gap-2 rounded-md border border-input bg-background px-4 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground">
                            <Upload className="h-4 w-4" />
                            Changer l'image
                            <input
                                type="file"
                                accept="image/*"
                                className="hidden"
                                onChange={(event) =>
                                    handleFileChange(
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </label>
                        <InputError message={errors.file} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="image-title">Titre</Label>
                        <Input
                            id="image-title"
                            value={data.title}
                            onChange={(event) =>
                                setData("title", event.target.value)
                            }
                        />
                        <InputError message={errors.title} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="image-description">Description</Label>
                        <Textarea
                            id="image-description"
                            value={data.description}
                            onChange={(event) =>
                                setData("description", event.target.value)
                            }
                        />
                        <InputError message={errors.description} />
                    </div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label>Entreprise</Label>
                            <Input
                                value={
                                    selectedProject?.clientName ||
                                    image?.clientName ||
                                    ""
                                }
                                disabled
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="image-project">Projet</Label>
                            <select
                                id="image-project"
                                value={data.project_id}
                                onChange={(event) =>
                                    setData("project_id", event.target.value)
                                }
                                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Selectionner un projet</option>
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
                            <Label htmlFor="image-orientation">
                                Orientation
                            </Label>
                            <select
                                id="image-orientation"
                                value={data.orientation}
                                onChange={(event) =>
                                    setData("orientation", event.target.value)
                                }
                                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Automatique</option>
                                <option value="landscape">Paysage</option>
                                <option value="portrait">Portrait</option>
                                <option value="square">Carré</option>
                            </select>
                            <InputError message={errors.orientation} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="image-tags">Tags</Label>
                            <Input
                                id="image-tags"
                                value={data.tags}
                                onChange={(event) =>
                                    setData("tags", event.target.value)
                                }
                            />
                            <InputError message={errors.tags} />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="image-status">Statut</Label>
                        <select
                            id="image-status"
                            value={data.status}
                            onChange={(event) =>
                                setData("status", event.target.value)
                            }
                            className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="ready">Disponible</option>
                            <option value="pending_upload">En attente</option>
                            <option value="archived">Archive</option>
                        </select>
                        <InputError message={errors.status} />
                        </div>
                </div>

                <DialogFooter className="border-t pt-4">
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Annuler
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? "Enregistrement..." : "Enregistrer"}
                    </Button>
                </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function UserEditModal({
    user,
    open,
    clients,
    roles,
    onOpenChange,
}: {
    user: UserForEdit | null;
    open: boolean;
    clients: Array<{ id: number; name: string }>;
    roles: Array<{ value: string; label: string }>;
    onOpenChange: (open: boolean) => void;
}) {
    const {
        data,
        setData,
        post,
        patch,
        processing,
        errors,
        reset,
        recentlySuccessful,
    } = useForm({
        first_name: "",
        last_name: "",
        email: "",
        role: "user",
        status: "active",
        password: "",
        client_ids: [] as number[],
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        setData({
            first_name: firstName(user?.name),
            last_name: lastName(user?.name),
            email: user?.email || "",
            role: user?.role || "user",
            status: user?.status || "active",
            password: "",
            client_ids: user?.clientIds || [],
        });
    }, [open, setData, user]);

    useEffect(() => {
        if (recentlySuccessful) {
            onOpenChange(false);
            reset();
        }
    }, [onOpenChange, recentlySuccessful, reset]);

    const toggleClient = (clientId: number) => {
        setData(
            "client_ids",
            data.client_ids.includes(clientId)
                ? data.client_ids.filter((id) => id !== clientId)
                : [...data.client_ids, clientId],
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (user) {
            patch(route("users.update", user.id), {
                preserveScroll: true,
            });
            return;
        }

        post(route("users.store"), {
            preserveScroll: true,
        });
    };

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

                <form onSubmit={submit} className="space-y-6">
                <div className="rounded-lg bg-white p-6 shadow-md">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="user-email">Email</Label>
                            <Input
                                id="user-email"
                                type="email"
                                value={data.email}
                                onChange={(event) =>
                                    setData("email", event.target.value)
                                }
                            />
                            <InputError message={errors.email} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="user-role">Rôle</Label>
                            <select
                                id="user-role"
                                value={data.role}
                                onChange={(event) =>
                                    setData("role", event.target.value)
                                }
                                className="h-10 w-full rounded-md border border-input bg-background px-3 py-2"
                            >
                                {roles.map((role) => (
                                    <option
                                        key={role.value}
                                        value={role.value}
                                    >
                                        {role.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.role} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="user-first-name">Prénom</Label>
                            <Input
                                id="user-first-name"
                                value={data.first_name}
                                onChange={(event) =>
                                    setData("first_name", event.target.value)
                                }
                            />
                            <InputError message={errors.first_name} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="user-last-name">Nom</Label>
                            <Input
                                id="user-last-name"
                                value={data.last_name}
                                onChange={(event) =>
                                    setData("last_name", event.target.value)
                                }
                            />
                            <InputError message={errors.last_name} />
                        </div>
                        <div className="space-y-2 md:col-span-2">
                            <Label>Entreprises</Label>
                            <div className="grid max-h-40 gap-2 overflow-y-auto rounded-md border p-3 md:grid-cols-2">
                                {clients.map((client) => (
                                    <label
                                        key={client.id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={data.client_ids.includes(
                                                client.id,
                                            )}
                                            onChange={() =>
                                                toggleClient(client.id)
                                            }
                                            className="h-4 w-4 rounded border-input"
                                        />
                                        <span>{client.name}</span>
                                    </label>
                                ))}
                            </div>
                            <InputError message={errors.client_ids} />
                        </div>
                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="user-status">Statut</Label>
                            <select
                                id="user-status"
                                value={data.status}
                                onChange={(event) =>
                                    setData("status", event.target.value)
                                }
                                className="h-10 w-full rounded-md border border-input bg-background px-3 py-2"
                            >
                                <option value="active">Actif</option>
                                <option value="paused">En pause</option>
                            </select>
                            <InputError message={errors.status} />
                        </div>
                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="user-password">
                                Mot de passe
                            </Label>
                            <Input
                                id="user-password"
                                type="password"
                                value={data.password}
                                onChange={(event) =>
                                    setData("password", event.target.value)
                                }
                                placeholder="Optionnel"
                            />
                            <InputError message={errors.password} />
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
                    <Button type="submit" disabled={processing}>
                        {processing
                            ? "Enregistrement..."
                            : user
                              ? "Mettre à jour"
                              : "Créer l'utilisateur"}
                    </Button>
                </DialogFooter>
                </form>
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
