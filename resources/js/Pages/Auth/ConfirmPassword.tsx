import InputError from "@/Components/InputError";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import GuestLayout from "@/Layouts/GuestLayout";
import { Head, useForm } from "@inertiajs/react";
import { FormEventHandler } from "react";

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: "",
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route("password.confirm"), {
            onFinish: () => reset("password"),
        });
    };

    return (
        <GuestLayout>
            <Head title="Confirmation" />

            <div className="mb-8 text-center">
                <h1 className="text-[28px] font-bold leading-tight text-[#080506]">
                    Confirmation
                </h1>
                <p className="mt-3 text-sm leading-6 text-[#657078]">
                    Confirmez votre mot de passe pour continuer.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-6">
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
                        onChange={(e) => setData("password", e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <Button
                    type="submit"
                    className="h-[46px] w-full rounded-lg bg-[#264b57] text-base font-semibold hover:bg-[#203f49] focus-visible:ring-[#264b57]"
                    disabled={processing}
                >
                    Confirmer
                </Button>
            </form>
        </GuestLayout>
    );
}
