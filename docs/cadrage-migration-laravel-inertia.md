# Cadrage migration - Laravel + React/Inertia

Date : 2026-05-29

Derniere mise a jour : 2026-05-30

## Objectif

Mettre en pause la stabilisation de l'application Supabase actuelle et preparer une migration ou reecriture vers une application plus maitrisee :

- backend Laravel ;
- frontend React via Inertia.js ;
- base PostgreSQL ;
- stockage des images dans un object storage compatible S3 ;
- traitements asynchrones par queues Laravel.

Le but n'est pas de refaire a l'identique les choix techniques existants. Le but est de reconstruire un socle plus coherent pour l'ensemble du metier deja porte par l'application : gestion de clients, projets, droits d'acces, banque d'images, partages, telechargements, ressources editoriales et administration.

La banque d'images est le coeur visible du produit, mais elle n'est pas le seul perimetre. La migration doit reprendre les fonctionnalites metier existantes, tout en corrigeant les fondations techniques : permissions cote serveur, stockage fichier robuste, pipeline image explicite, sauvegardes, observabilite et exploitation simples.

Point de cadrage important ajoute le 2026-05-30 : l'interface et les fonctionnalites de reference sont celles de l'ancien frontend React/Supabase disponible sur `main`. La migration Laravel/Inertia ne doit pas recreer les pages ou les fonctionnalites depuis zero si elles existent deja dans l'ancien projet. Elle doit recuperer les pages, composants, comportements, design et workflows existants, puis les rebrancher proprement au backend Laravel/Inertia.

Ce portage doit etre vertical : une page reprise sans backend equivalent, sans donnees reelles, sans mutations et sans permissions Laravel n'est pas consideree comme migree.

## Situation actuelle

L'application existante est une app Vite/React connectee directement a Supabase. Elle couvre deja beaucoup de besoins :

- authentification et roles ;
- gestion des clients ;
- gestion des projets ;
- galerie d'images ;
- filtres par client, projet, tags et orientation ;
- periodes d'acces ;
- telechargements ZIP et HD ;
- albums partages ;
- blog / ressources ;
- fonctions Edge Supabase pour certains traitements.

Ces fonctionnalites representent le perimetre metier de reference. Certaines pourront etre redecoupees ou simplifiees techniquement, mais elles ne doivent pas disparaitre par accident pendant la reecriture.

Les limites constatees sont structurelles :

- logique de securite dispersee entre frontend, RPC Supabase, RLS et Edge Functions ;
- dependance a des images servies depuis disque / arborescence web externe ;
- pipeline d'image implicite, avec URLs reconstruites a partir des noms de dossiers et titres ;
- complexite importante autour des caches frontend ;
- cron et traitements asynchrones fragiles ;
- manque de tests automatises ;
- schema Supabase et migrations difficiles a reconstruire proprement localement ;
- surface d'administration sensible trop proche du frontend.

## Decision de court terme

Les tickets Vikunja du projet "Stimergie Image Hub - Stabilisation Supabase" sont mis en standby.

La branche courante de cadrage est :

```text
cadrage-migration-laravel-inertia
```

L'application actuelle doit rester exploitable uniquement pour les besoins indispensables pendant la phase de cadrage. Les nouvelles corrections doivent etre limitees aux urgences de production, a la securite ou a la preservation des donnees.

Etat de la branche Laravel/Inertia au 2026-05-30 :

- la branche active de migration est `migration-complete-laravel-inertia` ;
- l'application cible est Laravel + Inertia.js + React ;
- l'URL applicative locale est `http://127.0.0.1:8000` ;
- Vite ne doit servir que les assets/HMR du front Inertia, pas une seconde application standalone ;
- le frontend actif doit rester dans `resources/js` ;
- l'ancien frontend Vite standalone (`src/`, `index.html`, port `8080`) ne doit pas etre reactive comme deuxieme front ;
- l'identite visuelle initiale Stimergie doit etre reprise depuis `main`, notamment navigation, layout, dashboard, profil, galerie et pages metier.

Etat de reprise interface au 2026-05-30 apres les commits `01d4611` et `c9fce46` :

