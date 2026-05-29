# Documentation fonctionnelle - Stimergie Image Hub

## 1. Objet du projet

Stimergie Image Hub est une application web de banque d'images et de gestion de ressources visuelles. Elle permet a Stimergie et a ses clients de centraliser des images par client et par projet, de controler les droits d'acces dans le temps, de telecharger les visuels en qualite web ou HD, et de partager des albums avec des contacts externes via des liens temporaires.

L'application inclut aussi des fonctions de gestion de contenus editoriaux, de gestion des utilisateurs, de contact, de profil et de pages legales.

## 2. Publics et roles

### Administrateur `admin`

L'administrateur dispose de l'acces global a la plateforme. Il peut :

- consulter les statistiques globales du tableau de bord ;
- gerer les clients ;
- gerer les projets ;
- gerer les images ;
- gerer les utilisateurs et leurs affectations clients ;
- gerer les periodes d'acces aux projets ;
- consulter la banque d'images complete ;
- acceder aux outils d'administration, de cache et de debug ;
- gerer les contenus editoriaux et les pages legales.

### Administrateur client `admin_client`

L'administrateur client represente un ou plusieurs clients. Il peut :

- consulter les donnees des clients auxquels il est associe ;
- voir les projets et images accessibles pour ces clients ;
- gerer des utilisateurs standards rattaches a ses clients ;
- acceder a la banque d'images et a la gestion des images ;
- telecharger des images et suivre ses demandes de telechargement.

Les fonctions Edge Supabase limitent explicitement les actions d'un `admin_client` : il ne peut creer que des utilisateurs standards et uniquement pour les clients auxquels il est rattache.

### Utilisateur `user`

L'utilisateur final consomme les visuels mis a disposition pour son ou ses clients. Il peut :

- acceder a la banque d'images selon ses droits ;
- filtrer, consulter, selectionner et telecharger des images ;
- consulter ses demandes de telechargement ;
- modifier son profil et son mot de passe ;
- utiliser le formulaire de contact.

## 3. Parcours principaux

### Connexion et session

L'utilisateur se connecte par email et mot de passe depuis la page d'accueil ou `/auth`. La session est geree par Supabase Auth. L'application recupere ensuite le role et les clients rattaches via des fonctions RPC Supabase.

La validite de session est controlee regulierement. En cas de jeton expire ou invalide, l'utilisateur est deconnecte et redirige vers la connexion.

Le mot de passe peut etre reinitialise via `/reset-password` avec le flux standard de recuperation Supabase.

### Tableau de bord

La page `/` affiche un contenu different selon le role :

- `admin` : statistiques globales sur les clients, projets et images, puis raccourcis de gestion ;
- `admin_client` : statistiques client, derniers visuels et acces rapides ;
- `user` : redirection directe vers la banque d'images ou affichage des derniers visuels accessibles.

### Banque d'images

La page `/gallery` est le coeur fonctionnel de l'application. Elle permet de :

- afficher les images en grille masonry ;
- rechercher dans les titres et tags ;
- filtrer par onglet/categorie, client, projet et orientation ;
- alterner entre pagination classique et scroll infini ;
- selectionner une ou plusieurs images ;
- ouvrir une fiche detaillee ;
- telecharger en format web ou HD ;
- partager une selection sous forme d'album temporaire.

Les images affichees dependent du role et des periodes d'acces. Les administrateurs voient l'ensemble du catalogue. Les autres roles voient uniquement les projets accessibles pour leurs clients rattaches et pour la periode courante.

### Detail d'une image

Depuis la galerie ou les tableaux de bord, l'utilisateur peut ouvrir une image en detail. La fiche detaillee permet de consulter :

- le visuel ;
- son titre ;
- ses tags ;
- ses dimensions et son orientation ;
- son projet et son client lorsque l'information est disponible ;
- les actions de telechargement web ou HD.

Les administrateurs disposent aussi de fonctions de gestion de tags et de partage client selon les composants disponibles.

### Telechargement

Le telechargement existe sous plusieurs formes :

- telechargement direct d'une image ;
- telechargement d'une selection sous forme de ZIP ;
- telechargement web, base sur les images standard ;
- telechargement HD, base sur l'URL HD du dossier projet.

Pour les petits lots, le ZIP peut etre prepare cote navigateur. Pour les lots plus importants, l'application cree une demande de telechargement et uploade le ZIP sur l'hebergement Stimergie/O2Switch.

Seuils actuellement codes :

- telechargement web cote serveur a partir de 10 images ;
- telechargement HD cote serveur a partir de 3 images ;
- division en plusieurs archives au-dela de 50 images.

La page `/downloads` liste les demandes de telechargement avec leur statut :

- `pending` ;
- `processing` ;
- `ready` ;
- `failed` ;
- `expired`.

