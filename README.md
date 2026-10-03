# Creative Touch Interiors — v1.6.0

An enterprise-grade interior design, architectural management, and spatial visualization platform engineered with PHP, MySQL, HTML5, CSS3, and JavaScript. Featuring a Three.js 3D spatial visualization engine, an automated room-by-room quotation calculation system, instant downloadable PDF proposals, a self-service client dashboard, and a centralized executive administration suite.

---

## 🌟 Key Highlights & Feature Matrix

### 1. Spatial 3D & Public Presentation Layer
- **Spatial 3D Experience (`js/spatial-3d.js`, `css/spatial-3d.css`)**: Built-in Three.js canvas integrated with GSAP smooth-scroll interactions for responsive, luxury architectural showcases.
- **Dynamic Project Portfolio (`projects.php`, `project.php`)**: Filterable project gallery organized by category (Residential, Commercial, Hospitality, Luxury Living) with comprehensive case-study views.
- **Visual Media Gallery (`gallery.php`)**: High-resolution image showcase with filter tabs and responsive modal preview.
- **Architectural Insights & Design Journal (`blog.php`)**: Content publishing platform covering interior design trends, architectural best practices, and material case studies.
- **Responsive Luxury Aesthetic**: Refined typography, glassmorphism UI elements, micro-animations, and full mobile/tablet/desktop responsiveness.

### 2. Intelligent Quotation & Estimation Engine
- **Room-by-Room Dynamic Calculator (`consultation.php`)**:
  - Allows clients and designers to define multiple rooms with custom dimensions (Length × Width = Sq.Ft.).
  - Selectable room types (Living Room, Master Bedroom, Kitchen, Dining Room, Home Office, Bathroom, etc.).
  - Tiered design packages (**Standard**, **Premium**, **Luxury**) with automatic pricing adjustments.
  - Granular service selection (Civil work, Electrical & Lighting, Modular Woodwork, False Ceiling, Wall Finishes & Painting, Flooring, Soft Furnishings).
  - Material grade multipliers and waste factor calculation for realistic budgetary estimates.
- **Client Quote Review (`quote_details.php`)**: Interactive itemized cost breakdown displaying rooms, applied services, unit rates, sub-totals, and lifecycle status.
- **Instant PDF Quote Export (`quote_pdf.php`)**: Server-rendered, print-ready formal quotation document for offline presentation and client sign-off.

### 3. Client Account & Portal System
- **Authentication & Security (`register.php`, `login.php`)**: Secure registration and session authentication with Bcrypt password hashing.
- **Password Recovery Workflow (`forgot_password.php`, `reset_password.php`)**: Cryptographically secure, time-limited token-based password reset mechanism.
- **Client Profile Dashboard (`profile.php`)**:
  - Real-time tracker for submitted quotation requests with status progression indicators.
  - Active consultation booking status and scheduled on-site appointments.
  - Direct access to contact message histories and proposal downloads.

### 4. Executive Administration Suite (`/admin`)
- **Executive Analytics Dashboard (`admin/dashboard.php`)**: Live business KPIs, monthly quote trends, active leads, scheduled site visits, and recent client inquiries.
- **Quotation Lifecycle Management (`admin/quotes.php`, `admin/quote_details.php`)**:
  - Full status lifecycle: *Pending Review → Site Visit Scheduled → Estimation in Progress → Quote Sent → Approved / Rejected → Project Started*.
  - Ability to adjust room dimensions, add/modify custom line items, modify material multipliers, and apply administrative discounts.
- **Consultation & Lead Management (`admin/consultations.php`, `admin/leads.php`)**: Schedule and track client on-site or virtual design consultations.
- **Inquiry Desk (`admin/contact_inquiries.php`)**: Centralized mailbox to triage, review, and follow up on customer inquiries.
- **Content Management Systems (CMS)**:
  - **Services (`admin/services.php`)**: Manage service catalog, base square-foot rates, unit types, and descriptions.
  - **Portfolio (`admin/projects.php`)**: Manage project showcases, before/after images, and detailed case studies.
  - **Gallery (`admin/gallery.php`)**: Manage media uploads, categories, and display status.
  - **Articles (`admin/blog.php`)**: Create, edit, and publish architectural and design articles.
- **Team & Testimonials (`admin/team.php`, `admin/testimonials.php`)**: Manage public team member profiles, roles, and verified client testimonials.
- **User & Access Control (`admin/users.php`)**: Manage registered client profiles and provision administrative team privileges.
- **Site Configuration (`admin/settings.php`)**: Centralized interface to manage company contact coordinates, office addresses, branding assets, and social profiles.

