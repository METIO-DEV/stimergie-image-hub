# Etat technique Laravel/Inertia

Date : 2026-06-26

## Objet

Ce document complete l'audit historique `docs/audit-technique.md`, qui decrit surtout l'ancien frontend React/Vite et l'ancien backend Supabase. Le depot courant est maintenant une application Laravel/Inertia avec PostgreSQL, queue database et stockage S3-compatible Scaleway.

## Etat valide

- Backend : Laravel, authentification web Laravel, FormRequests et policies.
- Frontend : React, Inertia, TypeScript, Vite et Tailwind.
- Stockage images : disque `scaleway` configure dans `config/filesystems.php`.
- Traitements asynchrones : queue Laravel `database`.
- Imports images : batch API + jobs `ProcessImageImportItem`.
- Telechargements groupes : jobs serveur `PrepareDownloadArchive`, avec archives stockees dans le bucket image.
- Analyse IA des tags : route Laravel serveur, jobs `AnalyzeImageTags`, OpenAI Responses API, variables `OPENAI_API_KEY` et `OPENAI_IMAGE_TAG_MODEL`.
- Emails transactionnels : templates Blade locaux dans `resources/views/emails/`, envoyes via le mailer Laravel. En local, utiliser Mailpit ; en dev/pre-prod/prod, utiliser le SMTP Brevo via `MAIL_HOST=smtp-relay.brevo.com`.
- Diagnostic email : `php artisan mail:diagnose-brevo` verifie le transport SMTP, les identifiants mail sans afficher les secrets et la presence des vues email locales. Ajouter `--send-to=email@example.com` pour envoyer un email de test via la configuration courante.
- Partage externe : albums publics temporaires `shared_albums`, invitations email via template Blade local.
- Partage interne : pivot `image_client_shares`, visible via `ProjectAccess` pour les clients destinataires pendant la periode active.
- Droits d'acces : `project_access_periods`, policy dediee, application dans la galerie, les projets et les controles de telechargement via `ProjectAccess`.
- Cessions de droits : champs de date sur les images, demandes d'extension, email transactionnel et page de suivi operationnel.
- Transfert d'assets : page super-admin `asset-transfers`, jobs sur la queue `sync`, script `scripts/o2switch-rclone-transfer.sh` et synchronisation DB depuis le bucket.
- Commandes de maintenance : nettoyage/retry des archives, reconciliation Scaleway, generation de variantes, migration de prefixes projet, logos clients, digest mensuel.
- Planification Laravel : nettoyage quotidien des archives expirees et digest mensuel des nouvelles images le 1er du mois.

## Surfaces applicatives courantes

- Pages authentifiees : galerie, projets, telechargements, contact, profil.
- Pages de gestion : images, imports, clients, membres client, utilisateurs, periodes d'acces, demandes d'extension de cession, suivi operationnel, transferts d'assets.
- Pages publiques ou semi-publiques : pages legales, ressources/blog publiees, albums partages par cle temporaire.
- Roles : `super_admin`, `admin_client` et utilisateur standard avec memberships client `owner`, `manager` ou `viewer`.
- Stockage objet : les images sous `photos/` sont traitees comme privees avec URLs temporaires ; les assets clients publics sous `clients/` gardent un cache long.

## Risques traites le 2026-06-04

- Les comptes non actifs sont refuses au login.
- Les abilities Inertia pour les periodes d'acces utilisent la policy backend.
- Les telechargements HD sont limites a 50 images par archive.
- Les archives dont la taille estimee depasse 1,5 Go sont refusees avant creation du job.
- L'analyse IA et le partage d'images ont ete portes cote Laravel, sans secret OpenAI ou Supabase expose au frontend.
- Les objets image peuvent etre servis par URL temporaire Laravel/S3 plutot que par URL publique durable.

## Commandes verifiees dans le depot

Installation/build local :

```sh
composer setup
composer dev
npm run build
```

Tests :

```sh
composer test
php artisan test tests/Feature/ScalabilitySmokeTest.php
```

Operations courantes :

```sh
php artisan downloads:cleanup-expired
php artisan downloads:retry-failed
php artisan mail:diagnose-brevo
php artisan images:send-monthly-digest --dry-run
```

Operations migration/assets a lancer d'abord en dry-run quand disponible :

```sh
php artisan legacy:import-dump --fresh
php artisan images:audit-storage --check-bucket
php artisan images:sync-project-bucket-assets --dry-run
php artisan images:generate-variants --dry-run
php artisan images:generate-project-thumbnails --dry-run
php artisan images:reconcile-scaleway-assets --dry-run
php artisan downloads:reconcile-legacy-urls --dry-run
php artisan clients:reconcile-logos --dry-run
```

## Points de vigilance restants

- La generation ZIP utilise encore `ZipArchive::addFromString()` avec lecture complete des objets source. Les limites ajoutees reduisent le risque, mais un streaming plus fin restera preferable si les lots HD reels sont volumineux.
- L'analyse IA utilise le niveau image `low` pour limiter cout et latence. Si les tags sont trop generiques en production, tester un niveau de detail plus eleve sur un petit echantillon.
- Les emails transactionnels reposent sur le mailer Laravel. En production et pre-prod, verifier les identifiants SMTP Brevo avec `php artisan mail:diagnose-brevo` avant activation.
- Les transferts o2switch et commandes de reconciliation manipulent des objets bucket et de la base ; conserver les dry-runs et isoler les commandes `--execute`/`--force`.
- Les recherches galerie utilisent encore des recherches SQL `LIKE`, a surveiller si les volumes augmentent fortement.
- Les documents historiques doivent rester consultables comme reference de migration, mais ne doivent plus etre utilises comme etat technique principal sans verification contre le code Laravel actuel.