Elle se rafraichit automatiquement toutes les 30 secondes et ecoute les changements temps reel Supabase sur `download_requests`.

### Partage d'album externe

Une selection d'images peut etre partagee sous forme d'album :

1. l'utilisateur selectionne des images ;
2. il ouvre le formulaire de partage ;
3. il renseigne le nom de l'album, une description, les emails destinataires, un message et une periode d'acces ;
4. l'application cree un enregistrement `albums` avec une cle de partage ;
5. elle associe les images dans `album_images` ;
6. elle envoie les invitations via la fonction Edge `send-album-invitation`.

Les destinataires accedent a l'album via `/shared-album/:shareKey`. L'album est consultable uniquement pendant la periode configuree. Un bouton permet de telecharger toutes les images de l'album en ZIP.

### Gestion des images

La page `/images` sert d'interface de gestion et d'inventaire. Elle permet de :

- afficher les images en liste ou en grille ;
- filtrer par client, orientation, titre et tag ;
- paginer les resultats ;
- ouvrir un formulaire d'ajout d'image.

Le formulaire d'upload permet de :

- choisir un fichier image ;
- detecter automatiquement les dimensions et l'orientation ;
- rattacher l'image a un projet ;
- definir un titre et une description ;
- ajouter des tags manuellement ;
- demander une analyse IA pour suggerer des tags ;
- enregistrer le fichier dans Supabase Storage et les metadonnees dans `images`.

L'analyse IA utilise la fonction Edge `analyze-image-ai`, qui appelle l'API OpenAI.

### Gestion des clients

La page `/clients` est reservee aux administrateurs. Elle permet de :

- lister les clients en cartes ou en tableau ;
- rechercher un client ;
- creer un client ;
- modifier ses informations ;
- supprimer un client.

La suppression est bloquee si le client est encore associe a des utilisateurs ou a des projets.

Les donnees client principales sont :

- nom ;
- email ;
- telephone ;
- contact principal ;
- logo.

### Gestion des projets

La page `/projects` permet de consulter et gerer les projets. Un projet appartient a un client et contient notamment :

- nom du projet ;
- type de projet ;
- client associe ;
- nom de dossier, utilise pour construire les URLs des images sur `stimergie.fr/photos`.

Les administrateurs et administrateurs clients peuvent creer, modifier ou supprimer des projets. Les utilisateurs standards ont un acces de consultation selon leurs droits.

Lors de la creation d'un projet, une periode d'acces par defaut est creee automatiquement par trigger SQL.

### Gestion des droits d'acces

La page `/access-periods` est reservee aux administrateurs. Elle gere la table `project_access_periods`.

Une periode d'acces definit :

- le client ;
- le projet ;
- une date de debut ;
- une date de fin ;
- un statut actif/inactif.

L'application valide qu'une date de fin est posterieure a la date de debut. Les administrateurs peuvent creer, modifier, activer/desactiver et supprimer les periodes.

Ces periodes sont utilisees par les fonctions RPC `check_project_access`, `get_accessible_projects` et `get_accessible_projects_details`.

### Gestion des utilisateurs

La page `/users` est accessible aux administrateurs et administrateurs clients. Elle permet de :

- lister les utilisateurs ;
- filtrer/rechercher selon les donnees disponibles ;
- creer un utilisateur ;
- modifier un utilisateur ;
- changer son mot de passe ;
- supprimer son profil.

La creation et la mise a jour passent par les fonctions Edge :

- `admin-create-user` ;
- `admin-update-user` ;
- `admin-update-password`.

Un utilisateur peut etre rattache a un ou plusieurs clients via `profiles.client_ids`, avec compatibilite historique sur `profiles.id_client`.

### Profil et securite

La page `/profile` permet a chaque utilisateur authentifie de :

- modifier prenom, nom et email ;
- consulter son role ;
- changer son mot de passe.

Le changement de mot de passe demande le mot de passe actuel, puis met a jour le compte Supabase Auth.

### Contact

Un formulaire de contact est disponible depuis la navigation. Il pre-remplit le nom et l'email lorsque l'utilisateur est connecte, puis envoie le message via la fonction Edge `send-contact-email`, elle-meme connectee a Mailjet.

### Ressources, Ensemble et blog

Les pages `/resources`, `/ensemble` et `/blog/:slug` gerent des contenus editoriaux stockes dans `blog_posts`.

Les contenus ont :

- un titre ;
- un slug unique ;
- un contenu riche ;
- une image mise en avant ;
- un client optionnel ;
- un type de contenu : `Ressource` ou `Ensemble` ;
- une categorie optionnelle pour certains contenus ;
- un statut publie/non publie.

La page `/blog-editor` est accessible aux administrateurs et administrateurs clients pour creer ou modifier ces contenus.

### Pages legales et institutionnelles