### 5. Architectural Self-Healing & Integrity Engine
- **Automated Schema Migrations (`includes/quote_db_setup.php`)**: Automatically checks database health, ensures all required quotation tables (`quote_requests`, `quote_rooms`, `quote_services`, `quote_status_history`) exist, and adds missing structural columns dynamically.
- **System Integrity Auto-Repair (`includes/system_repair.php`)**: Verifies system dependencies, seeds essential records, cleans orphaned data, and maintains data consistency seamlessly.
- **Dynamic Base URL Auto-Detection (`includes/config.php`)**: Automatically resolves base application paths across localhost environments, custom virtual hosts, and production domains.

---

## 📁 Project Directory Structure

```
creative-touch-interiors/
├── admin/                         # Executive Administration Suite
│   ├── includes/
│   │   └── sidebar.php            # Administrative sidebar navigation
│   ├── blog.php                   # CMS: Blog article management
│   ├── consultations.php          # Consultation schedule & appointments
│   ├── contact_inquiries.php      # Customer inquiry management
│   ├── dashboard.php              # Analytics overview & KPI reporting
│   ├── gallery.php                # CMS: Visual media management
│   ├── leads.php                  # Lead qualification tracking
│   ├── login.php                  # Administrative login gateway
│   ├── logout.php                 # Administrative session termination
│   ├── projects.php               # CMS: Portfolio projects management
│   ├── quote_details.php          # Quotation review & pricing adjustment
│   ├── quotes.php                 # Quotation lifecycle dashboard
│   ├── services.php               # CMS: Service catalog & rate card
│   ├── settings.php               # System & site configuration
│   ├── team.php                   # CMS: Team member profiles
│   ├── testimonials.php           # CMS: Client review management
│   └── users.php                  # User accounts & privilege control
├── css/
│   ├── spatial-3d.css             # 3D spatial viewport & luxury styling
│   └── style.css                  # Core application stylesheet
├── includes/
│   ├── config.php                 # Database connection, helpers & settings
│   ├── header.php                 # Global public navigation & header
│   ├── footer.php                 # Global public footer & scripts
│   ├── quote_db_setup.php         # Database migration engine for quotes
│   ├── quote_engine.php           # Mathematical quotation pricing logic
│   └── system_repair.php          # Data integrity checks & self-healing
├── js/
│   ├── vendor/                    # Local vendor libraries
│   │   ├── chart.umd.min.js       # Administrative analytics charts
│   │   ├── gsap.min.js            # Animation engine
│   │   ├── ScrollTrigger.min.js   # GSAP scroll interaction plugin
│   │   └── three.min.js           # 3D spatial scene engine
│   ├── script.js                  # Frontend interactions & validation
│   └── spatial-3d.js              # 3D viewport controller & camera rigs
├── uploads/                       # Media uploads (portfolio, quotes, team)
├── about.php                      # About the studio & design philosophy
├── blog.php                       # Design articles & trend spotlights
├── consultation.php               # Dynamic Quote Calculator & Booking
├── contact.php                    # Contact page with interactive form
├── creative_touch_interiors (2).sql # Database schema & seed records
├── forgot_password.php            # Password recovery request
├── gallery.php                    # Portfolio showcase & lightbox gallery
├── index.php                      # Studio homepage with 3D spatial hero
├── login.php                      # Client account login
├── privacy.php                    # Privacy policy
├── profile.php                    # Client portal dashboard & quote history
├── project.php                    # Project case-study detail view
├── projects.php                   # Categorized portfolio catalog
├── quote_details.php              # Client proposal review & approval view
├── quote_pdf.php                  # Instant printable PDF proposal generator
├── register.php                   # Client account registration
├── reset_password.php             # Token-verified password reset
├── services.php                   # Service catalog & pricing guide
├── terms.php                      # Terms of service
├── user_logout.php                # Client session termination
└── VERSION                        # Application release tracker
```

---

## 🛠️ Technology Stack

| Component | Technology | Description |
| :--- | :--- | :--- |
| **Backend** | PHP 7.4+ / PHP 8.x | Modular server-side application logic |
| **Database** | MySQL 5.7+ / MariaDB 10.4+ | Relational schema with transactional integrity |
| **Frontend** | HTML5, CSS3, JavaScript (ES6+) | Modern semantic architecture with Vanilla CSS |
| **3D & Motion** | Three.js & GSAP | Real-time spatial 3D scenes & smooth scroll transitions |
| **Visualization** | Chart.js | Executive KPI dashboards and quote trend metrics |
| **Security** | Bcrypt & Prepared Statements | Cryptographic hashing and SQL injection prevention |

---

## 🚀 Setup & Installation Guide

