# Performance-First

Cette application Symfony doit conserver une logique "performance-first" sur tout le developpement.

## Regles a garder

1. Les pages web sous WAMP doivent tourner en `prod` via `public/.htaccess`.
2. Les donnees communes du layout doivent etre servies via des services caches.
3. Les assets coeur de l'interface doivent etre servis localement depuis `public/`, pas depuis un CDN externe.
4. Les pages frequentes ne doivent pas utiliser `findAll()` si un select scalaire suffit.
5. Les transformations lourdes dans les boucles Twig doivent etre preparees en PHP dans les controleurs ou services.
6. Toute page admin qui modifie un cache applicatif doit aussi invalider son cache.
7. Toute nouvelle fonctionnalite doit etre verifiee avec un test de charge simple avant validation.

## Patterns obligatoires

- Doctrine : utiliser des methodes repository dediees avec `select(...)` et `getArrayResult()` pour les listes et les ecrans de configuration.
- Twig : limiter les `set`, `join`, `slice`, `lower`, calculs de styles et assemblages repetes dans les boucles.
- Theme et navigation : centraliser dans `ThemeService`, `PageDisplayService`, `MenuConfigService`, `ModuleService` et `PagePermissionService`.
- Assets : versionner les CSS locaux, publier les fichiers statiques dans `public/` et conserver le cache navigateur.

## Checklist avant merge

1. Verifier qu'aucun nouveau hot path n'utilise `findAll()` par confort.
2. Verifier qu'aucun layout critique ne recharge un CDN obligatoire.
3. Verifier les pages connectees principales avec le script `scripts/performance-smoke.ps1`.
4. Si une config admin change l'affichage global, verifier aussi l'invalidation de cache.
5. Vider le cache `prod` si la modification touche les templates, routes ou services partages.

## Commandes utiles

```powershell
& 'C:\wamp\bin\php\php8.3.28\php.exe' 'C:\wamp\www\Dashboard\bin\console' cache:clear --env=prod --no-debug
```

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\performance-smoke.ps1 -Email 'utilisateur@example.com' -Password 'motdepasse'
```
