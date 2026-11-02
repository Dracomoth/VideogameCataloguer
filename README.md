# 🎮 Videogame Vault (Videogame Cataloguer)

**Videogame Vault** (also known as **Videogame Cataloguer**) is a full-stack, zero-dependency web application built with **pure PHP 8.2+** and a responsive dark-slate workbench interface. It is designed to catalog, preserve, explore, and distribute a personal or multi-user video game and hardware collection, complete with AI-assisted metadata curation, secure cloud file distribution, per-player progress tracking, and dual-device Role-Based Access Control (RBAC).

---

## 🎯 Purpose of the Software

Videogame Vault serves as both an **archival management workbench** for collection administrators and an **interactive discovery portal** for players:

1. **Centralized Preservation & Cataloging:** Maintain a normalized relational database of video games, consoles/hardware platforms, publishers, hardware manufacturers, genres (categories & subcategories), and languages/regions.
2. **Digital Asset & Emulation Hub:** Store box art, gameplay screenshots, hardware photos, logos, controller button mappings (Gamepad, Cellphone Adapter, ASUS ROG Ally), emulator recommendations, RetroArch cores, and downloadable files (ROMs, ISOs, BIOS packs, patches, and manuals) hosted either externally or in a private **Backblaze B2** cloud bucket.
3. **AI-Powered Archival Curation:** Eliminate tedious manual data entry using integrated **Google Gemini AI** workflows that automatically write game descriptions, generate searchable taxonomy tags, compile console technical specifications & BIOS requirements, and author publisher histories.
4. **Multi-User Player Experience:** Separate read-only/player exploration portals from administrative catalog screens, enabling multiple users to browse the library, spin a random game selector, download files, report broken links, and track their own personal play and completion ("beaten") statistics.

---

## ✨ Key Features

### 1. Overview & Player Portals
* **Command Center Dashboard (`/`)**: Real-time collection telemetry and KPIs (total games, owned vs. reference, personal played/beaten counts, completion rates), top hardware rankings, genre breakdowns, and an interactive "In-Progress" games tracker.
* **Player Hub (`/collection` / `/player-hub`)**: A discovery spotlight experience featuring a random **"Spin / Pick a Game"** selector and live-filtered visual gallery by platform, category, subcategory, and keyword search.
* **Games Portal (`/games-portal`)**: Player-facing encyclopedia to browse games, inspect box art and screenshots, read descriptions, trigger secure file downloads, report broken download links, and toggle personal **Played** / **Won (Beaten)** status (unlocked once a game has been downloaded).
* **Consoles Portal (`/consoles-portal`)**: Interactive hardware showcase displaying console imagery, technical specifications, emulation setups (PC & Android emulators, RetroArch cores), downloadable BIOS/support files, and visual controller button mapping diagrams (Standard Gamepad, Cellphone Adapter, and ROG Ally).
* **Publishers Portal (`/publishers-portal`)**: Dedicated company directory highlighting studio histories, logos, manufactured consoles, and published game libraries.

### 2. Catalog & Taxonomy Administration
* **Games Catalog Workbench (`/games`)**: Master-detail split editor with instant `<vault-grid>` search, sorting, and pagination. Manages game metadata, collection status (`In Collection`), multiple downloadable file attachments, and strict 4-step image lifecycle management (`<ID>_Box.<ext>` and `<ID>_Img.<ext>`).
* **Consoles & Hardware Workbench (`/consoles`)**: Full CRUD for hardware platforms, release years, generations, form-factor classifications (`Console Types`), reference-only platform linking (`master_reference_id`), emulator links, downloadable files, hardware photos/logos, and 3-way controller mapping images.
* **Publishers & Studios (`/publishers`)**: Manage software publishers and hardware manufacturers (`is_console_maker`), company logos, and historical descriptions.
* **Metadata Taxonomies**:
  * **Console Types (`/console-types`)**: Dynamic hardware categories (e.g., *Home*, *Arcade*, *Microcomputer*, *Handheld*) with customizable hex badge background and font colors.
  * **Categories (`/categories`) & Subcategories (`/subcategories`)**: Two-tier hierarchical genre classification with relational deletion safeguards.
  * **Languages & Regions (`/languages`)**: Release localization and region tracking.

