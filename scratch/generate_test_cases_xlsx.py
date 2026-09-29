#!/usr/bin/env python3
"""
scratch/generate_test_cases_xlsx.py
Generates a comprehensive, professionally styled Excel spreadsheet (test/test_cases.xlsx)
covering all functional modules, happy paths, edge cases, and failure scenarios
for the Videogame Cataloguer project.
"""

import os
import sys
import zipfile
import xml.sax.saxutils as saxutils

# -----------------------------------------------------------------------------
# 1. Test Cases Data Definition
# -----------------------------------------------------------------------------

TEST_CASES = [
    # =========================================================================
    # MODULE: AUTHENTICATION & SESSION MANAGEMENT
    # =========================================================================
    {
        "id": "TC0001",
        "module": "Auth / Login",
        "scenario": "Successful authentication with valid credentials (Happy Path)",
        "inputs": "email: 'admin@videogamevault.local', password: 'Password#2026!'",
        "preconditions": "User account exists and has is_active = 1 in database.",
        "steps": "1. Navigate to /login.\n2. Enter registered email address.\n3. Enter valid password.\n4. Click 'Sign In' button.",
        "expected": "User is authenticated; session cookie 'vault_session' is created; user is redirected to Dashboard ('/')."
    },
    {
        "id": "TC0002",
        "module": "Auth / Session",
        "scenario": "Secure cookie attributes configuration (Happy Path)",
        "inputs": "HTTP GET /login -> valid login submission",
        "preconditions": "User submits valid login credentials.",
        "steps": "1. Inspect Set-Cookie header on login response.\n2. Verify cookie lifetime, path, domain, HttpOnly, and SameSite attributes.",
        "expected": "Cookie 'vault_session' has lifetime=7 days (604800s), path='/', HttpOnly=true, SameSite='Lax', and Secure=true if HTTPS."
    },
    {
        "id": "TC0003",
        "module": "Auth / Session",
        "scenario": "Session fixation prevention via ID regeneration (Happy Path)",
        "inputs": "Existing anonymous session cookie before login",
        "preconditions": "Anonymous session exists on /login page.",
        "steps": "1. Note initial session ID.\n2. Submit valid login credentials.\n3. Note new session ID after authentication.",
        "expected": "session_regenerate_id(true) is invoked; session ID changes to a new random token, preventing session fixation attacks."
    },
    {
        "id": "TC0004",
        "module": "Auth / Login",
        "scenario": "Last login timestamp audit update (Happy Path)",
        "inputs": "Valid user credentials",
        "preconditions": "User has previous or null last_login_at timestamp.",
        "steps": "1. Check users.last_login_at in DB.\n2. Log in successfully.\n3. Query users.last_login_at in DB.",
        "expected": "users.last_login_at is updated with current database timestamp NOW()."
    },
    {
        "id": "TC0005",
        "module": "Auth / Logout",
        "scenario": "User logout and session destruction (Happy Path)",
        "inputs": "HTTP GET or POST to /logout",
        "preconditions": "User is actively logged in with valid session.",
        "steps": "1. Click 'Logout' button or navigate to /logout.\n2. Inspect session state and cookies.\n3. Attempt to access /games.",
        "expected": "Session array is emptied, session destroyed, cookie expired in browser, user redirected to /login."
    },
    {
        "id": "TC0006",
        "module": "Auth / Login",
        "scenario": "Email whitespace trimming and case insensitivity (Edge Case)",
        "inputs": "email: '   AdMiN@VideoGameVault.LOCAL   ', password: 'Password#2026!'",
        "preconditions": "User email stored in database in lowercase format.",
        "steps": "1. Enter email with leading/trailing spaces and mixed casing.\n2. Enter correct password.\n3. Click 'Sign In'.",
        "expected": "Email is trimmed and converted to lowercase; authentication succeeds seamlessly."
    },
    {
        "id": "TC0007",
        "module": "Auth / Session",
        "scenario": "Session expiry after inactivity window (Edge Case)",
        "inputs": "Session cookie timestamp > 7 days old",
        "preconditions": "User was authenticated 7+ days ago with no activity.",
        "steps": "1. Send request with expired session cookie.\n2. Access /consoles.",
        "expected": "Session is recognized as expired; user is redirected to /login."
    },
    {
        "id": "TC0008",
        "module": "Auth / Login",
        "scenario": "Login attempt with non-existent email (Failure Scenario)",
        "inputs": "email: 'ghost_user@videogamevault.local', password: 'Password#2026!'",
        "preconditions": "Email does not exist in users table.",
        "steps": "1. Enter unlinked email on /login.\n2. Enter arbitrary password.\n3. Submit form.",
        "expected": "Login fails; generic error 'Invalid email or password.' is displayed; no session is created."
    },
    {
        "id": "TC0009",
        "module": "Auth / Login",
        "scenario": "Login attempt with invalid password (Failure Scenario)",
        "inputs": "email: 'admin@videogamevault.local', password: 'WrongPassword#123'",
        "preconditions": "User exists with different bcrypt password hash.",
        "steps": "1. Enter registered email.\n2. Enter incorrect password.\n3. Submit login form.",
        "expected": "password_verify() returns false; authentication fails; error 'Invalid email or password.' shown."
    },
    {
        "id": "TC0010",
        "module": "Auth / Login",
        "scenario": "Login attempt on deactivated user account (Failure Scenario)",
        "inputs": "email: 'deactivated@videogamevault.local', password: 'Password#2026!'",
        "preconditions": "User exists in database with is_active = 0.",
        "steps": "1. Enter valid email and password for deactivated user.\n2. Submit login form.",
        "expected": "Database query filters out is_active = 0; login is rejected; user cannot gain access."
    },
    {
        "id": "TC0011",
        "module": "Auth / Guard",
        "scenario": "Direct script access prevention via APP_INIT constant (Failure Scenario)",
        "inputs": "Direct HTTP request to /src/Views/games.php or /src/Auth/Auth.php",
        "preconditions": "Server allows direct file path traversal in URL.",
        "steps": "1. Navigate directly to /src/Views/games.php in browser.",
        "expected": "Script detects !defined('APP_INIT'); returns HTTP 403 Forbidden with 'Direct access not permitted.'."
    },
    {
        "id": "TC0012",
        "module": "Auth / Guard",
        "scenario": "Unauthenticated access redirection to login (Failure Scenario)",
        "inputs": "HTTP GET /games without vault_session cookie",
        "preconditions": "User is not logged in.",
        "steps": "1. Clear browser cookies.\n2. Navigate directly to /games.",
        "expected": "Auth::requireAccess() detects null user; redirects user via HTTP 302 to /login."
    },

    # =========================================================================
    # MODULE: DUAL-DEVICE ROLE-BASED ACCESS CONTROL (RBAC)
    # =========================================================================
    {
        "id": "TC0013",
        "module": "RBAC / SuperAdmin",
        "scenario": "Super Admin unrestricted bypass across all screens and devices (Happy Path)",
        "inputs": "Authenticated user with role is_super = 1",
        "preconditions": "User assigned to Super Admin role.",
        "steps": "1. Access all 12 registered system screens from PC.\n2. Access screens with mobile User-Agent.\n3. Perform create, update, and delete actions.",
        "expected": "Auth::isSuper() returns true; all checks bypass matrix; full read/write granted on all screens."
    },
    {
        "id": "TC0014",
        "module": "RBAC / Device",
        "scenario": "Desktop browser detection and PC permissions check (Happy Path)",
        "inputs": "User-Agent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0'",
        "preconditions": "Role has access_pc = 'write' on 'games'.",
        "steps": "1. Log in with standard role on PC browser.\n2. Navigate to /games.\n3. Verify form editing controls and grid display.",
        "expected": "Auth::deviceType() evaluates to 'pc'; user can read catalog and submit game modifications."
    },
    {
        "id": "TC0015",
        "module": "RBAC / Device",
        "scenario": "Mobile device detection via User-Agent inspection (Happy Path)",
        "inputs": "User-Agent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)'",
        "preconditions": "Role has access_other = 'read' on 'games'.",
        "steps": "1. Send request with mobile User-Agent to /games.\n2. Inspect UI response.",
        "expected": "Auth::deviceType() evaluates to 'other'; Auth::can('games', 'read') returns true; read-only view rendered."
    },
    {
        "id": "TC0016",
        "module": "RBAC / Override",
        "scenario": "Manual device testing override via query parameter (Happy Path)",
        "inputs": "HTTP GET /games?device=other, followed by /games?device=pc",
        "preconditions": "Authenticated user session active.",
        "steps": "1. Request /games?device=other.\n2. Verify $_SESSION['vault_device_override'] = 'other'.\n3. Request /games?device=pc.\n4. Verify override updates to 'pc'.",
        "expected": "Device override persists in session; allows simulated testing of dual-device permissions."
    },
    {
        "id": "TC0017",
        "module": "RBAC / DualMatrix",
        "scenario": "Asymmetric permission: PC write, Mobile read-only (Edge Case)",
        "inputs": "Role: access_pc='write', access_other='read' on 'consoles'",
        "preconditions": "Non-super user assigned to test role.",
        "steps": "1. From PC, edit console #1 -> should succeed.\n2. Switch to mobile User-Agent, attempt POST /consoles/1/update.",
        "expected": "PC update succeeds (HTTP 200). Mobile POST is blocked by Auth::requireAccess('consoles', 'write') with HTTP 403."
    },
    {
        "id": "TC0018",
        "module": "RBAC / DualMatrix",
        "scenario": "Asymmetric permission: PC read, Mobile none (Edge Case)",
        "inputs": "Role: access_pc='read', access_other='none' on 'publishers'",
        "preconditions": "Non-super user assigned to test role.",
        "steps": "1. Access /publishers on desktop -> page loads.\n2. Access /publishers with mobile User-Agent.",
        "expected": "Desktop displays publishers catalog. Mobile receives HTTP 403 Forbidden ('Access Denied: You do not have read permissions...')."
    },
    {
        "id": "TC0019",
        "module": "RBAC / Device",
        "scenario": "Missing or blank User-Agent header fallback (Edge Case)",
        "inputs": "HTTP request with empty HTTP_USER_AGENT",
        "preconditions": "Authenticated standard user.",
        "steps": "1. Send request to /games with no User-Agent header.\n2. Check Auth::deviceType().",
        "expected": "Auth::deviceType() safely falls back to 'pc' without PHP notices or exceptions."
    },
    {
        "id": "TC0020",
        "module": "RBAC / Enforce",
        "scenario": "Unauthorized navigation access blocked (Failure Scenario)",
        "inputs": "Role: access_pc='none' on 'users'",
        "preconditions": "Standard user without user management permission.",
        "steps": "1. User navigates to /users in address bar.",
        "expected": "Auth::requireAccess('users', 'read') halts execution; returns HTTP 403 Forbidden."
    },
    {
        "id": "TC0021",
        "module": "RBAC / Enforce",
        "scenario": "Unauthorized modification attempt blocked (Failure Scenario)",
        "inputs": "Role: access_pc='read' on 'categories'; POST to /categories/create",
        "preconditions": "User has read-only permission on Categories screen.",
        "steps": "1. User craft-posts JSON/form payload to /categories/create.",
        "expected": "CategoryController::create() invokes Auth::requireAccess('categories', 'write'); aborts with HTTP 403."
    },

    # =========================================================================
    # MODULE: GAME CATALOGUER - CORE CRUD OPERATIONS
    # =========================================================================
    {
        "id": "TC0022",
        "module": "Games / Create",
        "scenario": "Create game with all fields populated via AJAX (Happy Path)",
        "inputs": "title: 'The Legend of Zelda', console_id: 1, category_id: 1, subcategory_id: 1, language_id: 1, publisher_id: 1, year: '1986', in_collection: 1, is_played: 1, is_won: 1, tags: 'adventure, fantasy', comments: 'CIB pristine'",
        "preconditions": "Parent console, category, subcategory, language, and publisher exist in DB.",
        "steps": "1. Fill all input fields in left-hand form.\n2. Click 'Save Game' (or press Ctrl+S).\n3. Inspect JSON response.",
        "expected": "Database inserts game record; returns {success:true, data:{id:X, message:'Game created successfully'}}; grid refreshes immediately."
    },
    {
        "id": "TC0023",
        "module": "Games / Create",
        "scenario": "Create game with minimal required fields only (Happy Path)",
        "inputs": "title: 'Tetris Classic', console_id: 2, other fields blank/null",
        "preconditions": "Console with id=2 exists.",
        "steps": "1. Fill Title and Platform dropdown only.\n2. Submit form.",
        "expected": "Game record created with new auto-increment ID; optional fields set to NULL/0; returns HTTP 200 JSON."
    },
    {
        "id": "TC0024",
        "module": "Games / Update",
        "scenario": "Update game metadata via AJAX (Happy Path)",
        "inputs": "id: 5, title: 'Super Mario Bros. - Updated', year: '1985', tags: 'platformer, classic'",
        "preconditions": "Game #5 exists in database.",
        "steps": "1. Select game #5 in grid (form populates).\n2. Edit title and tags.\n3. Click 'Save Game'.",
        "expected": "Game #5 updated in DB; returns HTTP 200 JSON; grid refreshes without page reload; green toast displayed; row remains selected."
    },
    {
        "id": "TC0025",
        "module": "Games / Flags",
        "scenario": "Toggle collection, played, and won status flags (Happy Path)",
        "inputs": "id: 5, in_collection: 1, is_played: 1, is_won: 0 -> updated to in_collection: 1, is_played: 1, is_won: 1",
        "preconditions": "Game #5 exists in backlog.",
        "steps": "1. Select game #5.\n2. Check 'Won' checkbox.\n3. Click 'Save Game'.",
        "expected": "Database stores is_won=1; collection badge and dashboard won counters update accordingly."
    },
    {
        "id": "TC0026",
        "module": "Games / Delete",
        "scenario": "Delete game without media assets (Happy Path)",
        "inputs": "id: 12 (game with screenshot_path=NULL, boxart_path=NULL)",
        "preconditions": "Game #12 exists in database.",
        "steps": "1. Select game #12.\n2. Click 'Delete Game'.\n3. Confirm browser confirmation prompt.",
        "expected": "Record removed from database; form resets to 'NEW GAME ENTRY'; row removed from grid; green success toast displayed."
    },
    {
        "id": "TC0027",
        "module": "Games / API",
        "scenario": "Anti-caching HTTP headers on games API endpoint (Happy Path)",
        "inputs": "HTTP GET /api/games?_t=timestamp",
        "preconditions": "Games catalog populated.",
        "steps": "1. Dispatch GET request to /api/games.\n2. Inspect response headers.",
        "expected": "Headers include Cache-Control: 'no-store, no-cache, must-revalidate, max-age=0', Pragma: 'no-cache', Expires: '0'."
    },
    {
        "id": "TC0028",
        "module": "Games / Create",
        "scenario": "Game title with unicode and special characters (Edge Case)",
        "inputs": "title: 'Pokémon: Édition Rouge & Bleu (FR) [100% CIB] <v1.1>'",
        "preconditions": "Console selected.",
        "steps": "1. Enter title containing accented letters, ampersands, colons, brackets, and quotes.\n2. Submit form.\n3. Verify display in grid and form.",
        "expected": "Stored accurately in UTF-8 mb4; rendered safely with HTML entity escaping without XSS vulnerabilities."
    },
    {
        "id": "TC0029",
        "module": "Games / Create",
        "scenario": "Game title boundary limit: exactly 255 characters (Edge Case)",
        "inputs": "title: 255 characters of alphabetic string, console_id: 1",
        "preconditions": "Console selected.",
        "steps": "1. Paste exact 255-character string into gameTitle.\n2. Submit form.",
        "expected": "Validation passes; game created successfully with full 255-character title."
    },
    {
        "id": "TC0030",
        "module": "Games / Cascade",
        "scenario": "Dynamic Subcategory filtering by chosen Category (Edge Case)",
        "inputs": "Category: 'RPG' (id: 1) -> displays 'Action RPG', 'JRPG'. Category changed to blank.",
        "preconditions": "Categories and subcategories linked in database.",
        "steps": "1. Select Category 'RPG' -> verify subcategory dropdown populates.\n2. Change Category dropdown to '-- Choose Category --'.",
        "expected": "Subcategory dropdown is emptied immediately (contains only placeholder '-- Choose Subcategory --')."
    },
    {
        "id": "TC0031",
        "module": "Games / Grid",
        "scenario": "Active filter collision avoidance on save (Edge Case)",
        "inputs": "Grid filtered by Console: 'SNES'. User updates game #3 Platform to 'Genesis'.",
        "preconditions": "Game #3 currently displayed.",
        "steps": "1. Filter grid by Console='SNES'.\n2. Change game platform to 'Genesis'.\n3. Click 'Save Game'.",
        "expected": "reloadGridData() detects active filter would hide game; resets console filter to 'all'; navigates to item page; highlights row."
    },
    {
        "id": "TC0032",
        "module": "Games / Shortcuts",
        "scenario": "Keyboard shortcuts: Ctrl+S to save, Esc to reset (Edge Case)",
        "inputs": "Key combos: Ctrl+S and Escape in games workbench",
        "preconditions": "User focused in games workbench form.",
        "steps": "1. Edit game title; press Ctrl+S (Cmd+S on Mac).\n2. Confirm form submits.\n3. Press Escape key.",
        "expected": "Ctrl+S prevents browser default save and submits form. Escape resets form to 'NEW GAME ENTRY' state."
    },
    {
        "id": "TC0033",
        "module": "Games / AI",
        "scenario": "AI auto-fill placeholder button interaction (Edge Case)",
        "inputs": "Click on 'AI Auto-Fill' button (#aiAutoFillBtn)",
        "preconditions": "Games workbench open.",
        "steps": "1. Click AI Auto-Fill button located in tags/comments section.",
        "expected": "Button triggers non-destructive informational toast ('AI Assistant: API key integration will be enabled in an upcoming release.')."
    },
    {
        "id": "TC0034",
        "module": "Games / Create",
        "scenario": "Blank/empty title validation error (Failure Scenario)",
        "inputs": "title: '   ', console_id: 1",
        "preconditions": "Console selected.",
        "steps": "1. Leave Title blank or spaces only.\n2. Click 'Save Game'.",
        "expected": "Frontend toast displays 'Game title is required.'; if bypassed, backend returns HTTP 422 'Game title cannot be empty.'."
    },
    {
        "id": "TC0035",
        "module": "Games / Create",
        "scenario": "Title exceeding 255 characters (Failure Scenario)",
        "inputs": "title: 256 characters of text, console_id: 1",
        "preconditions": "Console selected.",
        "steps": "1. Submit game with 256-character title.",
        "expected": "Backend throws InvalidArgumentException; returns HTTP 422 'Game title cannot exceed 255 characters.'."
    },
    {
        "id": "TC0036",
        "module": "Games / Create",
        "scenario": "Missing platform/console selection (Failure Scenario)",
        "inputs": "title: 'Sonic the Hedgehog', console_id: ''",
        "preconditions": "Title filled, console unselected.",
        "steps": "1. Fill title.\n2. Leave Console select on default blank.\n3. Submit form.",
        "expected": "Frontend toast displays 'Please select a platform/console.'; backend returns HTTP 422 'Please select a platform/console.'."
    },
    {
        "id": "TC0037",
        "module": "Games / Update",
        "scenario": "Update non-existent game ID (Failure Scenario)",
        "inputs": "POST to /games/999999/update with valid body",
        "preconditions": "Game #999999 does not exist.",
        "steps": "1. Send update request for non-existent ID 999999.",
        "expected": "GameRepository throws RuntimeException; response returns HTTP 422 JSON with 'Game #999999 not found.'."
    },
    {
        "id": "TC0038",
        "module": "Games / Delete",
        "scenario": "Delete non-existent game ID (Failure Scenario)",
        "inputs": "POST to /games/999999/delete",
        "preconditions": "Game #999999 does not exist.",
        "steps": "1. Send delete request for non-existent ID 999999.",
        "expected": "GameRepository throws RuntimeException; response returns HTTP 422 JSON with 'Game #999999 does not exist.'."
    },

    # =========================================================================
    # MODULE: GAME CATALOGUER - VISUAL ASSET LIFECYCLE
    # =========================================================================
    {
        "id": "TC0039",
        "module": "Games / Media",
        "scenario": "Screenshot asset creation with convention <ID>_Img.<ext> (Happy Path)",
        "inputs": "New game insert with screenshot file 'gameplay_action.png'",
        "preconditions": "images/games/ directory exists with write permissions.",
        "steps": "1. Attach PNG image to screenshot dropzone.\n2. Save new game.\n3. Inspect images/games/ directory and games.screenshot_path in DB.",
        "expected": "File saved as images/games/<newId>_Img.png; database column screenshot_path stores 'images/games/<newId>_Img.png'."
    },
    {
        "id": "TC0040",
        "module": "Games / Media",
        "scenario": "Boxart asset creation with convention <ID>_Box.<ext> (Happy Path)",
        "inputs": "New game insert with boxart file 'front_cover.jpg'",
        "preconditions": "images/games/ directory exists with write permissions.",
        "steps": "1. Attach JPG image to boxart dropzone.\n2. Save new game.\n3. Check images/games/ folder and database.",
        "expected": "File saved as images/games/<newId>_Box.jpg; database column boxart_path stores 'images/games/<newId>_Box.jpg'."
    },
    {
        "id": "TC0041",
        "module": "Games / Media",
        "scenario": "Update visual assets deletes previous files on disk (Happy Path)",
        "inputs": "Game #10 has existing screenshot '10_Img.png' and boxart '10_Box.jpg'; upload new WEBP screenshot and new PNG boxart",
        "preconditions": "Game #10 has existing image files stored on disk.",
        "steps": "1. Select game #10.\n2. Upload replacement screenshot (screenshot_v2.webp).\n3. Upload replacement boxart (cover_v2.png).\n4. Click 'Save Game'.",
        "expected": "Old files (10_Img.png, 10_Box.jpg) are deleted from disk; new files (10_Img.webp, 10_Box.png) are stored; DB paths updated."
    },
    {
        "id": "TC0042",
        "module": "Games / Media",
        "scenario": "Read operation resolves asset URLs regardless of naming convention (Happy Path)",
        "inputs": "Game row with custom screenshot_path 'images/games/legacy_snap_01.png'",
        "preconditions": "Record in database has non-standard file path.",
        "steps": "1. Select game record in grid.\n2. Inspect screenshot preview in workbench dropzone.",
        "expected": "Read operation prepends root slash; loads '/images/games/legacy_snap_01.png' without enforcing convention."
    },
    {
        "id": "TC0043",
        "module": "Games / Media",
        "scenario": "Delete operation deletes all referenced media files from disk (Happy Path)",
        "inputs": "Game #15 has screenshot '15_Img.jpg' and boxart '15_Box.png'; delete game #15",
        "preconditions": "Physical files exist at images/games/15_Img.jpg and images/games/15_Box.png.",
        "steps": "1. Trigger delete on game #15.\n2. Check disk file existence after deletion.",
        "expected": "deleteAssetFile() unlinks both files from images/games/; record is deleted from DB."
    },
    {
        "id": "TC0044",
        "module": "Games / Media",
        "scenario": "Explicit visual asset removal without deleting game (Happy Path)",
        "inputs": "id: 8, delete_boxart: 1, delete_screenshot: 0",
        "preconditions": "Game #8 has boxart file on disk and path in DB.",
        "steps": "1. Select game #8.\n2. Click trash icon on Box Art dropzone.\n3. Click 'Save Game'.",
        "expected": "Boxart file deleted from disk; boxart_path set to NULL in DB; screenshot remains intact."
    },
    {
        "id": "TC0045",
        "module": "Games / Media",
        "scenario": "Support all permissible image mime types (Edge Case)",
        "inputs": "Uploads with formats: JPG, JPEG, PNG, WEBP, GIF, SVG",
        "preconditions": "File info (finfo) extension enabled in PHP.",
        "steps": "1. Test upload for each supported image extension.\n2. Verify MIME type detection and extension mapping.",
        "expected": "finfo detects image/jpeg->jpg, image/png->png, image/webp->webp, image/gif->gif, image/svg+xml->svg."
    },
    {
        "id": "TC0046",
        "module": "Games / Media",
        "scenario": "Upload invalid file extension or executable (Failure Scenario)",
        "inputs": "Upload file: 'script.php', 'malware.exe', or 'data.pdf'",
        "preconditions": "Attempt file upload in dropzone.",
        "steps": "1. Upload PDF or executable file to screenshot dropzone.\n2. Click 'Save Game'.",
        "expected": "storeAssetFile() throws InvalidArgumentException; returns HTTP 422 'Invalid file format... Allowed formats: JPG, PNG, WEBP, GIF, SVG.'."
    },
    {
        "id": "TC0047",
        "module": "Games / Media",
        "scenario": "Corrupted or partial upload error code handling (Failure Scenario)",
        "inputs": "File upload simulating UPLOAD_ERR_INI_SIZE or UPLOAD_ERR_PARTIAL",
        "preconditions": "PHP upload error code != UPLOAD_ERR_OK.",
        "steps": "1. Send upload payload with simulate error code 1 or 3.",
        "expected": "storeAssetFile() detects error code; throws RuntimeException 'Upload failed with error code X'; no files saved."
    },

    # =========================================================================
    # MODULE: GAME CATALOGUER - 3-TIER COLLECTION BADGES
    # =========================================================================
    {
        "id": "TC0048",
        "module": "Games / Badge",
        "scenario": "Badge text PENDING for game not in collection (Happy Path)",
        "inputs": "in_collection = 0",
        "preconditions": "Game created with in_collection checkbox unchecked.",
        "steps": "1. Inspect game in grid.\n2. Inspect collection badge text.",
        "expected": "Badge text displays 'PENDING'."
    },
    {
        "id": "TC0049",
        "module": "Games / Badge",
        "scenario": "Badge text BACKLOG for game in collection (Happy Path)",
        "inputs": "in_collection = 1",
        "preconditions": "Game created with in_collection checkbox checked.",
        "steps": "1. Inspect game in grid.\n2. Inspect collection badge text.",
        "expected": "Badge text displays 'BACKLOG'."
    },
    {
        "id": "TC0050",
        "module": "Games / Badge",
        "scenario": "Badge color RED: Any primary field missing (Happy Path)",
        "inputs": "title: 'Metroid', console_id: 1, year: null, category_id: null, language_id: null, publisher_id: null",
        "preconditions": "At least one primary field (title, year, platform, category, subcategory, publisher, language) is empty/null.",
        "steps": "1. Create/view game missing primary fields.\n2. Inspect badge class.",
        "expected": "Badge has class 'badge-collection-red' (red background/border); tooltip indicates missing required info."
    },
    {
        "id": "TC0051",
        "module": "Games / Badge",
        "scenario": "Badge color AMBER: Primary fields complete, missing media or comments (Happy Path)",
        "inputs": "All primary fields filled; screenshot_path=NULL, boxart_path=NULL, tags=NULL, comments=NULL",
        "preconditions": "Base metadata complete; secondary assets missing.",
        "steps": "1. Create game with full base metadata, no images or tags.\n2. Inspect badge class in grid.",
        "expected": "Badge has class 'badge-collection-amber' (exact gold/amber #fbbf24 color matching design); tooltip indicates missing media or tags/comments."
    },
    {
        "id": "TC0052",
        "module": "Games / Badge",
        "scenario": "Badge color GREEN: All base info, both images, tags and comments present (Happy Path)",
        "inputs": "All primary fields filled + screenshot + boxart + tags + comments present",
        "preconditions": "Complete documentation on all fields.",
        "steps": "1. Create fully documented game.\n2. Inspect badge class in grid.",
        "expected": "Badge has class 'badge-collection-green' (emerald green background/border); tooltip indicates all info complete."
    },
    {
        "id": "TC0053",
        "module": "Games / Badge",
        "scenario": "Badge color AMBER: Missing only one image asset (Edge Case)",
        "inputs": "Base info complete + boxart present + tags present + comments present, but screenshot_path=NULL",
        "preconditions": "Secondary info missing exactly one visual asset.",
        "steps": "1. Inspect badge calculation for record.",
        "expected": "hasCompleteSecondary evaluates to false; badge color remains AMBER until both images and tags/comments exist."
    },
    {
        "id": "TC0054",
        "module": "Games / Badge",
        "scenario": "Live badge update on client without page reload (Edge Case)",
        "inputs": "Edit red badge game in form -> fill all missing primary fields -> save",
        "preconditions": "Game initially renders red badge in grid.",
        "steps": "1. Fill missing year, language, publisher, category, and subcategory.\n2. Click 'Save Game'.",
        "expected": "reloadGridData() receives fresh game object; computeCollectionBadge() re-evaluates; badge transitions from RED to AMBER/GREEN immediately."
    },

    # =========================================================================
    # MODULE: HARDWARE & CONSOLES MAINTENANCE
    # =========================================================================
    {
        "id": "TC0055",
        "module": "Consoles / Create",
        "scenario": "Create console with hardware specs and emulator links (Happy Path)",
        "inputs": "name: 'Super Nintendo Entertainment System', publisher_id: 1, year: '1990', generation: '4th Gen', console_type_id: 1 ('Home'), is_for_reference: 0, emulator: 'Snes9x', emulator_link: 'https://snes9x.com'",
        "preconditions": "Publisher with id=1 is a console maker; Console Type id=1 ('Home') exists.",
        "steps": "1. Navigate to /consoles.\n2. Select Manufacturer 'Nintendo' and Console Hardware Type 'Home'.\n3. Fill release year, generation, and emulator details.\n4. Click 'Save Console'.",
        "expected": "Console created; record inserted in DB with console_type_id = 1; grid updates with Home badge; green toast displayed."
    },
    {
        "id": "TC0056",
        "module": "Consoles / Media",
        "scenario": "Console photo uploaded with convention <id>_<name>_image.<ext> (Happy Path)",
        "inputs": "Console name: 'PlayStation 2', upload photo 'ps2_fat.png'",
        "preconditions": "images/consoles/ directory writable.",
        "steps": "1. Attach photo file.\n2. Save console.\n3. Inspect images/consoles/ folder and DB image_path.",
        "expected": "Filename formatted as <id>_PlayStation_2_image.png; stored in images/consoles/."
    },
    {
        "id": "TC0057",
        "module": "Consoles / Media",
        "scenario": "Console logo uploaded with convention <id>_<name>_logo.<ext> (Happy Path)",
        "inputs": "Console name: 'Mega Drive', upload logo 'sega_md.png'",
        "preconditions": "images/consoles/ directory writable.",
        "steps": "1. Attach logo file.\n2. Save console.\n3. Inspect images/consoles/ folder and DB logo_path.",
        "expected": "Filename formatted as <id>_Mega_Drive_logo.png; stored in images/consoles/."
    },
    {
        "id": "TC0058",
        "module": "Consoles / Makers",
        "scenario": "Manufacturer dropdown strictly filters to console makers (Happy Path)",
        "inputs": "Publishers in DB: 'Nintendo' (is_console_maker=1), 'Capcom' (is_console_maker=0)",
        "preconditions": "Publishers table populated with mixed console makers.",
        "steps": "1. Open Consoles workbench.\n2. Open 'Manufacturer / Maker' dropdown.",
        "expected": "'Nintendo' is listed; 'Capcom' is excluded from manufacturer dropdown."
    },
    {
        "id": "TC0059",
        "module": "Consoles / Update",
        "scenario": "Console update deletes previous image files on disk (Happy Path)",
        "inputs": "Console #3 has photo '3_Genesis_image.jpg'; upload replacement photo '3_Genesis_image.png'",
        "preconditions": "Old photo file exists on disk.",
        "steps": "1. Select console #3.\n2. Upload new photo.\n3. Save console.",
        "expected": "Previous file (3_Genesis_image.jpg) deleted from disk; new file (3_Genesis_image.png) saved; DB updated."
    },
    {
        "id": "TC0060",
        "module": "Consoles / Delete",
        "scenario": "Delete console without linked games (Happy Path)",
        "inputs": "id: 8 (console with 0 games linked in games table)",
        "preconditions": "Console #8 has 0 games in games table.",
        "steps": "1. Select console #8.\n2. Click 'Delete Console'.\n3. Confirm dialog.",
        "expected": "Console images unlinked; database record deleted; grid reloaded."
    },
    {
        "id": "TC0061",
        "module": "Consoles / Naming",
        "scenario": "Console name escaping for filesystem safety (Edge Case)",
        "inputs": "name: 'Game & Watch: Super Mario Bros. (35th Anniv.)'",
        "preconditions": "Save console with photo upload.",
        "steps": "1. Save console with complex punctuation in name.\n2. Check generated filename on disk.",
        "expected": "Special characters replaced by underscores (<id>_Game_Watch_Super_Mario_Bros_35th_Anniv_image.png); safe for Windows and Linux filesystems."
    },
    {
        "id": "TC0062",
        "module": "Consoles / Validate",
        "scenario": "Case-insensitive duplicate console name detection (Edge Case)",
        "inputs": "Existing: 'Game Boy Advance'. New: 'game boy advance'",
        "preconditions": "'Game Boy Advance' exists in consoles table.",
        "steps": "1. Attempt creating console named 'game boy advance'.",
        "expected": "existsByName() detects case-insensitive match; throws InvalidArgumentException 'A console with this name already exists.'."
    },
    {
        "id": "TC0063",
        "module": "Consoles / Create",
        "scenario": "Blank/empty console name validation (Failure Scenario)",
        "inputs": "name: '   '",
        "preconditions": "Consoles workbench open.",
        "steps": "1. Submit console with blank name.",
        "expected": "Backend returns HTTP 422 'Console name cannot be empty.'."
    },
    {
        "id": "TC0064",
        "module": "Consoles / Delete",
        "scenario": "Delete console blocked by linked games foreign key (Failure Scenario)",
        "inputs": "Console #1 has 25 games registered in catalog",
        "preconditions": "Foreign key constraint fk_games_console has ON DELETE RESTRICT.",
        "steps": "1. Attempt deleting console #1.",
        "expected": "Database foreign key or controller pre-check prevents deletion; error displayed; linked games protected."
    },

    # =========================================================================
    # MODULE: PUBLISHERS & HARDWARE MANUFACTURERS
    # =========================================================================
    {
        "id": "TC0065",
        "module": "Publishers / Create",
        "scenario": "Create software-only publisher (Happy Path)",
        "inputs": "name: 'Konami', is_console_maker: 0",
        "preconditions": "'Konami' does not exist in publishers table.",
        "steps": "1. Navigate to /publishers.\n2. Enter name 'Konami', leave console maker unchecked.\n3. Save publisher.",
        "expected": "Publisher created with is_console_maker=0; appears in publishers grid; returns HTTP 200 JSON."
    },
    {
        "id": "TC0066",
        "module": "Publishers / Create",
        "scenario": "Create hardware manufacturer publisher (Happy Path)",
        "inputs": "name: 'SEGA', is_console_maker: 1",
        "preconditions": "'SEGA' does not exist in publishers table.",
        "steps": "1. Enter name 'SEGA', check 'Console Manufacturer' checkbox.\n2. Save publisher.",
        "expected": "Publisher created with is_console_maker=1; immediately available in Consoles manufacturer dropdown."
    },
    {
        "id": "TC0067",
        "module": "Publishers / Update",
        "scenario": "Update publisher name and console maker flag (Happy Path)",
        "inputs": "id: 4, name: 'Bandai Namco Entertainment', is_console_maker: 0",
        "preconditions": "Publisher #4 exists.",
        "steps": "1. Select publisher #4.\n2. Update name.\n3. Save changes.",
        "expected": "Record updated in DB; grid reflects new name; green success toast displayed."
    },
    {
        "id": "TC0068",
        "module": "Publishers / Delete",
        "scenario": "Delete publisher with 0 linked consoles and 0 games (Happy Path)",
        "inputs": "id: 9 (unused publisher)",
        "preconditions": "Publisher #9 has 0 linked consoles and 0 linked games.",
        "steps": "1. Select publisher #9.\n2. Click 'Delete'.\n3. Confirm dialog.",
        "expected": "Publisher removed from database; grid updates; returns HTTP 200 JSON."
    },
    {
        "id": "TC0069",
        "module": "Publishers / Makers",
        "scenario": "Dynamic synchronization with Consoles maker dropdown (Edge Case)",
        "inputs": "Toggle publisher #2 is_console_maker from 0 to 1, then back to 0",
        "preconditions": "Publisher #2 exists.",
        "steps": "1. Edit publisher #2; set is_console_maker=1; save.\n2. Check /api/consoles or consoles form -> publisher #2 is listed.\n3. Edit publisher #2; set is_console_maker=0; save.\n4. Check consoles form -> publisher #2 is excluded.",
        "expected": "Consoles manufacturer dropdown dynamically synchronizes with is_console_maker status."
    },
    {
        "id": "TC0070",
        "module": "Publishers / Create",
        "scenario": "Blank publisher name validation (Failure Scenario)",
        "inputs": "name: '   '",
        "preconditions": "Publishers workbench open.",
        "steps": "1. Submit form with empty publisher name.",
        "expected": "Returns HTTP 422 'Publisher name cannot be empty.'."
    },
    {
        "id": "TC0071",
        "module": "Publishers / Create",
        "scenario": "Duplicate publisher name validation (Failure Scenario)",
        "inputs": "name: 'Nintendo' (already exists in DB)",
        "preconditions": "Publisher named 'Nintendo' exists.",
        "steps": "1. Attempt creating publisher named 'Nintendo'.",
        "expected": "existsByName() throws InvalidArgumentException 'Another publisher named 'Nintendo' already exists.'."
    },
    {
        "id": "TC0072",
        "module": "Publishers / Delete",
        "scenario": "Delete publisher blocked by registered consoles (Failure Scenario)",
        "inputs": "Publisher #1 (Nintendo) has 8 consoles registered",
        "preconditions": "Publisher #1 has linked consoles in DB.",
        "steps": "1. Attempt deleting publisher #1.",
        "expected": "PublisherRepository::delete() blocks operation; throws RuntimeException 'Cannot delete this publisher because it has 8 console platform(s) registered.'."
    },
    {
        "id": "TC0073",
        "module": "Publishers / Delete",
        "scenario": "Delete publisher blocked by assigned games (Failure Scenario)",
        "inputs": "Publisher #2 (Capcom) has 14 games assigned",
        "preconditions": "Publisher #2 has linked games in DB.",
        "steps": "1. Attempt deleting publisher #2.",
        "expected": "PublisherRepository::delete() blocks operation; throws RuntimeException 'Cannot delete this publisher because it is assigned to 14 game(s).'."
    },

    # =========================================================================
    # MODULE: CATEGORIES & SUBCATEGORIES
    # =========================================================================
    {
        "id": "TC0074",
        "module": "Categories / Create",
        "scenario": "Create genre category (Happy Path)",
        "inputs": "name: 'Shoot em up'",
        "preconditions": "'Shoot em up' does not exist in categories table.",
        "steps": "1. Navigate to /categories.\n2. Enter name.\n3. Save category.",
        "expected": "Category inserted in DB; grid updates with new entry; returns HTTP 200 JSON."
    },
    {
        "id": "TC0075",
        "module": "Categories / Update",
        "scenario": "Update genre category name (Happy Path)",
        "inputs": "id: 2, name: 'Role-Playing Game (RPG)'",
        "preconditions": "Category #2 exists.",
        "steps": "1. Select category #2.\n2. Edit name.\n3. Save changes.",
        "expected": "Category updated in DB; grid updates; green toast displayed."
    },
    {
        "id": "TC0076",
        "module": "Categories / Delete",
        "scenario": "Delete unused category (Happy Path)",
        "inputs": "id: 10 (category with 0 subcategories and 0 games)",
        "preconditions": "Category #10 has no children.",
        "steps": "1. Select category #10.\n2. Click Delete.\n3. Confirm dialog.",
        "expected": "Category deleted from database; grid reloads."
    },
    {
        "id": "TC0077",
        "module": "Categories / Create",
        "scenario": "Duplicate category name validation (Failure Scenario)",
        "inputs": "name: 'Action' (already exists)",
        "preconditions": "'Action' category exists in DB.",
        "steps": "1. Attempt creating category named 'Action'.",
        "expected": "CategoryRepository throws InvalidArgumentException 'Another category named 'Action' already exists.'."
    },
    {
        "id": "TC0078",
        "module": "Categories / Delete",
        "scenario": "Delete category blocked by attached subcategories (Failure Scenario)",
        "inputs": "Category #1 has 4 subcategories attached",
        "preconditions": "Subcategories table has rows referencing category_id=1.",
        "steps": "1. Attempt deleting category #1.",
        "expected": "CategoryRepository::delete() blocks deletion; throws RuntimeException 'Cannot delete this category because it has 4 subcategor(ies) attached.'."
    },
    {
        "id": "TC0079",
        "module": "Categories / Delete",
        "scenario": "Delete category blocked by assigned games (Failure Scenario)",
        "inputs": "Category #2 has 18 games assigned",
        "preconditions": "Games table has rows referencing category_id=2.",
        "steps": "1. Attempt deleting category #2.",
        "expected": "CategoryRepository::delete() blocks deletion; throws RuntimeException 'Cannot delete this category because it is assigned to 18 game(s).'."
    },
    {
        "id": "TC0080",
        "module": "Subcategories / Create",
        "scenario": "Create subcategory under valid parent category (Happy Path)",
        "inputs": "category_id: 1, name: 'Metroidvania'",
        "preconditions": "Parent Category #1 exists.",
        "steps": "1. Navigate to /subcategories.\n2. Select parent category.\n3. Enter subcategory name.\n4. Save subcategory.",
        "expected": "Subcategory inserted with fk_subcategories_category; grid refreshes; returns HTTP 200 JSON."
    },
    {
        "id": "TC0081",
        "module": "Subcategories / Update",
        "scenario": "Update subcategory name and parent category (Happy Path)",
        "inputs": "id: 3, category_id: 2, name: 'Turn-Based RPG'",
        "preconditions": "Subcategory #3 exists.",
        "steps": "1. Select subcategory #3.\n2. Edit name and/or parent category.\n3. Save changes.",
        "expected": "Subcategory updated in DB; grid updates immediately."
    },
    {
        "id": "TC0082",
        "module": "Subcategories / Delete",
        "scenario": "Delete unused subcategory (Happy Path)",
        "inputs": "id: 7 (subcategory with 0 assigned games)",
        "preconditions": "Subcategory #7 has no games assigned.",
        "steps": "1. Select subcategory #7.\n2. Click Delete.\n3. Confirm dialog.",
        "expected": "Subcategory removed from DB; grid reloads."
    },
    {
        "id": "TC0083",
        "module": "Subcategories / Scope",
        "scenario": "Same subcategory name under different parent categories allowed (Edge Case)",
        "inputs": "name: '2D' under Category 'Platformer' and name: '2D' under Category 'Fighter'",
        "preconditions": "Both parent categories exist.",
        "steps": "1. Create '2D' under Platformer.\n2. Create '2D' under Fighter.",
        "expected": "Duplicate check is scoped to category_id; both subcategories are created successfully."
    },
    {
        "id": "TC0084",
        "module": "Subcategories / Create",
        "scenario": "Duplicate subcategory name under same parent category blocked (Failure Scenario)",
        "inputs": "name: 'JRPG' under Category #1 (where 'JRPG' already exists under Category #1)",
        "preconditions": "'JRPG' exists under Category #1.",
        "steps": "1. Attempt creating another 'JRPG' under Category #1.",
        "expected": "SubcategoryRepository throws InvalidArgumentException 'A subcategory named 'JRPG' already exists under category...'; returns HTTP 422."
    },
    {
        "id": "TC0085",
        "module": "Subcategories / Create",
        "scenario": "Missing or invalid parent category selection (Failure Scenario)",
        "inputs": "category_id: 0, name: 'Side-Scroller'",
        "preconditions": "Category dropdown left unselected.",
        "steps": "1. Enter subcategory name without selecting parent category.\n2. Submit form.",
        "expected": "SubcategoryRepository throws InvalidArgumentException 'Please select a valid parent category.'; returns HTTP 422."
    },
    {
        "id": "TC0086",
        "module": "Subcategories / Delete",
        "scenario": "Delete subcategory blocked by assigned games (Failure Scenario)",
        "inputs": "Subcategory #1 has 12 games assigned",
        "preconditions": "Games table has rows referencing subcategory_id=1.",
        "steps": "1. Attempt deleting subcategory #1.",
        "expected": "SubcategoryRepository::delete() blocks deletion; throws RuntimeException 'Cannot delete this subcategory because it is assigned to 12 game(s).'."
    },

    # =========================================================================
    # MODULE: LANGUAGES & REGIONS
    # =========================================================================
    {
        "id": "TC0087",
        "module": "Languages / Create",
        "scenario": "Create language and region entry (Happy Path)",
        "inputs": "name: 'Spanish (Latin America)'",
        "preconditions": "Language does not exist in languages table.",
        "steps": "1. Navigate to /languages.\n2. Enter name in workbench form.\n3. Click 'Save Language'.",
        "expected": "Language inserted in DB; grid updates; green toast displayed."
    },
    {
        "id": "TC0088",
        "module": "Languages / Update",
        "scenario": "Update language name (Happy Path)",
        "inputs": "id: 3, name: 'Japanese (NTSC-J)'",
        "preconditions": "Language #3 exists in DB.",
        "steps": "1. Select language #3.\n2. Edit name.\n3. Save changes.",
        "expected": "Language updated in DB; grid reflects new name."
    },
    {
        "id": "TC0089",
        "module": "Languages / Delete",
        "scenario": "Delete unused language entry (Happy Path)",
        "inputs": "id: 6 (language with 0 games assigned)",
        "preconditions": "Language #6 has no games assigned.",
        "steps": "1. Select language #6.\n2. Click Delete.\n3. Confirm dialog.",
        "expected": "Language removed from DB; grid reloads."
    },
    {
        "id": "TC0090",
        "module": "Languages / Create",
        "scenario": "Duplicate language name validation (Failure Scenario)",
        "inputs": "name: 'English' (already exists in DB)",
        "preconditions": "Language 'English' exists in DB.",
        "steps": "1. Attempt creating language named 'English'.",
        "expected": "LanguageRepository throws InvalidArgumentException 'Another language named 'English' already exists.'; returns HTTP 422."
    },
    {
        "id": "TC0091",
        "module": "Languages / Delete",
        "scenario": "Delete language blocked by assigned games (Failure Scenario)",
        "inputs": "Language #1 (English) has 45 games assigned",
        "preconditions": "Games table has rows referencing language_id=1.",
        "steps": "1. Attempt deleting language #1.",
        "expected": "LanguageRepository::delete() blocks deletion; throws RuntimeException 'Cannot delete this language because it is assigned to 45 game(s).'."
    },

    # =========================================================================
    # MODULE: USER MANAGEMENT & SECURITY
    # =========================================================================
    {
        "id": "TC0092",
        "module": "Users / Create",
        "scenario": "Super Admin creates new user account (Happy Path)",
        "inputs": "first_name: 'Jane', last_name: 'Doe', email: 'jane.doe@videogamevault.local', password: 'SecurePassword#2026', role_id: 2, is_active: 1",
        "preconditions": "Super Admin authenticated; role_id 2 exists.",
        "steps": "1. Navigate to /users.\n2. Fill new user form.\n3. Submit form.",
        "expected": "Password hashed with bcrypt cost 12; user created in DB; grid displays new user; returns HTTP 200 JSON."
    },
    {
        "id": "TC0093",
        "module": "Users / Update",
        "scenario": "Super Admin updates user details (Happy Path)",
        "inputs": "id: 2, first_name: 'Jane', last_name: 'Smith', role_id: 2",
        "preconditions": "User #2 exists.",
        "steps": "1. Select user #2.\n2. Update last name.\n3. Save user.",
        "expected": "User record updated in DB; grid reflects changes."
    },
    {
        "id": "TC0094",
        "module": "Users / Toggle",
        "scenario": "Toggle user active state (Happy Path)",
        "inputs": "POST to /users/2/toggle",
        "preconditions": "User #2 has is_active = 1.",
        "steps": "1. Click toggle active button for user #2.\n2. Check DB users.is_active.",
        "expected": "users.is_active toggled to 0; user immediately prevented from logging in."
    },
    {
        "id": "TC0095",
        "module": "Users / Delete",
        "scenario": "Delete standard user account (Happy Path)",
        "inputs": "POST to /users/3/delete",
        "preconditions": "User #3 is not Super Admin.",
        "steps": "1. Select user #3.\n2. Click Delete.\n3. Confirm dialog.",
        "expected": "User #3 deleted from database; grid updates."
    },
    {
        "id": "TC0096",
        "module": "Users / Update",
        "scenario": "Optional password update preserves existing hash when blank (Edge Case)",
        "inputs": "id: 2, password: '' (left blank during update)",
        "preconditions": "User #2 has valid password hash in DB.",
        "steps": "1. Edit user #2 without touching password field.\n2. Save user.",
        "expected": "Existing password_hash remains unchanged; user can still log in with original password."
    },
    {
        "id": "TC0097",
        "module": "Users / Create",
        "scenario": "Invalid email address format validation (Failure Scenario)",
        "inputs": "email: 'not-an-email-address'",
        "preconditions": "Users workbench open.",
        "steps": "1. Fill user form with invalid email format.\n2. Submit form.",
        "expected": "filter_var(FILTER_VALIDATE_EMAIL) fails; returns HTTP 422 'A valid email address is required.'."
    },
    {
        "id": "TC0098",
        "module": "Users / Create",
        "scenario": "Duplicate email address validation (Failure Scenario)",
        "inputs": "email: 'admin@videogamevault.local' (already registered)",
        "preconditions": "Email already exists in users table.",
        "steps": "1. Attempt creating user with existing email.",
        "expected": "UserRepository::findByEmail() detects collision; returns HTTP 422 'A user with the email '...' already exists.'."
    },
    {
        "id": "TC0099",
        "module": "Users / Create",
        "scenario": "Password shorter than 8 characters validation (Failure Scenario)",
        "inputs": "password: 'pass12'",
        "preconditions": "Users workbench open.",
        "steps": "1. Enter 6-character password.\n2. Submit form.",
        "expected": "UserRepository throws InvalidArgumentException 'Password must be at least 8 characters in length.'; returns HTTP 422."
    },
    {
        "id": "TC0100",
        "module": "Users / Security",
        "scenario": "Primary Super Admin (ID 1) deactivation protection (Failure Scenario)",
        "inputs": "POST to /users/1/toggle",
        "preconditions": "User #1 is the primary Super Admin.",
        "steps": "1. Attempt to toggle active state of user #1.",
        "expected": "UserRepository::toggleActive(1) throws RuntimeException 'The primary Super Admin account cannot be deactivated.'; returns HTTP 422."
    },
    {
        "id": "TC0101",
        "module": "Users / Security",
        "scenario": "Primary Super Admin (ID 1) deletion protection (Failure Scenario)",
        "inputs": "POST to /users/1/delete",
        "preconditions": "User #1 is the primary Super Admin.",
        "steps": "1. Attempt to delete user #1.",
        "expected": "UserRepository::delete(1) throws RuntimeException 'The primary Super Admin account cannot be deleted.'; returns HTTP 422."
    },

    # =========================================================================
    # MODULE: ROLES & DUAL-DEVICE PERMISSION MATRIX
    # =========================================================================
    {
        "id": "TC0102",
        "module": "Roles / Create",
        "scenario": "Super Admin creates custom user role (Happy Path)",
        "inputs": "name: 'Catalog Editor', description: 'Can edit games and consoles from desktop'",
        "preconditions": "Super Admin authenticated.",
        "steps": "1. Navigate to /roles.\n2. Enter role name and description.\n3. Save role.",
        "expected": "Role created with is_super=0; appears in roles grid; returns HTTP 200 JSON."
    },
    {
        "id": "TC0103",
        "module": "Roles / Matrix",
        "scenario": "Configure dual-device permissions matrix across 12 screens (Happy Path)",
        "inputs": "role_id: 2, matrix: {games: {pc: 'write', other: 'read'}, consoles: {pc: 'read', other: 'none'}, ...}",
        "preconditions": "Role #2 exists; 12 screens registered in screens table.",
        "steps": "1. Select role #2 in roles workbench.\n2. Configure PC and Other permission radios for each screen.\n3. Click 'Save Role & Permissions'.",
        "expected": "role_permissions table records updated for all screen keys with designated access levels; returns HTTP 200 JSON."
    },
    {
        "id": "TC0104",
        "module": "Roles / API",
        "scenario": "Fetch role permission matrix via API (Happy Path)",
        "inputs": "HTTP GET /api/roles/2/matrix",
        "preconditions": "Role #2 has saved permissions in role_permissions table.",
        "steps": "1. Dispatch GET request to /api/roles/2/matrix.\n2. Inspect JSON response.",
        "expected": "Returns JSON with role metadata and dictionary of screen keys with access_pc and access_other values."
    },
    {
        "id": "TC0105",
        "module": "Roles / Delete",
        "scenario": "Delete unused role with cascade permissions cleanup (Happy Path)",
        "inputs": "id: 3 (custom role with 0 assigned users)",
        "preconditions": "Role #3 has 0 users assigned in users table.",
        "steps": "1. Select role #3.\n2. Click Delete.\n3. Confirm dialog.",
        "expected": "Role deleted from roles table; fk_permissions_role cascades deletion of role_permissions rows."
    },
    {
        "id": "TC0106",
        "module": "Roles / Security",
        "scenario": "Super Admin role (ID 1) deletion protection (Failure Scenario)",
        "inputs": "POST to /roles/1/delete",
        "preconditions": "Role #1 is the Super Admin role.",
        "steps": "1. Attempt deleting role #1.",
        "expected": "RoleRepository::delete(1) throws RuntimeException 'The primary Super Admin role cannot be deleted.'; returns HTTP 422."
    },
    {
        "id": "TC0107",
        "module": "Roles / Delete",
        "scenario": "Delete role blocked by assigned users (Failure Scenario)",
        "inputs": "Role #2 has 3 users assigned",
        "preconditions": "Users table has 3 rows with role_id=2.",
        "steps": "1. Attempt deleting role #2.",
        "expected": "RoleRepository::delete() blocks operation; throws RuntimeException 'Cannot delete role: 3 user(s) are currently assigned to it.'."
    },

    # =========================================================================
    # MODULE: DASHBOARD & TELEMETRY
    # =========================================================================
    {
        "id": "TC0108",
        "module": "Dashboard / KPI",
        "scenario": "KPI counters aggregation across catalog (Happy Path)",
        "inputs": "Catalog with 100 total games (70 owned, 20 backlog, 10 playing, 40 won, 15 missing covers, 25 missing screens, 50 fully documented)",
        "preconditions": "Games catalog populated with diverse progression states.",
        "steps": "1. Navigate to /.\n2. Inspect KPI dashboard cards.",
        "expected": "Dashboard displays correct counts for Total Games, Owned, Backlog, Playing, Won, Missing Covers, Missing Screenshots, and Fully Documented."
    },
    {
        "id": "TC0109",
        "module": "Dashboard / Metrics",
        "scenario": "Collection health percentage calculation (Happy Path)",
        "inputs": "total_games: 100, fully_documented: 75",
        "preconditions": "Catalog contains 100 games.",
        "steps": "1. Verify formula: (fully_documented / total_games) * 100.\n2. Compare with displayed Health % on dashboard.",
        "expected": "Dashboard displays '75.0%' Collection Health progress meter."
    },
    {
        "id": "TC0110",
        "module": "Dashboard / Metrics",
        "scenario": "Completion percentage calculation (Happy Path)",
        "inputs": "owned_games: 50, won_games: 25",
        "preconditions": "50 owned games in collection.",
        "steps": "1. Verify formula: (won_games / owned_games) * 100.\n2. Compare with displayed Completion % on dashboard.",
        "expected": "Dashboard displays '50.0%' Backlog Completion rate."
    },
    {
        "id": "TC0111",
        "module": "Dashboard / API",
        "scenario": "Dashboard JSON telemetry API endpoint (Happy Path)",
        "inputs": "HTTP GET /api/dashboard",
        "preconditions": "User authenticated.",
        "steps": "1. Send request to /api/dashboard.\n2. Validate JSON structure.",
        "expected": "Returns {success:true, data:{kpi:{...}, counts:{...}, top_consoles:[...], currently_playing:[...]}}."
    },
    {
        "id": "TC0112",
        "module": "System / Health",
        "scenario": "Baseline application and database health check (Happy Path)",
        "inputs": "HTTP GET /health",
        "preconditions": "Web server and MariaDB service online.",
        "steps": "1. Navigate to /health in browser or curl.",
        "expected": "Returns HTTP 200 JSON with status='ok', app name, env, database='online', and server timestamp."
    },
    {
        "id": "TC0113",
        "module": "Dashboard / Metrics",
        "scenario": "Zero games catalog calculation safety (Edge Case)",
        "inputs": "Database with 0 total games",
        "preconditions": "Games table is completely empty.",
        "steps": "1. Access dashboard with 0 games in DB.",
        "expected": "Health % and Completion % evaluate to 0.0% without triggering PHP division by zero warnings or errors."
    },
    {
        "id": "TC0114",
        "module": "Dashboard / Widgets",
        "scenario": "Top consoles widget ranking by owned title count (Edge Case)",
        "inputs": "Multiple consoles with varying counts of owned games",
        "preconditions": "Consoles and games linked.",
        "steps": "1. Inspect Top Systems widget on Dashboard.",
        "expected": "Consoles ordered by owned_titles DESC, total_titles DESC; limit 6 applied."
    },

    # =========================================================================
    # MODULE: DATA GRID & CLIENT UI ENGINE
    # =========================================================================
    {
        "id": "TC0115",
        "module": "UI Grid / Render",
        "scenario": "Data grid renders columns, text, badges, and action buttons (Happy Path)",
        "inputs": "DataGrid element with defined columns and data array",
        "preconditions": "Vanilla JS Web Component <data-grid> registered in customElements.",
        "steps": "1. Inspect rendered <data-grid> DOM on /languages or /games.",
        "expected": "Table renders header, dynamic body rows, badges, action buttons, search bar, and pagination footer."
    },
    {
        "id": "TC0116",
        "module": "UI Grid / Search",
        "scenario": "Client-side debounced multi-column text search (Happy Path)",
        "inputs": "Search query: 'Mario' entered in grid search input",
        "preconditions": "Grid loaded with 100+ records.",
        "steps": "1. Type 'Mario' into search bar.\n2. Observe filtered results after 180ms debounce.",
        "expected": "Grid filters instantly to rows matching 'Mario' in any searchable column; telemetry counter updates."
    },
    {
        "id": "TC0117",
        "module": "UI Grid / Sort",
        "scenario": "Interactive column sorting with direction toggle (Happy Path)",
        "inputs": "Click on 'Title' column header, then click again",
        "preconditions": "Grid loaded with data.",
        "steps": "1. Click 'Title' header once (ascending).\n2. Click 'Title' header again (descending).",
        "expected": "First click sorts A-Z with up-arrow indicator. Second click sorts Z-A with down-arrow indicator."
    },
    {
        "id": "TC0118",
        "module": "UI Grid / Pagination",
        "scenario": "Pagination controls navigation and page size selector (Happy Path)",
        "inputs": "Navigation buttons: Next, Prev, First, Last, Jump to page; Page size: 50",
        "preconditions": "Catalog contains 100+ records.",
        "steps": "1. Click 'Next >' button.\n2. Change page size to 50.\n3. Enter page number in Jump input and click 'Go'.",
        "expected": "Table slices data correctly; current page indicator and telemetry ('Showing X to Y of Z') update seamlessly."
    },
    {
        "id": "TC0119",
        "module": "UI Grid / Selection",
        "scenario": "Row click selection and persistent form synchronization (Happy Path)",
        "inputs": "Click row corresponding to game #14 in games grid",
        "preconditions": "Games workbench open.",
        "steps": "1. Click row #14 in grid.\n2. Verify row highlight.\n3. Verify left form fields.",
        "expected": "Row dispatches 'row-click'; row receives .vault-grid-row-selected highlight; form populates with game #14 data."
    },
    {
        "id": "TC0120",
        "module": "UI Grid / Events",
        "scenario": "Grid dispatches grid-updated event for row highlight sync (Happy Path)",
        "inputs": "Grid pagination or sorting event",
        "preconditions": "Row selected in grid.",
        "steps": "1. Sort grid by column.\n2. Verify selected row highlight.",
        "expected": "Grid dispatches custom event 'grid-updated'; syncRowHighlight() listener maintains active highlight on selected item."
    },
    {
        "id": "TC0121",
        "module": "UI Toast / Notify",
        "scenario": "Centralized animated toast notification display (Happy Path)",
        "inputs": "showToast('Operation successful.', 'success')",
        "preconditions": "layout.php or games.php toast container present.",
        "steps": "1. Trigger success toast on save or delete.\n2. Observe toast styling and animation.",
        "expected": "Toast element slides into view with green border/styling; automatically fades and dismisses after 3 seconds."
    },
    {
        "id": "TC0122",
        "module": "UI Dropzone / Media",
        "scenario": "Visual asset drag-and-drop file upload with live preview (Happy Path)",
        "inputs": "Drag image file over dropzone box and drop",
        "preconditions": "User has write permissions.",
        "steps": "1. Drag image file over dropzone.\n2. Observe .drag-over hover style.\n3. Release mouse to drop file.",
        "expected": "File input captures file; container replaces placeholder with URL.createObjectURL() preview thumbnail; delete button enabled."
    },
    {
        "id": "TC0123",
        "module": "UI Grid / Empty",
        "scenario": "Empty state display when query matches no records (Edge Case)",
        "inputs": "Search query: 'XYZNonExistentGame999'",
        "preconditions": "Grid loaded with data.",
        "steps": "1. Enter search term matching zero rows.",
        "expected": "Grid body renders responsive empty state card: 'No records found - Try clearing your search query or relaxing active filters.'."
    },
    {
        "id": "TC0124",
        "module": "UI Flash / AJAX",
        "scenario": "AJAX requests do not pollute session flash message (Edge Case)",
        "inputs": "Save game via AJAX POST -> navigate to /consoles",
        "preconditions": "GameController and layout.php configured.",
        "steps": "1. Save game via AJAX in /games.\n2. Observe green toast on /games.\n3. Navigate to /consoles via top nav bar.",
        "expected": "Consoles page loads clean; NO lingering flash message appears on Consoles screen."
    },
    {
        "id": "TC0125",
        "module": "Security / CSRF & XSS",
        "scenario": "HTML entity escaping on dynamic cell rendering (Security)",
        "inputs": "Title: '<script>alert(\"XSS\")</script>' or '\" onmouseover=\"alert(1)'",
        "preconditions": "Record stored in database.",
        "steps": "1. View record in grid.\n2. Inspect DOM element.",
        "expected": "escapeHtml() sanitizes all output (< -> &lt;, > -> &gt;, \" -> &quot;); script tags are not executed."
    },

    # =========================================================================
    # MODULE: CONSOLE HARDWARE TYPES TAXONOMY & DYNAMIC BADGES
    # =========================================================================
    {
        "id": "TC0126",
        "module": "Consoles / Type Selector",
        "scenario": "Single mandatory dropdown selector replaces contradictory checkboxes (Happy Path)",
        "inputs": "Console form: name: 'Nintendo Switch', console_type_id: 1 ('Home'), is_for_reference: 0",
        "preconditions": "Console Types populated in database.",
        "steps": "1. Navigate to /consoles.\n2. Verify hardware checkboxes (Handheld, Computer, Arcade) are replaced by a single mandatory dropdown.\n3. Verify form cannot submit conflicting categories.\n4. Select 'Home' and save.",
        "expected": "Form submits with single console_type_id foreign key; conflicting multiple states prevented; console stored cleanly."
    },
    {
        "id": "TC0127",
        "module": "Consoles / Grid Badge",
        "scenario": "Console grid renders custom styled type badge followed by REF badge (Happy Path)",
        "inputs": "Console record with console_type_name='Home', badge_bg_color='#1E3A8A', badge_font_color='#93C5FD', is_for_reference=1",
        "preconditions": "Console registered with reference flag enabled.",
        "steps": "1. Navigate to /consoles.\n2. Inspect Console column in <data-grid>.",
        "expected": "Grid displays console title followed by custom badge with #1E3A8A background and #93C5FD text, immediately followed by red REF badge."
    },
    {
        "id": "TC0128",
        "module": "Console Types / Create",
        "scenario": "Create new console hardware type with custom hex colors (Happy Path)",
        "inputs": "name: 'Hybrid', badge_bg_color: '#0F172A', badge_font_color: '#38BDF8'",
        "preconditions": "Super Admin or user with console_types write permission.",
        "steps": "1. Navigate to /console-types.\n2. Enter Type Name 'Hybrid'.\n3. Set background color to #0F172A and font color to #38BDF8.\n4. Click 'Save Console Type'.",
        "expected": "Record inserted into console_types; returns HTTP 200 JSON; grid refreshes; new type appears in Consoles form dropdown."
    },
    {
        "id": "TC0129",
        "module": "Console Types / Preview",
        "scenario": "Live badge preview card reflects typed name and hex colors in real time (Happy Path)",
        "inputs": "Type name input: 'HYBRID', Background color input: '#1E293B', Font color input: '#60A5FA'",
        "preconditions": "Console Types workbench open.",
        "steps": "1. Type 'HYBRID' in Type Name input.\n2. Change background color to '#1E293B'.\n3. Change font color to '#60A5FA'.\n4. Observe live preview box.",
        "expected": "Live badge preview updates instantly with uppercase text 'HYBRID', background #1E293B, and text color #60A5FA without page reload."
    },
    {
        "id": "TC0130",
        "module": "Console Types / Color Sync",
        "scenario": "Bidirectional synchronization between text input and color swatch picker (Happy Path)",
        "inputs": "Color swatch picker change vs. manual hex text input change",
        "preconditions": "Console Types workbench open.",
        "steps": "1. Pick a color using the HTML5 color swatch -> verify text input updates to uppercase hex.\n2. Type '#064E3B' in text input -> verify color swatch updates to matching green.",
        "expected": "Values remain perfectly synchronized in both directions; live badge preview reflects the change."
    },
    {
        "id": "TC0131",
        "module": "Console Types / Update",
        "scenario": "Update console type name and badge styling (Happy Path)",
        "inputs": "id: 2, name: 'Arcade System Board', badge_bg_color: '#78350F', badge_font_color: '#FDE68A'",
        "preconditions": "Console Type #2 exists.",
        "steps": "1. Select Arcade row in <data-grid>.\n2. Update name and colors.\n3. Click 'Save Console Type'.",
        "expected": "Database record updated; grid reflects new name and badge styling; green success toast displayed."
    },
    {
        "id": "TC0132",
        "module": "Console Types / Delete",
        "scenario": "Delete unreferenced console hardware type (Happy Path)",
        "inputs": "id: 5 (console type with 0 assigned consoles)",
        "preconditions": "Console type has 0 consoles referencing its ID.",
        "steps": "1. Select console type #5 in grid.\n2. Click 'Delete'.\n3. Confirm dialog.",
        "expected": "Record removed from database; grid reloads; form resets to Auto ID state; green success toast displayed."
    },
    {
        "id": "TC0133",
        "module": "Console Types / Constraint",
        "scenario": "Delete console type blocked when consoles are linked (Failure Scenario)",
        "inputs": "id: 1 ('Home' console type referenced by existing consoles)",
        "preconditions": "At least one console has console_type_id = 1.",
        "steps": "1. Select console type #1 in grid.\n2. Verify Delete button is disabled or attempting delete throws error.",
        "expected": "Controller checks getConsolesCount(1) > 0; blocks delete; returns HTTP 422 'Cannot delete: X console(s) are currently assigned to this type.'."
    },
    {
        "id": "TC0134",
        "module": "Console Types / Validate",
        "scenario": "Hex color format validation enforces 6-digit hex code (Failure Scenario)",
        "inputs": "badge_bg_color: 'blue' or '#XYZ' or '#12345'",
        "preconditions": "Console Types workbench open.",
        "steps": "1. Enter invalid hex color string in background color field.\n2. Submit form.",
        "expected": "Frontend pattern validation blocks submission; backend throws InvalidArgumentException 'Background color must be a valid 6-digit hex code.'."
    },
    {
        "id": "TC0135",
        "module": "Console Types / RBAC",
        "scenario": "Console Types screen registered in RBAC matrix with dual-device permissions (Security)",
        "inputs": "GET /roles -> inspect screens list; evaluate access_pc and access_other",
        "preconditions": "Role 1 has write access; restricted roles have custom permissions.",
        "steps": "1. Navigate to /roles.\n2. Inspect permissions matrix table for 'Console Types' entry under Taxonomy.\n3. Verify independent PC and Other device permission toggles.",
        "expected": "Console Types screen appears in matrix; Super Admin has write/write; permissions enforced across devices via Auth::can()."
    }
]

