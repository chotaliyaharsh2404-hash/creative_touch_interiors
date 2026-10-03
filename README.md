# Creative Touch Interiors — v1.6.0

A premier, enterprise-grade interior design and architectural management platform built with PHP, MySQL, HTML5, CSS3, and JavaScript. Featuring a Three.js 3D spatial visualization engine, an automated room-by-room quotation calculator, instant PDF quote generation, a client customer portal, and a comprehensive executive administration suite.

---

## 🌟 Key Highlights & Features

### 1. Public & Client-Facing Portal
- **Spatial 3D Experience**: Integrated Three.js and GSAP smooth-scroll spatial animations for modern luxury aesthetics.
- **Dynamic Quote Estimator (`consultation.php`)**: Multi-room calculator supporting customizable dimensions (length × width), room types, design packages (Standard / Premium / Luxury), material tiers, and real-time cost computation.
- **Client Authentication & Portal (`register.php`, `login.php`, `profile.php`)**:
  - Secure client registration, login, and password reset workflow (`forgot_password.php`, `reset_password.php`).
  - Interactive client profile dashboard to monitor quotation statuses, consultation schedules, and personal inquiries.
- **Quote Details & Approvals (`quote_details.php`)**: Detailed breakdown of quotation line items, room specifications, and direct client status tracking.
- **Instant PDF Quote Export (`quote_pdf.php`)**: High-fidelity printable / downloadable formal interior design proposals.
- **Architectural Blog & Design Insights (`blog.php`)**: Published design articles, trend spotlights, and spatial design case studies.
- **Portfolio & Project Showcase (`projects.php`, `project.php`)**: Categorized project gallery with detailed case study views.
- **Interactive Visual Gallery (`gallery.php`)**: Filterable media showcase with responsive lightbox.

### 2. Executive Administration Suite (`/admin`)
- **Executive Analytics Dashboard (`admin/dashboard.php`)**: Real-time business KPIs, quote request volume, recent inquiries, scheduled consultations, and project metrics.
- **Quote Management (`admin/quotes.php`, `admin/quote_details.php`)**: Complete quote lifecycle workflow (Review, Site Visit Scheduling, Itemized Estimation, Cost & Multiplier Adjustments, Quote Sent, Approval, Project Started).
- **Consultation & Lead Management (`admin/consultations.php`, `admin/leads.php`)**: Booking schedule tracking and consultation status updates.
- **Inquiry Desk (`admin/contact_inquiries.php`)**: Triage and manage customer contact messages.
- **Content & Service Management (`admin/services.php`, `admin/projects.php`, `admin/gallery.php`, `admin/blog.php`)**: Full CRUD operations for service catalog, portfolio projects, gallery images, and blog posts.
- **Team & Testimonials (`admin/team.php`, `admin/testimonials.php`)**: Manage leadership profiles and client reviews.
- **User & Access Control (`admin/users.php`)**: Manage registered client accounts and administrative privileges (`super_admin` / `admin`).
- **Site Settings (`admin/settings.php`)**: Centralized branding, contact coordinates, and social handles.

### 3. Architecture & Self-Healing Engine
- **Automated Schema Migrations (`includes/quote_db_setup.php`)**: Automatically verifies and creates required quotation tables (`quote_requests`, `quote_rooms`, `quote_services`, `quote_status_history`) and adds missing columns dynamically.
- **System Integrity & Self-Repair (`includes/system_repair.php`)**: Auto-seeds missing team members, verifies default reviews, synchronizes quote site visits, and purges orphaned legacy data.
- **Dynamic Base URL Auto-Detection**: Eliminates hardcoded URL bugs across local XAMPP subdirectories, virtual hosts, and production domains.

---

## 📁 Project Structure

```
Creative Touch Interiors/
├── admin/                         # Executive Administration Suite
│   ├── blog.php                   # Blog article management
│   ├── consultations.php          # Consultation schedule management
│   ├── contact_inquiries.php      # Contact message inbox
│   ├── dashboard.php              # Analytics & KPI overview
│   ├── gallery.php                # Gallery media management
│   ├── leads.php                  # Legacy lead tracking
│   ├── login.php                  # Admin secure login
│   ├── logout.php                 # Admin session termination
│   ├── projects.php               # Project portfolio CRUD
│   ├── quote_details.php          # Detailed quotation manager
│   ├── quotes.php                 # Quotation lifecycle dashboard
│   ├── services.php               # Service catalog management
│   ├── settings.php               # Global site configuration
│   ├── team.php                   # Team & leadership management
│   ├── testimonials.php           # Client review management
│   ├── users.php                  # User accounts & administration
│   └── includes/
│       └── sidebar.php            # Admin unified navigation sidebar
├── css/
│   ├── spatial-3d.css             # Spatial 3D UI & luxury theme styles
│   └── style.css                  # Core global stylesheet
├── includes/
│   ├── config.php                 # Database credentials, dynamic URLs & helper utilities
│   ├── header.php                 # Global public header & navigation
│   ├── footer.php                 # Global public footer
│   ├── quote_db_setup.php         # Automated database schema migration for quotes
│   ├── quote_engine.php           # Mathematical quote pricing calculation engine
│   └── system_repair.php          # Automated integrity check & seeder
├── js/
│   ├── script.js                  # Global front-end scripts & interactions
│   ├── spatial-3d.js              # Three.js & GSAP animation controller
│   └── vendor/                    # Local vendor libraries
│       ├── chart.umd.min.js       # Charting engine for admin dashboards
│       ├── gsap.min.js            # GreenSock animation platform
│       ├── ScrollTrigger.min.js   # GSAP scroll interaction plugin
│       └── three.min.js           # 3D spatial graphics library
├── uploads/                       # User uploads (projects, quotes, team, blog)
├── about.php                      # About Us & company philosophy
├── blog.php                       # Design articles & news
├── consultation.php               # Dynamic Quote Calculator & Consultation request
├── contact.php                    # Contact page with interactive form
├── creative_touch_interiors (2).sql # Complete database SQL schema & seed dump
├── forgot_password.php            # Password recovery initiation
├── gallery.php                    # Visual showcase & lightbox gallery
├── index.php                      # Homepage with 3D hero & feature highlights
├── login.php                      # Client account login
├── privacy.php                    # Privacy policy
├── profile.php                    # Client portal dashboard & quote tracker
├── project.php                    # Single project case study detail
├── projects.php                   # Categorized portfolio gallery
├── quote_details.php              # Client quotation review & acceptance view
├── quote_pdf.php                  # Instant printable PDF quote generator
├── register.php                   # Client account registration
├── reset_password.php             # Secure password reset handler
├── services.php                   # Service catalog & pricing guide
├── terms.php                      # Terms of service
├── user_logout.php                # Client session logout
└── VERSION                        # Project version tracker (v1.6.0)
```

