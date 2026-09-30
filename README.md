# Videogame Cataloguer

## Description

**Videogame Cataloguer** is a modern, lightweight web application for managing a personal video‑game collection. Built with pure PHP 8.2+ (no third‑party dependencies) and a responsive, dark‑slate UI, it provides a full‑stack CRUD experience for games, consoles, taxonomies, users, and roles while enforcing granular, dual‑device role‑based access control (RBAC).

## Main Features

- **Modular Architecture** – Separate view components (`header.php`, `navbar.php`, `footer.php`) and a reusable vanilla‑JS `data‑grid` web component for fast, client‑side sorting, filtering, and pagination.
- **Rich UI** – Dark‑slate theme with CSS design tokens, responsive breakpoints, and micro‑animations for a premium feel.
- **RBAC & Device Awareness** – Permissions (`none`, `read`, `write`) are scoped per screen and per device type (PC vs. mobile/other), with a Super‑Admin bypass.
- **Comprehensive CRUD** – Manage games, consoles, categories, sub‑categories, publishers, languages, and users through clean JSON APIs and server‑rendered PHP views.
- **Secure Authentication** – Bcrypt‑hashed passwords, session handling, and automatic logout on inactivity.
- **Asset Lifecycle** – Automatic image handling for screenshots, box‑art, console logos, etc., with safe upload, rename, and cleanup.
- **Zero‑Dependency Deployment** – Runs on PHP’s built‑in web server; no Composer or external packages required.
- **Extensible Design** – Easily add new taxonomies or UI components without touching core logic.

## Installation

1. **Prerequisites**
   - PHP 8.2 or newer (CLI and web server)
   - MariaDB/MySQL database
   - Git (to clone the repository)
   - (Optional) A local development web server like `php -S` or any LAMP stack.

2. **Clone the repository**
   ```bash
   git clone https://github.com/your-org/VideogameCataloguer.git
   cd VideogameCataloguer
   ```

3. **Configure the application**
   - Copy the example config and edit your secrets:
     ```bash
     cp config/config.example.php config/config.php
     ```
   - Open `config/config.php` and set:
     - Database host, name, username, and password
     - Any other environment‑specific constants (e.g., `BASE_URL`).
   - Ensure `config/config.php` is listed in `.gitignore` (it already is) so credentials are not committed.

4. **Create the database schema**
   - Import the provided SQL files in order:
     ```bash
     mysql -u <user> -p < database/01_schema_init.sql
     mysql -u <user> -p < database/02_users_and_roles.sql
     ```
   - This will create tables for games, consoles, taxonomies, users, roles, and RBAC mappings, plus a default Super‑Admin account (`admin@videogamevault.local` / `Password#2026!`). Keep in mind you need to replacer this email and password on script 02_users_and_roles.sql with your own before you run this script on your database. The password must be hashed, so before adding it to the script you need to generate the hash from the password string. Just open a terminal window and run the following command:
   ```bash
   php -r "echo password_hash('YourNewPasswordHere!', PASSWORD_BCRYPT, ['cost' => 12]) . PHP_EOL;"
   ```
   Copy the result into the script, in the last insert.

5. **Start the development server**
   ```bash
   php -S localhost:8000 router.php
   ```
   - Open a browser and navigate to `http://localhost:8000`. The app will redirect to the login page if you are not authenticated.

6. **Deploy to production (Hostinger example)**
   - Upload the entire project to the sub‑domain’s `public_html/vdgn-test/` directory.
   - Ensure `.htaccess` is present to route all requests to `index.php`.
   - Update `config/config.php` with the production database credentials.
   - Secure the `config/` directory via the web‑server configuration (already blocked by `.htaccess`).

## Running the Application

- **Local**: `php -S localhost:8000 router.php`
- **Production**: Access through the configured domain/sub‑domain; the built‑in router works with the provided `.htaccess` rules.

## Contributing

Contributions are welcome! Please fork the repository, make your changes, and submit a pull request. Follow the existing coding style (PSR‑12) and keep the zero‑dependency philosophy.

## License

This project is licensed under the MIT License.


I. Roles & Working Agreement:

- User: Product Owner (PO) driving functional requirements, priorities, and decisions.
- Assistant: Expert Full-Stack Developer & UX Designer.
Working Rules:
- Strict step-by-step pace: ONE file per response.
- Every response must state: Target Destination (Local, Hostinger, or Both), Git Status (Committed or Ignored), a clear explanation of what is being built, and recommended Git commits.
- Adhere strictly to the agreed architecture and folder structure without inventing extra directories.