### Prerequisites
- **Web Server**: Apache / Nginx running within a stack like XAMPP, WAMP, LAMP, or MAMP
- **PHP Version**: PHP 7.4 or higher (PHP 8.0+ recommended)
- **Database**: MySQL 5.7+ or MariaDB 10.4+
- **Enabled PHP Extensions**: `mysqli`, `session`, `json`, `mbstring`, `fileinfo`
- Modern web browser with WebGL support for 3D spatial visualizers

### 1. Database Setup
1. Open your database administration tool (e.g., **phpMyAdmin** at `http://localhost/phpmyadmin`).
2. Create a new database named:
   ```sql
   CREATE DATABASE creative_touch_interiors CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
   ```
3. Import the database dump provided in the project root:
   - File: `creative_touch_interiors (2).sql`
4. *Self-Healing Note*: On initial page load, the built-in system repair scripts in [includes/system_repair.php](file:///c:/xampp/htdocs/project/creative%20touch%20interiors/includes/system_repair.php) and [includes/quote_db_setup.php](file:///c:/xampp/htdocs/project/creative%20touch%20interiors/includes/quote_db_setup.php) automatically verify table schemas and ensure essential records are in place.

### 2. Environment Configuration
Verify your database settings in [includes/config.php](file:///c:/xampp/htdocs/project/creative%20touch%20interiors/includes/config.php):

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_NAME', 'creative_touch_interiors');
```

> **Base URL Auto-Detection**: The application dynamically determines the base path for your environment. Whether hosted on `http://localhost/project/creative%20touch%20interiors/` or a custom virtual host domain, URLs resolve automatically.

### 3. Accessing the Application

- **Public Website & Client Portal**:
  ```text
  http://localhost/project/creative%20touch%20interiors/
  ```
- **Executive Administration Suite**:
  ```text
  http://localhost/project/creative%20touch%20interiors/admin/login.php
  ```

---

## 🔐 Administrative Access & Role-Based Control

The platform features a secure, compartmentalized administration suite designed for team collaboration:

### Role Privileges
- **Super Administrator**: Complete administrative control, platform settings configuration, database integrity management, and administrative user provisioning.
- **Administrator**: Day-to-day operations including quotation review, consultation bookings, project portfolio updates, service catalog management, and client inquiries.

### Access Protocol
- The administrative suite is accessed via the dedicated portal URL: `/admin/login.php`.
- Authorized personnel must log in using their assigned administrative accounts.
- Administrative accounts and permissions are provisioned and managed directly within the **User & Access Control** module (`admin/users.php`).
- For security in production deployments, ensure all team members utilize unique organizational credentials and implement role-appropriate permissions.

---

## 🗄️ Database Schema Overview

| Table Name | Description |
| :--- | :--- |
| `admin_users` | Administrative staff credentials, roles (`super_admin`, `admin`), and status |
| `users` | Registered client profiles, authentication credentials, and metadata |
| `password_resets` | Cryptographic recovery tokens with expiration timestamps |
| `quote_requests` | Quotation submissions, scope details, client contact, and totals |
| `quote_rooms` | Room-by-room dimensions (Length × Width × Sq.Ft.) and room types |
| `quote_services` | Itemized architectural and interior services associated with each quote |
| `quote_status_history` | Audit trail of lifecycle status transitions for quotations |
| `consultations` | Appointment bookings for on-site design evaluations |
| `contact_inquiries` | Inbound communication messages and feedback submitted by visitors |
| `projects` | Portfolio case studies, client stories, categories, and cover images |
| `services` | Service catalog with base rates, units, and category definitions |
| `gallery_images` | Visual media items, categories, and showcase metadata |
| `team_members` | Studio leadership, architects, and interior design specialists |
| `testimonials` | Client feedback, ratings, and verified review entries |
| `website_content` | Dynamic CMS text blocks and landing page content |

---

## 🛡️ Security & Reliability Architecture

- **SQL Injection Defense**: All user inputs and database queries are processed using parameterized `mysqli` prepared statements.
- **Password Protection**: Passwords are saved using industry-standard Bcrypt hashing via `password_hash()`.
- **Cross-Site Scripting (XSS) Mitigation**: Output rendering uses contextual sanitization through `htmlspecialchars()`.
- **Session Integrity**: Session identifiers are regenerated upon authentication (`session_regenerate_id(true)`) to mitigate session fixation attacks.
- **Access Boundary Enforcement**: Administrative routes verify active sessions and RBAC roles before executing sensitive actions.
- **Self-Healing Schema**: Missing columns or required schema adjustments are verified automatically by the backend system.

---

## 👥 Authors & Credits

**Creative Touch Interiors** was designed and developed by:
- **Harsh Ketanbhai Chotaliya**
- **Het D. Rana**

---

## 📄 License & Ownership

Proprietary software developed for **Creative Touch Interiors**. All rights reserved.
