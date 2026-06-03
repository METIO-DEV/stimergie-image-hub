<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LegalPageController extends Controller
{
    public function terms(): Response
    {
        return $this->render(
            "Conditions d'utilisation",
            [
                'Stimergie Image Hub est réservé aux utilisateurs autorisés par Stimergie ou ses clients.',
                'Les visuels consultés, partagés ou téléchargés doivent être utilisés dans le respect des droits associés à chaque projet et à chaque image.',
                "Les comptes utilisateurs sont personnels. Toute demande d'accès, de correction ou de retrait doit être transmise à Stimergie.",
            ],
        );
    }

    public function privacy(): Response
    {
        return $this->render(
            'Politique de confidentialité',
            [
                "Les données de compte et d'activité sont utilisées pour gérer les accès, sécuriser la plateforme et tracer les opérations sensibles.",
                "Les informations liées aux clients, projets, téléchargements et partages sont conservées uniquement pour les besoins opérationnels de la banque d'images.",
                'Pour toute demande relative aux données personnelles, contactez Stimergie via la page de contact.',
            ],
        );
    }

    public function legalNotice(): Response
    {
        return $this->render(
            'Mentions légales',
            [
                'Stimergie édite et exploite cette plateforme de gestion de ressources visuelles.',
                "Les contenus, marques, logos et visuels disponibles sur l'application restent soumis aux droits de leurs titulaires respectifs.",
                'Les informations légales détaillées peuvent être complétées par Stimergie selon les mentions administratives définitives.',
            ],
        );
    }

    private function render(string $title, array $paragraphs): Response
    {
        return Inertia::render('Legal/Show', [
            'title' => $title,
            'paragraphs' => $paragraphs,
        ]);
    }
}