- navigation connectee reprise sur le modele `main` : logo, Banque d'images, Contact en navigation haute, menu utilisateur complet ;
- dashboard, profil, clients, projets, utilisateurs, gestion des images, droits d'acces, telechargements, contact et galerie ont ete rebranches en pages Inertia ;
- les pages utilisent des composants React dans `resources/js` et ne reactivent pas l'ancien front `src/` ;
- la galerie reprend le header beige, les filtres, la selection, le masonry et la pagination ;
- le loader progressif des images de `main` a ete porte dans le masonry Inertia : placeholder pulse, lazy loading via IntersectionObserver, spinner de chargement et fallback image indisponible ;
- dans la Banque d'images, le clic sur une image ouvre un panneau lateral de detail image, reprenant l'intention de la vue detail de `main` ;
- dans la gestion des images, le clic sur une image declenche l'action de modification ;
- dans la gestion des images, le nom du client peut ouvrir un panneau lateral d'informations client ;
- le contact est disponible sous forme de modale depuis la navigation, comme dans l'ancien front ;
- les modales visuelles de creation/modification projet, image et utilisateur sont presentes cote interface ;
- les boutons `Ajouter un projet` et `Ajouter une image` ouvrent leurs modales dediees.
- les formulaires projet sont maintenant persistants : creation/modification via Laravel/Inertia, validation serveur, autorisation par client et slug unique par client.
- la gestion globale des utilisateurs permet maintenant de creer/modifier un utilisateur, son role affichable, son statut et ses rattachements clients via Laravel/Inertia ; les appartenances client restent la source des droits metier.
- la gestion des images permet maintenant de creer/modifier une image, remplacer le fichier original, changer projet/client par rattachement projet, statut, orientation et tags ; le fichier est stocke sur le disque public Laravel en attendant le pipeline objet/variantes final.
- la Banque d'images propose une action groupee `Lier a un projet` sur les images selectionnees ; Laravel verifie les droits sur le projet cible et les clients source avant de rattacher les images en lot.

Ecarts connus au 2026-05-30 :

- la modale image est persistante pour le fichier original, les metadonnees, le projet et les tags ; la regeneration avancee des variantes Web/HD, l'optimisation image et le stockage objet cible restent a finaliser dans le pipeline Laravel ;
- la modale image permet visuellement de changer l'image et les informations, mais l'upload/remplacement fichier, la regeneration des variantes et la persistance des tags restent a implementer cote Laravel ;
- le panneau lateral de detail image affiche les informations disponibles depuis les props Laravel ; l'edition inline des tags, les partages et les telechargements avances de la vue detail historique restent a raccorder ;
- le panneau client lateral affiche les informations disponibles depuis les props Laravel dans les vues d'administration ; les actions avancees de fiche client restent a raccorder ;
- la pagination galerie est pilotee par Laravel pour le volume global, puis les filtres locaux s'appliquent sur les images chargees de la page courante. Une pagination serveur combinee aux filtres client/projet/tag/orientation devra remplacer cette transition ;
- les telechargements SD/HD et ZIP gardent l'interface historique mais le workflow asynchrone complet doit encore etre finalise cote jobs Laravel ;
- les pages publiques et blog/ressources restent a porter si le perimetre est confirme.

Amelioration produit identifiee : association rapide d'images a un projet

L'ancien fonctionnement oblige a saisir ou modifier le projet depuis les informations de chaque image. Ce flux est trop lent pour les operations courantes. La cible Laravel/Inertia doit ajouter un parcours plus efficace :

- selectionner plusieurs images depuis la galerie ;
- ouvrir une action groupee `Lier a un projet` ;
- proposer les projets disponibles et verifier les droits cote Laravel avant mutation ;
- permettre de filtrer rapidement par client puis projet ;
- afficher un recapitulatif avant validation : nombre d'images, projet cible, client cible, impacts sur les droits ;
- executer la mutation cote Laravel avec Form Request dediee ; l'audit log, l'event de rafraichissement et l'extension a la gestion des images restent a ajouter ;
- conserver la modification individuelle dans la modale image pour les corrections ponctuelles.

Cette amelioration est consideree meilleure que l'ancien flux et doit etre documentee comme une evolution volontaire, pas comme un ecart accidentel avec `main`.

## Architecture cible

### Backend

Laravel devient la source de verite :

- routes HTTP cote serveur ;
- controllers Inertia pour les pages applicatives ;
- Form Requests pour la validation ;
- Policies et Gates pour les permissions ;
- Jobs et queues pour les traitements longs ;
- Scheduler Laravel pour les taches periodiques ;
- events/listeners pour les effets metier ;
- tests feature pour les workflows critiques.