### 3. AI Auto-Fill Integration (Google Gemini)
* **✨ Game Metadata Auto-Fill**: Generates a curated comma-separated list of lowercase tags (platform, subgenre, peripherals like Zapper/Paddle, player modes) and a clean 1–3 paragraph gameplay overview.
* **✨ Console Specs Auto-Fill**: Generates a structured hardware summary, bulleted technical specifications (CPU, RAM, video, audio, media), and required emulation BIOS filenames.
* **✨ Publisher History Auto-Fill**: Writes a 2–3 paragraph company profile covering founding, landmark franchises, hardware contributions, and legacy.
* **Multi-Model Fallback Cascade**: Automatically cascades across configured Gemini models (e.g., `gemini-3.8-flash` -> `gemini-3.7-flash` -> `gemini-3.5-flash`) if a model experiences high demand or rate limits.

### 4. Cloud Downloads & Broken Link Remediation
* **Backblaze B2 Private Bucket Integration**: Generates time-limited, signed download authorization URLs via native Backblaze B2 REST APIs or AWS S3 SigV4 presigned URLs—keeping ROMs, BIOS files, and patches secure from public hotlinking.
* **Download Telemetry**: Tracks per-file download counts, timestamps, and per-user download history (`player_games`).
* **Catalog Services (`/catalog-services`)**: Ticketing workbench where administrators review user-reported broken download links, update the underlying Backblaze file key or external URL in-place, or dismiss resolved tickets.

### 5. Data Operations, Reporting & Auditing
* **Bulk Upload Workbench (`/bulk-upload`)**: Schema-aware batch importer supporting CSV, TSV, semicolon-delimited `.txt`, and JSON payloads with pre-insertion foreign-key and constraint validation.
* **Reports & Collection Audits (`/reports`)**:
  * **Curated Premade Audits**: Instant health checks (e.g., games missing screenshots/box art, unlinked taxonomies, orphan assets, collection completion).
  * **Custom Report Builder**: Select any catalog table and custom column order, and export to **CSV** (UTF-8 BOM), **Tab-Delimited TXT**, or **Excel (`.xlsx`)** via bundled SheetJS.

### 6. Security, Users & Dual-Device RBAC
* **Dual-Device Role-Based Access Control (`/roles`)**: Granular permission matrix (`none`, `read`, `write`) configured per screen and split by device category: **PC (Desktop)** vs. **Other Devices (Mobile / Handheld / Tablet)**. For example, a role can be granted `write` access on a desktop PC while being restricted to `read`-only on a handheld or smartphone.
* **User Management & Self-Service Profiles (`/users`)**: Admin-only user provisioning, active status toggling, cryptographic password generator, and a self-service Profile Modal where any logged-in user can update their name, username, email, avatar image, and password.
* **Zero-Dependency Footprint**: Built entirely with native PHP 8.2+ (PDO, cURL, finfo, Sessions) and vanilla ES6 Web Components (`<vault-grid>`). No Composer, Node.js, or external vendor frameworks required.

---

## 📋 Prerequisites

### 1. Server & Hosting Environment
* **PHP**: Version **8.2** or newer (CLI for local development; Apache/LiteSpeed with `mod_rewrite` for production shared hosting such as **Hostinger**).
* **Required PHP Extensions**:
  * `pdo_mysql` (Database connectivity)
  * `curl` (Google Gemini & Backblaze B2 HTTPS API calls)
  * `mbstring` (UTF-8 string normalization)
  * `fileinfo` (MIME-type validation for image and avatar uploads)
  * `json` & `session` (Enabled by default in PHP 8.2+)
