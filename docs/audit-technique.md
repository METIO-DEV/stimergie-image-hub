# Audit technique et fonctionnel - Stimergie Image Hub

Date : 2026-05-26

## Synthese

L'application compile en production, mais elle presente plusieurs risques a traiter avant de la considerer robuste en production :

- des erreurs d'execution possibles liees aux hooks React ;
- des parcours blog/admin casses par des routes inexistantes ;
- une logique de session qui peut rediriger des visiteurs non connectes sur des pages publiques ;
- des donnees potentiellement trop exposees si les policies Supabase ne compensent pas certaines requetes front ;
- un lint tres degrade, avec 101 erreurs ;
- un bundle JavaScript principal volumineux.

Commandes executees :

- `npm run lint` : echec, 136 problemes dont 101 erreurs et 35 warnings.
- `npm run build` : succes, avec warnings de chunking et bundle principal de 1,626.89 kB minifie.

## Constats prioritaires

### P0 - Telechargement navigateur bloque par CORS

Fichiers concernes :

- `src/utils/image/download/requestDownload.ts`
- `src/hooks/downloads/useImageDownloader.ts`
- `src/hooks/downloads/useHDDownloader.ts`
- `supabase/functions/generate-zip/index.ts`

Constat :

- le parcours de telechargement groupé creait le ZIP dans le navigateur ;
- le frontend appelait directement `fetch(image.url)` sur les fichiers servis par `www.stimergie.fr` ;
- en cas d'absence de headers CORS compatibles, le navigateur retournait `Failed to fetch` ;
- la fonction `generate-zip` contenait aussi un endpoint et une cle O2Switch de production en dur.

Impact :

- impossible de preparer certains telechargements depuis `localhost` ou depuis un domaine non autorise ;
- le meme probleme peut arriver en production si l'application n'est pas servie depuis le meme origin que `www.stimergie.fr` ;
- le probleme depend du navigateur et des headers distants, donc il peut sembler intermittent ;
- la cle d'upload O2Switch etait aussi utilisee depuis du code frontend, ce qui n'est pas acceptable a terme.

Correction recommandee :

- faire creer les ZIP par une Edge Function Supabase ;
- laisser le navigateur uniquement creer une demande de telechargement ;
- en dev, stocker les ZIP generes dans le bucket Supabase `zip_downloads` du projet dev ;
- n'utiliser O2Switch que si des secrets explicites `O2SWITCH_UPLOAD_ENDPOINT` et `O2SWITCH_API_KEY` sont configures sur l'environnement cible ;
- suivre le resultat depuis la page `Telechargements`.

### P0 - Cron Supabase desactive ou dangereux si token invalide

Ticket Vikunja : `#11 - P0 - Decider upgrade temporaire Supabase ou nettoyage immediat de la base prod`

Fonctions concernees :

- `supabase/functions/cron-process-queue`
- `supabase/functions/process-queue`
- `supabase/functions/cron-cache-dropbox`
- `supabase/functions/cache-dropbox-images`

Constats :

- aucun planning `pg_cron` n'etait versionne dans le depot ;
- `cron-cache-dropbox` appelait `/functions/v1/cache-images`, alors que la fonction existante s'appelle `cache-dropbox-images` ;
- les wrappers cron renvoyaient `500` sur des erreurs de configuration ou d'authentification deterministes ;
- `process-queue` pouvait laisser une demande en `processing` si l'execution etait interrompue avant la fin.

Impact :

- relance periodique inutile si une cle ou un secret est invalide ;
- bruit operationnel et consommation d'invocations Edge Function sans traitement utile ;
- demandes de telechargement bloquees en `processing` ;
- difficulte a piloter la remise en route sans risquer de depasser inutilement le forfait gratuit.

Correction recommandee :

- rendre les wrappers cron idempotents et capables de retourner `200 skipped` sur configuration manquante ou token invalide ;
- corriger l'endpoint de cache ;
- reinitialiser les demandes `processing` bloquees depuis plus de 30 minutes ;
- versionner les schedules `pg_cron` avec des secrets lus depuis Vault, sans stocker de cle dans le repo ;
- demarrer avec une cadence faible : file ZIP toutes les 15 minutes, cache image une fois par jour.

