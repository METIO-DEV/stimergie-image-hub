# Tests de scalabilite legers

Ce depot contient une suite de smoke tests destinee a detecter les regressions evidentes sur les parcours sensibles avant une montee en volume de la phototheque.

## Commande

```sh
php artisan test tests/Feature/ScalabilitySmokeTest.php
```

La suite complete reste :

```sh
composer test
```

## Perimetre couvert

- Galerie : volume pagine, filtres serveur par orientation et tag, budget large de requetes SQL.
- Imports : recalcul de progression sur un batch proche de la limite actuelle de 500 items.
- ZIP : generation d'une archive web avec plusieurs objets Scaleway simules, y compris des objets absents.

## Principes

- Les tests utilisent SQLite en memoire et `Storage::fake('scaleway')`.
- Ils ne dependent pas du bucket Scaleway reel.
- Les budgets ne sont pas des benchmarks stricts. Ils servent a attraper une regression grossiere, par exemple un retour de requetes N+1 sur la galerie ou un recalcul d'import ligne par ligne.
- Les tests fonctionnels existants restent la reference pour les droits, les erreurs metier et les parcours detailles.

## Points a surveiller si les volumes augmentent

- Remplacer les recherches `LIKE "%...%"` par une recherche indexee si la galerie devient lente.
- Continuer a surveiller la memoire des jobs ZIP HD avec des fichiers reels volumineux.
- Garder les seuils de ces tests volontairement larges pour eviter une suite instable en CI.