* **Database**: **MySQL 8.0+** or **MariaDB 10.5+** with `InnoDB` and `utf8mb4` support.
* **Web Server**:
  * **Production**: Apache or LiteSpeed web server with `.htaccess` override support (`AllowOverride All`).
  * **Local Development**: Built-in PHP CLI server (`php -S localhost:8000 router.php`) or any WAMP/LAMP/XAMPP stack.
  * **Remote DB Note**: If running locally while connecting to a remote MySQL database (e.g., Hostinger Remote MySQL), ensure your local public IP address is whitelisted in your hosting provider's **Remote MySQL** panel.

### 2. External API Keys & Cloud Services
While core cataloging works out of the box, the following credentials are required in [`config/config.php`](file:///c:/Projects/antigravity/VideogameCataloguer/config/config.example.php) to unlock cloud downloads and AI features:

1. **Google Gemini API Key** *(Required for AI Auto-Fill)*
   * Obtain an API key from **Google AI Studio** (https://aistudio.google.com/).
   * Used by [`GeminiService`](file:///c:/Projects/antigravity/VideogameCataloguer/src/Services/GeminiService.php) to auto-generate game tags/descriptions, console specs, and publisher histories.
2. **Backblaze B2 Cloud Storage Credentials** *(Required for private B2 hosted file downloads)*
   * Create a private bucket in **Backblaze B2** (https://www.backblaze.com/b2/cloud-storage.html).
   * Generate an **Application Key ID** (`key_id`) and **Application Key** (`application_key`) with read/download permissions for your bucket.
   * Record your **Bucket Name** (`bucket_name`) and optionally your **Bucket ID** (`bucket_id`), **S3 Endpoint** (`s3_endpoint`), and **Region** (`region`).

---

## 🚀 Deployment Guide

### Step 1: Clone or Upload the Codebase

#### For Local Development:
```bash
git clone https://github.com/your-org/VideogameCataloguer.git
cd VideogameCataloguer
```

#### For Shared Hosting (e.g., Hostinger):
1. Upload the project contents to your target domain or subdomain document root (e.g., `public_html/` or `public_html/vdgn-test/`) via Git deployment, SFTP, or File Manager.
2. Verify that the root [`.htaccess`](file:///c:/Projects/antigravity/VideogameCataloguer/.htaccess) file is present. It automatically blocks direct HTTP access to `config/`, `src/`, `database/`, `storage/`, and `.sql`/`.md` files while routing application requests to [`index.php`](file:///c:/Projects/antigravity/VideogameCataloguer/index.php).
3. Ensure the web server has write permissions (`0775` or `0755`) on the image upload directories:
   * `images/games/`
   * `images/consoles/`
   * `images/publishers/`
   * `images/avatars/`
   * `storage/`

---

### Step 2: Configure Environment & API Keys

1. Copy the blueprint configuration file [`config/config.example.php`](file:///c:/Projects/antigravity/VideogameCataloguer/config/config.example.php) to `config/config.php`:
   ```bash
   cp config/config.example.php config/config.php
   ```
2. Open `config/config.php` and fill in your production/development settings:
   ```php
   'app' => [
       'name'        => 'Videogame Vault',
       'env'         => $isLocal ? 'development' : 'production',
       'debug'       => $isLocal,
       'base_url'    => $isLocal ? 'http://localhost:8000' : 'https://your-domain.com',
       'session_key' => 'vg_vault_session',
   ],

   'database' => [
       'host'    => $isLocal ? 'your_remote_mysql_host' : 'localhost',
       'port'    => 3306,
       'name'    => 'your_database_name',
       'user'    => 'your_database_user',
       'pass'    => 'your_database_password',
       'charset' => 'utf8mb4',
   ],

   'blackblaze' => [
       'key_id'          => 'your_backblaze_key_id',
       'application_key' => 'your_backblaze_application_key',
       'bucket_name'     => 'your_bucket_name',
       'bucket_id'       => '', // Optional: auto-discovered if left blank
       'server_url'      => 'https://api.backblazeb2.com',
       's3_endpoint'     => '', // Optional: e.g. https://s3.us-west-004.backblazeb2.com
       'region'          => '', // Optional: e.g. us-west-004
       'download_expiry' => 3600,
   ],

   'gemini' => [
       'api_key' => 'your_gemini_api_key',
       'models'  => [
           'gemini-3.8-flash',
           'gemini-3.7-flash',
           'gemini-3.5-flash',
       ],
       'timeout' => 20,
   ],
   ```
   > **Security Note:** `config/config.php` is git-ignored via [`.gitignore`](file:///c:/Projects/antigravity/VideogameCataloguer/.gitignore) and blocked from web access via [`.htaccess`](file:///c:/Projects/antigravity/VideogameCataloguer/.htaccess). Never commit real credentials to version control.

---

### Step 3: Initialize & Migrate the Database

Execute the SQL scripts inside the `database/` directory **in numerical order** (`01` through `14`) against your MySQL/MariaDB database (note: `00_old_db_normalization.sql` is only used when migrating from the legacy Access/PHP schema and should be skipped on a fresh install).

Using the MySQL CLI (or importing sequentially via **phpMyAdmin**):

```bash
mysql -u <db_user> -p <db_name> < database/01_schema_init.sql
mysql -u <db_user> -p <db_name> < database/02_users_and_roles.sql
mysql -u <db_user> -p <db_name> < database/03_console_types.sql
mysql -u <db_user> -p <db_name> < database/04_console_master_reference.sql
mysql -u <db_user> -p <db_name> < database/05_remove_played_and_won.sql
mysql -u <db_user> -p <db_name> < database/06_timestamps_and_downloads.sql
mysql -u <db_user> -p <db_name> < database/07_system_info.sql
mysql -u <db_user> -p <db_name> < database/08_player_games.sql
mysql -u <db_user> -p <db_name> < database/09_publisher_details.sql
mysql -u <db_user> -p <db_name> < database/10_portal_permissions.sql
mysql -u <db_user> -p <db_name> < database/11_broken_links.sql
mysql -u <db_user> -p <db_name> < database/12_button_mappings.sql
mysql -u <db_user> -p <db_name> < database/13_publisher_timestamps.sql
mysql -u <db_user> -p <db_name> < database/14_user_username.sql
```

---

### Step 4: Changing the Super Admin Name, Username, Email & Password

By default, [`database/02_users_and_roles.sql`](file:///c:/Projects/antigravity/VideogameCataloguer/database/02_users_and_roles.sql) and [`database/14_user_username.sql`](file:///c:/Projects/antigravity/VideogameCataloguer/database/14_user_username.sql) seed the primary **Super Admin** account (`id = 1`) with the following credentials:

* **Default Username**: `admin`
* **Default Email**: `admin@videogamevault.local`
* **Default Name**: `Super Admin`
* **Default Password**: `Password#2026!`

> **⚠️ Critical Security Step:** You must change the Super Admin name, username/email, and password immediately upon deployment. You can do this via **any of the three methods** below:

#### Method A: Directly in the Application UI *(Recommended after first login)*
1. Navigate to your deployed URL (e.g., `http://localhost:8000/login` or `https://your-domain.com/login`) and sign in with `admin` (or `admin@videogamevault.local`) and `Password#2026!`.
2. **Via User Management (`/users`)**:
   * Open the sidebar menu and go to **Admin -> Users** (`/users`).
   * Click **Edit** on the **Super Admin** row (`ID #1`).
   * Change the **Username**, **First Name**, **Last Name**, and **Email**, and enter a new **Password** (minimum 8 characters, or click **Generate** for a 16-character cryptographic password).
   * Click **Save User**.
3. **Or via Self-Service Profile Modal**:
   * Click your user badge/avatar in the top header to open the **My Profile** modal.
   * Update your **First Name**, **Last Name**, **Username**, **Email**, **New Password**, and **Confirm Password**, then click **Save Changes**.

#### Method B: Before Running the SQL Migrations *(Pre-Deployment)*
If you want your custom Super Admin credentials baked in before running the SQL scripts:
1. Generate a Bcrypt hash (cost `12`) for your chosen password using the PHP CLI:
   ```bash
   php -r "echo password_hash('YourNewStrongPassword!', PASSWORD_BCRYPT, ['cost' => 12]) . PHP_EOL;"
   ```
2. Open [`database/02_users_and_roles.sql`](file:///c:/Projects/antigravity/VideogameCataloguer/database/02_users_and_roles.sql) (around line 112) and replace the seeded values (`first_name`, `last_name`, `email`, and `password_hash`) with your own:
   ```sql
   INSERT INTO `users` (`id`, `role_id`, `first_name`, `last_name`, `email`, `password_hash`, `is_active`) VALUES
   (1, 1, 'Carlos', 'Admin', 'your.email@domain.com', '$2y$12$YOUR_GENERATED_BCRYPT_HASH_HERE', 1);
   ```
   *(When [`database/14_user_username.sql`](file:///c:/Projects/antigravity/VideogameCataloguer/database/14_user_username.sql) runs afterward, it will automatically populate `username` from the prefix before `@` in your email, or you can customize it directly).*

#### Method C: Via Direct SQL Query on an Existing Database
If the database is already initialized and you want to update the Super Admin (`id = 1`) via MySQL CLI or phpMyAdmin:
1. Generate your Bcrypt hash:
   ```bash
   php -r "echo password_hash('YourNewStrongPassword!', PASSWORD_BCRYPT, ['cost' => 12]) . PHP_EOL;"
   ```
2. Run the following SQL `UPDATE` statement:
   ```sql
   UPDATE `users`
   SET
       `username`      = 'your_new_username',
       `first_name`    = 'YourFirstName',
       `last_name`     = 'YourLastName',
       `email`         = 'your.email@domain.com',
       `password_hash` = '$2y$12$YOUR_GENERATED_BCRYPT_HASH_HERE',
       `updated_at`    = NOW()
   WHERE `id` = 1;
   ```

---

### Step 5: Running & Verifying the Deployment

* **Local Development Server**:
  Start the built-in PHP server from the project root using [`router.php`](file:///c:/Projects/antigravity/VideogameCataloguer/router.php):
  ```bash
  php -S localhost:8000 router.php
  ```
  Then open `http://localhost:8000` in your browser.

* **Health Check Endpoint**:
  Verify that the front controller and database connection are online by visiting:
  ```text
  GET /health
  ```
  A healthy deployment returns:
  ```json
  {
    "status": "ok",
    "app": "Videogame Vault",
    "env": "production",
    "database": "online",
    "time": "2026-11-02 11:00:00"
  }
  ```

---

## 📂 Project Structure Overview

```text
VideogameCataloguer/
├── assets/
│   ├── css/style.css                  # Master dark-slate stylesheet & design tokens
│   └── js/
│       ├── components/data-grid.js    # Reusable <vault-grid> vanilla JS Web Component
│       └── xlsx.full.min.js           # Client-side Excel (.xlsx) export engine
├── config/
│   ├── config.example.php             # Configuration template (Database, B2, Gemini)
│   └── config.php                     # Private environment credentials (git-ignored)
├── database/
│   ├── 01_schema_init.sql             # Core tables (games, consoles, taxonomies)
│   └── 02..14_*.sql                   # Sequential schema migrations (RBAC, Portals, B2, etc.)
├── images/                            # Uploaded visual assets (avatars, consoles, games, publishers)
├── src/
│   ├── Auth/Auth.php                  # Session security, Bcrypt auth & Dual-Device RBAC engine
│   ├── Controllers/                   # HTTP & JSON API request controllers
│   ├── Repositories/                  # PDO data access layer & file lifecycle handlers
│   ├── Services/                      # Core services (Database, Router, View, Backblaze, Gemini)
│   └── Views/                         # Modular PHP view templates & portals
├── .htaccess                          # Apache security rules & front-controller routing
├── index.php                          # Application front controller & route dispatcher
└── router.php                         # Local PHP CLI development server router
```

---

## 📄 License

This project is licensed under the **MIT License**.
