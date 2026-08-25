# Transfert photos o2switch vers Scaleway

Ce dossier contient un script pour copier les photos du serveur o2switch vers le bucket Scaleway avec `rclone`.

Par defaut, le script se lance depuis la machine locale : il lit les fichiers via FTP o2switch et les pousse vers Scaleway. Le flux passe donc par la machine locale.

Source FTP o2switch attendue :

```text
/collabspace.veni6445.odns.fr/photos
```

Destination bucket finale :

```text
photos
```

## Installation Locale

Installer `rclone` si necessaire :

```bash
brew install rclone
```

Depuis la racine du projet :

```bash
cp scripts/o2switch-transfer.env.example scripts/o2switch-transfer.env
nano scripts/o2switch-transfer.env
```

Renseigner les acces FTP et Scaleway :

```text
FTP_PASSWORD=...
SCALEWAY_ACCESS_KEY_ID=...
SCALEWAY_SECRET_KEY=...
SCALEWAY_BUCKET=stimergie
DEST_PREFIX=photos
```

Ne pas commiter le fichier `o2switch-transfer.env`.

## Modes Disponibles

Lister le lot courant de dossiers :

```bash
MODE=list-dirs
./o2switch-rclone-transfer.sh
```

Simuler une copie sans transfert :

```bash
MODE=batch-dry-run
./o2switch-rclone-transfer.sh
```

Copier le lot courant, puis verifier chaque dossier pendant le transfert :

```bash
MODE=batch-copy
VERIFY_AFTER_COPY=true
./o2switch-rclone-transfer.sh
```

Verifier un lot deja copie :

```bash
MODE=batch-verify
./o2switch-rclone-transfer.sh
```

Verifier la taille source et l'acces au bucket :

```bash
MODE=check
./o2switch-rclone-transfer.sh
```

## Transfert Par Lots

Le script peut transferer les dossiers par paquets alphabetiques.

Premier lot de 10 dossiers :

```bash
BATCH_SIZE=10
BATCH_OFFSET=0
MODE=batch-copy
./o2switch-rclone-transfer.sh
```

Deuxieme lot de 10 dossiers :

```bash
BATCH_SIZE=10
BATCH_OFFSET=10
MODE=batch-copy
./o2switch-rclone-transfer.sh
```

Troisieme lot :

```bash
BATCH_SIZE=10
BATCH_OFFSET=20
MODE=batch-copy
./o2switch-rclone-transfer.sh
```

`rclone copy` ne cree pas de doublons lorsque la destination est identique. Relancer le meme lot complete seulement les fichiers manquants ou modifies.

La verification est activee par defaut avec :

```bash
VERIFY_AFTER_COPY=true
```

En mode `batch-copy`, le script execute donc pour chaque dossier :

```text
rclone copy
rclone check --one-way
```

Cela permet de verifier un dossier avant de passer au suivant.

`batch-copy` complete la destination sans supprimer les fichiers deja presents. C'est le mode recommande pour les relances regulieres.

`batch-sync` remplace le contenu du dossier destination par le dossier source. Il peut supprimer des objets dans le bucket. Il faut l'activer explicitement :

```bash
MODE=batch-sync
ALLOW_SYNC_DELETE=true
VERIFY_AFTER_COPY=true
./o2switch-rclone-transfer.sh
```

## Transfert D'un Dossier Precise

Pour tester un dossier unique :

```bash
LIMIT_PATH=ADAMANCE_190224
MODE=batch-dry-run
./o2switch-rclone-transfer.sh
```

Puis :

```bash
LIMIT_PATH=ADAMANCE_190224
MODE=batch-copy
./o2switch-rclone-transfer.sh
```

## Liste Explicite De Dossiers

Creer un fichier sur o2switch :

```bash
nano /home/veni6445/scripts/batch-folders.txt
```

Exemple :

```text
ADAMANCE_190224
ADAMANCE_ESSENTIELS 091125
ADAMANCE_KVISUELS 200525
```

Puis configurer :

```bash
BATCH_FILE=/home/veni6445/scripts/batch-folders.txt
MODE=batch-copy
./o2switch-rclone-transfer.sh
```

Quand `BATCH_FILE` est renseigne, il prend le dessus sur `BATCH_SIZE` et `BATCH_OFFSET`.

## Limiter Le Volume

Pour stopper automatiquement apres 5 Go transferes :

```bash
MAX_TRANSFER_GB=5
MODE=batch-copy
./o2switch-rclone-transfer.sh
```

Pour supprimer la limite :

```bash
MAX_TRANSFER_GB=
```

## Prefixe De Destination

Le prefixe doit rester celui de production afin d'eviter les doublons et de permettre les reprises :

```bash
DEST_PREFIX=photos
```

Ne pas melanger plusieurs prefixes si l'objectif est d'eviter les recopies. `rclone` detecte les fichiers deja presents uniquement sur la meme destination.

## Logs

Par defaut :

```text
/home/veni6445/rclone-transfer.log
```

Voir les dernieres lignes :

```bash
tail -n 100 /home/veni6445/rclone-transfer.log
```

## Apres Le Transfert

Une fois les fichiers dans Scaleway, il faudra lancer une commande Laravel de reconciliation :

```bash
docker compose exec app php artisan images:reconcile-scaleway-assets --prefix=photos
```

Son role sera de mapper les `legacy_url` vers les objets `photos/...` et remplir `object_key_original`.
