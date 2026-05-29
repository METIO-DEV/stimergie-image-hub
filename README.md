# Stimergie Image Hub

Migration en cours vers une application Laravel + React/Inertia.

## Application cible

Le nouveau code applicatif est dans `laravel/`.

- Backend : Laravel
- Frontend : React + Inertia
- Base de donnees : PostgreSQL
- Queue : driver `database`
- Stockage images : disque S3-compatible `scaleway`

## Lancer en Docker

Preparer l'environnement Laravel :

```sh
cp laravel/.env.example laravel/.env
```

Renseigner les variables Scaleway dans `laravel/.env`, puis lancer :

```sh
docker compose up --build
```

Services exposes :

- App Laravel : http://localhost:8000
- Vite : http://localhost:5173
- PostgreSQL : localhost:5432

Le compose lance aussi un worker :

```sh
php artisan queue:work --sleep=1 --tries=3 --timeout=120
```

## Import du dump legacy

Depuis le dossier `laravel/` :

```sh
php artisan legacy:import-dump --fresh
php artisan legacy:import-dump --with-assets --asset-concurrency=12 --skip-existing-assets
```

Le dump attendu est `dumps/prod-public-data.sql` a la racine du depot.

## Ancien projet

Le backend Supabase a ete retire du depot. Le vieux front React/Vite racine reste temporairement present comme reference fonctionnelle pendant la migration metier. Il doit etre supprime quand les modules Laravel/Inertia auront repris les parcours clients, projets, images, albums et telechargements.
