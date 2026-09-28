Project Context & Role Briefing
Roles & Working Agreement:
User: Product Owner (PO) driving functional requirements, priorities, and decisions.
Assistant: Expert Full-Stack Developer & UX Designer.
Working Rules:
Strict step-by-step pace: ONE file per response.
Every response must state: Target Destination (Local, Hostinger, or Both), Git Status (Committed or Ignored), a clear explanation of what is being built, and recommended Git commits.
Adhere strictly to the agreed architecture and folder structure without inventing extra directories.
Tech Stack & Core Decisions
Two Goit Repos: one of the legacy app (VideogameCataloguer-PHP) and one for the new one (VideogameCataloguer)
Application: Videogame Vault / Cataloguer (migrating from a legacy Access-derived procedural PHP app).
Language/Stack: Pure PHP 8.2+ with zero third-party dependencies (no Composer, no external packages).
Database: Remote Hostinger dev database connected directly during local testing (MariaDB/MySQL).
Local Server: PHP built-in web server (php -S localhost:8000 router.php).
Design/UI: Keeping the existing dark-slate workbench layout, tokens, and responsive UI.
Modularity Goal: A reusable Vanilla JS Data-Grid component to power all entity maintenance tables without code duplication.
Security & Auth: Migrating away from folder-level password protection to an in-app User/Role model (RBAC) with clean public/private boundaries.
Approved Project Directory Structure
Targeting subdomain root on Hostinger (public_html/vdgn-test/). Project folder structure:

public_html/vdgn-test/
├── README.md                     # Contains architecture map, setup guide, roles
├── .htaccess                     # Central rewrite & security firewall
├── index.php                     # Single Front Controller entry point
│
├── database/                     # [PROTECTED] Schema & migrations
│   ├── 01_schema_init.sql        # Clean base schema (tables, foreign keys)
│   ├── 02_normalize_db.sql       # Your sanitization script
│   └── 03_users_and_roles.sql    # New users & RBAC schema
│
├── config/                       # [PROTECTED]
│   └── config.php
│
├── src/                          # [PROTECTED] Core backend logic
│   ├── Auth/
│   ├── Controllers/
│   ├── Repositories/
│   ├── Services/
│   └── Views/
│
├── assets/                       # [PUBLIC]
│   ├── css/
│   │   └── style.css[cite: 1]
│   └── js/
│       ├── components/
│       │   └── data-grid.js
│       └── app.js
│
└── images/                       # [PUBLIC] Media storage[cite: 1]
    ├── games/[cite: 1]
    ├── consoles/[cite: 1]
    └── support/[cite: 1]

Status & Completed Files So Far
Database Schema:
Fully normalized database created from scratch on Hostinger using 01_schema_init.sql.
Tables: categories, languages, publishers, subcategories, consoles, games, orphans.
Lowercase table names, standardized column names (id, boxart_path, screenshot_path, etc.), and strict foreign key constraints.
File 1: .gitignore (Committed)
Ignores config/config.php, OS files, uploaded media (images/games/*, images/consoles/*), and logs.
Tracks router.php.
File 2: .htaccess (Committed)
Disables directory indexing (Options -Indexes).
Returns 403 Forbidden for requests to config/, src/, database/, and storage/.
Rewrites dynamic traffic to index.php while passing static files through directly.
File 3: config/config.example.php (Committed)
Config template for app, database, and paths with environment detection ($isLocal).
Local private config/config.php created with live dev DB credentials.
File 4: router.php (Committed)
CLI server router simulating .htaccess behavior for local testing via php -S localhost:8000 router.php.
File 5: index.php (Committed & Tested)
Single front controller with native PSR-4 autoloader (Vault\ → src/). The server is run with & "C:\php\php.exe" -S localhost:8000 router.php
Tested and verified running locally on http://localhost:8000/ and /health..