II. Tech Stack & Core Decisions

- Two Git Repos: one of the legacy app (VideogameCataloguer-PHP) and one for the new one (VideogameCataloguer)
- Application: Videogame Vault / Cataloguer (migrating from a legacy Access-derived procedural PHP app).
- Language/Stack: Pure PHP 8.2+ with zero third-party dependencies (no Composer, no external packages).
- Database: Remote Hostinger dev database connected directly during local testing (MariaDB/MySQL).
- Local Server: PHP built-in web server (`php -S localhost:8000 router.php`).
- Design/UI: Dark-slate workbench layout, semantic tokens, and responsive UI.
- Modularity Goal: Modular view components (`header.php`, `navbar.php`, dynamic body slot, `footer.php`) and a reusable Vanilla JS Data-Grid component.
- Security & Auth: Comprehensive in-app User & Dual-Device Role-Based Access Control (RBAC). Differentiates Desktop PC from Other Devices (mobile, handheld, tablet) with granular permissions (`none`, `read`, `write`) per screen.

III. Status & Completed Files So Far

1) Database Schema:
- Base Schema (`database/01_schema_init.sql`): Fully normalized database created on Hostinger dev DB. Tables: `categories`, `languages`, `publishers`, `subcategories`, `consoles`, `games`, `orphans`.
- Users & RBAC Schema (`database/02_users_and_roles.sql`): Tables: `roles`, `screens`, `users`, `role_permissions`. Minimal seed containing only the primary `Super Admin` role and account (`admin@videogamevault.local` / `Password#2026!`).

2) File 1: .gitignore (Committed)
Ignores `config/config.php`, OS files, uploaded media (`images/games/*`, `images/consoles/*`), and logs. Tracks `router.php`.

3) File 2: .htaccess (Committed)
Disables directory indexing (`Options -Indexes`). Returns 403 Forbidden for requests to `config/`, `src/`, `database/`, and `storage/`. Rewrites dynamic traffic to `index.php`.

4) File 3: config/config.example.php (Committed)
Config template for app, database, and paths with environment detection (`$isLocal`). Local private `config/config.php` created with live dev DB credentials.

5) File 4: router.php (Committed)
CLI server router simulating `.htaccess` behavior for local testing via `php -S localhost:8000 router.php`.

6) File 5: src/Services/Database.php (Committed)
Centralized PDO database service singleton. Implements prepared query helpers (`fetchAll`, `fetchOne`, `fetchColumn`, `execute`, `lastInsertId`) and transaction wrapping.

7) File 6: src/Services/Response.php (Committed)
Unified HTTP response and payload formatter. Provides standardized JSON success/error envelopes (`Response::json`, `Response::error`), raw HTML rendering (`Response::html`), and redirects (`Response::redirect`).

8) File 7: src/Services/Router.php (Committed)
Lightweight HTTP router and dispatcher supporting GET, POST, PUT, and DELETE methods. Handles dynamic route parameters (`{id}`), form method overrides (`_method`), JSON/POST input parsing, and 404/405 error responses.

9) File 8: src/Services/View.php (Committed)
Native PHP template rendering engine. Evaluates template files in isolated variable scope using output buffering, handles master layout nesting, and includes `View::e()` for XSS escaping.

10) File 9: assets/css/style.css (Committed)
Complete master stylesheet and design tokens for the dark-slate workbench. Includes card containers, forms, inputs, chips, segmented buttons, badges, and responsive mobile/tablet breakpoints.

11) File 10: src/Repositories/DashboardRepository.php (Committed)
Data access layer for dashboard telemetry. Queries collection KPIs (totals, owned, beaten, backlog, documentation health %), taxonomy counts, hardware rankings, and active in-progress games.

12) File 11: Modular Views Architecture (Committed)
- `src/Views/header.php`: Global brand header with live database engine telemetry pills.
- `src/Views/navbar.php`: RBAC-aware navigation bar displaying accessible screens, user profile badge, and login/logout controls. Removed `+ Add Game` button for cleaner navigation.
- `src/Views/footer.php`: Status footer with shortcuts guide and copyright.
- `src/Views/layout.php`: Master scaffold coordinating header, navbar, dynamic body slot (`<?= $content ?>`), and footer directly under `src/Views/`.
- `src/Views/dashboard.php`: Permanent Command Center body view replacing the temporary prototype.

