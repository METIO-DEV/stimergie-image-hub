# Fonctionnalites et user stories

Date : 2026-06-26

Ce document recense les fonctionnalites du projet Stimergie Image Hub en prenant en compte :

- les fonctionnalites presentes dans le projet initial React/Supabase encore disponible sur la branche `main` ;
- les fonctionnalites deja reprises dans la branche Laravel/Inertia ;
- les ajouts produits faits pendant la migration, notamment le remplacement du fichier d'une image et l'action groupee `Lier a un projet`.

Il complete le fichier de cadrage de migration.

## Etat des fonctionnalites faites

### Socle applicatif

- Stack active : Laravel, Inertia.js, React.
- Front principal dans `resources/js`.
- Ancien front Vite standalone non reactive comme deuxieme application.
- Application locale prevue sur Laravel : `http://127.0.0.1:8000`.
- Navigation reprise de l'ancien front Stimergie : logo, Banque d'images, Contact, menu utilisateur.
- Menu utilisateur complet : Galerie, Projets, Vos telechargements, Profil, Gestion des images, Gestion des clients, Droits d'acces, Gestion des utilisateurs, Deconnexion.
- Contact accessible en modale depuis la navigation.

### Pages disponibles

- Login / authentification.
- Dashboard.
- Banque d'images / Galerie.
- Gestion des images.
- Projets.
- Clients.
- Fiche client.
- Creation client.
- Modification client.
- Gestion des membres d'un client.
- Gestion des utilisateurs.
- Droits d'acces / periodes d'acces.
- Vos telechargements.
- Profil.
- Contact.

### Banque d'images

- Header beige repris du design historique.
- Recherche texte.
- Filtres orientation, client, projet.
- Masonry grid.
- Pagination visible.
- Mode defilement infini.
- Selection multiple.
- Tout selectionner.
- Loader progressif : lazy loading, placeholder, spinner, fallback.
- Clic sur une image : panneau lateral de detail image.
- Action groupee `Lier a un projet`.
- Mutation Laravel pour rattacher plusieurs images selectionnees a un projet.
- Verification serveur des droits sur le projet cible et les clients source.
- Telechargement depuis la selection d'images.
- Bouton `Version web` pour preparer une archive SD/Web depuis les objets Scaleway.
- Bouton `HD impression` pour preparer une archive HD depuis les objets Scaleway.
- Generation d'une demande de telechargement depuis la Banque d'images.
- Creation d'un ZIP Laravel a partir des `object_key_web` ou `object_key_hd`.
- Redirection vers la page `Vos telechargements` une fois l'archive preparee.

### Gestion des images

- Vue liste/tableau.
- Vue carte/masonry.
- Filtres client, orientation, recherche, tags.
- Clic sur une image ou une vignette : ouverture de la modale de modification.
- Clic sur le nom du client : panneau lateral d'informations client.
- Bouton `Ajouter une image`.
- Creation image cote Laravel.
- Modification image cote Laravel.
- Remplacement du fichier original.
- Modification titre, description, projet, orientation, statut, tags.
- Synchronisation des tags cote Laravel.
- Stockage du fichier original et des variantes sur le disque image configure, par defaut `scaleway`.
- Generation des variantes image via `ImageVariantGenerator`.
- Analyse IA ponctuelle ou par lot des tags des images pretes.
- Saisie et suivi des dates de cession de droits.

### Projets

- Page projets avec vue carte et vue ligne.
- Filtres client et recherche.
- Bouton `Ajouter un projet`.
- Modale creation/modification.
- Creation projet cote Laravel.
- Modification projet cote Laravel.
- Validation serveur.
- Autorisation selon les droits du client.
- Slug unique par client.

### Clients

- Liste clients.
- Creation client.
- Modification client.
- Fiche client.
- Statistiques client : projets, images, membres.
- Gestion des membres depuis la fiche client.
- Ajout membre client.
- Modification role/statut membre.
- Suppression membre.
- Protection : un client conserve au moins un owner actif.
- Permissions Laravel via policy client.

### Utilisateurs

