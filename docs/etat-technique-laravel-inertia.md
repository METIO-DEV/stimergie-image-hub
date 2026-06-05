# Etat technique Laravel/Inertia

Date : 2026-06-04

## Objet

Ce document complete l'audit historique `docs/audit-technique.md`, qui decrit surtout l'ancien frontend React/Vite et l'ancien backend Supabase. Le depot courant est maintenant une application Laravel/Inertia avec PostgreSQL, queue database et stockage S3-compatible Scaleway.

## Etat valide

- Backend : Laravel, authentification web Laravel, FormRequests et policies.
- Frontend : React, Inertia, TypeScript, Vite et Tailwind.
- Stockage images : disque `scaleway` configure dans `config/filesystems.php`.
- Traitements asynchrones : queue Laravel `database`.
- Imports images : batch API + jobs `ProcessImageImportItem`.
- Telechargements groupes : jobs serveur `PrepareDownloadArchive`, avec archives stockees dans le bucket image.
- Analyse IA des tags : route Laravel serveur, OpenAI Responses API, variables `OPENAI_API_KEY` et `OPENAI_IMAGE_TAG_MODEL`.
- Emails transactionnels : templates Blade locaux dans `resources/views/emails/`, envoyes via le mailer Laravel. En local, utiliser Mailpit ; en dev/pre-prod/prod, utiliser le SMTP Brevo via `MAIL_HOST=smtp-relay.brevo.com`.
- Diagnostic email : `php artisan mail:diagnose-brevo` verifie le transport SMTP, les identifiants mail sans afficher les secrets et la presence des vues email locales. Ajouter `--send-to=email@example.com` pour envoyer un email de test via la configuration courante.
- Partage externe : albums publics temporaires `shared_albums`, invitations email via template Blade local.
- Partage interne : pivot `image_client_shares`, visible via `ProjectAccess` pour les clients destinataires pendant la periode active.

## Risques traites le 2026-06-04

- Les comptes non actifs sont refuses au login.
- Les abilities Inertia pour les periodes d'acces utilisent la policy backend.
- Les telechargements HD sont limites a 50 images par archive.
- Les archives dont la taille estimee depasse 1,5 Go sont refusees avant creation du job.
- L'analyse IA et le partage d'images ont ete portes cote Laravel, sans secret OpenAI ou Supabase expose au frontend.

## Points de vigilance restants

- La generation ZIP utilise encore `ZipArchive::addFromString()` avec lecture complete des objets source. Les limites ajoutees reduisent le risque, mais un streaming plus fin restera preferable si les lots HD reels sont volumineux.
- L'analyse IA utilise le niveau image `low` pour limiter cout et latence. Si les tags sont trop generiques en production, tester un niveau de detail plus eleve sur un petit echantillon.
- Les emails transactionnels reposent sur le mailer Laravel. En production et pre-prod, verifier les identifiants SMTP Brevo avec `php artisan mail:diagnose-brevo` avant activation.
- Les documents historiques doivent rester consultables comme reference de migration, mais ne doivent plus etre utilises comme etat technique principal sans verification contre le code Laravel actuel.
