# Perimetre des corrections client

Date : 2026-05-27

## Objectif

Ce document reprend les elements signes par le client, les traduit en livrables techniques concrets, et indique ce qui est deja pris en compte dans le projet, ce qui est partiel, et ce qui reste a livrer.

References externes utiles :

- Upload de dossier cote navigateur : l'attribut `webkitdirectory` permet a un champ `<input type="file">` de selectionner un dossier, avec `multiple`, et les fichiers exposes peuvent conserver un chemin relatif via `webkitRelativePath`. Reference : https://developer.mozilla.org/en-US/docs/Web/API/HTMLInputElement/webkitdirectory
- Securite Supabase : les droits ne doivent pas etre uniquement controles par l'interface. Supabase recommande Row Level Security, avec des policies attachees aux tables, comme couche de controle d'acces cote base/API. Reference : https://supabase.com/docs/guides/database/postgres/row-level-security
- Storage Supabase : les uploads vers Storage doivent aussi etre couverts par des policies RLS sur `storage.objects`. Reference : https://supabase.com/docs/guides/storage/security/access-control

## Lecture precise du perimetre signe

### 1. Reprise et stabilisation de l'existant

#### Prise en main du code source et de l'environnement existant

Signification concrete :

- comprendre la stack Vite/React/TypeScript/Supabase ;
- identifier les routes, les hooks, les Edge Functions, les migrations et les variables d'environnement ;
- documenter le fonctionnement et les zones a risque.

Statut : **deja pris en compte en grande partie**.

Elements constates :

- analyse initiale du projet effectuee ;
- documentation existante dans `docs/audit-technique.md`, `docs/documentation-fonctionnelle.md`, `docs/solutions-apportees.md`, `docs/supabase-dev-environment.md` ;
- environnement Supabase de dev documente ;
- `npm run build` passe, avec warnings de chunking ;
- `npm run lint` reste degrade globalement.

Reste a faire :

- transformer l'audit en backlog priorise et verifiable ;
- corriger les erreurs lint bloquantes progressivement, sans modifier les fonctionnalites.

#### Audit operationnel cible et securisation de la configuration utile au perimetre

Signification concrete :

- verifier les variables d'environnement utiles ;
- eviter les secrets en dur ou exposes cote frontend ;
- verifier RLS/policies sur les tables concernees ;
- verifier les Edge Functions necessaires aux livrables.

Statut : **partiellement pris en compte**.

Elements constates :

- le client Supabase lit `VITE_SUPABASE_URL`, `VITE_SUPABASE_PUBLISHABLE_KEY` et `VITE_SUPABASE_PROJECT_ID` ;
- des migrations recentes traitent des points RLS, notamment blog drafts et tables FTP ;
- les cron Supabase ont ete securises dans la branche courante ;
- les fonctions ZIP/queue ont ete revues dans la branche courante.

Reste a faire :

- verifier les policies liees aux futurs uploads images et dossiers ;
- verifier les policies de partage projet/image ;
- s'assurer que le `service_role` reste uniquement cote Edge Functions ;
- definir une check-list de configuration par environnement : dev, preview, prod.

#### Correction des bugs visibles par les utilisateurs, avec priorite a l'affichage des images

Signification concrete :

- corriger les erreurs qui empechent de voir, ouvrir, filtrer ou telecharger les images ;
- stabiliser les URLs d'affichage, miniatures, SD et HD ;
- eviter les regressions visibles dans la galerie, les pages detail et les albums partages.

Statut : **partiellement pris en compte**.

Elements constates :

- des generateurs d'URL existent pour display/SD/HD ;
- la galerie utilise des hooks dedies et un cache React Query ;
- les corrections recentes autour des telechargements ne doivent pas transformer le telechargement simple en ZIP ;
- le build passe.

Reste a faire :

- tester les parcours galerie avec vrais comptes `admin`, `admin_client`, `user` ;
- verifier les images manquantes, miniatures absentes, erreurs CORS ou URLs mal formees ;
- conserver le telechargement simple pour une image unique ;
- limiter le ZIP aux selections multiples ou aux seuils prevus.

#### Harmonisation des parcours et alignement des regles d'acces entre frontend et backend

Signification concrete :

- le frontend ne doit pas simuler seul les permissions ;
- les filtres visibles doivent correspondre aux droits reels ;
- les RPC/RLS doivent etre la source de verite ;
- la galerie, les pages admin, les uploads et les partages doivent appliquer les memes regles.

Statut : **partiellement pris en compte**.

Elements constates :

- `get_accessible_projects` existe et filtre les projets accessibles selon role, clients et periodes ;
- `user_roles` semble etre devenu la source de verite pour les roles ;
- les profils supportent `client_ids` pour le multi-client ;
- les routes React appliquent des `ProtectedRoute`.

Reste a faire :

- aligner les partages image/projet avec `get_accessible_projects` ou des RPC dediees ;
- verifier que les admin clients ne voient et ne modifient que leur perimetre ;
- eviter les controles uniquement frontend ;
- corriger les composants avec hooks conditionnels qui peuvent casser les parcours.

#### Prise en compte des integrations existantes necessaires au bon fonctionnement des livrables

Signification concrete :

- ne pas casser Supabase Auth, Storage, Edge Functions, Mailjet, analyse IA, synchronisation Dropbox/FTP, generation ZIP ;
- reutiliser les mecanismes existants si pertinents ;
- documenter les dependances de chaque livrable.

Statut : **deja pris en compte en analyse, a maintenir pendant les developpements**.

Elements constates :

