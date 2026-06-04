# Stimergie Image Hub

Application Laravel + React/Inertia pour la gestion metier Stimergie Image Hub.

## Stack

- Backend : Laravel
- Frontend : React + Inertia
- Base de donnees : PostgreSQL
- Queue : driver `database`
- Stockage images : disque S3-compatible `scaleway`

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

Le compose lance aussi un worker :

```sh
php artisan queue:work --sleep=1 --tries=3 --timeout=120
```

## Deploiement Dokploy

Utiliser `docker-compose.dokploy.yml` comme Compose Path.

La configuration prod construit une seule image applicative :

- `composer install --no-dev` est execute pendant le build Docker.
- `npm ci && npm run build` est execute pendant le build Docker.
- `app` sert Laravel via Apache sur le port interne `80`.
- `queue` reutilise la meme image pour `php artisan queue:work`.
- `pgsql` persiste ses donnees dans un volume Docker nomme.

Variables minimales a renseigner dans l'environnement Dokploy :

```env
APP_KEY=base64:...
APP_URL=https://votre-domaine.tld
DB_PASSWORD=mot-de-passe-solide
SCALEWAY_ACCESS_KEY_ID=...
SCALEWAY_SECRET_KEY=...
SCALEWAY_OBJECT_STORAGE_BUCKET=...
SCALEWAY_OBJECT_STORAGE_REGION=fr-par
SCALEWAY_OBJECT_STORAGE_ENDPOINT=https://s3.fr-par.scw.cloud
BREVO_TEMPLATE_MAILER=brevo
BREVO_API_KEY=...
MAIL_FROM_ADDRESS=...
```

Dans l'onglet Domains de Dokploy, pointer le domaine vers le service `app` et le port `80`. Dokploy injecte les variables de son UI dans un fichier `.env`; le compose les charge avec `env_file`.

## Import du dump legacy

Depuis la racine du depot :

```sh
php artisan legacy:import-dump --fresh
php artisan legacy:import-dump --with-assets --asset-concurrency=12 --skip-existing-assets
```

Le dump attendu est `dumps/prod-public-data.sql` a la racine du depot.

## Ancien projet

Le backend Supabase et l'ancien frontend React/Vite ont ete retires du depot. Les documents dans `docs/` restent la reference d'audit, de cadrage et de reprise fonctionnelle pour terminer la migration metier.
