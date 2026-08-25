# Stimergie Image Hub

Application Laravel + React/Inertia pour la gestion metier Stimergie Image Hub : phototheque client, projets, droits d'acces, cessions de droits, imports, telechargements ZIP et traitements de migration des assets.

## Stack

- Backend : Laravel 13, PHP 8.3
- Frontend : React + Inertia, TypeScript, Vite, Tailwind
- Base de donnees : PostgreSQL
- Queue : driver `database`
- Stockage images : disque S3-compatible `scaleway`
- Email : Mailpit en local, SMTP Brevo attendu en dev/pre-prod/prod

## Perimetre applicatif

- Galerie et gestion des images par client/projet, avec recherche, tags, variantes web/HD et telechargement.
- Gestion clients, projets, membres, utilisateurs et periodes d'acces.
- Cessions de droits image, demandes d'extension et suivi operationnel.
- Imports par lots, rapprochement des objets Scaleway et transfert o2switch vers le bucket.
- Albums partages publics temporaires et partage d'images entre clients.
- Blog/ressources, pages legales, contact et emails transactionnels.

## Monitoring

La liveness existante est conservee : `GET /up` est la route Laravel native et
`/healthz.txt` reste le healthcheck Docker statique. Elles sont publiques et ne
realisent pas de controle de dependance.

Les endpoints operationnels sont declares hors du groupe middleware `web` et
necessitent le header `X-Monitoring-Token` egal a `MONITORING_OPS_TOKEN` :

| Route | Reponse | Usage |
| --- | --- | --- |
| `GET /ready` | `200` lorsque PostgreSQL repond, `503` sinon | Readiness de l'application |
| `GET /ops` | Toujours `200` lorsqu'un snapshot peut etre produit | Snapshot de supervision protege |

Les deux routes repondent avec `Cache-Control: no-store`. Le token est envoye
uniquement dans le header, jamais dans une URL. Une valeur vide ou absente
refuse l'acces. Configurer ce token unique dans Dokploy ; ne pas le committer.

`/ops` expose uniquement des signaux techniques : version de schema, nom du
service, horodatage UTC, latence et etat PostgreSQL, attente par queue
`database` (`default` et `sync` par defaut), age du job executable le plus
ancien et nombre de jobs definitivement echoues sur les cinq dernieres minutes.
Definir `MONITORING_RELEASE` pour ajouter la version de build et
`MONITORING_QUEUE_NAMES` si les workers changent de queues.

L'application ne conserve pas de heartbeat worker, de compteur d'erreurs
applicatives general ni de sonde Scaleway bon marche. Ces champs sont donc
deliberement absents du snapshot plutot que simules.

Exemple de controle local, sans placer le token dans l'historique shell :

```sh
curl -H 'X-Monitoring-Token: <token>' https://stimergie.metio-dev.fr/ops
```

JSONPath Zabbix utiles quand les champs sont presents :

- `$.status`
- `$.checks.database.latency_ms`
- `$.checks.queues.queues[?(@.name=="default")].pending.first()`
- `$.checks.queues.queues[?(@.name=="sync")].oldest_job_age_s.first()`
- `$.checks.failed_jobs.count_last_5m`

## Lancer en Docker

Preparer l'environnement :

```sh
cp .env.example .env
```

Renseigner les variables Scaleway dans `.env`, puis lancer :

```sh
docker compose up --build
```

Services exposes :

- App Laravel : http://localhost:8100
- Vite : http://localhost:5174
- PostgreSQL : localhost:55432
- Mailpit : http://localhost:8025

Le compose lance aussi deux workers :

```sh
php artisan queue:work --sleep=1 --tries=3 --timeout=900
php artisan queue:work --queue=sync --sleep=1 --tries=1 --timeout=0
```

## Commandes utiles

Installer et construire hors Docker :

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

Maintenance et diagnostics :

```sh
php artisan downloads:cleanup-expired
php artisan downloads:retry-failed
php artisan mail:diagnose-brevo
php artisan images:send-monthly-digest --dry-run
```

Commandes d'assets et migration a manier avec prudence :

```sh
php artisan legacy:import-dump --fresh
php artisan images:sync-project-bucket-assets --dry-run
php artisan images:generate-variants --dry-run
php artisan images:reconcile-scaleway-assets --dry-run
php artisan clients:reconcile-logos --dry-run
```

Les commandes avec `--execute`, `--force`, suppression d'objets ou import `--fresh` peuvent modifier fortement la base ou le bucket. Toujours lancer le mode dry-run quand il existe.

## Variables d'environnement principales