- Page utilisateurs.
- Vue carte et vue tableau.
- Filtres client et role.
- Bouton `Ajouter un utilisateur`.
- Modale creation/modification.
- Creation utilisateur cote Laravel.
- Modification utilisateur cote Laravel.
- Gestion nom, email, role affiche, statut, mot de passe optionnel.
- Rattachement a un ou plusieurs clients.
- Synchronisation des memberships client.
- Gestion globale reservee super-admin.

### Droits d'acces

La page `Droits d'acces` parle des periodes pendant lesquelles un client ou ses utilisateurs peuvent consulter un projet donne. Elle ne gere pas seulement un role utilisateur ; elle gere une autorisation metier temporaire entre un client et un projet.

Utilite metier :

- ouvrir l'acces a un projet pour un client pendant une periode donnee ;
- fermer automatiquement l'acces lorsque la periode est terminee ;
- suspendre manuellement une periode sans supprimer son historique ;
- controler quelles images apparaissent dans la galerie pour les utilisateurs rattaches au client ;
- eviter qu'un client voie des projets qui ne lui sont plus ouverts.

Personnes concernees :

- super-admin Stimergie : gere toutes les periodes ;
- administrateur ou owner client, si ce droit est confirme : peut gerer les periodes de son espace client ;
- utilisateurs standards : ne gerent pas ces periodes, mais leur acces a la galerie en depend.

Fonctionnalites presentes dans le projet initial `main` :

- page reservee aux administrateurs ;
- statistiques : total des periodes, periodes actives, clients concernes ;
- liste des periodes par client et projet ;
- creation d'une nouvelle periode ;
- modification d'une periode existante ;
- activation/desactivation rapide ;
- suppression d'une periode ;
- filtres par client, projet, statut actif/inactif ;
- recherche par client, projet ou date ;
- affichage des statuts `Active`, `Inactive`, `A venir`, `Expiree` selon dates et activation ;
- formulaire avec client, projet, date de debut, date de fin, switch actif ;
- validation : la date de fin doit etre posterieure a la date de debut.

Etat repris dans Laravel/Inertia :

- page `Droits d'acces` rebranchee ;
- liste des periodes avec client, projet, dates et statut actif ;
- cartes de statistiques : total des periodes, periodes actives, clients concernes ;
- recherche par client/projet ;
- filtre actif/inactif ;
- bouton `Nouvelle periode` present cote interface ;
- creation persistante d'une periode ;
- modification persistante ;
- activation/desactivation via le champ `is_active` ;
- suppression ;
- calcul des statuts `active`, `inactive`, `upcoming`, `expired` ;
- application des periodes dans les requetes galerie et projets ;
- policies dediees pour encadrer l'administration des periodes.

Reste a completer cote Laravel/Inertia :

- filtres complets client/projet/statut/date ;
- revue UX des statuts et filtres si le volume de periodes augmente.

### Vos telechargements

Fonctionnalites presentes dans le projet initial `main` :

- telechargement direct d'une image seule ;
- telechargement standard SD/Web pour usage web et reseaux sociaux ;
- telechargement HD pour impression ;
- telechargement d'une selection d'images en ZIP ;
- telechargement de toutes les images d'une vue ou selection ;
- generation d'URL SD a partir du dossier projet et du titre de l'image ;
- generation d'URL HD a partir du dossier projet et du titre de l'image ;
- transformation d'une URL Web contenant `/JPG/` vers une URL HD en retirant ce segment ;
- validation des URLs avant ajout dans un ZIP ;
- exclusion des images invalides avec avertissement de telechargement partiel ;
- progression de preparation pour les lots ;
- seuil SD : a partir de 10 images, bascule vers une demande serveur ;
- seuil HD : a partir de 3 images, bascule vers une demande serveur ;
- pour une seule image HD, telechargement direct ;
- pour un petit lot HD, ZIP cote navigateur ;
- pour les gros lots, creation d'une demande dans la page `Vos telechargements` ;
- decoupage des lots de plus de 50 images en plusieurs archives ;
- historique des demandes de telechargement ;
- statuts : `pending`, `processing`, `ready`, `failed`, `expired` ;
- rafraichissement manuel ;
- rafraichissement automatique des demandes en attente ;
- bouton de verification/recuperation d'URL si une archive existe mais que le lien est manquant ;
- expiration des liens de telechargement ;
- badge HD sur les demandes haute definition.

Etat repris dans Laravel/Inertia :

