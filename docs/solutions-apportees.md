# Solutions apportees

Date : 2026-05-27

## P0 - Telechargement navigateur `Failed to fetch`

Statut : corrige sur la branche `dev/supabase-environment` et fonction `generate-zip` deployee sur le projet Supabase dev.

Probleme traite :

- les telechargements groupes tentaient de recuperer les images depuis le navigateur ;
- les URLs `https://www.stimergie.fr/photos/...` peuvent etre bloquees par CORS en `fetch`, ce qui produit `Failed to fetch` ;
- ce blocage peut arriver en dev comme en prod si l'application et les images ne partagent pas le meme origin ;
- le parcours serveur existant `generate-zip` n'etait pas utilise par le bouton.

Solution appliquee :

- `requestServerDownload` appelle maintenant l'Edge Function `generate-zip` ;
- les seuils SD et HD passent a `1`, donc tout telechargement depuis la selection utilise le serveur ;
- `generate-zip` cree une demande valide avec `download_url: ''`, `status: processing` et `processed_at` ;
- `generate-zip` utilise `EdgeRuntime.waitUntil(...)` pour poursuivre la creation du ZIP apres la reponse HTTP ;
- `generate-zip` n'utilise plus O2Switch par defaut : sans secrets `O2SWITCH_UPLOAD_ENDPOINT` et `O2SWITCH_API_KEY`, les ZIP sont stockes dans le bucket Supabase `zip_downloads` du projet courant ;
- `downloadImage` ouvre maintenant l'URL directe sans `fetch()` navigateur ;
- les telechargements depuis les cartes et depuis les boutons globaux n'essaient plus de lire les fichiers O2Switch en JavaScript ;
- la fonction a ete deployee sur le projet dev.

Verification effectuee :

- invocation directe de `generate-zip` sur une image dev : reponse `status: processing` ;
- la demande creee est ensuite passee en `ready` avec une URL de telechargement ;
- `npm run build` passe.

## P0 - Cron Supabase et boucle sur token invalide

Statut : corrige sur la branche `dev/supabase-environment`, a valider sur l'environnement Supabase cible avant activation prod.

Probleme traite :

- les cron n'etaient pas versionnes dans les migrations ;
- `cron-cache-dropbox` ciblait une fonction inexistante (`cache-images`) ;
- une erreur de secret/token pouvait produire une erreur cron repetitive au lieu d'un arret propre ;
- une interruption de `process-queue` pouvait laisser des lignes `download_requests` bloquees en `processing`.

Solution appliquee :

- `cron-cache-dropbox` appelle maintenant `cache-dropbox-images` ;
- les wrappers cron utilisent la cle `service_role` si elle est disponible, sinon la cle anon ;
- les wrappers cron peuvent aussi reutiliser le JWT entrant du cron pour appeler la fonction enfant, afin d'eviter les erreurs liees aux cles publishable `sb_publishable_...` ;
- les cas configuration manquante, cron desactive, token invalide et timeout de declenchement retournent `success: true` avec `skipped: true`, pour eviter les relances d'erreur deterministe ;
- `process-queue` marque le debut de traitement avec `processed_at` et remet en `pending` les traitements bloques depuis plus de 30 minutes ;
- nouvelle migration `20260527103000_reactivate_safe_cron_jobs.sql` :
  - active `pg_cron`, `pg_net` et Vault ;
  - cree `public.invoke_scheduled_edge_function(...)` ;
  - planifie `cron-process-queue` toutes les 15 minutes ;
  - planifie `cron-cache-dropbox` une fois par jour a `03:15 UTC` ;
  - lit les secrets depuis Vault (`scheduled_edge_function_url`, `scheduled_edge_function_key`) sans les versionner ;
  - `scheduled_edge_function_key` doit etre la cle anon JWT (`eyJ...`), pas la cle publishable `sb_publishable_...`, car les fonctions Edge verifient un JWT.

Verification attendue :

- les jobs apparaissent dans `cron.job` avec les noms `stimergie-process-download-queue` et `stimergie-cache-dropbox-images` ;
- si les secrets Vault sont absents, les jobs se terminent sans appel Edge Function ;
- si une cle d'appel est invalide, les wrappers retournent `skipped: true` au lieu d'une erreur repetitive ;
- une demande ZIP bloquee en `processing` depuis plus de 30 minutes repasse en `pending`.

## P0 - Tables de synchronisation FTP sans RLS

Statut : corrige sur la branche `dev/supabase-environment`.

Probleme traite :

- `public.ftp_folders`, `public.ftp_sync_state` et `public.ftp_tracking` etaient marquees `UNRESTRICTED` dans Supabase ;
- ces tables internes de synchronisation n'ont pas vocation a etre accessibles publiquement ou par des utilisateurs standards.

Solution appliquee :

- nouvelle migration Supabase `20260527090150_enable_rls_on_ftp_sync_tables.sql` ;
- activation RLS sur les trois tables ;
- policies `FOR ALL` reservees au role applicatif `admin` ;
- les traitements serveur utilisant la cle `service_role` conservent leur acces technique.

Verification attendue :

- le dashboard Supabase ne doit plus afficher `UNRESTRICTED` sur ces trois tables ;
- un role `anon` ne doit lire aucune ligne ;
- un administrateur applicatif conserve la possibilite d'inspecter ces tables si necessaire.

## P0 - Brouillons blog potentiellement publics

Statut : corrige sur la branche `dev/supabase-environment`.

Probleme traite :

