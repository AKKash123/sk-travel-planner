```markdown
# ✈️ SK Travel Planner

> A complete, secure PHP web application for creating and managing travel itineraries with an admin panel, image uploads, PDF generation, and database backup — built with XSS and SQL injection protection at every layer.

---

## 📌 Table of Contents

- [Overview](#-overview)
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Project Structure](#-project-structure)
- [Installation](#-installation)
- [Configuration](#-configuration)
- [Usage Guide](#-usage-guide)
- [Security Measures](#-security-measures)
- [Database Schema](#-database-schema)
- [Default Credentials](#-default-credentials)
- [Troubleshooting](#-troubleshooting)
- [License](#-license)

---

## 🌍 Overview

SK Travel Planner allows travel agencies and independent planners to **create, edit, delete, and publish** curated travel itineraries. Visitors browse active itineraries on the public-facing site, view full day-wise details, and download a professionally formatted PDF. Administrators manage everything through a secure dashboard.

---

## ✨ Features

### Public Frontend
| Feature | Description |
|---|---|
| Hero Section | Animated landing with floating shapes and gradient background |
| Itinerary Cards | Image, destination, price, duration, highlight count |
| Detail Page | Full day-wise breakdown, highlights, inclusions/exclusions |
| PDF Download | One-click PDF generation with styled itinerary layout |
| Responsive Design | Mobile-first layout, works on all screen sizes |

### Admin Panel
| Feature | Description |
|---|---|
| Secure Login | bcrypt password hashing, session regeneration |
| Dashboard | Stats overview (total/active/inactive) + data table |
| Create Itinerary | Full form with image upload, day-wise plan builder |
| Edit Itinerary | Pre-populated form, image replacement |
| Delete Itinerary | Confirmation prompt, image file cleanup |
| Toggle Status | Activate/deactivate itineraries instantly |
| Database Backup | Full SQL dump download with restore instructions |

### Security
| Feature | Description |
|---|---|
| XSS Prevention | `htmlspecialchars()` on every output via `e()` helper |
| SQL Injection Prevention | PDO prepared statements throughout |
| CSRF Protection | Cryptographic tokens on all forms with `hash_equals()` |
| File Upload Security | MIME validation, extension whitelist, size limit, unique filenames |
| Session Security | HttpOnly cookies, regeneration on login, strict mode |
| Security Headers | `X-Content-Type-Options`, `X-Frame-Options`, `X-XSS-Protection` |

---

## 🛠 Tech Stack

| Component | Technology |
|---|---|
| Backend | PHP 8.0+ |
| Database | MySQL / MariaDB (utf8mb4) |
| DB Driver | PDO with prepared statements |
| PDF Generation | FPDF 1.82 |
| Frontend | Vanilla CSS (custom design system), Font Awesome 6 |
| Fonts | Playfair Display + Source Sans 3 (Google Fonts) |
| Authentication | bcrypt via `password_hash()` / `password_verify()` |

---

## 📁 Project Structure

```
sk-travel-planner/
│
├── config.php                  # DB connection, session, all helper functions
├── database.sql                # Schema + seed data + default admin
├── style.css                   # Complete design system (public + admin)
│
├── index.php                   # Public homepage — itinerary cards
├── detail.php                  # Single itinerary detail view
├── download-pdf.php            # PDF generation & download
│
├── images/                     # Official brand logos, marks, and favicons
│   ├── logo.png                # Master full logo (white background)
│   ├── logo-light.png          # Logo adapted for dark backgrounds
│   ├── logo-transparent.png    # Transparent background logo
│   ├── logo-mark.png           # Standalone brand mark icon
│   └── favicon.ico             # Browser favicon
│
├── uploads/                    # Uploaded itinerary images (chmod 755)
│
├── fpdf/                       # FPDF library
│   └── fpdf.php
│
└── admin/
    ├── index.php               # Login page
    ├── dashboard.php           # Admin dashboard + itinerary table
    ├── itinerary-form.php      # Create / Edit form
    ├── itinerary-action.php    # Save / Delete / Toggle handler
    ├── backup.php              # Database backup & download
    ├── logout.php              # Session destroy & redirect
    └── sidebar-fragment.php    # Shared sidebar navigation
