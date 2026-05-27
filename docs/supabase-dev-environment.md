# Environnement Supabase de dev

Etat du depot au 27 mai 2026 :

- la prod actuelle pointe vers le projet Supabase `mjhbugzaqmtfnbxaqpss` ;
- le frontend lit maintenant `VITE_SUPABASE_URL`, `VITE_SUPABASE_PUBLISHABLE_KEY` et `VITE_SUPABASE_PROJECT_ID` ;
- `.env.local` doit etre utilise pour le dev local, car il surcharge le `.env` existant ;
- le CLI Supabase est disponible via `npx supabase` ou `supabase`.

## Garde-fous de forfait gratuit

Avant de creer `stimergie-dev`, verifier dans Supabase Dashboard > Organization > Usage :

- Free autorise 2 projets actifs gratuits. La prod compte deja pour 1 projet actif.
- Database : la limite Free est 500 MB par projet actif.
- Storage : verifier la taille totale des buckets. Une copie complete des images peut rapidement depasser le Free.
- Egress : le Free inclut 5 GB d'egress et 5 GB d'egress cache par organisation. Copier beaucoup de fichiers depuis la prod consomme de l'egress.
- Branching Supabase n'est pas inclus en Free, donc le bon modele est un deuxieme projet, pas une branche Supabase.

Decision conseillee pour ce projet : copier le schema, les fonctions et un petit jeu de donnees, mais ne pas copier tout le Storage en dev tant que la taille des images n'est pas confirmee sous 1 GB.

Mesures utiles dans le SQL Editor de la prod :

```sql
select pg_size_pretty(pg_database_size(current_database())) as database_size;

select
  bucket_id,
  pg_size_pretty(sum((metadata->>'size')::bigint)) as storage_size,
  count(*) as object_count
from storage.objects
group by bucket_id
order by bucket_id;
```

Si `database_size` depasse 500 MB, un dump complet ne passera pas en Free. Si le Storage approche ou depasse 1 GB, ne pas copier les fichiers en dev.

## Creation du projet dev

1. Aller dans Supabase Dashboard > New project.
2. Nommer le projet `stimergie-dev`.
3. Choisir la meme region que la prod si possible.
4. Garder le plan Free et ne pas activer d'add-on payant.
5. Noter ces valeurs du projet dev :
   - Project ref ;
   - Project URL ;
   - anon/publishable key ;
   - Database password ;
   - connection string Postgres.

## Configuration locale frontend

Creer `.env.local` depuis l'exemple :

```bash
cp .env.local.example .env.local
```

Puis remplir avec les valeurs du projet dev :

```bash
VITE_SUPABASE_PROJECT_ID=<dev-project-ref>
VITE_SUPABASE_URL=https://<dev-project-ref>.supabase.co
VITE_SUPABASE_PUBLISHABLE_KEY=<dev-anon-or-publishable-key>
```

Verifier que Vite utilise bien le dev :

```bash
npm run dev
```

## Login CLI

```bash
npx supabase login
```

Si le navigateur ne s'ouvre pas :

```bash
npx supabase login --no-browser
```

## Recuperer le schema de prod

Le projet n'est pas linke localement. Lier temporairement la prod :

```bash
npx supabase link --project-ref mjhbugzaqmtfnbxaqpss
```

Option recommandee : synchroniser les migrations locales avec le schema distant si elles ne sont pas deja a jour :

```bash
npx supabase db pull
```

Creer un dump de securite du schema :

```bash
mkdir -p dumps
npx supabase db dump --linked --schema public,auth,storage --file dumps/prod-schema.sql
```

Pour ce projet, les migrations historiques locales ne reconstruisent pas une base neuve de facon fiable. Certaines anciennes migrations ont un nom ignore par le CLI Supabase (`timestamp-uuid.sql`) et les tables coeur (`clients`, `projets`, `images`, `profiles`) viennent de l'etat prod. La strategie de dev retenue est donc :

- restaurer le schema `public` depuis un dump prod ;
- retirer le trigger prod qui appelle `supabase_functions.http_request` vers Make ;
- restaurer les donnees publiques avec contraintes temporairement desactivees ;
- creer un profil `admin` pour l'utilisateur Auth du projet dev.

## Initialiser la base dev par dump

Relier le dossier au projet dev :

```bash
npx supabase link --project-ref <dev-project-ref>
```

Restaurer le schema `public` avec `psql` ou l'image Postgres fournie par Supabase CLI. Avant restauration, nettoyer uniquement le schema `public` du projet dev :

```bash
DROP SCHEMA IF EXISTS public CASCADE;
CREATE SCHEMA public;
GRANT USAGE ON SCHEMA public TO postgres, anon, authenticated, service_role;
GRANT ALL ON SCHEMA public TO postgres, service_role;
```

Apres restauration par dump, marquer les migrations valides comme appliquees pour eviter que `db push` tente de les rejouer :

```bash
npx supabase migration repair --linked --status applied <versions>
```

## Donnees de dev

Pour rester dans le Free, commencer par un jeu de donnees reduit.

Dump data-only complet, uniquement si les tailles sont confirmees compatibles :

```bash
npx supabase link --project-ref mjhbugzaqmtfnbxaqpss
npx supabase db dump --linked --data-only --use-copy --schema public --file dumps/prod-public-data.sql
```

Restaurer vers le projet dev avec la connection string Postgres du projet dev :

```bash
psql "<dev-postgres-connection-string>" -f dumps/prod-public-data.sql
```

Ne pas copier `auth.users` par defaut. Creer plutot des comptes de test dans Dashboard > Authentication > Users, puis associer leurs profils aux clients/projets necessaires.

## Edge Functions et secrets

Deployer les fonctions sur le projet dev :

```bash
npx supabase functions deploy --project-ref <dev-project-ref>
```

Configurer les secrets non-Supabase requis :

```bash
npx supabase secrets set --project-ref <dev-project-ref> MAILJET_API_KEY=... MAILJET_API_SECRET=... PUBLIC_URL=https://<dev-project-ref>.supabase.co
```

Verifier les secrets :

```bash
npx supabase secrets list --project-ref <dev-project-ref>
```

## Storage

Ne copier le Storage que si la taille totale est compatible avec le Free. Pour un environnement dev, privilegier :

- quelques images de test dans le bucket `images` ;
- quelques fichiers de ZIP/downloads si necessaire ;
- aucune copie massive des images HD.

Une copie massive de Storage peut depasser le quota de taille et consommer l'egress de l'organisation.

## Checklist avant de developper

- `.env.local` pointe vers `<dev-project-ref>`.
- Le dashboard ouvert est le projet `stimergie-dev`, pas `Stimergie` prod.
- Les Edge Functions ont ete deployees sur le projet dev.
- Les secrets Mailjet/Public URL sont configures sur le projet dev.
- Les utilisateurs de test existent et ont des profils/roles coherents.
- Les images de test suffisent a reproduire les workflows d'upload, galerie, partage et ZIP.
