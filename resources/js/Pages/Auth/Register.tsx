import InputError from "@/Components/InputError";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, Link, useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route("register"), {
            onFinish: () => reset("password", "password_confirmation"),
        });
    };

    return (
        <GuestLayout>
            <Head title="Inscription" />

            <div className="mb-8 text-center">
                <h1 className="text-[28px] font-bold leading-tight text-[#080506]">
                    Inscription
                </h1>
            </div>

            <form onSubmit={submit} className="space-y-6">
                <div>
                    <Label
                        htmlFor="name"
                        className="font-semibold text-[#080506]"
                    >
                        Nom
                    </Label>

                    <Input
                        id="name"
                        name="name"
                        value={data.name}
                        className="mt-3 h-[46px] rounded-lg border-[#dfe4e5] bg-white px-4 text-base text-[#1d2528] shadow-none focus-visible:ring-[#264b57]"
                        autoComplete="name"
                        onChange={(e) => setData("name", e.target.value)}
                        required
                    />

                    <InputError message={errors.name} className="mt-2" />
                </div>

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
                        required
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
                        required
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
                        required
                    />

                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

                <div className="flex items-center justify-between gap-4">
                    <Link
                        href={route("login")}
                        className="text-sm font-semibold text-[#657078] underline-offset-4 hover:text-[#264b57] hover:underline focus:outline-none focus:ring-2 focus:ring-[#264b57] focus:ring-offset-2"
                    >
                        Deja inscrit ?
                    </Link>

                    <Button
                        type="submit"
                        className="rounded-lg bg-[#264b57] text-base font-semibold hover:bg-[#203f49] focus-visible:ring-[#264b57]"
                        disabled={processing}
                    >
                        Créer le compte
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