- page `Vos telechargements` ;
- affichage des jobs de telechargement importes ou crees ;
- statut de chaque demande ;
- badge HD lorsque la demande est haute definition ;
- nombre d'images ;
- client concerne ;
- date de demande ;
- bouton `Actualiser` ;
- bouton `Telecharger` active seulement quand le job est `ready` ;
- lien de telechargement vers l'archive ZIP stockee sur Scaleway ;
- filtrage serveur : un utilisateur ne voit que ses demandes, sauf super-admin ;
- prise en compte des clients accessibles pour limiter les demandes visibles ;
- affichage des demandes creees depuis la Banque d'images ;
- distinction visuelle des demandes HD avec badge `HD` ;
- stockage de l'archive ZIP sur le disque image configure, par defaut Scaleway ;
- acces au ZIP depuis la page `Vos telechargements` ;
- telechargement d'une image seule via controle Laravel puis URL temporaire S3 quand disponible ;
- controle d'autorisation par projet/periode avant telechargement ;
- choix de variante web ou HD dans la demande de telechargement.

Reste a completer cote Laravel/Inertia :

- seuils de bascule SD 10 images / HD 3 images ;
- decoupage en lots de 50 images maximum ;
- progression de preparation ;
- recuperation ou renouvellement d'URL expiree ;
- UX plus fine sur les telechargements partiels.

### Profil

- Modification des informations de profil.
- Modification du mot de passe.
- Suppression du compte via les composants Laravel existants.

### Backend et tests

- Controllers metier : pages app, clients, membres client, projets, utilisateurs, images, imports, downloads, albums partages, blog, pages legales, cessions de droits, transferts d'assets.
- Form Requests pour validation et autorisation : clients, membres, projets, utilisateurs, images, assignation groupee, imports, partages, periodes d'acces, blog.
- Permissions cote serveur sur les mutations sensibles.
- Tests feature pour pages, clients, projets, utilisateurs, images, assignation groupee, imports, telechargements, partages, mails, blog et smoke tests de scalabilite.
- Build frontend valide via `npm run build`.

## User stories metier synthetiques

### Client

- En tant que super-admin Stimergie, je veux creer un client afin d'ouvrir un espace de marque dedie.
- En tant que super-admin Stimergie, je veux modifier les informations d'un client afin de tenir l'espace a jour.
- En tant que responsable client, je veux voir les projets, images et membres rattaches a mon client afin de comprendre son perimetre.
- En tant que responsable client, je veux gerer les membres de mon client afin de controler qui accede aux contenus.

### Projet

- En tant qu'administrateur, je veux creer un projet pour organiser les images d'un client par campagne, shooting ou dossier.
- En tant qu'administrateur, je veux rattacher un projet a un client afin que les droits et les images suivent le bon espace.
- En tant qu'administrateur, je veux modifier un projet afin de corriger son nom, son type ou son dossier source.
- En tant qu'utilisateur, je veux filtrer les images par projet afin de retrouver rapidement les visuels d'une campagne.

### Images et classement

- En tant qu'administrateur, je veux ajouter des images dans la banque afin d'alimenter le catalogue visuel.
- En tant qu'administrateur, je veux remplacer le fichier d'une image afin de corriger ou mettre a jour un visuel sans perdre ses metadonnees.
- En tant qu'administrateur, je veux modifier les informations d'une image afin d'ameliorer sa recherche et son classement.
- En tant qu'administrateur, je veux selectionner plusieurs images et les lier a un projet afin de classer rapidement un lot.
- En tant qu'administrateur, je veux disposer d'un flux `Images a classer` afin de pouvoir importer avant de choisir le projet final.
- En tant qu'utilisateur, je veux cliquer sur une image pour consulter ses informations avant de la telecharger ou l'utiliser.

### Droits d'acces

- En tant que super-admin, je veux ouvrir l'acces d'un client a un projet pour une periode donnee afin de partager les bons visuels au bon moment.
- En tant que super-admin, je veux definir une date de debut et une date de fin afin que l'acces expire automatiquement.
- En tant que super-admin, je veux desactiver temporairement une periode afin de couper un acces sans supprimer l'historique.
- En tant qu'utilisateur client, je veux ne voir que les projets actuellement accessibles afin d'eviter les erreurs d'utilisation.
- En tant qu'application, je dois appliquer ces periodes a la galerie, aux projets et aux telechargements.