13) File 12: src/Auth/Auth.php (Committed)
Central authentication and RBAC service. Detects device category (`pc` vs `other`), enforces session lifetime, checks screen permissions (`can($screen, $level)`), and provides automatic Super Admin bypass (`is_super = 1`).

14) File 13: src/Controllers/AuthController.php & src/Views/login.php (Committed)
Authentication controller and standalone dark-slate login screen. Validates credentials via Bcrypt hashing, establishes secure sessions, and handles safe logouts.

15) File 14: src/Repositories/UserRepository.php & src/Controllers/UserController.php (Committed)
User data access layer and management controller. Handles admin-created users, profile updates, secure Bcrypt password resets, active status toggling, and Super Admin deletion safeguards.

16) File 15: src/Views/users.php (Committed)
User Management directory table and modal form. Features read-only state for restricted devices/roles and a client-side 16-character cryptographic random password generator (`generateSecurePassword()`).

17) File 16: src/Repositories/RoleRepository.php & src/Controllers/RoleController.php (Committed)
Role repository and controller managing custom roles and transactional dual-device permission matrix persistence across all registered screens.

18) File 17: src/Views/roles.php (Committed)
Two-column permissions workbench. Left column lists roles; right column provides a granular segmented radio matrix table (None / Read / Write) for PC Access and Other Device Access, complete with batch device preset buttons (`All Read`, `All Write`, `All None`).

19) File 18: index.php (Committed & Tested)
Updated front controller routing table connecting Authentication (`/login`, `/logout`), Dashboard (`/`, `/api/dashboard`), User Management (`/users`), and Roles Matrix (`/roles`, `/api/roles/{id}/matrix`).

20) File 19: src/Controllers/DashboardController.php (Committed & Tested)
Enforced `Auth::requireAccess('dashboard', 'read')` on `index` and `api` endpoints, redirecting unauthenticated visitors to `/login`.

21) File 20: Universal Data-Grid Web Component (Committed & Tested)
- `assets/js/components/data-grid.js`: Zero-dependency, reusable `<data-grid>` (`<vault-grid>`) Web Component.
- Features: Rich column rendering (pill badges, bold text, action buttons, custom HTML callbacks), instant multi-column search filtering, multi-type column sorting (text, number, date), and comprehensive pagination controls: First (`<<`), Previous (`<`), Next (`>`), Last (`>>`), Page X of Y indicator, Jump to Page direct input, and 25 / 50 / 100 items per page selector.
- `assets/css/style.css`: Section 20 `.vault-grid-*` centralized dark-slate styles with zero embedded runtime styles in JavaScript.
- `src/Views/layout.php`: Registered Web Component script globally with automatic cache-busting (`filemtime`).

22) File 21: Taxonomy Languages & Regions Maintenance Module (Tested & Complete)
- `src/Repositories/LanguageRepository.php`: Data access layer for `languages` table with game reference counts (`COUNT(games.id)`), duplicate name checking, and safe deletion prevention if linked games exist.
- `src/Controllers/LanguageController.php`: RBAC protected (`Auth::requireAccess('languages', 'read'|'write')`) controller providing `index`, `apiList`, `create`, `update`, and `delete`.
- `src/Views/languages.php`: Master-detail split workbench layout with persistent form editor and reusable `<data-grid>`.
- `src/Views/navbar.php` & `index.php`: Updated desktop navigation tab and registered routes (`/languages`, `/api/languages`, `/languages/create`, `/languages/{id}/update`, `/languages/{id}/delete`).

23) File 22: Taxonomy Categories Maintenance Module (Tested & Complete)
- `src/Repositories/CategoryRepository.php`: Data access layer with hierarchy aggregation (`subcategories_raw`), `subcat_count`, `games_count`, and deletion safeguards blocking deletion if subcategories or games are attached.
- `src/Controllers/CategoryController.php`: RBAC protected controller with standard JSON response envelopes (`Response::json()`, `Response::error()`).
- `src/Views/categories.php`: Split workbench layout with persistent left editor, subcategory chip inspector, `<data-grid>` (ID, Category, Subcategories badge, Linked Games badge), and keyboard shortcuts.
- `index.php`: Registered routes (`/categories`, `/api/categories`, `/categories/create`, `/categories/{id}/update`, `/categories/{id}/delete`).