# -----------------------------------------------------------------------------
# 2. XML Generation Helpers
# -----------------------------------------------------------------------------

def escape_xml(val):
    if val is None:
        return ""
    return saxutils.escape(str(val))

def col_letter(col_idx):
    """1-based column index to Excel column letter (e.g. 1 -> A, 8 -> H)."""
    result = ""
    while col_idx > 0:
        col_idx, rem = divmod(col_idx - 1, 26)
        result = chr(65 + rem) + result
    return result

def build_content_types_xml():
    return """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
</Types>"""

def build_root_rels_xml():
    return """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>"""

def build_workbook_rels_xml():
    return """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>"""

def build_workbook_xml():
    return """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Test Cases" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>"""

def build_styles_xml():
    """
    Constructs professional styles:
    - Font 0: Regular 10pt Segoe UI / Calibri
    - Font 1: Bold 10.5pt Segoe UI / Calibri (#0F172A)
    - Font 2: Bold Monospace 9.5pt Consolas for TC IDs (#1E293B)
    - Fill 0: none
    - Fill 1: gray125
    - Fill 2: Light Gray Header (#E2E8F0)
    - Fill 3: Soft Alternating Row Zebra (#F8FAFC)
    - Border 0: none
    - Border 1: Thin border (#CBD5E1)
    - Formats (cellXfs):
      0: Normal
      1: Header Style (Font 1, Fill 2, Border 1, Center/Center, Wrap)
      2: Data Text Left (Font 0, Fill 0, Border 1, Left/Top, Wrap)
      3: Data Text Center (Font 0, Fill 0, Border 1, Center/Top, Wrap)
      4: TC ID Style (Font 2, Fill 0, Border 1, Center/Top)
      5: Zebra Data Text Left (Font 0, Fill 3, Border 1, Left/Top, Wrap)
      6: Zebra Data Text Center (Font 0, Fill 3, Border 1, Center/Top, Wrap)
      7: Zebra TC ID Style (Font 2, Fill 3, Border 1, Center/Top)
    """
    return """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="3">
    <font>
      <sz val="10"/>
      <name val="Segoe UI"/>
      <family val="2"/>
      <color rgb="FF1E293B"/>
    </font>
    <font>
      <b/>
      <sz val="10.5"/>
      <name val="Segoe UI"/>
      <family val="2"/>
      <color rgb="FF0F172A"/>
    </font>
    <font>
      <b/>
      <sz val="9.5"/>
      <name val="Consolas"/>
      <family val="3"/>
      <color rgb="FF0F172A"/>
    </font>
  </fonts>
  <fills count="4">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill>
      <patternFill patternType="solid">
        <fgColor rgb="FFE2E8F0"/>
        <bgColor indexed="64"/>
      </patternFill>
    </fill>
    <fill>
      <patternFill patternType="solid">
        <fgColor rgb="FFF8FAFC"/>
        <bgColor indexed="64"/>
      </patternFill>
    </fill>
  </fills>
  <borders count="2">
    <border><left/><right/><top/><bottom/><diagonal/></border>
    <border>
      <left style="thin"><color rgb="FFCBD5E1"/></left>
      <right style="thin"><color rgb="FFCBD5E1"/></right>
      <top style="thin"><color rgb="FFCBD5E1"/></top>
      <bottom style="thin"><color rgb="FFCBD5E1"/></bottom>
    </border>
  </borders>
  <cellStyleXfs count="1">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
  </cellStyleXfs>
  <cellXfs count="8">
    <!-- 0: Default -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    
    <!-- 1: Header Row Style (Bold text, light gray background #E2E8F0, thin border) -->
    <xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="center" wrapText="1"/>
    </xf>
    
    <!-- 2: Data Text Left (Normal) -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="left" vertical="top" wrapText="1"/>
    </xf>
    
    <!-- 3: Data Text Center (Normal) -->
    <xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="top" wrapText="1"/>
    </xf>
    
    <!-- 4: TC ID Bold Monospace (Normal) -->
    <xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="top"/>
    </xf>

    <!-- 5: Zebra Data Text Left -->
    <xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="left" vertical="top" wrapText="1"/>
    </xf>

    <!-- 6: Zebra Data Text Center -->
    <xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="top" wrapText="1"/>
    </xf>

    <!-- 7: Zebra TC ID Bold Monospace -->
    <xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">
      <alignment horizontal="center" vertical="top"/>
    </xf>
  </cellXfs>
</styleSheet>"""