Les droits ne doivent pas dependre du frontend. React ne doit jamais masquer une operation en esperant que cela suffise a securiser l'acces. Toutes les mutations sensibles doivent passer par Laravel et etre protegees par policies.

## Perimetre metier a reproduire

La reecriture doit couvrir le produit existant, pas seulement une galerie.

### Comptes, clients et utilisateurs

La logique actuelle autour des utilisateurs et des clients doit etre simplifiee. Dans la nouvelle application, la gestion client/utilisateur doit etre un seul module metier : `Comptes & acces`.

Principe cible :

- un client est un espace metier ;
- un utilisateur est une personne qui peut appartenir a un ou plusieurs espaces clients ;
- le role principal d'un utilisateur est porte par son appartenance a un client, pas par un melange de champs globaux ;
- un administrateur global reste un cas particulier, gere au niveau application ;
- l'ancien concept d'admin client devient simplement une appartenance avec le role `owner` ou `manager` dans un espace client donne.

Fonctionnalites a conserver, mais avec une logique plus lisible :

- gestion des clients ;
- gestion des utilisateurs depuis la fiche d'un client ;
- rattachement d'un utilisateur a un ou plusieurs clients ;
- role par client : proprietaire, gestionnaire, membre ou lecteur ;
- possibilite pour un utilisateur multi-client de changer d'espace courant ;
- restrictions d'administration derivees du role dans le client courant ;
- consultation des tableaux de bord selon l'espace courant et les droits effectifs.

Ce modele remplace la confusion actuelle entre `role`, `admin_client`, `id_client` et `client_ids`. L'interface doit eviter deux gestions separees "Clients" et "Utilisateurs" qui racontent la meme relation depuis deux endroits differents. Le point d'entree principal doit etre le client : on administre un client, ses projets, ses membres et ses droits.

Vue produit recommandee :

- page `Clients` : liste des espaces clients ;
- fiche client : informations client, projets, membres, invitations, droits ;
- fiche utilisateur : profil personnel et recapitulatif de ses appartenances ;
- ecran global `Utilisateurs` reserve aux administrateurs globaux pour rechercher ou depanner.

### Gestion des projets

Fonctionnalites a conserver :

- creation, modification et suppression de projets ;
- rattachement d'un projet a un client ;
- nom metier du projet et nom de dossier/source ;
- filtrage et recherche des projets ;
- acces projet selon client, utilisateur et periode d'acces ;
- impact immediat des changements de projet sur la galerie.

### Periodes d'acces

Fonctionnalites a conserver :

- creation de periodes d'acces par projet/client ;
- activation/desactivation ;
- dates de debut et de fin ;
- filtrage par client, projet, statut et recherche ;
- application effective dans la galerie et les projets accessibles.

### Galerie et images

Fonctionnalites a conserver :

- affichage galerie pagine ou scroll infini ;
- filtres par client, projet, tag, categorie et orientation ;
- recherche texte ;
- selection multiple ;
- vue detail image ;
- affichage des metadonnees projet/client ;
- tags ;
- variantes d'image pour miniature, affichage web et telechargement HD ;
- prise en compte des droits d'acces dans toutes les requetes.

### Upload, import et enrichissement

Fonctionnalites a conserver ou reclarifier :

- import ou upload d'images par projet ;
- association automatique au client/projet ;
- generation ou stockage des variantes ;
- extraction dimensions/orientation ;
- generation ou edition de tags ;
- eventuelle analyse IA si elle reste utile au client ;
- suivi des erreurs d'import.

### Telechargements

Fonctionnalites a conserver :

- telechargement simple d'une image ;
- telechargement SD/Web ;
- telechargement HD ;
- generation ZIP asynchrone pour lots ;
- page de suivi des demandes de telechargement ;
- statuts `pending`, `processing`, `ready`, `failed`, `expired` ;
- expiration ou nettoyage des archives ;
- visibilite limitee au demandeur, sauf admin.

### Partages et albums

Fonctionnalites a conserver :

- creation d'albums partages ;
- selection d'images ;
- lien public ou semi-public avec cle de partage ;
- periode de validite ;
- invitations email si conservees ;
- consultation sans compte selon les regles metier ;
- retrait ou expiration d'un partage.

