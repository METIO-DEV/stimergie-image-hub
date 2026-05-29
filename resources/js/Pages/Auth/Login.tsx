import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Connexion" />

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
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
                    <InputLabel
                        htmlFor="email"
                        value="Email"
                        className="font-semibold text-[#080506]"
                    />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        placeholder="exemple@email.com"
                        className="mt-3 block h-[46px] w-full rounded-lg border-[#dfe4e5] bg-white px-4 text-base text-[#1d2528] shadow-none placeholder:text-[#657078] focus:border-[#264b57] focus:ring-[#264b57]"
                        autoComplete="username"
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <InputLabel
                        htmlFor="password"
                        value="Mot de passe"
                        className="font-semibold text-[#080506]"
                    />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        placeholder="********"
                        className="mt-3 block h-[46px] w-full rounded-lg border-[#dfe4e5] bg-white px-4 text-base text-[#1d2528] shadow-none placeholder:text-[#657078] focus:border-[#264b57] focus:ring-[#264b57]"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="flex justify-end pt-1">
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-base font-semibold text-[#657078] underline-offset-4 hover:text-[#264b57] hover:underline focus:outline-none focus:ring-2 focus:ring-[#264b57] focus:ring-offset-2"
                        >
                            Mot de passe oublie ?
                        </Link>
                    )}
                </div>

                <PrimaryButton
                    className="mt-7 flex h-[46px] w-full justify-center rounded-lg bg-[#264b57] px-5 text-base font-semibold normal-case tracking-normal hover:bg-[#203f49] focus:bg-[#203f49] focus:ring-[#264b57] active:bg-[#1b3540]"
                    disabled={processing}
                >
                    Se connecter
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
