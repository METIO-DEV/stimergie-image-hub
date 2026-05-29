import Checkbox from '@/Components/Checkbox';
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

            <div className="mb-8">
                <img
                    src="/logo_stimergie_header.png"
                    alt="Stimergie"
                    className="hidden h-auto w-48 lg:block"
                />
                <h1 className="mt-7 text-2xl font-semibold text-[#223f49]">
                    Connexion
                </h1>
                <p className="mt-2 text-sm text-[#657078]">
                    Accedez a votre espace de gestion des visuels.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel htmlFor="email" value="Adresse email" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block h-11 w-full rounded-sm border-[#cfd5d6] bg-white text-[#1d2528] focus:border-[#244955] focus:ring-[#244955]"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Mot de passe" />

                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block h-11 w-full rounded-sm border-[#cfd5d6] bg-white text-[#1d2528] focus:border-[#244955] focus:ring-[#244955]"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData(
                                    'remember',
                                    (e.target.checked || false) as false,
                                )
                            }
                        />
                        <span className="ms-2 text-sm text-gray-600">
                            Se souvenir de moi
                        </span>
                    </label>
                </div>

                <div className="flex items-center justify-between gap-4 pt-1">
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="text-sm text-[#657078] underline-offset-4 hover:text-[#223f49] hover:underline focus:outline-none focus:ring-2 focus:ring-[#244955] focus:ring-offset-2"
                        >
                            Mot de passe oublie ?
                        </Link>
                    )}

                    <PrimaryButton
                        className="h-11 rounded-sm bg-[#244955] px-5 text-sm normal-case tracking-normal hover:bg-[#1f3d47] focus:bg-[#1f3d47] focus:ring-[#244955] active:bg-[#19323a]"
                        disabled={processing}
                    >
                        Se connecter
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