```

---

## 🚀 Installation

### Prerequisites

- **PHP 8.0+** with PDO and `fileinfo` extension enabled
- **MySQL 5.7+** or **MariaDB 10.3+**
- **Apache** with `mod_rewrite` (or Nginx with PHP-FPM)
- A web server environment (XAMPP, WAMP, Laragon, or LAMP)

### Step 1 — Clone or Copy

```bash
# Clone into your web server document root
cd /var/www/html    # or C:\xampp\htdocs on Windows
git clone https://github.com/your-repo/sk-travel-planner.git
cd sk-travel-planner
```

Or simply copy all project files into your web server directory.

### Step 2 — Create the Database

Open phpMyAdmin or MySQL CLI and run:

```bash
mysql -u root -p < database.sql
```

This creates:
- Database: `sk_travel_planner`
- Tables: `admin_users`, `itineraries`
- Default admin account
- 3 sample itineraries (Bali, Tokyo, Kerala)

### Step 3 — Set Upload Permissions

```bash
mkdir -p uploads
chmod 755 uploads
```

On Windows (XAMPP), the directory is writable by default — no action needed.

### Step 4 — Install FPDF

1. Download from [fpdf.org/en/dl.php?v=152&f=zip](https://www.fpdf.org/en/dl.php?v=152&f=zip)
2. Extract the archive
3. Place `fpdf.php` inside the `fpdf/` directory:

```
sk-travel-planner/
└── fpdf/
    └── fpdf.php
```

### Step 5 — Configure

Edit `config.php` and update these values to match your environment:

```php
define('DB_HOST', 'localhost');       // Your MySQL host
define('DB_NAME', 'sk_travel_planner'); // Database name
define('DB_USER', 'root');            // MySQL username
define('DB_PASS', '');                // MySQL password
define('APP_URL', 'http://localhost/sk-travel-planner'); // Your base URL
```

### Step 6 — Verify

Navigate to your app in a browser:

```
http://localhost/sk-travel-planner/index.php
```

You should see the public homepage with sample itineraries.

---

## ⚙ Configuration

All configuration lives in `config.php`. Here is the full reference:

| Constant | Default | Description |
|---|---|---|
| `DB_HOST` | `localhost` | MySQL server hostname |
| `DB_NAME` | `sk_travel_planner` | Database name |
| `DB_USER` | `root` | MySQL username |
| `DB_PASS` | `''` | MySQL password |
| `APP_NAME` | `SK Travel Planner` | Application display name |
| `APP_URL` | `http://localhost/sk-travel-planner` | Full base URL (no trailing slash) |
| `UPLOAD_DIR` | `__DIR__ . '/uploads/'` | Absolute path to upload directory |
| `UPLOAD_URL` | `APP_URL . '/uploads/'` | URL to upload directory |
| `MAX_FILE_SIZE` | `5242880` (5 MB) | Maximum image upload size in bytes |
| `ALLOWED_EXT` | `['jpg','jpeg','png','gif','webp']` | Allowed image extensions |

---

## 📖 Usage Guide

### Browsing Itineraries (Public)

1. Visit `index.php` — all **active** itineraries appear as cards
2. Click **View Details** to see the full day-wise breakdown
3. On the detail page, click **Download PDF** to get a formatted document

### Admin Access

1. Navigate to `admin/index.php` or click **Admin** in the navbar
2. Log in with credentials (default: `admin` / `admin123`)
3. You arrive at the **Dashboard** with stats and full itinerary table

### Creating an Itinerary

1. Click **Add New Itinerary** on the dashboard
2. Fill in the form:
   - **Title & Destination** — required
   - **Description** — overview text
   - **Price** — in INR
   - **Duration** — number of days
   - **Featured Image** — JPG/PNG/GIF/WebP, max 5MB
   - **Highlights** — one per line
   - **Inclusions / Exclusions** — one per line
   - **Day-wise Plan** — click **Add Day** for each day, fill title, description, meals, accommodation
   - **Status** — Active (visible) or Inactive (hidden)
3. Click **Create Itinerary**

### Editing an Itinerary

1. Click the **Edit** button on any row in the dashboard table
2. The form loads with all existing data pre-populated
3. Make changes and click **Update Itinerary**
4. To replace the image, upload a new one — the old file is automatically deleted

### Deleting an Itinerary