### Partage inter-clients

Fonctionnalites a conserver si validees comme encore necessaires :

- partage d'une image avec un autre client ;
- liste des clients secondaires ayant acces ;
- retrait du partage ;
- prise en compte dans la galerie et les controles d'acces.

Cette fonctionnalite existe dans le code actuel mais semble incomplete cote interface. Elle doit etre tranchee explicitement : soit reprise proprement, soit abandonnee volontairement.

### Blog, ressources et contenus editoriaux

Fonctionnalites a conserver si le client les utilise :

- articles de type Ressource ;
- articles de type Ensemble ;
- brouillon/publication ;
- image mise en avant ;
- categorie ;
- slug public ;
- edition par `super_admin`, `owner` ou `manager` selon le client rattache au contenu ;
- lecture authentifiee uniquement : Ensemble pour tous les utilisateurs connectes, Ressources limitees aux utilisateurs rattaches au client concerne.

Le blog ne doit pas etre traite comme secondaire sans validation metier. S'il est conserve, il doit avoir les memes exigences de droits, publication et migration que le reste.

### Administration et exploitation metier

Fonctionnalites a conserver :

- guide ou documentation admin si utile ;
- pages de debug uniquement en environnement autorise ;
- supervision des imports et telechargements ;
- relance des traitements en erreur ;
- visibilite sur les volumes et erreurs ;
- journalisation des actions sensibles.

### Frontend

React reste le langage d'interface, mais via Inertia.js :

- pas de SPA API-first pour les ecrans classiques ;
- routes Laravel comme source de routing ;
- pages React hydratees par props Inertia ;
- formulaires Inertia avec validation Laravel ;
- shared props pour `auth.user`, roles, permissions et flash messages ;
- composants React reutilisables pour galerie, filtres, modales et upload.

### Reprise du frontend historique

Le frontend historique sur `main` est la reference produit et design. Il est base sur React, Radix et shadcn, avec une identite visuelle deja validee. La migration doit donc suivre une logique de portage, pas de recreation.

Regles de reprise :

- recuperer les pages existantes depuis `main` quand elles existent ;
- conserver autant que possible les composants, libelles, layouts, interactions et workflows deja presents ;
- adapter les appels Supabase vers des props Inertia, routes Laravel, controllers, Form Requests, Policies et endpoints JSON Laravel quand necessaire ;
- ne pas remplacer une page existante par une page Laravel/Breeze generique ;
- supprimer progressivement les restes de formulaires ou layouts Breeze/Laravel quand ils apparaissent dans l'interface ;
- garder `resources/js` comme unique emplacement du front actif ;
- ne pas restaurer `src/` comme application separee.

Pages a reprendre et rebrancher en priorite depuis l'ancien frontend :

- tableau de bord administrateur et tableaux de bord selon role ;
- navigation principale et menu utilisateur ;
- page profil, avec le design de l'ancien front et non des formulaires type Laravel ;
- banque d'images / galerie ;
- contact ;
- galerie utilisateur ;
- vos telechargements ;
- gestion des images ;
- gestion des clients ;
- projets ;
- utilisateurs ;
- droits d'acces / periodes d'acces ;
- blog / ressources si conserve ;
- pages publiques utiles : a propos, conditions, confidentialite, licences ;
- footer et structure de navigation publique si toujours presents dans le parcours cible.

Chaque page reprise doit faire l'objet d'un rebranchement explicite :

- route Laravel nommee ;
- controller ou closure temporaire Inertia clairement identifiee ;
- props Inertia minimales pour charger la page ;
- remplacement des hooks Supabase par des hooks/services Laravel ou props serveur ;
- preservation du design existant ;
- tests ou verification navigateur quand la page devient active.

Le portage peut etre fait par lots. Les liens du menu ne doivent etre actives que lorsque la page cible existe cote Laravel/Inertia et ne depend plus directement du client Supabase.

### Reprise backend et fonctionnelle

Chaque page ou fonctionnalite reprise depuis `main` doit etre rebranchee completement cote Laravel. Il ne suffit pas de porter le JSX ou le design.

Pour chaque fonctionnalite, le lot de migration attendu comprend :