def build_sheet_xml(test_cases):
    """
    Constructs the worksheet XML with column widths, frozen header row,
    auto-wrap, and styled data rows.
    """
    headers = [
        ("Test Case ID", 15),
        ("Module/Function", 20),
        ("Description/Scenario", 38),
        ("Test Data/Inputs", 32),
        ("Preconditions", 28),
        ("Execution Steps", 46),
        ("Expected Result", 42),
        ("Status", 12)
    ]
    
    cols_xml = []
    for idx, (hname, width) in enumerate(headers, 1):
        cols_xml.append(f'<col min="{idx}" max="{idx}" width="{width}" customWidth="1"/>')
    
    rows_xml = []
    
    # 1. Header Row (Row 1, height=32pt, style=1)
    header_cells = []
    for c_idx, (hname, _) in enumerate(headers, 1):
        ref = f"{col_letter(c_idx)}1"
        header_cells.append(
            f'<c r="{ref}" s="1" t="inlineStr"><is><t>{escape_xml(hname)}</t></is></c>'
        )
    rows_xml.append(f'<row r="1" ht="32" customHeight="1">{"".join(header_cells)}</row>')
    
    # 2. Data Rows
    for r_idx, tc in enumerate(test_cases, 2):
        is_zebra = (r_idx % 2 == 1)
        id_style = 7 if is_zebra else 4
        text_style = 5 if is_zebra else 2
        center_style = 6 if is_zebra else 3
        
        # Calculate dynamic row height based on content line count
        max_lines = max(
            tc["steps"].count("\n") + 1,
            tc["expected"].count("\n") + 1,
            tc["scenario"].count("\n") + 1,
            tc["inputs"].count("\n") + 1,
            len(tc["steps"]) // 40 + 1,
            len(tc["expected"]) // 38 + 1
        )
        row_height = max(26, min(140, max_lines * 16 + 10))
        
        cells = [
            f'<c r="A{r_idx}" s="{id_style}" t="inlineStr"><is><t>{escape_xml(tc["id"])}</t></is></c>',
            f'<c r="B{r_idx}" s="{text_style}" t="inlineStr"><is><t>{escape_xml(tc["module"])}</t></is></c>',
            f'<c r="C{r_idx}" s="{text_style}" t="inlineStr"><is><t>{escape_xml(tc["scenario"])}</t></is></c>',
            f'<c r="D{r_idx}" s="{text_style}" t="inlineStr"><is><t>{escape_xml(tc["inputs"])}</t></is></c>',
            f'<c r="E{r_idx}" s="{text_style}" t="inlineStr"><is><t>{escape_xml(tc["preconditions"])}</t></is></c>',
            f'<c r="F{r_idx}" s="{text_style}" t="inlineStr"><is><t>{escape_xml(tc["steps"])}</t></is></c>',
            f'<c r="G{r_idx}" s="{text_style}" t="inlineStr"><is><t>{escape_xml(tc["expected"])}</t></is></c>',
            f'<c r="H{r_idx}" s="{center_style}" t="inlineStr"><is><t></t></is></c>',
        ]
        rows_xml.append(f'<row r="{r_idx}" ht="{row_height}" customHeight="1">{"".join(cells)}</row>')
    
    last_row = len(test_cases) + 1
    
    return f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheetViews>
    <sheetView tabSelected="1" workbookViewId="0">
      <!-- Freeze Header Row (Row 1 stays fixed when scrolling) -->
      <pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>
    </sheetView>
  </sheetViews>
  <sheetFormatPr defaultRowHeight="18" baseColWidth="10"/>
  <cols>
    {"".join(cols_xml)}
  </cols>
  <sheetData>
    {"".join(rows_xml)}
  </sheetData>
  <autoFilter ref="A1:H{last_row}"/>