24) File 23: Taxonomy Publishers & Hardware Manufacturers Maintenance Module (Tested & Complete)
- `src/Repositories/PublisherRepository.php`: Data access layer joining `consoles` and `games`, computing `consoles_count` and `games_count`, with `is_console_maker` classification.
- `src/Controllers/PublisherController.php`: RBAC protected controller supporting hardware manufacturer flag mutations and deletion safeguards.
- `src/Views/publishers.php`: Split workbench layout with persistent left editor, `🕹️ Console / Hardware Maker` checkbox, registered platforms inspector, and `<data-grid>` with hardware badges and platform counts.
- `index.php`: Registered routes (`/publishers`, `/api/publishers`, `/publishers/create`, `/publishers/{id}/update`, `/publishers/{id}/delete`).

25) File 24: Taxonomy Subcategories Maintenance Module (Tested & Complete)
- `src/Repositories/SubcategoryRepository.php`: Data access layer joining `categories` and `games`, ensuring scoped category uniqueness and relational safeguards.
- `src/Controllers/SubcategoryController.php`: RBAC protected controller passing categories list for parent assignment dropdown and grid filtering.
- `src/Views/subcategories.php`: Split workbench layout with persistent parent category dropdown, `<data-grid>` with category pill badges and top category filter dropdown (`grid.customFilters`).
- `index.php`: Registered routes (`/subcategories`, `/api/subcategories`, `/subcategories/create`, `/subcategories/{id}/update`, `/subcategories/{id}/delete`).

26) File 25: Hardware & Consoles Maintenance Module (Tested & Complete)
- `src/Repositories/ConsoleRepository.php`: Full CRUD repository managing hardware specifications, form factor flags, emulation links, and strict 4-step image lifecycle operations:
  - **Insert**: Uploaded images are renamed before storage using `<id>_<console name(escaped)>_image.<ext>` for `image_path` and `<id>_<console name(escaped)>_logo.<ext>` for `logo_path`.
  - **Update**: Retrieves previous values, unlinks existing files on disk regardless of previous path value, and renames/stores newly uploaded assets with the same convention.
  - **Read**: Retrieves values directly from fields and exposes web URLs without enforcing naming conventions.
  - **Delete**: Unlinks whatever files are referenced in `image_path` and `logo_path` before record deletion, subject to library game reference safeguards.
- `src/Controllers/ConsoleController.php`: RBAC protected controller handling multipart form uploads and JSON API responses.
- `src/Views/consoles.php`: Master-detail split workbench with persistent form editor (460px), dual visual asset dropzones with instant image preview & removal, collapsible emulation & technical links details, hardware type flags, and searchable `<data-grid>` with form factor filtering (`grid.customFilters`).
- `index.php`: Registered routes (`/consoles`, `/api/consoles`, `/consoles/create`, `/consoles/{id}/update`, `/consoles/{id}/delete`).

27) File 26: Game Cataloguer & Asset Hub Maintenance Module (Tested & Complete)
- `src/Repositories/GameRepository.php`: Full CRUD repository managing game titles, platforms, categories, subcategories, publishers, languages, collection status toggle, personal notes, tags, and strict 4-step image lifecycle operations:
  - **Insert**: Uploaded images are renamed before storage using `<ID>_Img.<ext>` for `screenshot_path` and `<ID>_Box.<ext>` for `boxart_path`. Relative paths stored in database.
  - **Update**: Retrieves previous values from DB, unlinks existing files on disk regardless of previous path value, and renames/stores newly uploaded assets with `<ID>_Img.<ext>` and `<ID>_Box.<ext>`. If removal is requested (`delete_screenshot` or `delete_boxart`), unlinks existing file and clears field to `NULL`.
  - **Read**: Retrieves values directly from fields and exposes web URLs without enforcing naming conventions on read. Computes `collection_status` badge.
  - **Delete**: Unlinks whatever files are referenced in `screenshot_path` and `boxart_path` before record deletion.
- `src/Controllers/GameController.php`: RBAC protected controller handling multipart form uploads, taxonomy reference datasets, and JSON API responses.
- `src/Views/games.php`: Master-detail split workbench with persistent form editor (500px), platform and dynamic subcategory hierarchy filter, collection status chip toggle (`In Collection`), dual visual asset dropzones (`Upload Box Art` `<ID>_Box.ext` and `Upload Screenshot` `<ID>_Img.ext`) with instant preview & removal, AI Auto-Fill action button (`✨ AI Auto-Fill`) with sparkle icon, tags and personal notes, and high-density searchable `<data-grid>` with platform and genre dropdown filtering (`grid.customFilters`) and completion status badges.
- `index.php`: Registered routes (`/games`, `/api/games`, `/games/create`, `/games/{id}/update`, `/games/{id}/delete`).