- route Laravel nommee ;
- controller ou action Inertia dediee ;
- props Inertia ou endpoints JSON necessaires ;
- modeles Eloquent et relations correspondantes ;
- Form Requests pour les mutations ;
- Policies/Gates pour toutes les operations sensibles ;
- services metier quand la logique depasse un controller simple ;
- Jobs/queues quand le traitement est long ou asynchrone ;
- remplacement des appels Supabase, RPC et Edge Functions par Laravel, Eloquent, services ou jobs ;
- etats de chargement, erreurs et messages flash ;
- tests feature pour les workflows critiques ;
- verification navigateur quand le parcours devient actif.

Definition de termine pour une page migree :

- elle reprend l'interface et les interactions de l'ancien front ;
- elle lit les donnees depuis Laravel/PostgreSQL ou depuis une source explicitement acceptee pendant la transition ;
- elle ecrit via Laravel et non via Supabase depuis le navigateur ;
- elle applique les permissions cote serveur ;
- elle gere les erreurs de validation et d'autorisation ;
- elle ne depend plus du client Supabase frontend ;
- elle est accessible depuis la navigation uniquement si le parcours est fonctionnel.

Les anciennes fonctionnalites Supabase doivent etre cartographiees une par une :

- hooks React et services Supabase -> controllers, services Laravel, props Inertia ou endpoints JSON ;
- RPC Supabase -> queries Eloquent, scopes, services ou actions dediees ;
- Edge Functions -> Jobs Laravel, commandes Artisan, services backend ou endpoints controles ;
- RLS Supabase -> Policies Laravel et scopes d'acces ;
- Supabase Realtime/cache frontend -> strategie Laravel explicite : props serveur, polling, events ou cache serveur si necessaire ;
- Supabase Storage/O2Switch -> object storage cible et URLs signees serveur.

La migration doit donc reprendre le produit complet : UI, donnees, mutations, droits, traitements asynchrones et exploitation. Toute simplification fonctionnelle doit etre une decision explicite, pas un effet secondaire du portage.

Des endpoints JSON restent possibles pour les besoins techniques :

- upload direct multipart ;
- autocomplete ;
- polling de jobs ;
- endpoints de presigned URLs ;
- interactions tres dynamiques de galerie.

### Base de donnees

PostgreSQL reste le choix recommande.

Laravel doit posseder les migrations, seeders et factories. Le schema doit pouvoir etre reconstruit de zero en local et en staging.

Tables coeur pressenties :

- `users`
- `clients`
- `client_memberships`
- `projects`
- `project_access_periods`
- `images`
- `image_variants`
- `tags`
- `image_tag`
- `image_client_shares`
- `shared_albums`
- `shared_album_images`
- `download_jobs`
- `blog_posts`
- `audit_logs`
- `imports`
- `import_items`

La table `client_memberships` est le pivot central de la simplification.

Champs pressentis :

- `id`
- `client_id`
- `user_id`
- `role` : `owner`, `manager`, `member`, `viewer`
- `status` : `active`, `invited`, `disabled`
- `is_default`
- `created_by`
- timestamps

Le role global doit etre limite au strict necessaire :

- `super_admin` pour l'administration de toute la plateforme ;
- aucun role global pour exprimer une appartenance client.

Un utilisateur standard rattache a deux clients n'a donc pas deux concepts concurrents. Il a deux lignes dans `client_memberships`.

### Stockage images

Les images ne doivent plus etre servies comme fichiers disperses sur disque web.

Object storage cible :

- compatible S3 ;
- region France ou Europe selon exigence client ;
- chiffrement au repos si disponible ;
- policies bucket strictes ;
- URLs signees pour les fichiers prives ;
- CDN possible uniquement si compatible avec les exigences d'acces.

Options a comparer :

- Scaleway Object Storage ;
- OVHcloud Object Storage ;
- Hetzner Object Storage ;
- MinIO self-hosted ;
- AWS S3 si la contrainte de souverainete le permet.

Decision initiale :

- un bucket Scaleway Object Storage prive a ete cree ;
- region : `fr-par` / `PAR` ;
- bucket : `stimergie` ;
- les secrets doivent rester cote serveur, scripts de migration ou Edge Functions ;
- aucune cle Scaleway ne doit etre exposee avec un prefixe `VITE_`.

Variables serveur attendues :

