# Cadrage client - Fonctionnalites et user stories

Document de travail editable pour presenter le perimetre fonctionnel cible de Stimergie Image Hub au client.

## Objectif du document

Ce document sert de support de cadrage. Il permet de valider le perimetre cible :

- les pages attendues dans l'application ;
- les fonctionnalites visibles par les utilisateurs ;
- les droits et roles associes ;
- les user stories metier ;
- les travaux restant a finaliser.

Les statuts utilises sont :

- `Disponible` : fonctionnalite deja reprise dans Laravel/Inertia ou couverte par le projet actuel.
- `Partiel` : fonctionnalite presente, mais avec un comportement ou une integration encore a completer.
- `A finaliser` : fonctionnalite identifiee et attendue, mais pas encore complete cote Laravel/Inertia.

## Synthese du perimetre

Stimergie Image Hub est une application web de banque d'images et de gestion de ressources visuelles. Elle permet a Stimergie et a ses clients de centraliser des images par client et par projet, de gerer les droits d'acces, de rechercher et consulter les visuels, de partager une selection d'images, puis de preparer des telechargements web ou HD.

Le projet actuel repose sur Laravel, Inertia.js et React. Il reprend les principaux parcours de l'application historique, en les consolidant dans une application Laravel.

## Roles utilisateurs

| Role | Description | Droits principaux |
| --- | --- | --- |
| Super-admin Stimergie | Administrateur global de la plateforme. | Gere clients, projets, images, utilisateurs, droits d'acces et telechargements. |
| Owner client | Responsable d'un espace client. | Consulte la fiche client, gere les membres du client si autorise, accede aux contenus rattaches. |
| Manager client | Profil de gestion rattache a un client. | Gere les projets et images du client selon les droits attribues. |
| Viewer / utilisateur client | Utilisateur final consommant les visuels. | Consulte la banque d'images autorisee et telecharge les fichiers disponibles. |

Hypothese de perimetre : les owners/managers client peuvent gerer les membres et projets de leur espace selon leurs droits. La gestion des periodes d'acces reste reservee a l'administration Stimergie dans le scope cible.

## Pages disponibles

| Page | Objectif | Statut |
| --- | --- | --- |
| Login / authentification | Connexion des utilisateurs. | Disponible |
| Dashboard | Vue de synthese des volumes clients, projets et images. | Disponible |
| Banque d'images / Galerie | Recherche, consultation, selection et telechargement d'images. | Disponible |
| Gestion des images | Administration des images, metadonnees et fichiers. | Disponible |
| Projets | Gestion des projets rattaches aux clients. | Disponible |
| Clients | Liste, creation et modification des clients. | Disponible |
| Fiche client | Detail client, statistiques, projets, images et membres. | Disponible |
| Creation client | Creation d'un nouvel espace client. | Disponible |
| Modification client | Mise a jour des informations client. | Disponible |
| Membres client | Ajout, modification et suppression des membres rattaches. | Disponible |
| Gestion des utilisateurs | Administration globale des utilisateurs et rattachements clients. | Disponible |
| Droits d'acces / periodes d'acces | Gestion des periodes d'ouverture client/projet. | Partiel |
| Partages / albums partages | Creation et consultation de partages temporaires d'images. | A finaliser |
| Vos telechargements | Historique et recuperation des archives preparees. | Disponible |
| Profil | Informations personnelles, mot de passe, suppression du compte. | Disponible |
| Contact | Envoi d'une demande a Stimergie. | Disponible |

## Fonctionnalites par page

### Authentification et navigation

| Fonctionnalite | Statut | Commentaire |
| --- | --- | --- |
| Connexion par email et mot de passe | Disponible | Flux Laravel standard. |
| Navigation principale Stimergie | Disponible | Acces rapide a Banque d'images et Contact. |
| Menu utilisateur | Disponible | Acces a Galerie, Projets, Telechargements, Profil et pages d'administration selon droits. |
| Deconnexion | Disponible | Action disponible depuis le menu utilisateur. |

### Dashboard

| Fonctionnalite | Statut | Commentaire |
| --- | --- | --- |
| Statistiques clients, projets, images | Disponible | Compteurs globaux affiches pour les utilisateurs autorises. |
| Acces rapides aux modules | Disponible | Permet d'entrer rapidement dans les parcours de gestion. |

### Banque d'images / Galerie