---

## 🚀 Setup & Installation Guide

### Prerequisites
- **XAMPP / WAMP / LAMP / MAMP** with:
  - **PHP 7.4+** or **PHP 8.0+** (PHP 8.x recommended)
  - **MySQL 5.7+** or **MariaDB 10.4+**
  - Enabled PHP extensions: `mysqli`, `session`, `json`, `mbstring`, `fileinfo`
- Modern web browser (Chrome, Firefox, Safari, Edge)

### 1. Database Setup
1. Start **Apache** and **MySQL** in your XAMPP Control Panel.
2. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
3. Create a new database named `creative_touch_interiors` with collation `utf8mb4_general_ci`.
4. Import the provided SQL file:
   - File: `creative_touch_interiors (2).sql`
5. *(Optional)* On first access, the system's self-healing migrations in `includes/system_repair.php` and `includes/quote_db_setup.php` will automatically verify table integrity and seed necessary records.

### 2. Database Connection Configuration
Open [includes/config.php](file:///c:/xampp/htdocs/project/creative%20touch%20interiors/includes/config.php) to confirm or modify credentials:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'creative_touch_interiors');
```

> **Base URL Note:** `BASE_URL` is automatically detected dynamically based on the current server host and directory path. Manual configuration is typically not required.

### 3. Accessing the Platform

- **Public Website & Client Portal**:
  `http://localhost/project/creative%20touch%20interiors/`
- **Admin Management Suite**:
  `http://localhost/project/creative%20touch%20interiors/admin/login.php`

---

## 🔐 Administrative Access

The default database includes administrative user accounts configured in `admin_users`:

| Username | Role | Privileges |
| :--- | :--- | :--- |
| `harsh` | Super Admin | Full administrative access, user management, and system settings |
| `het` | Admin | Content, project, quote, and inquiry management |

> 🔒 **Security Notice**: Always change default passwords after production deployment via the Admin Portal.

---

## 🗄️ Database Tables Overview

| Table Name | Description |
| :--- | :--- |
| `admin_users` | Administrator accounts and role-based permissions (`super_admin`, `admin`) |
| `users` | Registered client accounts for the client portal |
| `password_resets` | Cryptographic tokens for password recovery |
| `quote_requests` | Detailed quotation requests, budget tiers, and overall estimate totals |
| `quote_rooms` | Multi-room dimension breakdowns (Length × Width × SqFt) per quote |
| `quote_services` | Itemized services, unit rates, wastage %, and material multipliers |
| `quote_status_history` | Audit trail of status transitions for quotes |
| `consultations` | Scheduled on-site or virtual client appointments |
| `contact_inquiries` | Direct inquiries submitted via the contact form |
| `projects` | Portfolio projects, descriptions, categories, and cover images |
| `services` | Service catalog with base rates, units, and categories |
| `gallery_images` | Gallery media assets and categorization tags |
| `team_members` | Leadership and architectural staff profiles |
| `testimonials` | Verified client reviews and project associations |
| `website_content` | Dynamic CMS text blocks and landing page content |

---

## 🛡️ Security Implementations

- **Parameterized Queries**: All database operations utilize `mysqli` prepared statements to prevent SQL Injection.
- **CSRF Protection**: Form submissions validate anti-CSRF session tokens.
- **Password Security**: Client and admin passwords use standard `password_hash()` (Bcrypt).
- **Session Protection**: Session regeneration upon login (`session_regenerate_id(true)`) prevents session fixation attacks.
- **Upload Safety**: Secure upload directories with script execution prevention (`.htaccess`).
- **Input Sanitization**: Multi-layer sanitization with `htmlspecialchars()` to prevent Cross-Site Scripting (XSS).

---

## 📄 License & Ownership

Proprietary software developed for **Creative Touch Interiors**. All rights reserved.


make by harsh ketanbhai  chotaliya and het d. rana 