```text
SCALEWAY_ACCESS_KEY_ID=...
SCALEWAY_SECRET_KEY=...
SCALEWAY_OBJECT_STORAGE_BUCKET=stimergie
SCALEWAY_OBJECT_STORAGE_REGION=fr-par
SCALEWAY_OBJECT_STORAGE_ENDPOINT=https://s3.fr-par.scw.cloud
```

Premier lot technique demarre :

- les Edge Functions de generation ZIP utilisent Scaleway Object Storage en priorite si ces variables sont configurees ;
- le bucket restant prive, les URLs retournees sont des URLs signees temporaires ;
- les anciens chemins O2Switch / Supabase Storage restent en fallback tant que la migration n'est pas terminee.

Convention de cles proposee :

```text
clients/{client_uuid}/projects/{project_uuid}/images/{image_uuid}/original.{ext}
clients/{client_uuid}/projects/{project_uuid}/images/{image_uuid}/thumb.webp
clients/{client_uuid}/projects/{project_uuid}/images/{image_uuid}/web.webp
clients/{client_uuid}/projects/{project_uuid}/images/{image_uuid}/hd.{ext}
```

La base stocke les metadonnees et les cles objet, pas des URLs reconstruites depuis des titres.

### Pipeline image

Le pipeline cible doit etre explicite :

1. Upload ou import d'un fichier original.
2. Calcul checksum et detection doublons.
3. Extraction metadonnees : largeur, hauteur, mime, poids, orientation.
4. Stockage original.
5. Creation des variantes via job queue.
6. Mise a jour du statut image.
7. Indexation tags/recherche.
8. Publication dans la galerie.

Statuts possibles :

- `pending_upload`
- `uploaded`
- `processing`
- `ready`
- `failed`
- `archived`

Chaque echec de traitement doit etre visible en admin et relancable.

## Permissions

Le modele de permissions doit etre fonde sur deux niveaux simples.

Niveau plateforme :

- `super_admin` : voit et gere toute l'application.

Niveau client :

- `owner` : gere le client, les membres, les projets et les droits ;
- `manager` : gere les projets, images, partages et utilisateurs standards du client ;
- `member` : consulte et telecharge selon les projets autorises ;
- `viewer` : consulte uniquement selon les projets autorises.

Regles principales :

- un `super_admin` voit et gere tout ;
- un utilisateur non `super_admin` agit toujours dans le contexte d'un client courant ;
- les droits d'administration viennent de `client_memberships.role` ;
- un utilisateur multi-client choisit ou conserve un client courant ;
- un manager client ne peut gerer que les membres et projets de ses propres clients ;
- un membre ou lecteur voit uniquement les projets autorises ;
- les periodes d'acces limitent la visibilite des projets ;
- les albums partages ont leurs propres regles d'expiration ;
- les telechargements ne sont visibles que par leur demandeur, sauf `super_admin` ou role client autorise.

Cette approche garde la fonctionnalite principale actuelle, mais supprime les cas ambigus :

- plus de champ `id_client` concurrent de `client_ids` ;
- plus de role global `admin_client` ;
- plus de logique speciale dispersee selon "un client" ou "plusieurs clients" ;
- tout passe par une appartenance client explicite.

Implementation recommandee :

- Policies Laravel par modele ;
- scopes Eloquent pour les filtres d'acces recurrents ;
- tests feature par role ;
- audit log sur les operations sensibles.

Exemples de policies :

- `ClientPolicy::view` : autorise si `super_admin` ou membre actif du client ;
- `ClientPolicy::manageMembers` : autorise si `super_admin`, `owner` ou `manager` du client ;
- `ProjectPolicy::view` : autorise si membre actif du client et projet accessible ;
- `ImagePolicy::download` : autorise si image dans un projet accessible ;
- `DownloadJobPolicy::view` : autorise si demandeur, `super_admin`, ou gestionnaire du client concerne.

## Fonctionnalites MVP

Le MVP doit rester vertical, mais il doit etre metier, pas uniquement technique. Il doit prouver que la nouvelle architecture sait reproduire le cycle principal : administrer un client, administrer un projet, importer des images, controler les droits, consulter la galerie, partager ou telecharger.

Le MVP ne doit pas chercher a reprendre toute l'application actuelle des le premier lot, mais il doit etre compatible avec la reprise complete du perimetre metier.

Priorite 1 :