- Supabase Auth et RLS ;
- Supabase Storage pour les uploads actuels ;
- Edge Functions `analyze-image-ai`, `send-album-invitation`, `generate-zip`, `process-queue`, `cache-dropbox-images` ;
- Mailjet pour contact et invitations ;
- mecanismes de sync/cache Dropbox/FTP.

Reste a faire :

- verifier quelles integrations sont requises pour l'upload multi-images et dossier ;
- eviter les nouveaux flux paralleles qui dupliquent les mecanismes existants.

## 2. Evolutions fonctionnelles

### Upload multi-images

Signification concrete :

- permettre a un utilisateur autorise de selectionner plusieurs images en une fois ;
- rattacher toutes les images au meme projet ;
- calculer dimensions/orientation par fichier ;
- generer ou saisir les tags ;
- afficher une progression globale et par image ;
- gerer les echecs partiels sans bloquer tout le lot ;
- invalider/rafraichir la galerie apres upload.

Statut : **non livre**.

Elements constates :

- `src/components/images/ImageUploadForm.tsx` gere un seul `File` ;
- l'input fichier n'a pas `multiple` ;
- l'etat de formulaire est mono-image : `file`, `preview`, `dimensions`, `title`.

Reste a livrer :

- modele d'etat multi-fichiers ;
- preview/listing des fichiers ;
- upload sequentiel ou concurrent limite ;
- creation des lignes `images` pour chaque fichier ;
- gestion des tags par lot et/ou par image ;
- feedback d'erreur par image.

### Upload d'un dossier complet par projet

Signification concrete :

- permettre de choisir un dossier local complet ;
- importer toutes les images du dossier, possiblement avec sous-dossiers ;
- conserver le chemin relatif si utile ;
- rattacher le lot a un projet choisi ;
- definir comment les noms de fichiers deviennent `title`, tags ou metadonnees ;
- gerer les doublons.

Statut : **non livre**.

Elements constates :

- aucun `webkitdirectory` trouve dans le code ;
- aucun parcours dossier dedie ;
- le projet connait deja `nom_dossier`, mais c'est aujourd'hui surtout lie aux URLs/synchronisation existantes.

Reste a livrer :

- input dossier via `webkitdirectory multiple` ;
- lecture de `file.webkitRelativePath` ;
- filtrage images uniquement ;
- mapping dossier/fichier vers projet et metadonnees ;
- strategie anti-doublons ;
- progression et erreurs partielles.

### Droits de partage au niveau projet

Signification concrete :

- permettre de partager un projet complet avec un ou plusieurs clients/utilisateurs ;
- completer le systeme d'acces existant sans le remplacer ;
- faire apparaitre toutes les images du projet partage dans la galerie des destinataires ;
- appliquer le droit cote backend/RLS/RPC ;
- distinguer partage permanent, temporaire ou borne par dates si le client le souhaite.

Statut : **non livre comme mecanisme dedie**.

Elements constates :

- `project_access_periods` existe pour gerer des periodes d'acces client/projet ;
- `get_accessible_projects` utilise les clients et periodes d'acces ;
- aucune table ou service dedie de type `project_shared_clients` / `project_shares` n'a ete trouve.

Point de clarification :

- soit le partage projet est implemente comme une extension de `project_access_periods` ;
- soit il faut une table dediee de partage projet pour separer "periode d'acces contractuelle" et "partage manuel".

Reste a livrer :

- choix du modele de donnees ;
- migration SQL ;
- policies RLS/RPC ;
- UI de gestion du partage projet ;
- integration dans les requetes galerie/projets ;
- tests par roles.

### Droits de partage au niveau image

Signification concrete :

- finaliser le mecanisme permettant de partager une image precise avec un client ;
- afficher les partages existants ;
- ajouter/retirer un partage ;
- faire apparaitre l'image partagee chez le client destinataire ;
- appliquer les droits cote backend/RLS/RPC.

Statut : **partiellement existant, non finalise**.

Elements constates :

- table `image_shared_clients` presente ;
- service `src/services/gallery/sharingService.ts` present ;
- policies RLS presentes pour lecture admin/admin_client/user ;
- composant `ImageSharingManager` present mais retourne `null` pour les non-admins et `return;` apres le loading, donc l'UI de gestion ne rend pas de contenu utile actuellement ;
- les requetes galerie principales ne semblent pas encore integrer les images partagees via `image_shared_clients`.

Reste a livrer :

- corriger l'UI `ImageSharingManager` ;
- definir qui peut partager : admin seulement ou aussi admin_client ;
- integrer les images partagees dans les RPC/requetes de galerie ;
- verifier les policies INSERT/DELETE/SELECT ;
- afficher clairement les clients avec lesquels une image est partagee ;
- tester ajout, retrait, visibilite destinataire et non-visibilite hors perimetre.

## Priorisation proposee

1. Stabiliser les bugs visibles et les droits existants : affichage images, telechargement simple, routes, hooks conditionnels, RLS critique.
2. Finaliser le partage image, car le modele existe deja mais n'est pas completement raccorde.
3. Definir et livrer le partage projet, car il impacte la source de verite des droits.
4. Livrer l'upload multi-images.
5. Livrer l'upload dossier complet par projet, qui est une extension naturelle du multi-upload.

## Criteres d'acceptation transverses

- Chaque fonctionnalite doit etre testee avec `admin`, `admin_client` et `user`.
- Les droits doivent etre verifies cote Supabase, pas uniquement par affichage/masquage frontend.
- Une erreur sur une image ne doit pas bloquer tout un lot d'upload si les autres fichiers sont valides.
- Les changements doivent conserver les integrations existantes.
- `npm run build` doit passer avant livraison.
- Les erreurs lint nouvelles doivent etre evitees ; les erreurs lint historiques doivent etre traitees par lots dedies.