### Telechargements

- En tant qu'utilisateur, je veux telecharger une image seule en qualite web afin de l'utiliser rapidement en digital.
- En tant qu'utilisateur autorise, je veux telecharger une image en HD afin de l'utiliser pour l'impression.
- En tant qu'utilisateur, je veux telecharger une selection en ZIP afin de recuperer plusieurs visuels en une fois.
- En tant qu'utilisateur, je veux choisir entre SD/Web et HD/Impression afin d'obtenir le bon format.
- En tant qu'utilisateur, je veux suivre mes demandes de telechargement afin de recuperer les archives quand elles sont pretes.
- En tant qu'application, je dois traiter les gros lots en tache serveur afin d'eviter les limites navigateur.
- En tant qu'application, je dois signaler les images invalides ou indisponibles afin que l'utilisateur comprenne les telechargements partiels.

### Variantes d'images

- En tant qu'utilisateur, je veux voir des miniatures rapides dans les grilles afin de naviguer confortablement.
- En tant qu'utilisateur, je veux previsualiser une image optimisee pour l'ecran.
- En tant qu'utilisateur autorise, je veux recuperer une version HD pour l'impression.
- En tant qu'application, je dois regenerer les variantes lorsqu'un fichier original est remplace.
- En tant qu'application, je dois conserver les liens original, miniature, web et HD pour chaque image.

### Administration et suivi

- En tant que super-admin, je veux filtrer les utilisateurs par client et role afin de gerer les acces efficacement.
- En tant qu'utilisateur, je veux consulter mon profil et changer mon mot de passe.
- En tant qu'utilisateur, je veux contacter Stimergie depuis l'application.
- En tant qu'administrateur, je veux voir l'etat des traitements longs afin de diagnostiquer les imports et telechargements.

## User stories detaillees

### Authentification et navigation

- En tant qu'utilisateur, je veux me connecter a l'application pour acceder a mes contenus autorises.
- En tant qu'utilisateur connecte, je veux retrouver la navigation Stimergie historique pour ne pas etre perdu pendant la migration.
- En tant qu'utilisateur, je veux acceder rapidement a la Banque d'images et au Contact depuis la navigation principale.
- En tant qu'utilisateur, je veux ouvrir mon menu utilisateur pour acceder a mon profil, mes telechargements et les pages d'administration selon mes droits.
- En tant qu'utilisateur, je veux me deconnecter depuis le menu utilisateur.

### Dashboard

- En tant qu'administrateur, je veux consulter un tableau de bord pour voir rapidement les volumes de clients, projets et images.
- En tant qu'administrateur, je veux acceder aux principaux modules depuis le dashboard.

### Creation et gestion des clients

- En tant que super-admin, je veux creer un client pour ouvrir un nouvel espace de marque.
- En tant que super-admin, je veux renseigner le nom et le statut d'un client.
- En tant que super-admin, je veux qu'un slug client unique soit genere pour identifier proprement l'espace.
- En tant que super-admin, je veux modifier les informations d'un client.
- En tant que super-admin ou owner client, je veux consulter la fiche client pour voir ses projets, images et membres.
- En tant que super-admin ou owner client, je veux ajouter un membre au client.
- En tant que super-admin ou owner client, je veux modifier le role d'un membre client.
- En tant que super-admin ou owner client, je veux suspendre ou reactiver un membre.
- En tant que super-admin ou owner client, je veux retirer un membre d'un client.
- En tant qu'application, je dois empecher la suppression ou degradation du dernier owner actif d'un client.
- En tant qu'utilisateur multi-client, je veux que mes droits soient derives de mes appartenances client.

### Roles et utilisateurs

- En tant que super-admin, je veux creer un utilisateur global.
- En tant que super-admin, je veux modifier le nom, l'email, le statut et le role affiche d'un utilisateur.
- En tant que super-admin, je veux rattacher un utilisateur a un ou plusieurs clients.
- En tant que super-admin, je veux retirer un utilisateur d'un client.
- En tant que super-admin, je veux filtrer les utilisateurs par client.
- En tant que super-admin, je veux filtrer les utilisateurs par role.
- En tant que super-admin, je veux voir les utilisateurs en cartes ou en tableau.
- En tant qu'application, je dois synchroniser les memberships client lorsque les rattachements d'un utilisateur changent.
- En tant qu'application, je dois reserver la gestion globale des utilisateurs aux super-admins.

