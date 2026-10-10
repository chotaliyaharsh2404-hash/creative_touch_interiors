# Creative Touch Interiors — Changelog

All notable changes to the **Creative Touch Interiors** platform are documented in this file.

The project follows [Semantic Versioning](https://semver.org/):
- **MAJOR (`vX.0.0`)**: Major breaking architectural or application changes.
- **MINOR (`vx.Y.0`)**: Significant new features or modules added in a backwards-compatible manner.
- **PATCH (`vx.y.Z`)**: Backwards-compatible bug fixes, security patches, and minor UI enhancements.

---

## [v2.1.0] — Admin Profile & Avatar System

Current stable release introducing a personal administrative profile picture and avatar management system, unified executive header, interactive profile dropdown, and multi-tier upload security.

### Added
- **Admin Profile Picture System**: Self-service profile picture management allowing administrators to upload, replace, and remove avatar images.
- **Dynamic Fallback Avatar**: Automatic initials fallback badge (e.g. `H` for Harsh) rendered with `#0057FF` background and white typography when no custom image is uploaded or if an image fails to load.
- **Unified Executive Admin Header (`admin/includes/header.php`)**: Reusable SaaS header component containing brand emblem, interactive notifications bell with unread count flyout, administrative role badge, and profile capsule.
- **Interactive Profile Dropdown**: Accessible menu triggered by clicking the avatar/name capsule, featuring profile overview, quick links to account settings/security, and session termination via `logout.php`.
- **Sidebar Avatar Integration (`admin/includes/sidebar.php`)**: User profile capsule updated to display the uploaded circular avatar or initial badge.
- **Staff Management Avatar Display (`admin/users.php`)**: Staff directory table in User Management now showcases profile pictures for each team member.
- **Role Support**: Profile picture capabilities fully available across all three administrative roles: `super_admin`, `admin`, and `receptionist`.

### Security
- **MIME & Image Validation**: Multi-layer verification utilizing `finfo_file()` for authentic MIME detection and `getimagesize()` to verify true image binaries.
- **Strict Format Whitelist**: Whitelist restricted to JPG, JPEG, PNG, and WebP formats.
- **File Size Ceiling**: Enforced 5 MB maximum file size limit via upload error codes and physical disk inspection.
- **Extension & Payload Guards**: Rejection of dangerous executable extensions, shell scripts, null-byte injections, and double-extension payloads (e.g., `shell.php.jpg`).
- **Cryptographic Naming**: Uploaded files renamed using cryptographically secure random tokens (`admin_{id}_{token}.{ext}`) to eliminate original filename vulnerabilities.
- **Path Traversal Protection**: Validation preventing directory traversal (`../`) and illegal stream wrappers in file resolution and deletion routines.
- **Safe Replacement Order**: Old profile pictures are removed from disk only after the replacement image is verified and saved to the database.
- **Directory Execution Guard**: Uploads stored in `uploads/profiles/`, protected by `.htaccess` rules disabling PHP and CGI engine execution.
- **CSRF Protection**: All profile and avatar form submissions protected by session-bound CSRF tokens.

### Database
- Added `profile_image VARCHAR(255) NULL DEFAULT NULL` column to `admin_users` table via migration step 9 in `includes/system_repair.php`.

### UI/UX
- **Design Alignment**: Clean, modern aesthetics utilizing the authoritative design tokens: Primary Blue (`#0057FF`), Main Background (`#F8F7F4`), White cards (`#FFFFFF`), and dark charcoal typography (`#0F172A`).
- **Header Avatar**: Circular avatar (42px) with `2px solid #0057FF` border, subtle shadow, and hover scaling.
- **Profile Card (`admin/profile.php`)**: Dedicated hero profile card displaying a 108px avatar, name, email, role badge, and account creation date.
- **Upload Modal**: Dedicated modal dialog with drag-and-drop file selector, instant client-side preview, and file removal option.
- **Responsive Adaptations**: Header avatar remains visible across mobile and tablet viewports while text labels collapse smoothly.

### Testing
- **33 Automated Tests Passed**: Executed comprehensive verification suite covering database schema, initial generation, path traversal guards, script rejections, payload handling, file replacements, deletions, and all three RBAC roles.
- **0 Failures**: 100% test pass rate.
- **Syntax Validation**: All modified PHP files passed `php -l` linting with zero syntax errors.

---

## [v2.0.0] — Role-Based Access Control (RBAC)

Major security and access control release establishing a three-tier Role-Based Access Control (RBAC) framework across the executive administration suite.

### Added
- **Three-Tier Administrative Roles**:
  - `super_admin` (Super Admin): Full CRUD authority across all business modules, user management, editorial blog, studio team, website settings, and database tools.
  - `admin` (Admin): Operational management authority over Leads, Inquiries, Consultations, Quotations & Estimations, Projects, Services, Gallery, and Testimonials. Restricted from user management, blog, team, settings, and system tools.
  - `receptionist` (Receptionist): Front-desk customer relationship access with Add & Update capabilities for Leads, Inquiries, Consultations, and Quotes. Strict **View-Only** mode for Projects, Services, and Visual Gallery. Restricted from all deletion actions, user management, testimonials, blog, team, and settings.
- **Central RBAC Helper Library (`includes/config.php`)**:
  - `getAdminRole()`: Retrieves active admin role from session.
  - `getAdminRoleLabel($role)`: Returns human-readable role labels.
  - `isSuperAdmin()`, `isAdminRole()`, `isReceptionist()`: Role inspection helpers.
  - `hasRole($allowedRoles)`: Checks if authenticated admin matches allowed roles.
  - `requireRoles($allowedRoles, $redirectUrl)`: Server-side authorization gatekeeper with redirect handling.
  - `requireSuperAdmin($redirectUrl)`: Dedicated Super Admin route protection.
- **Staff Profile Workspace (`admin/profile.php`)**: Dedicated interface for administrative staff across all roles to view account details and update credentials.
- **Dynamic Role-Aware Sidebar Navigation (`admin/includes/sidebar.php`)**: Sidebar dynamically displays allowed navigation links, role badges, and live unread counters based on active permissions.

### Security
- **Server-Side Action Guards**: Direct `POST` submissions to view-only pages (`projects.php`, `services.php`, `gallery.php`) are validated on the server and blocked for unauthorized roles.
- **Deletion Locks**: Deletion endpoints across Leads, Contact Inquiries, Consultations, and Quotes strictly prohibit execution by Receptionists.
- **Estimation Engine Protections**: Quotation multiplier adjustments, room unit rate edits, and discounts in `admin/quote_details.php` locked to Super Admins and Admins.
- **Direct URL Redirection**: Direct navigation attempts to restricted endpoints automatically redirect unauthorized users back to `dashboard.php`.
- **Self-Deletion & Demotion Locks**: Active Super Admins prevented from deleting or demoting their own accounts.

### Database
- Expanded `admin_users.role` column from `ENUM('admin', 'super_admin')` to `ENUM('admin', 'super_admin', 'receptionist')`.
- Integrated self-healing schema migration check into `includes/system_repair.php` (Step 8) with zero data loss for existing users.

### Testing
- Automated test suite verified 87 / 87 permission scenarios across 14 administrative endpoints and 3 roles (100% pass rate).
