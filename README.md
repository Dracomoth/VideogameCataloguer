

& "C:\php\php.exe" -S localhost:8000 router.php

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