- login/logout ;
- gestion clients comme espaces metier ;
- gestion des membres depuis une fiche client ;
- role par appartenance client ;
- gestion projets ;
- upload ou import images ;
- stockage object storage ;
- generation variantes ;
- galerie paginee ;
- filtres client/projet/orientation/tags ;
- droits par role ;
- telechargement d'une image ;
- demande de ZIP asynchrone ;
- page de suivi des telechargements.

Priorite 2 :

- periodes d'acces ;
- albums partages ;
- partage d'images entre clients ;
- recherche avancee ;
- audit logs ;
- tableau de bord par espace client et tableau de bord plateforme pour les `super_admin`.

Priorite 3 :

- blog / ressources, si usage metier confirme ;
- analyse IA, si usage metier confirme ;
- synchronisation depuis source externe ;
- optimisations CDN ;
- workflows editoriaux.

Fonctionnalites a ne pas perdre pendant la migration :

- acces multi-client par utilisateur, via appartenances client explicites ;
- difference super admin / gestionnaire client ;
- periodes d'acces projet ;
- albums partages avec expiration ;
- telechargements HD et ZIP ;
- contenus publies vs brouillons ;
- audit et relance des traitements en erreur.

## Backlog de portage UI depuis `main`

Ce backlog complete le MVP technique. Il sert a eviter de livrer une application Laravel/Inertia fonctionnelle mais visuellement ou ergonomiquement differente du produit initial.

### Lot UI 1 - Navigation et structure

- aligner la navigation connectee sur l'ancien front ;
- reprendre le menu utilisateur complet : Galerie, Projets, Vos telechargements, Profil, Gestion des images, Gestion des clients, Droits d'acces, Gestion des utilisateurs, Deconnexion ;
- garder la navigation haute courte : logo, Banque d'images, Contact ;
- supprimer les sous-headers ou divs heritees de Laravel/Breeze qui ne sont pas dans la cible ;
- reprendre le footer de l'ancien front quand le layout connecte l'affiche ;
- verifier les breakpoints mobile/tablette.

### Lot UI 2 - Profil

- remplacer la page profil actuelle par l'interface de l'ancien front ;
- conserver les formulaires Laravel/Inertia uniquement comme mecanique de soumission, pas comme design ;
- reprendre les champs, sections, espacements, textes et boutons de l'ancien front ;
- brancher les mutations sur `ProfileController` et `PasswordController` existants ou les adapter proprement.

### Lot UI 3 - Pages metier a activer

- Banque d'images / Galerie ;
- Contact ;
- Vos telechargements ;
- Gestion des images ;
- Gestion des clients ;
- Projets ;
- Utilisateurs ;
- Droits d'acces ;
- Blog/Ressources si conserve.

Pour chaque page, la consigne est de partir du code de `main`, puis de remplacer les dependances Supabase par le backend Laravel. Les fonctionnalites deja implementees dans l'ancien front ne doivent pas etre reinterpretees ou simplifiees sans decision explicite.

Chaque page de ce lot doit etre livree avec son backend minimal fonctionnel :

- lecture des donnees depuis Laravel ;
- mutations branchees sur Laravel ;
- permissions serveur ;
- erreurs et validations Laravel ;
- tests ou verification de parcours.

Une page visuellement portee mais sans backend n'est qu'un brouillon et ne doit pas etre consideree comme activee.

### Lot UI 4 - Nettoyage des restes Laravel/Breeze

- rechercher regulierement les composants `PrimaryButton`, `SecondaryButton`, `DangerButton`, `TextInput`, `InputLabel`, `GuestLayout` generiques quand ils produisent un rendu Breeze ;
- remplacer par les composants shadcn/Radix du design Stimergie ou par les composants recuperes de l'ancien front ;
- verifier que les pages auth, profil, clients et dashboard ne contiennent plus de blocs visuels "Laravel starter kit".

## Migration des donnees

La migration doit etre idempotente et traçable.

Etapes recommandees :

1. Export schema et donnees Supabase.
2. Cartographie des tables existantes vers le nouveau modele.
3. Identification des donnees inutiles ou historiques a exclure.
4. Migration users/clients/projects.
5. Transformation des anciens droits en `client_memberships`.
6. Migration metadonnees images.
7. Copie des fichiers vers object storage.
8. Verification checksum / poids / existence objet.
9. Migration albums et partages.
10. Migration telechargements uniquement si necessaire.
11. Migration blog uniquement si conserve.

