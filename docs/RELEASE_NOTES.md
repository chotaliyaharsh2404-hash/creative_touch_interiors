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

### [v2.2.2] — Project Category Badge UI Polish
**Status:** Current Stable Release

#### Summary
Replaces the former heavy dark/black project category badges on project cards with a light, translucent glassmorphism pill badge that seamlessly fits the `#0057FF` brand palette and `#F8F7F4` architectural design language. Features `rgba(255, 255, 255, 0.88)` / `rgba(0, 87, 255, 0.08)` backdrop, `1px solid rgba(0, 87, 255, 0.20)` border, `10px` backdrop blur, primary `#0057FF` text, and smooth hover illumination. Tested for high contrast across all project categories and image backgrounds.

#### UI Polish & Visual Design
- **Light Translucent Glass Badges**: Transitioned `.spatial-project-badge-tag` to a light glass surface with frosted blur.
- **Brand Geometry & Typography**: Pill radius (`999px`) with crisp `#0057FF` typography at `700` font weight.
- **Micro-Shadow & Elevation**: Subtle depth via `0 4px 12px rgba(0, 30, 90, 0.08)` without muddy or dark shadows.
- **Interactive State**: Soft hover transition to `rgba(255, 255, 255, 0.96)` with heightened blue border illumination.
- **Image Legibility**: Crystal-clear visibility across bright and dark photography.

#### Architectural Integrity
- **Zero Redesigns**: Card dimensions, grid system, image ratios, and exploration buttons remain unaltered.
- **All Categories Supported**: Residential, Commercial, Office, Retail.
- **Full Compatibility**: No database changes, RBAC alterations, or regressions.

---

### [v2.2.1] — Announcement Bell Interaction Fix
**Status:** Previous Patch Release

#### Summary
Resolves an issue where clicking the announcement bell button in the public website header did not open the glassmorphism dropdown menu. The fix eliminates CSS overflow clipping on `.spatial-nav-container`, adds robust DOM lifecycle initialization, prevents default link interruptions, handles dropdown outside-clicks and ESC key dismissal, and introduces full ARIA accessibility (`aria-controls`, `aria-expanded`).

#### Fixed
- **Announcement Bell Click Interaction**: Fixed the public announcement bell button not responding to clicks.
- **Dropdown Open/Close Toggle**: Fixed announcement dropdown opening smoothly on bell click and closing on second click.
- **Outside-Click & ESC Key Closing**: Robust closing behavior when clicking outside the dropdown or pressing Escape, with focus returned to trigger button.
- **Interaction Reliability**: Resolved CSS overflow clipping on `.spatial-nav-container`, added DOM ready safety checks, and ensured click events inside dropdown items don't prematurely close menus while preserving link navigation.
- **Data & Pages Preserved**: Preserved existing announcement database records, active seed announcements, and public announcements catalog and detail views.

#### UI & Accessibility
- **No Visual Redesign**: Existing #0057FF / #F8F7F4 glassmorphism design preserved.
- **ARIA & Keyboard Accessibility**: Added `aria-controls="spatialAnnouncementDropdown"`, dynamic `aria-expanded="false|true"`, and visible `:focus-visible` styling.
- **Responsive Layout**: Refined dropdown alignment and `max-width` on mobile/tablet viewports.

#### Security
- **RBAC**: Existing RBAC remains unchanged (Super Admin and Admin manage; Receptionist view only).
- **CSRF**: Existing CSRF protection remains unchanged.
- **Announcement Security**: Server-side validation, safe CTA sanitization, and prepared queries remain unchanged.

> **Note**: Announcements are public and do not require visitor login. Read/unread tracking is intentionally not implemented.

---

### [v2.2.0] — Public Announcement & Promotion System
**Status:** Previous Minor Release

#### Summary
Introduced a comprehensive Public Announcement & Promotion System natively integrated into the Creative Touch Interiors platform, enabling high-impact visitor communication, promotional offer broadcasting, glassmorphic header interactions, and privacy-preserving anonymous view analytics.

#### Added
- **Public Announcement System**: Broadcast promotional offers, festive discounts, new architectural services, and notices to all website visitors without login requirements.
- **Header Announcement Interface (`includes/header.php`)**: Subtle glassmorphism bell icon in header with animated notification dot and drop-down menu showcasing recent broadcasts.
- **Public Announcement Bar (`includes/header.php`)**: Top glassmorphic announcement banner highlighting top urgent or active promotions with dismiss memory.
- **Public Announcements Page (`announcements.php`)**: Responsive catalog of studio announcements featuring filter pills (All, Promotions, Updates, Notices), live search, cover image displays, and validity badges.
- **Announcement Details Page (`announcement.php`)**: Dedicated detail view presenting full broadcast copy, validity notices, cover artwork, anonymous share capability, and direct quote consultation CTA.
- **Anonymous View Analytics**: Privacy-first aggregate metrics tracking total impressions and approximate unique visitors via salted SHA-256 telemetry without capturing PII.
- **Optional Announcement Images**: Secure file upload support for custom promotional graphic banners (.jpg, .jpeg, .png, .webp).
- **Admin Announcement Management (`admin/announcements.php`)**: Executive administrative CRUD workspace featuring real-time telemetry metrics, filter tabs, create/edit modals, and one-click publish/archive controls.
- **Announcement Preview (`admin/announcement_preview.php`)**: Live architectural preview modal and full-page preview HUD with `[ Back to Edit ]` and `[ Publish Announcement ]` controls.
- **Publish / Update / Archive Workflow**: Complete lifecycle progression from Draft &rarr; Preview &rarr; Published &rarr; Archived &rarr; Deleted.
- **What's New Section (`admin/settings.php`)**: Professional application release overview documenting features, architectural improvements, security parameters, and lineage.

#### Improved
- **Visitor Communication**: Direct, friction-free discovery of current interior packages and studio offerings.
- **Promotion Visibility**: Multi-touchpoint placement across top notification bar, header bell dropdown, public index, dedicated catalog, and footer.
- **Public Website Engagement**: Clean glassmorphic styling perfectly aligned with the `#0057FF` primary palette, `#F8F7F4` surfaces, and blur mesh aesthetics.
- **Navigation Discoverability**: Added announcements route to mobile drawer and site footer.

#### Security
- **RBAC Protection**: Administrative announcement management strictly restricted to `super_admin` and `admin` roles; `receptionist` restricted to public viewing only.
- **CSRF Protection**: All mutations (create, edit, publish, archive, delete) guarded by session-bound CSRF tokens.
- **Server-Side Validation**: Rigorous validation on title, message, dates, priorities, and status values.
- **Safe CTA Sanitization**: Strict blocking of `javascript:`, `data:`, and malicious pseudo-protocols on CTA destination URLs.
- **Secure Image Uploads**: Multi-tier verification enforcing true MIME detection via `finfo_file()`, image binary verification via `getimagesize()`, 5 MB file size limit, and execution-disabled directory rules.
- **Prepared Database Queries**: 100% prepared SQL statements preventing SQL injection attacks.

> **Note**: Announcements are public and do not require visitor login. Read/unread tracking is intentionally not implemented.

---

### [v2.1.0] — Admin Profile & Avatar System
**Status:** Previous Release

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
