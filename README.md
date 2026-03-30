# ERP Dashboard Symfony

Bienvenue dans le depot du projet **ERP Dashboard**, une interface centralisee et modulaire concue en Symfony pour regrouper les services de gestion de l'entreprise.

## Fonctionnalites et modules

L'architecture metier de l'application est divisee en modules dedies, chacun disposant de sa logique et de son espace visuel :

- Ressources Humaines
- Comptabilite
- Production
- Prestation
- Sinistre
- Service Entreprise
- Controle Interne
- Communication
- Relation Client
- Marketing
- Vente

## Integrations API prevues

Le projet est configure nativement avec le `http-client` de Symfony et pre-scaffolde avec des classes de service dans `src/Service/` pour accueillir les API suivantes :

- YouTrack
- Odoo

## Stack technique

- Backend : Symfony 7.4
- Base de donnees : MySQL / Doctrine ORM
- Frontend : Twig, Bootstrap 5

## Installation

1. Cloner le projet :

```bash
git clone https://github.com/merouanme-a11y/Dashboard.git
cd Dashboard
```

2. Installer les dependances :

```bash
composer install
```

3. Configurer l'environnement et la base :

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

4. Lancer le tableau de bord :

- sous WampServer : `http://localhost/Dashboard/public/`
- avec le serveur PHP integre :

```bash
php -S 127.0.0.1:8000 -t public
```

## Performance-first

Le projet suit maintenant une logique "performance-first" pour tout le developpement.

- Les ecrans frequents doivent utiliser des requetes Doctrine scalaires plutot que `findAll()` quand c'est possible.
- Les calculs repetitifs dans Twig doivent etre prepares en PHP.
- Les assets coeur du layout doivent etre servis localement depuis `public/`.
- Les services transverses doivent rester caches et invalider leurs caches apres modification admin.

Le detail des regles est documente dans `docs/performance-first.md`.

Pour verifier rapidement les temps de reponse :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\performance-smoke.ps1 -Email 'utilisateur@example.com' -Password 'motdepasse'
```