### Creation et gestion des projets

- En tant que super-admin ou manager d'un client, je veux creer un projet pour organiser les images d'un client.
- En tant que super-admin ou manager d'un client, je veux rattacher un projet a un client.
- En tant que super-admin ou manager d'un client, je veux renseigner un nom de projet.
- En tant que super-admin ou manager d'un client, je veux renseigner un type de projet.
- En tant que super-admin ou manager d'un client, je veux renseigner un nom de dossier/source.
- En tant que super-admin ou manager d'un client, je veux modifier un projet existant.
- En tant qu'application, je dois generer un slug projet unique par client.
- En tant qu'application, je dois refuser la creation d'un projet sur un client que l'utilisateur ne peut pas administrer.
- En tant qu'application, je dois refuser le deplacement d'un projet vers un client non administre par l'utilisateur.
- En tant qu'utilisateur, je veux filtrer les projets par client.
- En tant qu'utilisateur, je veux rechercher un projet par nom ou client.
- En tant qu'utilisateur, je veux consulter les projets en cartes ou en lignes.

### Ajout et modification d'images

- En tant que super-admin ou manager d'un client, je veux ajouter une image pour alimenter la banque d'images.
- En tant que super-admin ou manager d'un client, je veux choisir le projet de l'image lors de l'ajout.
- En tant que super-admin ou manager d'un client, je veux uploader le fichier original.
- En tant que super-admin ou manager d'un client, je veux renseigner un titre.
- En tant que super-admin ou manager d'un client, je veux renseigner une description.
- En tant que super-admin ou manager d'un client, je veux definir ou laisser calculer l'orientation de l'image.
- En tant que super-admin ou manager d'un client, je veux renseigner des tags separes par virgule.
- En tant que super-admin ou manager d'un client, je veux changer le statut d'une image.
- En tant que super-admin ou manager d'un client, je veux remplacer le fichier original d'une image.
- En tant que super-admin ou manager d'un client, je veux changer le projet d'une image.
- En tant qu'application, je dois deduire le client de l'image a partir du projet selectionne.
- En tant qu'application, je dois synchroniser les tags lors de la creation ou modification d'une image.
- En tant qu'application, je dois refuser la modification d'une image si l'utilisateur n'a pas les droits sur le client source et le projet cible.

### Liaison rapide images vers projet

- En tant qu'utilisateur habilite, je veux selectionner plusieurs images dans la Banque d'images.
- En tant qu'utilisateur habilite, je veux cliquer sur `Lier a un projet` pour eviter de modifier chaque image une par une.
- En tant qu'utilisateur habilite, je veux choisir le projet cible.
- En tant qu'utilisateur habilite, je veux voir combien d'images vont etre rattachees.
- En tant qu'application, je dois rattacher les images selectionnees au projet cible.
- En tant qu'application, je dois mettre a jour le client des images selon le client du projet cible.
- En tant qu'application, je dois verifier les droits sur les clients source et le projet cible avant la mutation.
- En tant qu'application, je dois refuser l'assignation groupee si une image source n'est pas administrable par l'utilisateur.
- En tant qu'application, je dois refuser l'assignation groupee si le projet cible n'est pas administrable par l'utilisateur.

### Banque d'images et consultation

- En tant qu'utilisateur, je veux voir les images sous forme de masonry grid.
- En tant qu'utilisateur, je veux rechercher une image par titre ou tag.
- En tant qu'utilisateur, je veux filtrer par orientation.
- En tant qu'utilisateur, je veux filtrer par client.
- En tant qu'utilisateur, je veux filtrer par projet.
- En tant qu'utilisateur, je veux utiliser la pagination pour naviguer dans un grand volume d'images.
- En tant qu'utilisateur, je veux activer le defilement infini.
- En tant qu'utilisateur, je veux selectionner une ou plusieurs images.
- En tant qu'utilisateur, je veux tout selectionner sur la page courante.
- En tant qu'utilisateur, je veux effacer ma selection.
- En tant qu'utilisateur, je veux cliquer sur une image pour afficher ses details dans un panneau lateral.
- En tant qu'utilisateur, je veux voir le titre, la description, le client, le projet, les tags, les dimensions et la date d'ajout d'une image.
- En tant qu'utilisateur, je veux voir les images se charger progressivement avec un loader propre.