- les listes publiques de ressources/articles chargeaient les articles sans filtre `published = true` ;
- la page detail `/blog/:slug` pouvait charger un brouillon par slug ;
- la policy RLS `blog_posts` autorisait la lecture de tous les articles avec `USING (true)`.

Solution appliquee :

- filtrage frontend explicite des listes publiques dans `src/hooks/useBlogPosts.ts` ;
- filtrage frontend explicite de la fiche article dans `src/components/blog/BlogPostView.tsx` ;
- nouvelle migration Supabase `20260527085515_restrict_blog_drafts_read_access.sql` :
  - supprime la policy permissive `"Enable read access for all users"` ;
  - autorise la lecture publique uniquement si `published = true` ;
  - conserve la lecture des brouillons pour les roles `admin` et `admin_client`.

Verification attendue :

- un visiteur anonyme ne voit que les articles publies sur `/resources`, `/ensemble` et `/blog/:slug` ;
- un utilisateur standard ne voit que les articles publies ;
- un `admin` ou `admin_client` peut toujours voir les brouillons.

## P1 - Affichage galerie et images sources absentes

Statut : diagnostic confirme, fallback d'affichage corrige. Les fichiers sources absents doivent etre republies ou retires des donnees.

Tickets Vikunja :

- `#318 - P1 - Diagnostiquer les images manquantes dans la galerie`
- `#319 - P1 - Corriger la strategie de resolution des URLs d'images`

Probleme traite :

- certaines premieres images de la galerie restaient en placeholder ou affichaient une icone d'image cassee ;
- le detail image chargeait bien les metadonnees en base, mais pas le fichier visuel ;
- les fichiers de fallback locaux `/image-not-available.png` et `/placeholder.png` n'etaient pas de vraies images PNG, mais du texte, ce qui cassait aussi l'affichage de secours ;
- `ImageCard` appliquait un ratio via `AspectRatio`, puis `LazyImage` ajoutait a nouveau un padding de ratio, creant de grands blocs vides en haut de la masonry.

Diagnostic effectue :

- image testee : `id=4784`, `VALRHONA_FSP SHOOT FINAL_301020250205`, projet `VALRHONA_FSP_SHOOTFINAL_301025` ;
- l'URL stockee en base pointe vers `https://www.stimergie.fr/photos/VALRHONA_FSP_SHOOTFINAL_301025/JPG/VALRHONA_FSP SHOOT FINAL_301020250205.jpg` ;
- le serveur repond `200 text/html` au lieu de `image/jpeg` ;
- le contenu retourne est le HTML de l'application, pas un fichier image ;
- les 17 premieres images recentes testees du meme projet `VALRHONA_FSP_SHOOTFINAL_301025` ont le meme symptome : `url_miniature=null` et reponse `text/html` ;
- les images suivantes du projet `VALRHONA_BARISTA_221025` repondent correctement en `image/jpeg`, ce qui confirme que le probleme est lie a certains fichiers sources absents ou non publies, pas a la galerie dans son ensemble.

Solution appliquee :

- `ImageCard` ne transmet plus `aspectRatio` a `LazyImage` lorsque l'image est deja encadree par `AspectRatio` ;
- un ratio de secours `4 / 3` est applique uniquement si aucune dimension/orientation n'est disponible ;
- `LazyImage` et `ImageCard` reinitialisent leur etat d'erreur quand `src` change ;
- les vues galerie, detail, album partage, selection d'image blog et service worker utilisent maintenant `/placeholder.svg` comme fallback valide ;
- la generation d'URL Stimergie est centralisee et encode les segments de chemin sans casser les sous-dossiers ;
- les formats galerie reconstruisent des URLs de secours a partir de `nom_dossier` et `title` quand les champs de base sont incomplets.

Limite restante :

- le frontend peut afficher un fallback propre, mais il ne peut pas inventer une image absente sur `www.stimergie.fr/photos` ;
- pour les images Valrhona testees, il faut republier/re-uploader les fichiers dans le dossier attendu ou nettoyer les lignes `images` qui pointent vers des fichiers inexistants.

Verification effectuee :

- test HTTP direct sur l'URL Valrhona : `200 text/html`, donc fichier image absent a l'emplacement attendu ;
- test HTTP direct sur `VALRHONA_BARISTA_2210250385` : `200 image/jpeg`, donc le serveur d'images fonctionne pour les fichiers presents ;
- `npx eslint` cible sur les fichiers d'affichage images : succes avec un warning historique dans `ImageSelector.tsx` ;
- `npm run build` : succes, avec warnings Vite deja presents.

## P1 - Upload image pour `admin_client` multi-client

Statut : corrige sur la branche `dev/supabase-environment`.

Probleme traite :

- le formulaire d'upload d'image filtrait les projets d'un `admin_client` uniquement avec `profiles.id_client` ;
- le champ multi-client `profiles.client_ids` n'etait pas pris en compte ;
- un `admin_client` rattache a plusieurs clients pouvait donc ne voir qu'une partie de ses projets autorises.

Solution appliquee :

- `ImageUploadForm` lit maintenant `id_client` et `client_ids` depuis `profiles` ;
- les deux sources sont fusionnees et dedupliquees ;
- la requete projets utilise `.in('id_client', clientIds)` pour afficher tous les projets des clients rattaches ;
- le comportement mono-client existant est conserve.

Verification effectuee :

- `npx eslint src/components/images/ImageUploadForm.tsx` : succes ;
- `npm run build` : succes, avec warnings Vite deja presents.