Les pages publiques suivantes existent :

- `/privacy-policy` ;
- `/terms-of-service` ;
- `/licenses` ;
- `/about`.

Elles s'appuient sur la table `legal_pages` et certaines pages peuvent etre editees par les administrateurs selon les composants presents.

## 4. Regles d'acces fonctionnelles

### Controle par route

Les routes sensibles sont encapsulees dans `ProtectedRoute`.

Routes publiques :

- `/` ;
- `/auth` ;
- `/reset-password` ;
- `/resources` ;
- `/privacy-policy` ;
- `/terms-of-service` ;
- `/licenses` ;
- `/about` ;
- `/blog/:slug` ;
- `/shared-album/:shareKey`.

Routes authentifiees :

- `/profile` ;
- `/gallery` ;
- `/images/:id` ;
- `/projects` ;
- `/downloads` ;
- `/ensemble`.

Routes limitees par role :

- `/images` : `admin`, `admin_client`, `user` ;
- `/clients` : `admin` ;
- `/users` : `admin`, `admin_client` ;
- `/access-periods` : `admin` ;
- `/blog-editor` : `admin`, `admin_client` ;
- `/admin-guide` : `admin`.

### Controle par donnees

Le filtrage fonctionnel des images repose sur :

- le role utilisateur ;
- les clients rattaches a l'utilisateur ;
- les projets des clients ;
- les periodes d'acces actives ;
- les RPC Supabase de verification d'acces.

Pour un utilisateur non administrateur, l'application recupere les projets accessibles avant de charger les images. Pour un utilisateur multi-client, elle autorise l'affichage global de ses projets accessibles ou le filtrage par client autorise.

## 5. Objets metier

### Client

Representent les organisations clientes de Stimergie.

Champs principaux :

- `id` ;
- `nom` ;
- `email` ;
- `telephone` ;
- `contact_principal` ;
- `logo`.

### Projet

Regroupe les images d'un client.

Champs principaux :

- `id` ;
- `nom_projet` ;
- `type_projet` ;
- `id_client` ;
- `nom_dossier`.

Le champ `nom_dossier` est fonctionnellement important : il sert a generer les URLs d'affichage et de telechargement sur le domaine Stimergie.

### Image

Visuel indexe dans la banque d'images.

Champs principaux :

- `id` ;
- `title` ;
- `description` ;
- `url` ;
- `url_miniature` ;
- `width` ;
- `height` ;
- `orientation` ;
- `tags` ;
- `id_projet` ;
- `created_by`.

Les tags sont utilises pour la recherche, les filtres et la categorisation.

### Profil utilisateur

Complete le compte Supabase Auth.

Champs principaux :

- `id` ;
- `email` ;
- `first_name` ;
- `last_name` ;
- `role` ;
- `id_client` ;
- `client_ids`.

### Periode d'acces projet

Definit le droit d'un client sur un projet pendant une fenetre de temps.

Champs principaux :

- `project_id` ;
- `client_id` ;
- `access_start` ;
- `access_end` ;
- `is_active`.

### Album partage

Regroupe une selection d'images envoyee a des contacts externes.

Champs principaux :

- `name` ;
- `description` ;
- `share_key` ;
- `recipients` ;
- `access_from` ;
- `access_until` ;
- `created_by`.

Les images de l'album sont stockees dans `album_images`.

### Demande de telechargement

Trace les ZIP prepares pour un utilisateur.

Champs principaux :

- `user_id` ;
- `image_id` ;
- `image_title` ;
- `image_src` ;
- `download_url` ;
- `status` ;
- `is_hd` ;
- `expires_at` ;
- `processed_at` ;
- `error_details`.

### Article / contenu editorial

Stocke les pages de type ressource, ensemble ou article de blog.

Champs principaux :

- `title` ;
- `slug` ;
- `content` ;
- `content_type` ;
- `category` ;
- `client_id` ;
- `featured_image_url` ;
- `dropbox_image_url` ;
- `url_miniature` ;
- `published`.

## 6. Integrations et services externes

### Supabase

Supabase fournit :

- l'authentification ;
- la base de donnees PostgreSQL ;
- le stockage de fichiers ;
- le temps reel sur certaines tables ;
- les fonctions Edge ;
- les politiques RLS et fonctions RPC.

### Stimergie / O2Switch

Le domaine `www.stimergie.fr` sert a :

- exposer les images source dans `/photos/...` ;
- exposer les ZIP dans `/zip-downloads/...` ;
- recevoir les uploads ZIP via `upload-zip.php`.

### Mailjet

Mailjet est utilise pour :

- envoyer les invitations d'albums partages ;
- envoyer les messages du formulaire de contact.

### OpenAI

OpenAI est utilise par la fonction `analyze-image-ai` pour proposer des tags a partir d'une image uploadee.

