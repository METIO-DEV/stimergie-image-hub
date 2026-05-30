import InputError from "@/Components/InputError";
import Modal from "@/Components/Modal";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { useForm } from "@inertiajs/react";
import { FormEventHandler, useRef, useState } from "react";

export default function DeleteUserForm({
    className = "",
}: {
    className?: string;
}) {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: "",
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser: FormEventHandler = (e) => {
        e.preventDefault();

        destroy(route("profile.destroy"), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        clearErrors();
        reset();
    };

    return (
        <section className={`space-y-6 ${className}`}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Suppression du compte
                </h2>

                <p className="mt-1 text-sm text-muted-foreground">
                    Cette action supprimera definitivement les donnees liees au
                    compte.
                </p>
            </header>

            <Button variant="destructive" onClick={confirmUserDeletion}>
                Supprimer le compte
            </Button>

            <Modal show={confirmingUserDeletion} onClose={closeModal}>
                <form onSubmit={deleteUser} className="p-6">
                    <h2 className="text-lg font-medium text-gray-900">
                        Confirmer la suppression du compte
                    </h2>

                    <p className="mt-1 text-sm text-gray-600">
                        Saisissez votre mot de passe pour confirmer cette action
                        definitive.
                    </p>

                    <div className="mt-6">
                        <Label htmlFor="password" className="sr-only">
                            Mot de passe
                        </Label>

                        <Input
                            id="password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) =>
                                setData("password", e.target.value)
                            }
                            className="mt-1 w-3/4"
                            placeholder="Mot de passe"
                        />

                        <InputError
                            message={errors.password}
                            className="mt-2"
                        />
                    </div>

                    <div className="mt-6 flex justify-end">
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={closeModal}
                        >
                            Annuler
                        </Button>

                        <Button
                            type="submit"
                            variant="destructive"
                            className="ms-3"
                            disabled={processing}
                        >
                            Supprimer
                        </Button>
                    </div>
                </form>
            </Modal>
        </section>
    );
}