1. Click the **Delete** button (red trash icon)
2. Confirm the deletion in the browser dialog
3. The itinerary and its image file are permanently removed

### Toggling Status

1. Click the **eye** icon to toggle between Active and Inactive
2. Inactive itineraries are hidden from the public site but remain in the admin list

### Database Backup

1. Go to **DB Backup** in the sidebar
2. View current database stats (tables, itinerary count, admin count)
3. Click **Generate & Download Backup**
4. A complete `.sql` file downloads — includes all `CREATE TABLE` and `INSERT` statements
5. To restore: import the file via phpMyAdmin or `mysql -u root -p sk_travel_planner < backup.sql`

### PDF Downloads

PDFs are generated on-demand when a visitor clicks **Download PDF** on the detail page. The PDF includes:
- Itinerary title and destination
- Price and duration
- Full overview
- Highlights list
- Day-wise schedule with meals and accommodation
- Inclusions and exclusions
- Branded header and footer with page numbers

---

## 🛡 Security Measures

### XSS (Cross-Site Scripting) Prevention

Every piece of user-generated data passes through the `e()` function before rendering:

```php
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
```

- Used in **every** `<?= e(...) ?>` output across all templates
- No user input is ever rendered as raw HTML
- JSON data (highlights, day plans) is decoded then each element escaped individually

### SQL Injection Prevention

All database queries use **PDO prepared statements** with positional placeholders:

```php
$stmt = $pdo->prepare("SELECT * FROM itineraries WHERE id = ?");
$stmt->execute([$id]);
```

- `PDO::ATTR_EMULATE_PREPARES` is set to `false` — true prepared statements
- Zero string concatenation in SQL queries
- `PDO::ATTR_ERRMODE` set to `ERRMODE_EXCEPTION`

### CSRF (Cross-Site Request Forgery) Protection

Every form includes a hidden token:

```php
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
```

Verified on submission using `hash_equals()` (timing-safe comparison):

```php
function csrfVerify($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
```

### File Upload Security

| Check | Implementation |
|---|---|
| MIME type | `finfo_file()` with `FILEINFO_MIME_TYPE` against whitelist |
| Extension | `pathinfo()` checked against `ALLOWED_EXT` |
| File size | Compared against `MAX_FILE_SIZE` (5MB) |
| Filename | `uniqid('sk_', true)` prevents overwrite and path traversal |
| Old files | Automatically deleted on image replacement or itinerary deletion |

### Session Security

- `session.cookie_httponly = 1` — JavaScript cannot access session cookie
- `session.use_strict_mode = 1` — rejects uninitialized session IDs
- `session_regenerate_id(true)` — new session ID on login, old destroyed

### HTTP Security Headers

```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
```

### Password Security

- Stored using `password_hash()` with `PASSWORD_BCRYPT` (cost 10)
- Verified using `password_verify()` — timing-safe
- Never logged, never echoed, never stored in plaintext

---

## 🗄 Database Schema

### `admin_users`

| Column | Type | Description |
|---|---|---|
| `id` | `INT AUTO_INCREMENT PK` | Unique ID |
| `username` | `VARCHAR(50) UNIQUE` | Login username |
| `password` | `VARCHAR(255)` | bcrypt hash |
| `created_at` | `TIMESTAMP` | Account creation time |

### `itineraries`

| Column | Type | Description |
|---|---|---|
| `id` | `INT AUTO_INCREMENT PK` | Unique ID |
| `title` | `VARCHAR(255)` | Itinerary name |
| `destination` | `VARCHAR(255)` | Location |
| `description` | `TEXT` | Overview paragraph |
| `price` | `DECIMAL(12,2)` | Price in INR |
| `duration_days` | `INT` | Trip length |
| `image` | `VARCHAR(255)` | Uploaded filename |
| `highlights` | `TEXT` | JSON array of strings |
| `inclusions` | `TEXT` | JSON array of strings |
| `exclusions` | `TEXT` | JSON array of strings |
| `day_plan` | `TEXT` | JSON array of day objects |
| `status` | `TINYINT(1)` | 1 = Active, 0 = Inactive |
| `created_at` | `TIMESTAMP` | Creation time |
| `updated_at` | `TIMESTAMP` | Last update (auto) |

#### `day_plan` JSON Structure

