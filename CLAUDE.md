# CLAUDE.md — Dashboard Project Context

Ce fichier est lu automatiquement par Claude Code à chaque session sur ce projet.

---

## Présentation

**Dashboard** est une application **Symfony 7.4** de tableau de bord d'entreprise multi-départements.
Accessible à : `http://localhost/Dashboard/public/`

---

## Stack Technique

| Composant | Technologie |
|-----------|-------------|
| Framework | Symfony 7.4.* (LTS) |
| Langage | PHP 8.2+ |
| Base de données | MySQL 8.0.32 — schema `dashboard` |
| ORM | Doctrine ORM 3.6 + Migrations |
| Templates | Twig |
| CSS | Bootstrap 5.3.0 |
| Icônes | Font Awesome 6.4.0 |
| JS | Stimulus 3.2.2 + Turbo 7.3.0 |
| Assets | Asset Mapper (pas Webpack Encore) |
| Auth | Symfony Security Bundle |
| Queue | Symfony Messenger (transport Doctrine) |
| Tests | PHPUnit 12.5 |
| Environnement local | WAMP (Windows/Apache/MySQL/PHP) |

---

## Structure du Projet

```
Dashboard/
├── assets/                  # JS (Stimulus controllers), CSS
│   ├── app.js
│   ├── controllers/         # csrf_protection, hello
│   └── styles/app.css
├── config/
│   ├── bundles.php
│   ├── packages/            # doctrine, security, twig, mailer, messenger...
│   └── routes/
├── migrations/
│   └── Version20260322175412.php   # User, Department, MessengerMessages
├── public/index.php          # Front controller
├── src/
│   ├── Controller/           # 12 controllers (un par département)
│   ├── Entity/               # User.php, Department.php
│   ├── Repository/           # Repositories Doctrine
│   └── Service/
│       ├── YouTrackApiService.php  # Placeholder intégration YouTrack
│       └── OdooApiService.php     # Placeholder intégration Odoo ERP
├── templates/
│   ├── base.html.twig        # Layout principal avec sidebar Bootstrap 5
│   └── [département]/index.html.twig  (12 vues)
├── .env                      # Config locale (DATABASE_URL, etc.)
├── CLAUDE.md                 # Ce fichier
├── implementation_plan.md    # Plan d'implémentation
└── walkthrough.md            # Résumé du projet (FR)
```

---

## Modules / Départements (12)

| URL | Controller | Module |
|-----|-----------|--------|
| `/` | DashboardController | Dashboard principal |
| `/rh` | RHController | Ressources Humaines |
| `/compta` | ComptaController | Comptabilité |
| `/production` | ProductionController | Production |
| `/prestation` | PrestationController | Prestations |
| `/sinistre` | SinistreController | Sinistres |
| `/serviceentreprise` | ServiceEntrepriseController | Service Entreprise |
| `/controleinterne` | ControleInterneController | Contrôle Interne |
| `/communication` | CommunicationController | Communication |
| `/relationclient` | RelationClientController | Relation Client |
| `/marketing` | MarketingController | Marketing |
| `/vente` | VenteController | Vente |

---

## Base de Données

**Migration :** `Version20260322175412`

```sql
-- Table user
id INT AUTO_INCREMENT PRIMARY KEY
email VARCHAR(180) UNIQUE NOT NULL
roles JSON NOT NULL
password VARCHAR(255) NOT NULL

-- Table department
id INT AUTO_INCREMENT PRIMARY KEY
name VARCHAR(255) NOT NULL

-- Table messenger_messages  (interne Symfony Messenger)
```

**Connexion locale (.env) :**
```
DATABASE_URL="mysql://root:@127.0.0.1:3306/dashboard?serverVersion=8.0.32&charset=utf8mb4"
```

---

## Intégrations API Prévues

- **YouTrack** — Suivi de tickets (`src/Service/YouTrackApiService.php`)
- **Odoo ERP** — Gestion ERP (`src/Service/OdooApiService.php`)

Ces services sont des placeholders à configurer avec les credentials dans `.env`.

---

## État du Projet (mars 2026)

- Scaffolding Symfony complet (controllers, entities, templates, navigation)
- Interface Bootstrap 5 avec sidebar de navigation
- Authentification Symfony Security en place
- Logique métier à implémenter dans chaque module département
- Services API externes à connecter (YouTrack, Odoo)

---

## Commandes Utiles

```bash
# Lancer le serveur Symfony (si pas WAMP)
symfony server:start

# Créer/mettre à jour la base de données
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# Vider le cache
php bin/console cache:clear

# Générer une entité
php bin/console make:entity

# Générer un controller
php bin/console make:controller

# Lancer les tests
php bin/phpunit
```