### P0 - Tables de synchronisation FTP sans RLS

Tables Supabase :

- `public.ftp_folders`
- `public.ftp_sync_state`
- `public.ftp_tracking`

Le dashboard Supabase signale ces tables comme `UNRESTRICTED`, avec RLS desactivee. Elles contiennent de l'etat technique de synchronisation FTP/Dropbox et ne sont pas utilisees directement par le frontend.

Impact :

- lecture ou modification possible si une cle client dispose de droits sur ces tables ;
- exposition inutile d'informations operationnelles internes ;
- surface d'attaque plus large en cas d'erreur de policy ou de requete.

Correction recommandee :

- activer RLS sur les trois tables ;
- autoriser uniquement les administrateurs a lire/gerer ces donnees depuis l'application ;
- laisser les traitements serveur en `service_role` gerer la synchronisation, car `service_role` bypass RLS.

### P0 - Redirection abusive des visiteurs non connectes

Fichier : `src/context/AuthContext.tsx`

La verification periodique de session considere l'absence de session comme une session invalide. Or un visiteur anonyme sur une route publique n'a pas de session par definition. Apres 5 minutes, il est donc susceptible d'etre redirige vers `/auth`.

Impact :

- les pages publiques peuvent devenir inutilisables sur une consultation longue ;
- un visiteur sur un album partage, une ressource ou une page legale peut etre interrompu ;
- le comportement confond "pas connecte" et "session connectee expiree".

Correction recommandee :

- ne lancer le controle periodique que si `user` ou `session` existe ;
- traiter `!session` comme normal lorsqu'aucune session n'etait attendue ;
- ne rediriger que sur erreur de refresh ou session precedemment authentifiee devenue invalide.

### P0 - Routes blog d'edition inexistantes

Fichiers :

- `src/components/dashboard/AdminDashboard.tsx`
- `src/components/blog/BlogPostList.tsx`
- `src/components/blog/BlogPostView.tsx`
- `src/App.tsx`

Plusieurs boutons naviguent vers `/blog/new` ou `/blog/edit/:id`, mais l'application ne declare que `/blog-editor` pour l'edition.

Impact :

- le bouton "Blog" du tableau de bord administrateur mene a une 404 ;
- le bouton "Nouvel article" mene a une 404 ;
- le bouton "Modifier" depuis une liste ou une fiche article mene a une 404 ;
- l'edition d'un article existant n'est pas accessible par le parcours prevu.

Correction recommandee :

- soit ajouter les routes `/blog/new` et `/blog/edit/:id` ;
- soit remplacer tous les liens par `/blog-editor` et `/blog-editor/:id`, puis declarer la route parametree correspondante.

### P0 - Brouillons blog potentiellement publics

Fichiers :

- `src/hooks/useBlogPosts.ts`
- `src/components/blog/BlogPostView.tsx`
- `src/components/blog/BlogPostList.tsx`

Les listes publiques chargent les articles sans filtrer `published = true`. La fiche article charge aussi par slug sans verifier le statut publie.

Impact :

- des brouillons peuvent apparaitre sur `/resources`, `/ensemble` et `/blog/:slug` ;
- du contenu non valide ou confidentiel peut etre expose si les policies Supabase autorisent la lecture ;
- le badge "Brouillon" est rendu dans une interface publique, ce qui confirme l'exposition.

Correction recommandee :

- appliquer `eq('published', true)` pour les utilisateurs anonymes et standards ;
- reserver la lecture des brouillons aux roles `admin` et `admin_client` ;
- renforcer la policy RLS `blog_posts` pour que la base protege aussi ce comportement.

### P1 - Hooks React appeles conditionnellement

Fichiers :

- `src/components/images/ImageSharingManager.tsx`
- `src/components/access-periods/ProjectAccessPeriods.tsx`