Voir `.env.example` pour les valeurs locales. Les noms importants sont :

- Application : `APP_KEY`, `APP_URL`, `APP_ENV`, `APP_DEBUG`
- Ports Docker locaux : `APP_HOST_PORT`, `VITE_HOST_PORT`, `POSTGRES_HOST_PORT`, `MAILPIT_UI_HOST_PORT`
- Base : `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Queue/session/cache : `QUEUE_CONNECTION`, `DB_QUEUE_RETRY_AFTER`, `SESSION_DRIVER`, `CACHE_STORE`
- Stockage : `IMAGE_STORAGE_DISK`, `SCALEWAY_ACCESS_KEY_ID`, `SCALEWAY_SECRET_KEY`, `SCALEWAY_OBJECT_STORAGE_BUCKET`, `SCALEWAY_OBJECT_STORAGE_REGION`, `SCALEWAY_OBJECT_STORAGE_ENDPOINT`
- Email : `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`
- IA : `OPENAI_API_KEY`, `OPENAI_IMAGE_TAG_MODEL`

## Deploiement Dokploy

Utiliser `docker-compose.dokploy.yml` comme Compose Path.

La configuration prod construit une seule image applicative :

- `composer install --no-dev` est execute pendant le build Docker.
- `npm ci && npm run build` est execute pendant le build Docker.
- `app` sert Laravel via Apache sur le port interne `80`.
- `queue` reutilise la meme image pour `php artisan queue:work` avec un timeout adapte aux archives ZIP.
- `queue-sync` traite la queue `sync` pour les transferts et generations de variantes.
- `pgsql` persiste ses donnees dans un volume Docker nomme.

Variables minimales a renseigner dans l'environnement Dokploy :

```env
APP_KEY=base64:...
APP_URL=https://votre-domaine.tld
DB_PASSWORD=mot-de-passe-solide
POSTGRES_DB=stimergie
POSTGRES_USER=stimergie
DB_QUEUE_RETRY_AFTER=1200
SCALEWAY_ACCESS_KEY_ID=...
SCALEWAY_SECRET_KEY=...
SCALEWAY_OBJECT_STORAGE_BUCKET=...
SCALEWAY_OBJECT_STORAGE_REGION=fr-par
SCALEWAY_OBJECT_STORAGE_ENDPOINT=https://s3.fr-par.scw.cloud
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=...
```

Dans l'onglet Domains de Dokploy, pointer le domaine vers le service `app` et le port `80`. Dokploy injecte les variables de son UI dans un fichier `.env`; le compose les charge avec `env_file`.

`APP_URL` doit imperativement utiliser `https://` en production. Si elle reste en `http://`, les routes Ziggy/Inertia peuvent poster les formulaires vers une origine HTTP et le navigateur bloque les requetes.

Le compose force `DB_HOST=pgsql` et `DB_PORT=5432` pour eviter qu'une ancienne variable Laravel dans l'environnement Dokploy casse la connexion entre les conteneurs. Pour changer le nom de base ou l'utilisateur Postgres, utiliser `POSTGRES_DB` et `POSTGRES_USER`, pas `DB_DATABASE` ou `DB_USERNAME`.

Attention si le volume Postgres existe deja, par exemple apres import d'un dump : l'image `postgres` ignore alors `POSTGRES_DB`, `POSTGRES_USER` et `DB_PASSWORD` pour l'initialisation. Ces variables doivent correspondre a la base, l'utilisateur et le mot de passe deja presents dans le volume, ou il faut modifier le role directement dans Postgres.

## Import du dump legacy

Depuis la racine du depot :

```sh
php artisan legacy:import-dump --fresh
php artisan legacy:import-dump --with-assets --asset-concurrency=12 --skip-existing-assets
```

Le dump attendu est `dumps/prod-public-data.sql` a la racine du depot.

## Documentation

- `docs/etat-technique-laravel-inertia.md` : etat technique courant du depot Laravel/Inertia.
- `docs/fonctionnalites-et-user-stories.md` : couverture fonctionnelle et user stories.
- `docs/tests-scalabilite.md` : smoke tests de scalabilite legers.
- `scripts/README-o2switch-rclone-transfer.md` : transfert o2switch vers Scaleway avec `rclone`.
- `docs/audit-technique.md` et documents de cadrage : references historiques de migration, a verifier contre le code courant avant decision technique.

## Ancien projet

Le backend Supabase et l'ancien frontend React/Vite ont ete retires du depot. Les documents dans `docs/` restent la reference d'audit, de cadrage et de reprise fonctionnelle pour terminer la migration metier.