</worksheet>"""

# -----------------------------------------------------------------------------
# 3. Main File Generation Orchestrator
# -----------------------------------------------------------------------------

def main():
    workspace_root = r"c:\Projects\antigravity\VideogameCataloguer"
    test_dir = os.path.join(workspace_root, "test")
    os.makedirs(test_dir, exist_ok=True)
    
    # Destination xlsx file
    xlsx_path = os.path.join(test_dir, "test_cases.xlsx")
    
    print(f"Generating comprehensive test cases sheet at: {xlsx_path}")
    print(f"Total test cases defined: {len(TEST_CASES)}")
    
    # Build OpenXML package
    content_types = build_content_types_xml()
    root_rels = build_root_rels_xml()
    wb_rels = build_workbook_rels_xml()
    workbook = build_workbook_xml()
    styles = build_styles_xml()
    worksheet = build_sheet_xml(TEST_CASES)
    
    with zipfile.ZipFile(xlsx_path, "w", zipfile.ZIP_DEFLATED) as zf:
        zf.writestr("[Content_Types].xml", content_types)
        zf.writestr("_rels/.rels", root_rels)
        zf.writestr("xl/_rels/workbook.xml.rels", wb_rels)
        zf.writestr("xl/workbook.xml", workbook)
        zf.writestr("xl/styles.xml", styles)
        zf.writestr("xl/worksheets/sheet1.xml", worksheet)
        
    print(f"Successfully generated {xlsx_path} (Size: {os.path.getsize(xlsx_path)} bytes)")

if __name__ == "__main__":
    main()