ESLint detecte des violations de `react-hooks/rules-of-hooks`. Dans `ImageSharingManager`, le composant retourne `null` avant un `useEffect` lorsque le role n'est pas admin. Si le role passe de `user` a `admin` apres chargement du profil, l'ordre des hooks change.

Impact :

- crash React possible a l'execution ;
- comportement instable lors du chargement du role ;
- composant de partage client inutilisable.

Correction recommandee :

- declarer tous les hooks avant tout retour conditionnel ;
- deplacer les guards dans le rendu ou dans les effets ;
- corriger aussi `ProjectAccessPeriods`, qui retourne avant ses `useEffect` si l'utilisateur n'est pas admin.

### P1 - `ImageSharingManager` ne rend aucune interface

Fichier : `src/components/images/ImageSharingManager.tsx`

Le composant charge les clients, definit les handlers de partage/retrait, mais termine par `return;`.

Impact :

- l'administrateur ne peut pas utiliser la fonctionnalite "partage d'une image avec un autre client" ;
- le code donne l'impression que la fonctionnalite existe alors que l'UI n'est jamais rendue ;
- risque de divergence entre logique metier et interface.

Correction recommandee :

- rendre une carte avec la liste des clients partages ;
- afficher un select des clients disponibles et un bouton d'ajout ;
- afficher une action de retrait par client partage ;
- ou supprimer le composant si la fonctionnalite n'est plus supportee.

### P1 - Requete de telechargements non filtree par utilisateur

Fichier : `src/hooks/useDownloads.ts`

La souscription temps reel filtre bien `user_id=eq.${user.id}`, mais la requete initiale lit `download_requests` sans `eq('user_id', user.id)`.

Impact :

- dependance forte a la RLS pour eviter la fuite de donnees ;
- si une policy est modifiee ou trop permissive, un utilisateur peut voir les demandes de telechargement d'autres comptes ;
- incoherence entre la requete initiale et le flux realtime.

Correction recommandee :

- ajouter explicitement `.eq('user_id', user.id)` sur la requete initiale ;
- garder les policies RLS comme defense principale cote base.

### P1 - `admin_client` multi-client mal gere dans l'upload image

Fichier : `src/components/images/ImageUploadForm.tsx`

Pour un `admin_client`, la liste des projets est filtree uniquement via `profiles.id_client`. Le champ multi-client actuel `client_ids` n'est pas pris en compte.

Impact :

- un admin client rattache a plusieurs clients ne verra pas tous ses projets ;
- il peut etre bloque pour uploader sur des projets pourtant autorises ;
- incoherence avec les autres modules qui supportent `client_ids`.

Correction recommandee :

- recuperer `id_client` et `client_ids` ;
- construire la liste effective des clients ;
- filtrer les projets avec `.in('id_client', clientIds)`.

### P1 - Appels directs a la fonction IA avec cle anon codee en dur

Fichiers :

- `src/components/images/ImageUploadForm.tsx`
- `src/integrations/supabase/client.ts`

Le composant d'upload appelle directement l'URL Supabase Function avec une cle anon de fallback codee en dur. Le client Supabase contient aussi URL et cle publiee en dur.

Impact :

- environnement difficile a changer sans rebuild/code edit ;
- risque d'appeler le mauvais projet Supabase si les variables d'environnement sont absentes ;
- duplication de configuration.

Correction recommandee :

- utiliser `supabase.functions.invoke('analyze-image-ai', ...)` ;
- centraliser URL/cle dans le client Supabase ;
- supprimer les fallbacks hardcodes dans les composants applicatifs.

### P1 - Controle d'acces front incomplet dans `ProtectedRoute`

Fichier : `src/components/auth/ProtectedRoute.tsx`

Les props `requiresClient`, `clientId` et `requiresEditPermission` existent mais ne sont pas utilisees. Le commentaire indique aussi une simplification "With RLS disabled", alors que les migrations activent des policies RLS sur plusieurs tables.

Impact :

- intention de securite confuse ;
- risque que des routes futures croient beneficier d'un controle client qui n'est pas applique ;
- dette fonctionnelle sur les permissions.

Correction recommandee :