| Fonctionnalite | Statut | Commentaire |
| --- | --- | --- |
| Header beige repris du design historique | Disponible | Cohesion avec l'existant Stimergie. |
| Recherche texte | Disponible | Recherche sur titre et donnees associees selon implementation. |
| Filtres orientation, client, projet | Disponible | Filtres principaux de consultation. |
| Masonry grid | Disponible | Affichage visuel de la banque d'images. |
| Pagination visible | Disponible | Navigation dans les volumes importants. |
| Defilement infini | Disponible | Mode alternatif de consultation. |
| Selection multiple | Disponible | Preparation d'actions groupees. |
| Tout selectionner | Disponible | Selection de la page courante. |
| Loader progressif | Disponible | Lazy loading, placeholder, spinner et fallback. |
| Panneau lateral de detail image | Disponible | Ouverture au clic sur une image. |
| Action groupee `Lier a un projet` | Disponible | Permet de rattacher plusieurs images a un projet cible. |
| Verification des droits sur liaison groupee | Disponible | Controle serveur sur les clients source et le projet cible. |
| Creation de demande de telechargement depuis la galerie | Disponible | Redirection vers `Vos telechargements` apres preparation. |
| Choix `Version web` | Disponible | Utilise les objets `object_key_web`. |
| Choix `HD impression` | Disponible | Utilise les objets `object_key_hd`. |

### Gestion des images

| Fonctionnalite | Statut | Commentaire |
| --- | --- | --- |
| Vue liste/tableau | Disponible | Gestion rapide des metadonnees. |
| Vue carte/masonry | Disponible | Gestion visuelle. |
| Filtres client, orientation, recherche, tags | Disponible | Filtres d'administration. |
| Ouverture de la modification depuis image/vignette | Disponible | Edition rapide depuis la liste ou grille. |
| Bouton `Ajouter une image` | Disponible | Creation d'image cote Laravel. |
| Modification titre et description | Disponible | Metadonnees principales. |
| Modification projet | Disponible | Le client est deduit du projet selectionne. |
| Modification orientation | Disponible | Saisie ou calcul selon le flux. |
| Modification statut | Disponible | Gestion de disponibilite de l'image. |
| Gestion des tags | Disponible | Synchronisation cote Laravel. |
| Remplacement du fichier original | Disponible | Nouvelle fonctionnalite reprise dans le cadrage. |
| Generation reelle des variantes thumb/web/HD | A finaliser | Les champs existent, mais le pipeline final reste a consolider. |
| Stockage objet S3/Scaleway des nouveaux uploads | A finaliser | Decision technique actee ; implementation complete des nouveaux uploads a finaliser. |

### Projets

| Fonctionnalite | Statut | Commentaire |
| --- | --- | --- |
| Vue carte et vue ligne | Disponible | Deux modes de consultation. |
| Filtres client et recherche | Disponible | Recherche par nom ou client. |
| Bouton `Ajouter un projet` | Disponible | Creation cote Laravel. |
| Creation/modification projet | Disponible | Validation serveur active. |
| Rattachement projet a client | Disponible | Base du modele de droits. |
| Type de projet | Disponible | Champ de qualification metier. |
| Nom de dossier/source | Disponible | Sert a organiser les sources et variantes. |
| Slug unique par client | Disponible | Unicite projet dans l'espace client. |
| Autorisation selon droits client | Disponible | Creation et modification limitees aux clients administrables. |

### Clients

| Fonctionnalite | Statut | Commentaire |
| --- | --- | --- |
| Liste clients | Disponible | Acces aux espaces clients. |
| Creation client | Disponible | Creation d'un espace de marque. |
| Modification client | Disponible | Mise a jour nom/statut et donnees associees. |
| Fiche client | Disponible | Vue detaillee du client. |
| Statistiques client | Disponible | Projets, images, membres. |
| Gestion des membres depuis la fiche client | Disponible | Ajout, modification, suppression. |
| Modification role/statut membre | Disponible | Gestion des droits client. |
| Protection dernier owner actif | Disponible | Evite de rendre un client sans responsable actif. |
| Desactivation client | A finaliser | Privilegiee a la suppression pour conserver l'historique. |

### Gestion des utilisateurs

| Fonctionnalite | Statut | Commentaire |
| --- | --- | --- |
| Page utilisateurs | Disponible | Administration globale. |
| Vue carte et vue tableau | Disponible | Deux modes de consultation. |
| Filtres client et role | Disponible | Recherche operationnelle. |
| Ajout utilisateur | Disponible | Creation cote Laravel. |
| Modification utilisateur | Disponible | Mise a jour nom, email, role affiche, statut. |
| Mot de passe optionnel | Disponible | Selon creation ou modification. |
| Rattachement a un ou plusieurs clients | Disponible | Via memberships client. |
| Synchronisation des memberships | Disponible | Les rattachements pilotent les droits. |
| Gestion reservee super-admin | Disponible | Protection serveur attendue. |

