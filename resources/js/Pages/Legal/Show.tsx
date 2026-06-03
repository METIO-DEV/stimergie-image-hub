import InputError from "@/Components/InputError";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Textarea } from "@/Components/ui/textarea";
import { Head, Link, useForm } from "@inertiajs/react";
import { Pencil, Save, X } from "lucide-react";
import { FormEvent, useEffect, useState } from "react";

type LegalPage = {
    id: number;
    pageType: string;
    title: string;
    content: string;
    updatedAt: string;
};

export default function LegalShow({
    page,
    canEdit,
}: {
    page: LegalPage;
    canEdit: boolean;
}) {
    const [isEditing, setIsEditing] = useState(false);
    const { data, setData, patch, processing, errors, reset } = useForm({
        title: page.title,
        content: page.content,
    });

    useEffect(() => {
        setData({
            title: page.title,
            content: page.content,
        });
    }, [page.content, page.title, setData]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        patch(route("legal-pages.update", page.id), {
            preserveScroll: true,
            onSuccess: () => setIsEditing(false),
        });
    };

    const cancel = () => {
        reset();
        setData({
            title: page.title,
            content: page.content,
        });
        setIsEditing(false);
    };

    return (
        <div className="min-h-screen bg-background text-foreground">
            <Head title={page.title} />
            <header className="border-b border-border/80 bg-[#F2F0F0]">
                <div className="container flex h-16 items-center justify-between">
                    <Link href={route("gallery.index")}>
                        <img
                            src="/logo_stimergie_header.png"
                            alt="Stimergie"
                            className="h-8 w-auto"
                        />
                    </Link>
                    <Link
                        href={route("gallery.index")}
                        className="text-sm font-semibold text-primary"
                    >
                        Banque d'images
                    </Link>
                </div>
            </header>
            <main className="container py-14">
                <Card className="mx-auto max-w-4xl">
                    <CardHeader>
                        <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            {isEditing ? (
                                <div className="w-full space-y-2">
                                    <Label htmlFor="legal-title">
                                        Titre de la page
                                    </Label>
                                    <Input
                                        id="legal-title"
                                        value={data.title}
                                        onChange={(event) =>
                                            setData(
                                                "title",
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={errors.title} />
                                </div>
                            ) : (
                                <CardTitle className="text-3xl">
                                    {page.title}
                                </CardTitle>
                            )}

                            {canEdit && !isEditing && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="gap-2"
                                    onClick={() => setIsEditing(true)}
                                >
                                    <Pencil className="h-4 w-4" />
                                    Modifier
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {isEditing ? (
                            <form onSubmit={submit} className="space-y-5">
                                <div className="space-y-2">
                                    <Label htmlFor="legal-content">
                                        Contenu HTML
                                    </Label>
                                    <Textarea
                                        id="legal-content"
                                        value={data.content}
                                        onChange={(event) =>
                                            setData(
                                                "content",
                                                event.target.value,
                                            )
                                        }
                                        className="min-h-[420px] font-mono text-sm"
                                    />
                                    <InputError message={errors.content} />
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="gap-2"
                                        onClick={cancel}
                                        disabled={processing}
                                    >
                                        <X className="h-4 w-4" />
                                        Annuler
                                    </Button>
                                    <Button
                                        type="submit"
                                        className="gap-2"
                                        disabled={processing}
                                    >
                                        <Save className="h-4 w-4" />
                                        {processing
                                            ? "Enregistrement..."
                                            : "Enregistrer"}
                                    </Button>
                                </div>
                            </form>
                        ) : (
                            <>
                                <div
                                    className="legal-content max-w-none space-y-4 text-base leading-7 text-muted-foreground [&_a]:text-primary [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:pl-4 [&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-foreground [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-foreground [&_hr]:my-8 [&_li]:ml-5 [&_ol]:list-decimal [&_p]:my-3 [&_ul]:list-disc"
                                    dangerouslySetInnerHTML={{
                                        __html: page.content,
                                    }}
                                />
                                <div className="border-t pt-4 text-sm text-muted-foreground">
                                    Dernière mise à jour :{" "}
                                    {formatDate(page.updatedAt)}
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </main>
        </div>
    );
}

function formatDate(value: string) {
    return new Intl.DateTimeFormat("fr-FR", {
        day: "2-digit",
        month: "long",
        year: "numeric",
    }).format(new Date(value));
}