### Gestion des images

- En tant qu'administrateur, je veux afficher les images en tableau pour gerer rapidement leurs informations.
- En tant qu'administrateur, je veux afficher les images en grille pour les reconnaitre visuellement.
- En tant qu'administrateur, je veux ouvrir la modification en cliquant sur la vignette.
- En tant qu'administrateur, je veux ouvrir la fiche client depuis le nom du client d'une image.
- En tant qu'administrateur, je veux filtrer les images par client, orientation, texte et tag.

### Variantes d'images

- En tant qu'application, je dois conserver le fichier original d'une image.
- En tant qu'application, je dois disposer d'une variante miniature pour les grilles et listes.
- En tant qu'application, je dois disposer d'une variante Web optimisee pour l'affichage.
- En tant qu'application, je dois disposer d'une variante HD pour les telechargements haute definition.
- En tant qu'utilisateur, je veux voir rapidement des miniatures legeres dans la galerie.
- En tant qu'utilisateur, je veux previsualiser une image dans une qualite adaptee a l'ecran.
- En tant qu'utilisateur autorise, je veux telecharger une version HD lorsque mes droits le permettent.
- En tant qu'application, je dois stocker les chemins de variantes dans `object_key_original`, `object_key_thumb`, `object_key_web` et `object_key_hd`.
- En tant qu'application, je dois regenerer les variantes lorsqu'un fichier original est remplace.
- En tant qu'application, je dois tracer les erreurs de traitement d'image.

Etat actuel des variantes :

- Le schema prevoit deja les champs original, thumb, web et HD.
- La creation/modification stocke le fichier original et genere les variantes via `ImageVariantGenerator`.
- Les objets sont stockes sur le disque image configure, par defaut `scaleway`.
- Des commandes Artisan permettent d'auditer, generer, migrer ou deplacer les variantes de projets existants.
- La convention cible projet est `photos/<projet>/web/`, `photos/<projet>/hd/` et `photos/<projet>/miniatures/`.
- Les commandes de migration doivent rester executees en dry-run avant toute option `--execute` ou `--force`.

### Telechargements

- En tant qu'utilisateur, je veux consulter mes demandes de telechargement.
- En tant qu'utilisateur, je veux connaitre le statut d'un telechargement.
- En tant qu'utilisateur, je veux voir le nombre d'images concernees.
- En tant qu'utilisateur, je veux savoir a quel client correspond un telechargement.
- En tant qu'application, je dois filtrer les telechargements selon l'utilisateur et ses droits.
- En tant qu'utilisateur autorise, je veux telecharger une image seule.
- En tant qu'utilisateur autorise, je veux telecharger une selection en ZIP.
- En tant qu'utilisateur autorise, je veux choisir `Version web & reseaux sociaux` pour obtenir une version SD/Web.
- En tant qu'utilisateur autorise, je veux choisir `Version HD impression` pour obtenir une version haute definition.
- En tant qu'utilisateur autorise, je veux que les petits lots soient prepares immediatement lorsque c'est possible.
- En tant qu'utilisateur autorise, je veux que les gros lots soient ajoutes a `Vos telechargements`.
- En tant qu'utilisateur autorise, je veux etre averti si certaines images sont exclues car leurs URLs sont invalides.
- En tant qu'utilisateur autorise, je veux retrouver les archives pretes dans l'historique.
- En tant qu'utilisateur autorise, je veux relancer une demande si le lien a expire.
- En tant qu'application, je dois traiter les ZIP en asynchrone.
- En tant qu'application, je dois distinguer les seuils SD et HD pour choisir le bon mode de traitement.
- En tant qu'application, je dois decouper les tres grands lots en plusieurs archives.

Etat actuel :

