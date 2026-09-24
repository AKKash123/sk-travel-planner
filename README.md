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
