import { Button } from "@/Components/ui/button";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, Link, useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route("verification.send"));
    };

    return (
        <GuestLayout>
            <Head title="Vérification email" />

            <div className="mb-8 text-center">
                <h1 className="text-[28px] font-bold leading-tight text-[#080506]">
                    Vérification email
                </h1>
                <p className="mt-3 text-sm leading-6 text-[#657078]">
                    Validez votre adresse email avec le lien que nous venons de
                    vous envoyer.
                </p>
            </div>

            {status === "verification-link-sent" && (
                <div className="mb-5 rounded-md border border-primary/15 bg-primary/5 px-4 py-3 text-sm font-medium text-primary">
                    Un nouveau lien de verification a ete envoye.
                </div>
            )}

            <form onSubmit={submit}>
                <div className="flex items-center justify-between gap-4">
                    <Button
                        type="submit"
                        className="rounded-lg bg-[#264b57] font-semibold hover:bg-[#203f49] focus-visible:ring-[#264b57]"
                        disabled={processing}
                    >
                        Renvoyer l'email
                    </Button>

                    <Link
                        href={route("logout")}
                        method="post"
                        as="button"
                        className="text-sm font-semibold text-[#657078] underline-offset-4 hover:text-[#264b57] hover:underline focus:outline-none focus:ring-2 focus:ring-[#264b57] focus:ring-offset-2"
                    >
                        Deconnexion
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