### Droits d'acces / periodes d'acces

La page `Droits d'acces` gere les periodes pendant lesquelles un client ou ses utilisateurs peuvent consulter un projet donne. Elle ne gere pas seulement un role utilisateur ; elle gere une autorisation metier temporaire entre un client et un projet.

| Fonctionnalite | Statut | Commentaire |
| --- | --- | --- |
| Page reservee aux administrateurs Stimergie | Partiel | La consultation existe, les mutations restent a finaliser. |
| Statistiques des periodes | Disponible | Total, periodes actives, clients concernes. |
| Liste des periodes par client/projet | Disponible | Consultation existante. |
| Recherche client/projet | Disponible | Recherche principale disponible. |
| Filtre actif/inactif | Disponible | Filtrage de base. |
| Creation d'une periode | A finaliser | Bouton present, persistance a completer. |
| Modification d'une periode | A finaliser | A brancher cote Laravel/Inertia. |
| Activation/desactivation rapide | A finaliser | A brancher cote Laravel/Inertia. |
| Suppression d'une periode | A finaliser | A brancher cote Laravel/Inertia. |
| Statuts `Active`, `Inactive`, `A venir`, `Expiree` | A finaliser | Calcul fin a consolider. |
| Validation date de fin apres date de debut | A finaliser | Regle attendue sur le formulaire. |
| Application stricte aux galeries/projets/telechargements | A finaliser | Point cle pour la securite metier. |

### Vos telechargements

| Fonctionnalite | Statut | Commentaire |
| --- | --- | --- |
| Historique des demandes | Disponible | Page `Vos telechargements`. |
| Statuts `pending`, `processing`, `ready`, `failed`, `expired` | Disponible | Statuts metier prevus. |
| Nombre d'images par demande | Disponible | Affiche dans l'historique. |
| Client concerne | Disponible | Affiche dans l'historique. |
| Badge HD | Disponible | Distinction des demandes haute definition. |
| Rafraichissement manuel | Disponible | Bouton `Actualiser`. |
| Telechargement d'une archive prete | Disponible | Lien actif quand le job est `ready`. |
| Filtrage serveur par utilisateur et droits | Disponible | Super-admin voit plus largement. |
| Creation ZIP Laravel depuis `object_key_web` | Disponible | Archive web preparee cote serveur. |
| Creation ZIP Laravel depuis `object_key_hd` | Disponible | Archive HD preparee cote serveur. |
| Exclusion des images invalides avec avertissement | A finaliser | Comportement attendu pour telechargements partiels. |
| Progression de preparation pour les lots | A finaliser | UX attendue sur gros lots. |
| Seuil SD a partir de 10 images | A finaliser | Regle historique a rebrancher. |
| Seuil HD a partir de 3 images | A finaliser | Regle historique a rebrancher. |
| Decoupage des lots de plus de 50 images | A finaliser | Regle historique a rebrancher. |
| Renouvellement ou recuperation d'URL expiree | A finaliser | Comportement attendu sur archives expirees. |

### Partages / albums partages

Le projet initial contenait des fonctionnalites de partage, mais elles etaient incompletes ou instables.

Deux types de partage sont a distinguer :

- album partage externe : une selection d'images est partagee via un lien temporaire, consultable par des destinataires externes selon les regles definies ;
- partage inter-clients : une image rattachee a un client devient visible par un autre client sans changer son client proprietaire.

| Fonctionnalite | Statut | Commentaire |
| --- | --- | --- |
| Schema `shared_albums` et `shared_album_images` | Disponible | Les tables et le modele Laravel existent. |
| Import legacy des albums et images d'albums | Disponible | L'import du dump reprend les anciennes donnees. |
| Schema `image_client_shares` | Disponible | La table de partage inter-clients existe. |
| Creation d'un album depuis une selection d'images | A finaliser | Interface et controller a construire ou rebrancher. |
| Formulaire nom, description, destinataires, message | A finaliser | Fonctionnalite issue du projet initial. |
| Periode de validite du partage | A finaliser | Dates de debut/expiration et statut actif. |
| Generation d'une cle de partage unique | A finaliser | Acces public ou semi-public par URL. |
| Page publique ou semi-publique de consultation | A finaliser | Consultation par lien temporaire, avec controle de validite. |
| Telechargement des images d'un album partage | A finaliser | A traiter cote serveur pour eviter les limites navigateur. |
| Envoi d'invitations email | A finaliser | Present dans le projet initial, a reprendre proprement. |
| Desactivation ou expiration d'un partage | A finaliser | Necessaire pour controler la diffusion. |
| Partage d'une image avec un autre client | A finaliser | Fonctionnalite legacy identifiee comme non fonctionnelle cote UI. |
| Retrait d'un partage inter-clients | A finaliser | Permet de retirer un acces client secondaire. |
| Prise en compte dans la galerie et les droits | A finaliser | Les images partagees doivent apparaitre uniquement pour les bons clients. |

