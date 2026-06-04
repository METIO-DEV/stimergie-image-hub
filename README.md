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

## Import du dump legacy

Depuis la racine du depot :

```sh
php artisan legacy:import-dump --fresh
php artisan legacy:import-dump --with-assets --asset-concurrency=12 --skip-existing-assets
```

Le dump attendu est `dumps/prod-public-data.sql` a la racine du depot.

## Ancien projet

Le backend Supabase et l'ancien frontend React/Vite ont ete retires du depot. Les documents dans `docs/` restent la reference d'audit, de cadrage et de reprise fonctionnelle pour terminer la migration metier.