Les scripts doivent pouvoir etre relances sans creer de doublons.

Regle de transformation recommandee pour les utilisateurs :

- ancien `admin` -> `super_admin` ;
- ancien `admin_client` avec `id_client` ou `client_ids` -> membre `owner` ou `manager` des clients correspondants ;
- ancien `user` avec `id_client` ou `client_ids` -> membre `member` ou `viewer` des clients correspondants ;
- si un utilisateur a plusieurs clients, creer une ligne `client_memberships` par client ;
- ne pas conserver la double logique `id_client` + `client_ids` dans le nouveau schema.

Cle de correspondance recommandee :

- conserver les UUID Supabase en colonnes `legacy_id` quand utile ;
- generer de nouveaux IDs internes si necessaire ;
- tenir une table `legacy_mappings`.

## Strategie de bascule

Approche recommandee : migration progressive avec phase parallele.

1. L'application actuelle reste en production.
2. La nouvelle application est developpee sur un environnement staging.
3. Un premier import complet est fait depuis Supabase.
4. Les images sont copiees vers object storage.
5. La recette se fait sur donnees reelles.
6. Les ecarts sont corriges.
7. Une fenetre de gel d'ecriture est planifiee.
8. Migration finale incrementale.
9. Bascule DNS / application.
10. Ancienne application conservee en lecture seule pendant une periode definie.

## Exploitation

Elements a prevoir des le depart :

- backup PostgreSQL quotidien ;
- backup ou versioning object storage ;
- monitoring des queues ;
- relance des jobs echoues ;
- logs applicatifs centralises ;
- alertes sur erreurs 5xx et jobs en echec ;
- rotation des secrets ;
- environnements separes : local, staging, production ;
- procedure documentee de restauration.

## Tests

Tests indispensables :

- auth ;
- policies par role ;
- acces galerie par client/projet ;
- periodes d'acces ;
- upload image ;
- generation variantes ;
- creation ZIP ;
- albums partages ;
- non-regression sur brouillons blog si le blog est conserve ;
- migration idempotente.

Le niveau minimum vise pour demarrer :

- tests feature Laravel pour les parcours critiques ;
- tests unitaires sur services metier ;
- quelques tests navigateur Playwright sur login, galerie et upload.

## Risques

### Risque de reecriture trop large

Ne pas refaire tout le produit avant de montrer une version utilisable. Le MVP doit etre vertical : auth, client, projet, image, galerie, stockage.

### Risque migration images

Le stockage actuel par disque/URL reconstruite peut contenir des incoherences : noms, accents, casse, extensions, fichiers manquants. Il faut auditer avant de promettre une migration totale.

### Risque permissions

Les regles actuelles sont dispersees. Il faut les reecrire explicitement et les tester par role, sinon la nouvelle application reproduira les memes incertitudes.

### Risque delai

Laravel/Inertia simplifie l'architecture, mais la migration images + droits + donnees reelles reste le cout principal.

### Risque exploitation

L'object storage, les queues et les backups doivent etre operationnels avant la mise en production, pas ajoutes apres.

## Decisions a prendre

1. Object storage cible.
2. Hebergement cible de Laravel/PostgreSQL.
3. Niveau de souverainete attendu.
4. Conservation ou abandon du blog.
5. Conservation ou abandon de l'analyse IA.
6. Source officielle des images : upload manuel, import FTP, synchro externe, ou mixte.
7. Duree de conservation de l'ancienne application en lecture seule.
8. Strategie CDN ou URLs signees uniquement.
9. Besoin multi-langue ou non.
10. Niveau d'audit attendu par le client.

## Prochaine etape recommandee

Produire une note de decision courte avec trois options :

1. corriger l'application Supabase actuelle ;
2. self-host Supabase ;
3. migrer vers Laravel + React/Inertia.

Pour chaque option :

- cout estime ;
- delai ;
- risques ;
- dette restante ;
- impact exploitation ;
- impact securite ;
- recommandation.

Si l'option Laravel est retenue, demarrer par un spike de 2 a 3 jours :

- projet Laravel/Inertia minimal ;
- PostgreSQL ;
- login ;
- un client ;
- un projet ;
- upload d'une image vers object storage ;
- generation d'une miniature ;
- affichage galerie.

Ce spike doit valider le socle avant tout engagement de reecriture complete.
