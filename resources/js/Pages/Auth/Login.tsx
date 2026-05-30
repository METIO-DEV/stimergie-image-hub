import InputError from "@/Components/InputError";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, Link, useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        password: "",
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route("login"), {
            onFinish: () => reset("password"),
        });
    };

    return (
        <GuestLayout>
            <Head title="Connexion" />

            {status && (
                <div className="mb-5 rounded-md border border-primary/15 bg-primary/5 px-4 py-3 text-sm font-medium text-primary">
                    {status}
                </div>
            )}

            <div className="mb-9 text-center">
                <h1 className="text-[28px] font-bold leading-tight tracking-normal text-[#080506]">
                    Connexion
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
                        placeholder="exemple@email.com"
                        className="mt-3 h-[46px] rounded-lg border-[#dfe4e5] bg-white px-4 text-base text-[#1d2528] shadow-none placeholder:text-[#657078] focus-visible:ring-[#264b57]"
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
                        placeholder="********"
                        className="mt-3 h-[46px] rounded-lg border-[#dfe4e5] bg-white px-4 text-base text-[#1d2528] shadow-none placeholder:text-[#657078] focus-visible:ring-[#264b57]"
                        autoComplete="current-password"
                        onChange={(e) => setData("password", e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="flex justify-end pt-1">
                    {canResetPassword && (
                        <Link
                            href={route("password.request")}
                            className="text-base font-semibold text-[#657078] underline-offset-4 hover:text-[#264b57] hover:underline focus:outline-none focus:ring-2 focus:ring-[#264b57] focus:ring-offset-2"
                        >
                            Mot de passe oublie ?
                        </Link>
                    )}
                </div>

                <Button
                    type="submit"
                    className="mt-7 h-[46px] w-full rounded-lg bg-[#264b57] px-5 text-base font-semibold hover:bg-[#203f49] focus-visible:ring-[#264b57] active:bg-[#1b3540]"
                    disabled={processing}
                >
                    Se connecter
                </Button>
            </form>
        </GuestLayout>
    );
}
