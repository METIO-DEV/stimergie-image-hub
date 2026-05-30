import InputError from "@/Components/InputError";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: "",
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route("password.email"));
    };

    return (
        <GuestLayout>
            <Head title="Mot de passe oublie" />

            <div className="mb-8 text-center">
                <h1 className="text-[28px] font-bold leading-tight text-[#080506]">
                    Mot de passe oublie
                </h1>
                <p className="mt-3 text-sm leading-6 text-[#657078]">
                    Indiquez votre email pour recevoir un lien de
                    reinitialisation.
                </p>
            </div>

            {status && (
                <div className="mb-5 rounded-md border border-primary/15 bg-primary/5 px-4 py-3 text-sm font-medium text-primary">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-6">
                <div>
                    <Label
                        htmlFor="email"
                        className="font-semibold text-[#080506]"
                    >
                        Email
                    </Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-3 h-[46px] rounded-lg border-[#dfe4e5] bg-white px-4 text-base text-[#1d2528] shadow-none placeholder:text-[#657078] focus-visible:ring-[#264b57]"
                        placeholder="exemple@email.com"
                        onChange={(e) => setData("email", e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <Button
                    type="submit"
                    className="h-[46px] w-full rounded-lg bg-[#264b57] text-base font-semibold hover:bg-[#203f49] focus-visible:ring-[#264b57]"
                    disabled={processing}
                >
                    Envoyer le lien
                </Button>
            </form>
        </GuestLayout>
    );
}