```json
[
  {
    "day": 1,
    "title": "Arrival & Seminyak",
    "desc": "Airport pickup, beach walk, sunset cocktails",
    "meals": "Dinner",
    "hotel": "4-star Seminyak Resort"
  }
]
```

---

## 🔑 Default Credentials

| Field | Value |
|---|---|
| **Username** | `admin` |
| **Password** | `admin123` |

> ⚠️ **Change this immediately after installation.** Generate a new hash:
>
> ```php
> <?php echo password_hash('your_new_password', PASSWORD_DEFAULT); ?>
> ```
>
> Then update the database:
>
> ```sql
> UPDATE admin_users SET password = 'new_hash_here' WHERE username = 'admin';
> ```

---

## 🔧 Troubleshooting

### Blank page / 500 error

- Check PHP version: `php -v` — must be **8.0+**
- Enable `display_errors` in `config.php` temporarily to see the message
- Verify database credentials in `config.php`

### Database connection failed

- Confirm MySQL is running: `sudo systemctl status mysql`
- Check credentials match your MySQL setup
- Ensure the database `sk_travel_planner` exists: run `database.sql`

### Images not uploading

- Verify `uploads/` directory exists and is writable
- Check `MAX_FILE_SIZE` in `config.php` matches your needs
- Ensure `fileinfo` extension is enabled in `php.ini`:
  ```ini
  extension=fileinfo
  ```

### PDF not downloading

- Confirm FPDF is installed: `fpdf/fpdf.php` must exist
- Download from [fpdf.org](https://www.fpdf.org/) if missing
- Check PHP error log for FPDF-specific errors

### CSRF token errors

- Sessions must be working — check `session.save_path` in `php.ini`
- Cookies must be enabled in the browser
- Do not open multiple tabs of the admin form simultaneously

### Styles look broken

- Ensure `style.css` is in the project root
- Font Awesome CDN must be accessible (check internet connection)
- Google Fonts must be loadable

### Backup file is empty

- Ensure the database has data
- Check MySQL user has `SHOW CREATE TABLE` and `SELECT` privileges
- Verify `sys_get_temp_dir()` is writable

---

## 🎨 Design System

| Token | Value | Usage |
|---|---|---|
| `--primary` | `#0D7377` | Deep teal — buttons, accents |
| `--primary-dark` | `#095558` | Hover states |
| `--accent` | `#E8912D` | Warm orange — CTAs, badges |
| `--accent-dark` | `#C75B2A` | Accent hover |
| `--dark` | `#1B2838` | Headings, navbar, sidebar |
| `--light` | `#F7F3ED` | Page backgrounds |
| `--white` | `#FFFFFF` | Card backgrounds |
| `--success` | `#2E8B57` | Inclusions, active status |
| `--danger` | `#DC3545` | Exclusions, delete buttons |
| Font (Display) | Playfair Display | Headings, prices |
| Font (Body) | Source Sans 3 | Body text, UI |

---

## 📋 Feature Checklist

- [x] Public homepage with itinerary cards
- [x] Detail page with day-wise breakdown
- [x] PDF download per itinerary
- [x] Admin login with bcrypt
- [x] Dashboard with stats
- [x] Create itinerary with image upload
- [x] Edit itinerary with pre-populated form
- [x] Delete itinerary with image cleanup
- [x] Toggle active/inactive status
- [x] Dynamic day-wise plan builder (add/remove days)
- [x] Highlights, inclusions, exclusions as line items
- [x] Database backup with SQL dump download
- [x] CSRF protection on all forms
- [x] XSS prevention on all outputs
- [x] SQL injection prevention via prepared statements
- [x] Secure file upload with MIME/extension/size validation
- [x] Session security (HttpOnly, regeneration)
- [x] Security HTTP headers
- [x] Responsive design (mobile/tablet/desktop)
- [x] Flash messages for user feedback
- [x] Fallback placeholder images via Picsum

---

## 📄 License

This project is provided as-is for educational and commercial use. No warranty expressed or implied. Customize and deploy at your own responsibility.

---

**Built with care by SK Travel Planner Team**
```

This README covers every aspect of the application — from zero-to-running installation, through daily usage, to understanding the full security architecture and troubleshooting edge cases. It is structured for both developers setting up the project and non-technical users managing content through the admin panel.