### Profil

| Fonctionnalite | Statut | Commentaire |
| --- | --- | --- |
| Modification informations de profil | Disponible | Flux Laravel standard. |
| Modification mot de passe | Disponible | Flux Laravel standard. |
| Suppression du compte | Disponible | Selon autorisation Laravel. |

### Contact

| Fonctionnalite | Statut | Commentaire |
| --- | --- | --- |
| Acces depuis la navigation | Disponible | Page ou modale selon parcours. |
| Envoi sujet et message | Disponible | Formulaire authentifie. |
| Enregistrement dans l'audit log | Disponible | Action `contact.requested`. |

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
- La creation/modification actuelle stocke le fichier original.
- En attendant le pipeline final, les quatre champs de variantes peuvent pointer vers le meme fichier stocke localement.
- La generation reelle des variantes Web/thumbnail/HD reste a finaliser.
- Le stockage objet S3/Scaleway est la cible actee pour les nouveaux uploads.

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

Etat actuel des telechargements :

- La page et l'affichage des jobs sont disponibles.
- La preparation ZIP depuis les objets web et HD est disponible.
- Le workflow complet de seuils, lots, progression et expiration reste a finaliser.

### Partages et albums partages

- En tant qu'utilisateur habilite, je veux selectionner plusieurs images pour creer un album partage.
- En tant qu'utilisateur habilite, je veux renseigner un nom et une description d'album pour contextualiser le partage.
- En tant qu'utilisateur habilite, je veux definir une date de debut et une date d'expiration pour limiter l'acces dans le temps.
- En tant qu'utilisateur habilite, je veux generer un lien de partage unique pour transmettre une selection d'images.
- En tant qu'utilisateur habilite, je veux envoyer une invitation par email aux destinataires si cette option est conservee.
- En tant que destinataire externe, je veux ouvrir un album partage depuis un lien pour consulter les images autorisees.
- En tant que destinataire externe, je veux telecharger les images de l'album dans les formats autorises.
- En tant qu'utilisateur habilite, je veux desactiver un album partage pour couper l'acces avant son expiration.
- En tant qu'utilisateur habilite, je veux voir les albums partages existants et leur statut.
- En tant qu'application, je dois empecher l'acces a un album expire, inactif ou inconnu.
- En tant qu'application, je dois verifier les droits du createur sur toutes les images partagees.
- En tant qu'application, je dois traiter le telechargement d'un album cote serveur pour eviter les limites navigateur sur les gros volumes.

### Partage inter-clients

- En tant que super-admin ou manager autorise, je veux partager une image avec un autre client sans changer son client proprietaire.
- En tant que super-admin ou manager autorise, je veux voir la liste des clients secondaires ayant acces a une image.
- En tant que super-admin ou manager autorise, je veux retirer le partage d'une image a un client secondaire.
- En tant qu'utilisateur client, je veux voir dans ma galerie les images qui me sont explicitement partagees.
- En tant qu'application, je dois distinguer le client proprietaire d'une image et les clients avec lesquels elle est partagee.
- En tant qu'application, je dois appliquer ces partages dans les requetes de galerie, de detail et de telechargement.

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

Etat actuel des droits d'acces :

- La page de consultation est disponible.
- La creation/modification des periodes et leur application fine a tous les flux restent a renforcer.

### Contact

- En tant qu'utilisateur, je veux ouvrir le formulaire de contact depuis la navigation.
- En tant qu'utilisateur, je veux envoyer un sujet et un message.
- En tant qu'application, je dois enregistrer la demande dans l'audit log.

### Profil

- En tant qu'utilisateur, je veux modifier mes informations personnelles.
- En tant qu'utilisateur, je veux modifier mon mot de passe.
- En tant qu'utilisateur, je veux supprimer mon compte si autorise.
