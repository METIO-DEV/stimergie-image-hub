import { Link } from "@inertiajs/react";

type FooterItem = {
    label: string;
    href?: string;
};

export default function AppFooter() {
    return (
        <footer className="border-t border-border/80 bg-[#F2F0F0]">
            <div className="container py-14">
                <div className="grid gap-10 md:grid-cols-[1.4fr_1fr_1fr]">
                    <div>
                        <h2 className="text-xl font-bold">Stimergie</h2>
                        <p className="mt-8 max-w-sm text-base font-medium leading-7 text-muted-foreground">
                            Stimergie centralise, organise et valorise vos
                            contenus de marque. Un espace unique, simple et
                            souverain pour gagner du temps, retrouver vos
                            visuels facilement et les utiliser.
                        </p>
                    </div>
                    <FooterColumn
                        title="NAVIGATION"
                        items={[
                            { label: "Accueil", href: "/" },
                            {
                                label: "Banque d'images",
                                href: route("gallery.index"),
                            },
                            { label: "À propos", href: route("about") },
                            { label: "Contacter", href: route("contact.index") },
                        ]}
                    />
                    <FooterColumn
                        title="LÉGAL"
                        items={[
                            {
                                label: "Conditions d'utilisation",
                                href: route("terms"),
                            },
                            {
                                label: "Politique de confidentialité",
                                href: route("privacy"),
                            },
                            {
                                label: "Licences",
                                href: route("licenses"),
                            },
                        ]}
                    />
                </div>
                <div className="mt-14 border-t border-border/80 pt-8">
                    <p className="text-sm font-medium text-muted-foreground">
                        © 2026 Stimergie. Tous droits réservés.
                    </p>
                </div>
            </div>
        </footer>
    );
}

function FooterColumn({ title, items }: { title: string; items: FooterItem[] }) {
    return (
        <div>
            <h3 className="text-sm font-bold uppercase tracking-wide text-muted-foreground">
                {title}
            </h3>
            <div className="mt-6 space-y-4">
                {items.map((item) => (
                    <div
                        key={item.label}
                        className="text-base text-foreground/80"
                    >
                        {item.href ? (
                            <Link
                                href={item.href}
                                className="transition-colors hover:text-primary"
                            >
                                {item.label}
                            </Link>
                        ) : (
                            item.label
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
