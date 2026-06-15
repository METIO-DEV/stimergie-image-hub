import InputError from "@/Components/InputError";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, useForm, usePage } from "@inertiajs/react";
import { Mail } from "lucide-react";
import { FormEventHandler } from "react";

export default function ContactIndex() {
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

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        post(route("contact.send"), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Contact" />

            <main className="flex min-h-[calc(100vh-4rem)] items-start justify-center px-6 py-16">
                <div className="w-full max-w-[500px] rounded-lg border bg-[#c8bfac] p-6 shadow-xl">
                    <div className="mb-6 flex items-center gap-3">
                        <Mail className="h-5 w-5" />
                        <h1 className="text-xl font-bold">
                            Formulaire de contact
                        </h1>
                    </div>
                    <p className="mb-6 text-sm leading-6 text-foreground/80">
                        Envoyez une demande à l'équipe Stimergie depuis votre
                        compte connecté.
                    </p>

                    <div className="space-y-4">
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="min-w-0">
                                    <Label htmlFor="firstName">Prénom</Label>
                                    <Input
                                        id="firstName"
                                        className="mt-2"
                                        value={user.name.split(" ")[0] || ""}
                                        readOnly
                                    />
                                </div>
                                <div className="min-w-0">
                                    <Label htmlFor="lastName">Nom</Label>
                                    <Input
                                        id="lastName"
                                        className="mt-2"
                                        value={
                                            user.name
                                                .split(" ")
                                                .slice(1)
                                                .join(" ") || user.name
                                        }
                                        readOnly
                                    />
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="email">Adresse e-mail</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-2"
                                    value={user.email}
                                    readOnly
                                />
                            </div>

                            <div>
                                <Label htmlFor="subject">Objet</Label>
                                <Input
                                    id="subject"
                                    className="mt-2"
                                    value={data.subject}
                                    placeholder="Sujet de votre message"
                                    onChange={(event) =>
                                        setData("subject", event.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.subject}
                                    className="mt-2"
                                />
                            </div>

                            <div>
                                <Label htmlFor="message">Message</Label>
                                <textarea
                                    id="message"
                                    className="mt-2 min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                    value={data.message}
                                    placeholder="Votre message..."
                                    onChange={(event) =>
                                        setData("message", event.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.message}
                                    className="mt-2"
                                />
                            </div>

                            <div className="flex justify-end gap-2 pt-4">
                                <Button type="button" variant="outline">
                                    Annuler
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? "Envoi..." : "Envoyer"}
                                </Button>
                            </div>
                            {recentlySuccessful && (
                                <p className="text-sm text-muted-foreground">
                                    Message envoyé avec succès !
                                </p>
                            )}
                        </form>
                    </div>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
