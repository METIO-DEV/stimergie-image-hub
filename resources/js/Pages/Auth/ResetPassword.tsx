import InputError from "@/Components/InputError";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";

export default function ResetPassword({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token: token,
        email: email,
        password: "",
        password_confirmation: "",
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route("password.store"), {
            onFinish: () => reset("password", "password_confirmation"),
        });
    };

    return (
        <GuestLayout>
            <Head title="Reinitialiser le mot de passe" />

            <div className="mb-8 text-center">
                <h1 className="text-[28px] font-bold leading-tight text-[#080506]">
                    Nouveau mot de passe
                </h1>
            </div>

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
                        className="mt-3 h-[46px] rounded-lg border-[#dfe4e5] bg-white px-4 text-base text-[#1d2528] shadow-none focus-visible:ring-[#264b57]"
                        autoComplete="username"
                        onChange={(e) => setData("email", e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <Label
                        htmlFor="password"
                        className="font-semibold text-[#080506]"
                    >
                        Mot de passe
                    </Label>

                    <Input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-3 h-[46px] rounded-lg border-[#dfe4e5] bg-white px-4 text-base text-[#1d2528] shadow-none focus-visible:ring-[#264b57]"
                        autoComplete="new-password"
                        onChange={(e) => setData("password", e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <Label
                        htmlFor="password_confirmation"
                        className="font-semibold text-[#080506]"
                    >
                        Confirmation
                    </Label>

                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-3 h-[46px] rounded-lg border-[#dfe4e5] bg-white px-4 text-base text-[#1d2528] shadow-none focus-visible:ring-[#264b57]"
                        autoComplete="new-password"
                        onChange={(e) =>
                            setData("password_confirmation", e.target.value)
                        }
                    />

                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

                <Button
                    type="submit"
                    className="h-[46px] w-full rounded-lg bg-[#264b57] text-base font-semibold hover:bg-[#203f49] focus-visible:ring-[#264b57]"
                    disabled={processing}
                >
                    Reinitialiser
                </Button>
            </form>
        </GuestLayout>
    );
}
