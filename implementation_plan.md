# Dashboard Symfony Project Plan

## Goal
Create a modular enterprise dashboard in Symfony, connected to a MySQL database named `dashboard`. The system will encompass multiple business scopes (RH, Compta, Production, etc.) and integrate with external APIs like YouTrack and Odoo.

## User Review Required
- Do you have a specific Symfony version preference? (e.g., 7.x, 6.4 LTS). We will default to the latest stable version (7.x) using WAMP's local PHP.
- Do you already have the YouTrack URL/Token for integration, or should we just build the empty service structure for now?

## Proposed Architecture

### 1. Initialization
- **Environment**: PHP and Composer are not globally installed, so we will use WAMP's PHP executable and download `composer.phar` locally to initialize the project within `C:\wamp\www\Dashboard`.
- **Framework**: Symfony WebApp (contains Twig, Doctrine, Security).
- **Database**: MySQL/MariaDB (via Doctrine ORM).
- **Location**: `C:\wamp\www\Dashboard`.

### 2. Modules (Controllers & Views)
We will create a structured namespace or simple Controller segregation for each department:
- `RHController`
- `ComptaController`
- `ProductionController`
- `PrestationController`
- `SinistreController`
- `EntrepriseController` (Service Entreprise)
- `ControleInterneController`
- `CommunicationController`
- `RelationClientController`
- `MarketingController`
- `VenteController`

### 3. API Integrations
- **HTTP Client**: Use `symfony/http-client`.
- **YouTrackService**: A dedicated service class `src/Service/YouTrackApiService.php` to fetch and post tickets.
- **OdooService**: A placeholder service class `src/Service/OdooApiService.php` for future ERP integration.

## Verification Plan
### Automated & Manual Verification
- Verify the Symfony welcome page at `http://localhost/Dashboard/public/`.
- Ensure `php bin/console doctrine:database:create` executes successfully.
- Verify that `http://localhost/Dashboard/public/rh` routes to the RH dashboard module.
