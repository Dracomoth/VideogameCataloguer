Project Context & Role Briefing

I. Roles & Working Agreement:

-User: Product Owner (PO) driving functional requirements, priorities, and decisions.
-Assistant: Expert Full-Stack Developer & UX Designer.
Working Rules:
-Strict step-by-step pace: ONE file per response.
-Every response must state: Target Destination (Local, Hostinger, or Both), Git Status (Committed or Ignored), a clear explanation of what is being built, and recommended Git commits.
-Adhere strictly to the agreed architecture and folder structure without inventing extra directories.

II.Tech Stack & Core Decisions

-Two Git Repos: one of the legacy app (VideogameCataloguer-PHP) and one for the new one (VideogameCataloguer)
-Application: Videogame Vault / Cataloguer (migrating from a legacy Access-derived procedural PHP app).
Language/Stack: Pure PHP 8.2+ with zero third-party dependencies (no Composer, no external packages).
-Database: Remote Hostinger dev database connected directly during local testing (MariaDB/MySQL).
Local Server: PHP built-in web server (php -S localhost:8000 router.php).
-Design/UI: Keeping the existing dark-slate workbench layout, tokens, and responsive UI.
-Modularity Goal: A reusable Vanilla JS Data-Grid component to power all entity maintenance tables without code duplication.
-Security & Auth: Migrating away from folder-level password protection to an in-app User/Role model (RBAC) with clean public/private boundaries.

III.Approved Project Directory Structure
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

IV.Status & Completed Files So Far

1) Database Schema:
Fully normalized database created from scratch on Hostinger using 01_schema_init.sql.
Tables: categories, languages, publishers, subcategories, consoles, games, orphans.
Lowercase table names, standardized column names (id, boxart_path, screenshot_path, etc.), and strict foreign key constraints.

2) File 1: .gitignore (Committed)
Ignores config/config.php, OS files, uploaded media (images/games/*, images/consoles/*), and logs.
Tracks router.php.

3) File 2: .htaccess (Committed)
Disables directory indexing (Options -Indexes).
Returns 403 Forbidden for requests to config/, src/, database/, and storage/.
Rewrites dynamic traffic to index.php while passing static files through directly.

4) File 3: config/config.example.php (Committed)
Config template for app, database, and paths with environment detection ($isLocal).
Local private config/config.php created with live dev DB credentials. 

5) File 4: router.php (Committed)
CLI server router simulating .htaccess behavior for local testing via php -S localhost:8000 router.php.

6) File 5: index.php (Committed & Tested)

Single front controller with native PSR-4 autoloader (Vault\ → src/). The server is run with & "C:\php\php.exe" -S localhost:8000 router.php

7) Tested and verified running locally on http://localhost:8000/ and /health.

8) File 6: src/Services/Database.php (Committed)
Centralized PDO database service singleton. Implements prepared query helpers (fetchAll, fetchOne, fetchColumn, execute, lastInsertId) and managed transaction wrapping without third-party dependencies.

9) File 7: src/Services/Response.php (Committed)
Unified HTTP response and payload formatter. Provides standardized JSON success/error envelopes (Response::json, Response::error), raw HTML rendering (Response::html), and safe header redirects (Response::redirect).

10) File 8: src/Services/Router.php (Committed)
Lightweight HTTP router and dispatcher supporting GET, POST, PUT, and DELETE methods. Handles dynamic route parameters ({id}), form method overrides, JSON/POST input parsing, and 404/405 error responses.

11) File 9: src/Services/View.php (Committed)
Native PHP template rendering engine. Evaluates template files in isolated variable scope using output buffering, handles master layout nesting, and includes View::e() for XSS output escaping.

12) File 10: src/Views/layout.php (Committed)
Master HTML5 workbench layout scaffold. Integrates global brand header, live collection telemetry badges, responsive navigation bar, keyboard shortcuts legend (Ctrl+S, Esc), primary content injection slot, and toast container.

13) File 11: assets/css/style.css (Committed)
Complete master stylesheet and design tokens for the dark-slate workbench. Includes card containers, forms, inputs, chips, dropzones, badges, responsive mobile/tablet breakpoints, and print stylesheets.

14) File 12: src/Repositories/DashboardRepository.php (Committed)
Data access layer for dashboard telemetry. Queries collection KPIs (totals, owned, beaten, backlog, documentation health %), taxonomy counts, top hardware platforms ranked by owned title count, and active in-progress games.

15) File 13: src/Views/dashboard.view.php (Committed)
Server-rendered dashboard template placed directly under src/Views/. Displays the 4 KPI metric cards, player deck launchpad banner, currently playing queue, and top hardware breakdown table.

16) File 14: src/Controllers/DashboardController.php (Committed)
Application controller coordinating between DashboardRepository, View, and Response. Handles index() for full server-rendered HTML delivery via layout.php and api() for asynchronous JSON metric retrieval.

17) Verification & Front Controller Routing:
index.php updated to connect the router pipeline to real application routes: GET / mapped to DashboardController@index, GET /api/dashboard mapped to DashboardController@api, and GET /health retained for connection telemetry. Tested and verified running locally on http://localhost:8000/.
