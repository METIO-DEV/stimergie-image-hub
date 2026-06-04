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
- Emails transactionnels : Brevo API `/v3/smtp/email` avec templates configures par `BREVO_TEMPLATE_REGISTRATION`, `BREVO_TEMPLATE_SHARED_ALBUM_INVITATION` et `BREVO_TEMPLATE_MONTHLY_IMAGE_DIGEST`.
- Partage externe : albums publics temporaires `shared_albums`, invitations email via template Brevo.
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
- Les emails transactionnels reposent sur Brevo. Si `BREVO_API_KEY` ou le template ID cible manque, l'envoi est ignore et journalise. En production, configurer les trois templates transactionnels avant activation.
- Les documents historiques doivent rester consultables comme reference de migration, mais ne doivent plus etre utilises comme etat technique principal sans verification contre le code Laravel actuel.
