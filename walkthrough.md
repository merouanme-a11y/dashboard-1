# Symfony Dashboard Workspace

Le projet Symfony a ete genere et configure avec succes.

## Ce qui a ete accompli

1. Initialisation Symfony avec votre PHP local WAMP.
2. Configuration MySQL / MariaDB pour la base `dashboard`.
3. Mise en place d'une architecture modulaire multi-services.
4. Creation d'une interface utilisateur basee sur Twig et Bootstrap.
5. Preparation des integrations API via `http-client`.

## Comment valider et utiliser

- L'espace de travail est disponible sur `http://localhost/Dashboard/public/`
- Les modules sont accessibles depuis le menu lateral
- La base de donnees est prete a recevoir de nouvelles entites

## Logique de developpement a conserver

- Le projet doit rester optimise pour WAMP avec une logique `performance-first`.
- Les donnees de configuration globales doivent passer par des services caches.
- Les listes et ecrans chauds doivent preferer les requetes scalaires Doctrine.
- Les transformations d'affichage doivent etre preparees en PHP plutot que dans Twig.
- Les assets structurels du site doivent etre locaux et servis depuis `public/`.
- Le script `scripts/performance-smoke.ps1` permet de revalider rapidement les temps de chargement.