- La page et l'affichage des jobs sont disponibles.
- Le workflow ZIP web/HD est traite cote Laravel, stocke les archives sur le disque image configure et applique les droits via les images accessibles.
- Les liens de telechargement d'image seule passent par le backend puis par URL temporaire quand le stockage le permet.
- Les raffinements restants concernent surtout la progression fine, les seuils historiques et le renouvellement d'URL expiree.

### Droits d'acces

- En tant qu'administrateur, je veux voir les periodes d'acces par client et projet.
- En tant qu'administrateur, je veux creer une periode d'acces pour un couple client/projet.
- En tant qu'administrateur, je veux modifier une periode d'acces.
- En tant qu'administrateur, je veux activer ou desactiver une periode.
- En tant qu'administrateur, je veux supprimer une periode obsolete.
- En tant qu'administrateur, je veux connaitre les dates de debut et fin d'une periode.
- En tant qu'administrateur, je veux savoir si une periode est active, inactive, a venir ou expiree.
- En tant qu'administrateur, je veux filtrer les periodes par client.
- En tant qu'administrateur, je veux filtrer les periodes par projet.
- En tant qu'administrateur, je veux filtrer les periodes par statut.
- En tant qu'administrateur, je veux rechercher une periode par client, projet ou date.
- En tant qu'utilisateur client, je veux que ces periodes limitent automatiquement les projets visibles.
- En tant qu'application, je dois appliquer les droits dans les requetes de galerie et de projets accessibles.
- En tant qu'application, je dois empecher le telechargement d'images dont le projet n'est plus accessible.

Etat actuel :

- La page de consultation est disponible.
- La creation, modification, activation/desactivation et suppression sont branchees cote Laravel.
- Les policies dediees encadrent les droits d'administration.
- Les periodes sont appliquees aux requetes galerie/projets et aux controles de visibilite image.
- Les filtres avances et la lisibilite UX des statuts restent a renforcer.

### Contact

- En tant qu'utilisateur, je veux ouvrir le formulaire de contact depuis la navigation.
- En tant qu'utilisateur, je veux envoyer un sujet et un message.
- En tant qu'application, je dois enregistrer la demande dans l'audit log.

### Profil

- En tant qu'utilisateur, je veux modifier mes informations personnelles.
- En tant qu'utilisateur, je veux modifier mon mot de passe.
- En tant qu'utilisateur, je veux supprimer mon compte si autorise.

## Peut-on ajouter des images sans les lier a un projet tout de suite ?

Etat actuel : non, pas dans le flux standard.

Raisons techniques actuelles :

- La table `images` impose un `project_id` non nullable.
- La validation `StoreImageRequest` exige un `project_id`.
- Le client d'une image est deduit du projet selectionne.
- Les permissions de creation/modification s'appuient sur le client du projet.

Recommandation produit : oui, il faudrait le permettre.

Le meilleur flux serait d'ajouter une zone `Images a classer` ou `Import non classe` :

- l'utilisateur ajoute une ou plusieurs images sans projet final ;
- il selectionne au minimum un client ou un espace d'import ;
- les images apparaissent avec un statut `a_classer` ou `pending_classification` ;
- l'utilisateur les rattache ensuite a un projet via l'action groupee `Lier a un projet` ;
- les images non classees restent exclues des vues client finales si les droits ne sont pas clairs ;
- Laravel conserve les permissions via le client ou l'espace d'import temporaire.

Impacts techniques pour permettre ce flux :

- rendre `images.project_id` nullable ou creer un projet systeme `A classer` par client ;
- adapter `StoreImageRequest` pour accepter un `client_id` sans `project_id` ;
- adapter les policies pour autoriser l'upload sur client sans projet ;
- adapter la galerie et la gestion des images pour filtrer `A classer` ;
- adapter l'action groupee `Lier a un projet` pour devenir le flux principal de classement ;
- ajouter des tests sur upload sans projet puis rattachement ulterieur.

Option recommandee a court terme :

- creer automatiquement un projet technique `A classer` par client ;
- garder `project_id` obligatoire pour limiter les changements de schema ;
- afficher ce projet comme une file de classement dans l'interface ;
- utiliser l'action groupee existante pour deplacer ensuite les images vers le bon projet.

Cette option est plus rapide et plus sure pendant la migration. L'option nullable est plus propre a long terme, mais demande une reprise plus large du schema, des policies et des vues.
