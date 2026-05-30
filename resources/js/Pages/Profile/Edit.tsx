import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { PageProps } from "@/types";
import { Head, usePage } from "@inertiajs/react";
import { Shield, User } from "lucide-react";
import { useState } from "react";
import DeleteUserForm from "./Partials/DeleteUserForm";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm";
import UpdateProfileInformationForm from "./Partials/UpdateProfileInformationForm";

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    const user = usePage().props.auth.user;
    const [tab, setTab] = useState<"info" | "security">("info");

    return (
        <AuthenticatedLayout>
            <Head title="Mon Profil" />

            <div className="container py-10">
                <div className="mx-auto max-w-4xl">
                    <h1 className="text-4xl font-bold tracking-normal">
                        Mon Profil
                    </h1>

                    <div className="mt-8 grid grid-cols-2 rounded-lg bg-muted p-1">
                        <Button
                            type="button"
                            variant={tab === "info" ? "secondary" : "ghost"}
                            className="justify-center"
                            onClick={() => setTab("info")}
                        >
                            <User className="h-4 w-4" />
                            Informations personnelles
                        </Button>
                        <Button
                            type="button"
                            variant={tab === "security" ? "secondary" : "ghost"}
                            className="justify-center"
                            onClick={() => setTab("security")}
                        >
                            <Shield className="h-4 w-4" />
                            Sécurité
                        </Button>
                    </div>

                    {tab === "info" ? (
                        <Card className="mt-6 rounded-xl border-border/80 shadow-sm">
                            <CardHeader>
                                <CardTitle>Informations personnelles</CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    Gérez vos informations de profil.
                                </p>
                            </CardHeader>
                            <CardContent>
                                <UpdateProfileInformationForm
                                    mustVerifyEmail={mustVerifyEmail}
                                    status={status}
                                    className="max-w-xl"
                                />
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="mt-6 space-y-6">
                            <Card className="rounded-xl border-border/80 shadow-sm">
                                <CardHeader>
                                    <CardTitle>Sécurité</CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        Gérez votre mot de passe et vos
                                        paramètres de sécurité.
                                    </p>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    <div className="rounded-lg bg-muted p-4">
                                        <h3 className="font-medium">Rôle</h3>
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            {user.platform_role ===
                                            "super_admin"
                                                ? "Administrateur"
                                                : "Utilisateur"}
                                        </p>
                                    </div>
                                    <UpdatePasswordForm className="max-w-xl" />
                                </CardContent>
                            </Card>

                            <Card className="rounded-xl border-border/80 shadow-sm">
                                <CardContent className="p-6">
                                    <DeleteUserForm className="max-w-xl" />
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