- supprimer les props non supportees ou implementer les controles ;
- clarifier la strategie : route guard pour UX, RLS/RPC pour securite ;
- ajouter des tests de roles sur les routes sensibles.

### P2 - Navigation incoherente selon desktop/mobile

Fichiers :

- `src/components/ui/layout/Header.tsx`
- `src/components/ui/layout/header/MobileMenu.tsx`

Sur desktop, "Gestion des images" n'est affichee que pour `admin_client` et `user`, alors que la route autorise aussi `admin`. Sur mobile, l'item `/images` est affiche pour tout non-admin, ce qui inclut potentiellement un utilisateur sans profil charge.

Impact :

- experience differente selon le device ;
- acces admin cache sur desktop hors menu utilisateur ;
- confusion dans les parcours.

Correction recommandee :

- definir une matrice de navigation unique par role ;
- reutiliser cette matrice pour desktop, mobile et menu utilisateur.

### P2 - Telechargement d'album partage entierement cote navigateur

Fichier : `src/pages/SharedAlbum.tsx`

Le bouton "Telecharger tout" telecharge toutes les images en parallele et genere le ZIP dans le navigateur.

Impact :

- risque de gel navigateur ou erreur memoire sur gros albums ;
- aucune limite de concurrence ;
- un ZIP peut etre genere meme si toutes les images ont echoue, car les erreurs image sont absorbees individuellement.

Correction recommandee :

- limiter la concurrence de telechargement ;
- refuser ou basculer cote serveur au-dela d'un seuil ;
- verifier qu'au moins une image a ete ajoutee avant de sauvegarder le ZIP ;
- reutiliser la logique de demandes serveur deja presente pour les gros lots.

### P2 - Bundle principal trop volumineux

Commande : `npm run build`

Le build produit un fichier principal `dist/assets/index-*.js` de 1,626.89 kB minifie, 482.37 kB gzip. Vite signale aussi que plusieurs imports dynamiques ne creent pas de chunks parce que les modules sont deja importes statiquement ailleurs.

Impact :

- chargement initial plus lent ;
- experience degradee sur mobile/reseau lent ;
- les modules lourds comme editeur riche, zip, debug/admin et fonctions image peuvent etre charges trop tot.

Correction recommandee :

- lazy-loader les pages de routes via `React.lazy` ;
- isoler les modules blog editor, ZIP, debug/admin ;
- eviter les doubles imports statiques/dynamiques des memes modules ;
- ajouter une analyse de bundle.

### P2 - Lint tres degrade

Commande : `npm run lint`

Resultat :

- 136 problemes ;
- 101 erreurs ;
- 35 warnings.

Themes dominants :

- usage massif de `any` ;
- hooks avec dependances manquantes ;
- hooks conditionnels ;
- imports `require()` interdits ;
- interfaces vides ;
- fast refresh warnings.

Impact :

- le lint ne joue plus son role de filet de securite ;
- les erreurs critiques sont noyees dans le bruit ;
- CI difficile a rendre bloquante sans correction progressive.

Correction recommandee :

- traiter d'abord `react-hooks/rules-of-hooks` ;
- corriger ensuite les erreurs de routes et d'exposition de donnees ;
- reduire progressivement les `any` par domaine, en commencant par image, user et blog ;
- envisager une baseline temporaire si l'objectif est de reactiver une CI stricte.

## Priorite de correction conseillee

1. Corriger la redirection de session anonyme.
2. Corriger les routes blog et filtrer les brouillons.
3. Corriger les hooks conditionnels.
4. Ajouter le filtre utilisateur explicite sur `download_requests`.
5. Corriger l'upload multi-client pour `admin_client`.
6. Restaurer ou supprimer `ImageSharingManager`.
7. Nettoyer navigation desktop/mobile.
8. Reduire le bundle par lazy loading.
9. Remettre le lint sous controle par lots.

## Verification realisee

`npm run build` passe, ce qui indique que le projet reste compilable.

`npm run lint` echoue. Tant que le lint n'est pas assaini, il ne peut pas servir de gate qualite fiable.