### Dropbox / cache d'images

Des fonctions et composants de cache Dropbox/Stimergie existent pour precharger ou verifier les images distantes. Ce perimetre est plutot administratif et technique, mais il soutient la disponibilite des visuels.

## 7. Fonctions Edge Supabase

Fonctions identifiees :

- `admin-create-user` : creation d'un utilisateur via droits administrateur ;
- `admin-update-user` : mise a jour d'un utilisateur ;
- `admin-update-password` : changement du mot de passe d'un utilisateur par un administrateur autorise ;
- `analyze-image-ai` : generation de tags par IA ;
- `analyze-image` : analyse image historique/simple ;
- `send-album-invitation` : envoi des invitations d'album ;
- `send-contact-email` : envoi d'un message de contact ;
- `generate-zip` : generation ZIP cote serveur ;
- `generate-hd-zip` : generation ZIP HD cote serveur ;
- `process-queue` et `cron-process-queue` : traitement de demandes de telechargement ;
- `check-download-url` : verification d'une URL de telechargement ;
- `cache-dropbox-images` et `cron-cache-dropbox` : cache des images distantes ;
- `debug-album-share` : diagnostic d'un album partage.

## 8. Navigation fonctionnelle

Navigation principale desktop :

- Banque d'images ;
- Gestion des images selon role ;
- Contact ;
- Mode d'emploi pour les administrateurs.

Menu utilisateur :

- Galerie ;
- Projets pour les administrateurs ;
- Telechargements ;
- Profil ;
- Gestion des images ;
- Gestion des clients pour les administrateurs ;
- Droits d'acces pour les administrateurs ;
- Gestion des utilisateurs ;
- Deconnexion.

## 9. Points d'attention fonctionnels

- Les droits d'acces sont partiellement verifies cote front et fortement dependants des RPC/RLS Supabase. La coherence des fonctions SQL est donc critique.
- `profiles.client_ids` est la logique multi-client actuelle, mais `profiles.id_client` reste utilise comme compatibilite historique.
- Les URLs d'images HD/web dependent fortement de la convention `/photos/{nom_dossier}/...` et du sous-dossier `/JPG/` pour certaines versions web.
- Les gros telechargements sont sensibles aux limites navigateur, reseau et serveur ; les seuils et la division en lots reduisent ce risque.
- Le README actuel est encore le README generique Lovable et ne decrit pas le produit.
- Certaines routes ou liens historiques peuvent diverger des routes declarees, par exemple des liens de dashboard pointant vers des URLs de blog differentes de `/blog-editor`.
- La presence de composants de debug/cache indique des outils utiles aux administrateurs, mais ils doivent rester controles par role.

## 10. Resume fonctionnel par module

| Module | Objectif | Roles principaux |
| --- | --- | --- |
| Authentification | Connexion, session, reset mot de passe | Tous |
| Tableau de bord | Vue d'accueil adaptee au role | Tous |
| Banque d'images | Recherche, consultation, selection, telechargement, partage | Tous selon droits |
| Gestion des images | Inventaire, filtres, upload, tags IA | Admin, admin_client, user |
| Clients | CRUD client | Admin |
| Projets | CRUD projet et rattachement client | Admin, admin_client |
| Droits d'acces | Fenetres d'acces client/projet | Admin |
| Utilisateurs | CRUD utilisateurs, roles, rattachements clients | Admin, admin_client |
| Telechargements | Suivi des ZIP et statuts | Tous |
| Albums partages | Partage externe temporaire | Utilisateurs authentifies pour creation, public avec lien pour consultation |
| Blog/Ressources | Contenus editoriaux | Lecture publique, edition admin/admin_client |
| Profil | Donnees personnelles et mot de passe | Tous |
| Contact | Envoi de message a Stimergie | Tous |

## 11. Perimetre technique observe

Stack principale :

- React 18 ;
- TypeScript ;
- Vite ;
- React Router ;
- TanStack Query ;
- Supabase JS ;
- Tailwind CSS ;
- shadcn/ui et Radix UI ;
- TipTap pour l'edition riche ;
- JSZip et FileSaver pour les ZIP client ;
- Mailjet, OpenAI et hebergement Stimergie/O2Switch via fonctions Edge.

Les sources fonctionnelles principales sont :

- `src/App.tsx` pour les routes ;
- `src/context/AuthContext.tsx` pour l'authentification et les roles ;
- `src/pages/*` pour les ecrans ;
- `src/components/*` pour les modules UI ;
- `src/hooks/*` pour la logique de donnees ;
- `src/services/gallery/*` pour les requetes et controles galerie ;
- `src/integrations/supabase/types.ts` pour le modele de donnees type ;
- `supabase/functions/*` pour les traitements serveur ;
- `supabase/migrations/*` pour les tables, policies, triggers et RPC.
