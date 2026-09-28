Project Context & Role Briefing

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

III. Approved Project Directory Structure
Targeting subdomain root on Hostinger (public_html/vdgn-test/). Project folder structure:

public_html/vdgn-test/
├── README.md                     # Contains architecture map, setup guide, roles
├── .htaccess                     # Central rewrite & security firewall
├── index.php                     # Single Front Controller entry point
│
├── database/                     # [PROTECTED] Schema & migrations
│   ├── 01_schema_init.sql        # Clean base schema (games, consoles, taxonomies)
│   └── 02_users_and_roles.sql    # Users, roles, screens, and permissions schema
│
├── config/                       # [PROTECTED]
│   ├── config.example.php        # Distribution template
│   └── config.php                # Environment secrets & DB credentials (git-ignored)
│
├── src/                          # [PROTECTED] Core backend logic
│   ├── Auth/
│   │   └── Auth.php              # Central authentication, session & RBAC engine
│   ├── Controllers/
│   │   ├── AuthController.php    # Session login & logout handler
│   │   ├── DashboardController.php # Command center telemetry & workbench
│   │   ├── RoleController.php    # Dual-device matrix & role configuration
│   │   └── UserController.php    # User administration & profile CRUD
│   ├── Repositories/
│   │   ├── DashboardRepository.php # Collection metrics & rankings
│   │   ├── RoleRepository.php    # Dual-device matrix persistence
│   │   └── UserRepository.php    # User record & role queries
│   ├── Services/
│   │   ├── Database.php          # PDO connection & query service
│   │   ├── Response.php          # JSON, HTML & redirect responder
│   │   ├── Router.php            # Zero-dependency HTTP dispatcher
│   │   └── View.php              # PHP template rendering engine
│   └── Views/                    # Modular view layer (flat structure)
│       ├── dashboard.php         # Permanent Command Center body view
│       ├── footer.php            # Modular footer component
│       ├── header.php            # Modular top brand & telemetry banner
│       ├── layout.php            # Master scaffold wrapper
│       ├── login.php             # Standalone dark-slate login screen
│       ├── navbar.php            # Modular RBAC-filtered navigation bar
│       ├── roles.php             # Dual-device permissions matrix workbench
│       └── users.php             # User management directory & modal
│
├── assets/                       # [PUBLIC]
│   ├── css/
│   │   └── style.css             # Master stylesheet & CSS variables
│   └── js/
│       ├── components/
│       │   └── data-grid.js      # Reusable vanilla data-grid
│       └── app.js
│
└── images/                       # [PUBLIC] Media storage
    ├── games/
    ├── consoles/
    └── support/

IV. Status & Completed Files So Far

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
