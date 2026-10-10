# Creative Touch Interiors — Release Notes

This document provides a concise summary of official, verified releases for the **Creative Touch Interiors** platform.

---

## Versioning Policy

Creative Touch Interiors adheres to Semantic Versioning (`MAJOR.MINOR.PATCH`):

| Scope | Format | Trigger Criteria | Examples |
| :--- | :--- | :--- | :--- |
| **MAJOR** | `vX.0.0` | Major architectural changes or breaking core migrations | `v3.0.0` — Core platform framework redesign |
| **MINOR** | `vx.Y.0` | Significant new features or modules added backwards-compatibly | `v2.1.0` — Admin Profile Picture & Avatar System<br>`v2.2.0` — Advanced Dashboard Analytics |
| **PATCH** | `vx.y.Z` | Backwards-compatible bug fixes, security patches, minor UI tweaks | `v2.1.1` — Profile picture caching adjustment |

---

## Release History

### [v2.1.0] — Admin Profile & Avatar System
**Status:** Current Stable Release

#### Summary
Introduced an executive personal avatar and profile picture system across the administration suite, complete with multi-tier upload security, fallback initials generation, and a unified executive top header with profile dropdown.

#### Added
- Self-service profile picture upload, replacement, and removal in `admin/profile.php`.
- Dynamic initials fallback avatar (e.g., `H` for Harsh) styled with `#0057FF` background and white typography.
- Unified executive admin header (`admin/includes/header.php`) with brand emblem, live notifications bell flyout, and profile capsule.
- Interactive profile dropdown menu with role status and direct link to session logout.
- Avatar display integrated across header, sidebar user capsule, and staff directory in `admin/users.php`.
- Full profile picture management enabled for all 3 administrative roles (`super_admin`, `admin`, `receptionist`).

#### Changed
- Enhanced `admin/includes/sidebar.php` to render dynamic uploaded avatars or initials fallback.
- Standardized admin typography hierarchy to preserve white branding on dark navy sidebar.

#### Security
- Multi-tier upload validation: authentic MIME check via `finfo_file()`, image binary verification via `getimagesize()`.
- Strict file format whitelist: JPG, JPEG, PNG, WebP.
- 5 MB maximum file size limit enforced.
- Blocked executable scripts, double-extension payloads (`.php.jpg`), and path traversal attempts.
- Cryptographically secure randomized filenames (`admin_{id}_{token}.{ext}`).
- Old image deleted from disk only after new replacement image is verified and stored in database.
- `.htaccess` script execution prevention verified inside `uploads/profiles/`.
- Session-bound CSRF token validation on all profile mutations.

#### Database
- Added `profile_image VARCHAR(255) NULL DEFAULT NULL` column to `admin_users` table via `includes/system_repair.php`.

#### Testing
- 33 automated tests executed via PHP CLI test suite (33 passed, 0 failed, 100% compliance).
- Linting via `php -l` passed with zero syntax errors on all modified PHP files.

---

### [v2.0.0] — Role-Based Access Control (RBAC)
**Status:** Verified Predecessor Release

#### Summary
Engineered a comprehensive three-tier Role-Based Access Control (RBAC) security framework across the administration suite with server-side request enforcement, view-only module protections, and dynamic menu filtering.

#### Added
- Three distinct administrative roles:
  - `super_admin`: Full CRUD authority across all business modules, staff management, settings, blog, and database tools.
  - `admin`: Operations authority over Leads, Inquiries, Consultations, Quotes, Projects, Services, Gallery, and Testimonials.
  - `receptionist`: Customer service authority with Add/Update permissions for Leads, Inquiries, Consultations, and Quotes; View-Only mode for Projects, Services, and Gallery; deletion actions blocked.
- Centralized RBAC helper library in `includes/config.php` (`getAdminRole()`, `getAdminRoleLabel()`, `hasRole()`, `requireRoles()`, `requireSuperAdmin()`).
- Self-service staff profile management page in `admin/profile.php`.
- Role-aware sidebar menu filtering in `admin/includes/sidebar.php`.

#### Changed
- Upgraded administrative authentication flow in `admin/login.php` to bind user roles directly into sessions.
- Restricted critical deletion buttons and actions in UI based on authenticated role permissions.

#### Security
- Server-side authorization verification on all `POST` action endpoints to prevent client-side bypass.
- Deletion locks preventing Receptionists from deleting Leads, Inquiries, Consultations, or Quotes.
- Quotation estimation parameters and discounts locked against Receptionist adjustments.
- Direct URL access to restricted pages automatically halted with redirect to `dashboard.php`.
- Safety locks preventing the active Super Admin from self-deletion or self-demotion.

#### Database
- Altered `admin_users.role` ENUM to `ENUM('admin', 'super_admin', 'receptionist')` with zero data loss.
- Added self-healing schema migration check into `includes/system_repair.php`.

#### Testing
- 87 / 87 permission and endpoint scenarios verified via automated PHP CLI test suite (100% pass rate).
