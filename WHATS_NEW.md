# What's New in Creative Touch Interiors

## Version 2.2.2 — Project Category Badge UI Polish

This patch release refines the visual styling of project category badges across project cards to seamlessly harmonize with the primary `#0057FF` and `#F8F7F4` glassmorphism architectural design system. The previous heavy dark/black badge styling is replaced with a refined, light translucent glassmorphic pill badge with crisp brand typography.

---

### 🎨 1. Light Glassmorphism Category Badge Styling

- **Light Translucent Glass Surface**: Replaced opaque dark/black badge background with a modern light translucent surface (`rgba(255, 255, 255, 0.88)` / `rgba(0, 87, 255, 0.08)`) with `10px` backdrop blur.
- **Brand Typography**: Category labels now render in the signature `#0057FF` royal blue hue with `700` font weight and crisp architectural uppercase letter spacing.
- **Architectural Pill Geometry**: Clean `999px` fully rounded pill shape with a subtle `1px solid rgba(0, 87, 255, 0.20)` border.
- **Micro-Shadow & Elevation**: Added delicate elevation (`box-shadow: 0 4px 12px rgba(0, 30, 90, 0.08)`) ensuring the badge lifts gently from project photography.
- **Subtle Interaction Hover**: Hovering over the card softly illuminates the badge to `rgba(255, 255, 255, 0.96)` with enhanced border and shadow clarity.

---

### 🖼️ 2. Universal Legibility & Zero Regressions

- **High-Contrast Photography Legibility**: Tested against both bright, high-key renderings and dark, mood-lit architectural scenes to ensure zero text illegibility.
- **All Categories Supported**: Consistently applied to `RESIDENTIAL`, `COMMERCIAL`, `OFFICE`, and `RETAIL` project cards.
- **Preserved Card Dimensions**: No alteration to card layouts, grid systems, images, descriptions, or exploration buttons.
- **Responsive Geometry**: Flawless presentation across desktop, tablet, and mobile breakpoints without text clipping.

---

## Version 2.2.1 — Announcement Bell Interaction Fix

This patch release resolves an issue where clicking the announcement bell button in the public website header did not open the glassmorphism dropdown. It improves interaction reliability, accessibility, and event lifecycle management across all public pages while strictly preserving existing styling and announcement data.

---

### 🔔 1. Announcement Bell Interaction Fix & Dropdown Toggle

- **Root Cause Resolution**: Resolved CSS overflow clipping on `.spatial-nav-container` that prevented dropdown content from displaying below the navigation bar.
- **Reliable Open / Close Toggle**: Clicking the bell opens the dropdown; clicking the bell again closes it without page reloading.
- **Outside-Click Dismissal**: Clicking anywhere outside the dropdown or trigger button safely closes the dropdown menu.
- **Keyboard ESC Key Handling**: Pressing `Escape` smoothly closes the dropdown and returns keyboard focus to the bell button.
- **Unobstructed Link Navigation**: Clicking announcements inside the dropdown opens their respective detail page (`announcement.php?id=...`) without dropdown interference.

---

### ♿ 2. Accessibility & UX Enhancements

- **ARIA Attributes**: Properly configured `aria-controls="spatialAnnouncementDropdown"` and reactive `aria-expanded="false|true"`.
- **Accessible Focus States**: Added `:focus-visible` styling with high-contrast indicator matching the `#0057FF` brand color.
- **Empty State Enhancement**: Displays clear `"No new announcements"` status when no active broadcasts are currently scheduled.
- **Responsive Viewport Alignment**: Dropdown alignment optimized for desktop, tablet, and mobile displays without horizontal scroll overflow.

---

### 🛡️ 3. Unchanged Core Architectures

- **Security & RBAC**: Existing Three-Tier RBAC, CSRF protections, server-side validation, and safe URL sanitization remain completely unchanged.
- **Zero PII Telemetry**: Announcements remain strictly public with zero login requirements and no personal data collection.

---

## Version 2.2.0 — Public Announcement & Promotion System

This release introduces an enterprise-grade Public Announcement & Promotion System natively integrated into the Creative Touch Interiors platform, enabling high-impact visitor communication, promotional offer broadcasting, glassmorphic header interactions, and privacy-preserving anonymous view analytics.

---

### 📢 1. Public Announcement & Promotional Offer System

Broadcast time-sensitive offers, seasonal promotions, and studio updates to all website visitors without login requirements:
- **10% Discount Offers & Festive Packages**: Highlighting tailored discounts across bespoke residential estates, luxury villas, and modular kitchens.
- **New Service & Project Launches**: Promoting new capabilities such as German Modular Kitchen installations and 3D architectural renders.
- **Holiday & Studio Advisories**: Sharing booking schedules, holiday deadlines, and consultation windows.
- **Full Call-To-Action (CTA) Integration**: Directly connecting visitors to the custom quotation calculator (`consultation.php`) or contact form (`contact.php`).

---

### 🌐 2. Dual Front-Facing Interfaces (Announcement Bar & Header Dropdown)

- **Subtle Glassmorphic Announcement Bar**: Fixed top banner with animated badge (`PROMOTION`, `UPDATE`, `NOTICE`), offer validity timeframe, direct "View Details" link, and session-remembered dismissal.
- **Header Announcement Bell & Dropdown (`includes/header.php`)**: A dedicated icon in the spatial navigation with an animated red notification dot and interactive glassmorphic dropdown showcasing recent broadcasts and a quick link to the full catalog.
- **Mobile Navigation Drawer**: Directly accessible from the fullscreen mobile drawer on smartphones and tablets.

---

### 📋 3. Public Announcements Catalog & Dedicated Details

- **Public Announcements Hub (`announcements.php`)**: No login required. Filterable by type (All, Promotions, Updates, Notices), live search input, responsive cards grid, cover images, and validity badges.
- **Announcement Details Page (`announcement.php`)**: Full formatted broadcast copy, validity notices, cover artwork, anonymous share capability, and direct quote consultation CTA.

---

### 📊 4. Anonymous View Analytics (Zero PII Privacy Architecture)

- **Aggregate Telemetry**: Tracks Total Views and Approximate Unique Visitors using salted SHA-256 visitor hashing.
- **Privacy Compliance**: No individual visitor accounts, no read/unread tracking, and zero personal information collected.
- **Admin Insights**: Real-time telemetry displayed on the admin dashboard and within the details modal.

---

### 🛠️ 5. Executive Administration & Preview Workflow

- **Role-Based Authority**: Super Admins and Admins manage the full lifecycle: `Create` &rarr; `Preview` &rarr; `Publish` &rarr; `Edit` &rarr; `Archive` &rarr; `Delete`. Receptionists are strictly restricted to public viewing.
- **Interactive Preview HUD (`admin/announcement_preview.php`)**: Allows administrators to preview the announcement using the exact public design layout before making it live, featuring `[ Back to Edit ]` and `[ Publish Announcement ]` buttons.
- **Security & Integrity**: Session-bound CSRF tokens, strict CTA URL sanitization blocking `javascript:` payloads, prepared SQL queries, and multi-tier image validation.

> **Privacy Architecture Notice**: Announcements are public and do not require visitor login. Read/unread tracking is intentionally not implemented.

---

## Version 1.8.0 — Enterprise Role-Based Access Control (RBAC) & Security Release

This release introduces a three-tier Role-Based Access Control (RBAC) architecture, view-only module protections, staff profile management, and server-side authorization enforcement across the administration suite.

---

### 1. Three-Tier Role-Based Access Control (RBAC)

The system now enforces exactly three distinct administrative roles with server-side validation:

#### 👑 Super Admin (`super_admin`)
- **Full System Authority**: Complete CRUD access to all modules, content, and configuration.
- **Staff & User Management**: Create, edit, role-assign, and deactivate Administrators and Receptionists via `admin/users.php`.
- **Exclusive Modules**: Full control over Editorial Blog (`admin/blog.php`), Studio Team (`admin/team.php`), Website Settings (`admin/settings.php`), and Database Tools.
- **Safety Locks**: Built-in safeguards prevent the active Super Admin from deleting or demoting their own account.

#### 🏢 Admin (`admin`)
- **Business Operations Authority**: Complete management over core business workflows including:
  - Leads CRM (`admin/leads.php`)
  - Contact Inquiries (`admin/contact_inquiries.php`)
  - Design Consultations (`admin/consultations.php`)
  - Quotations & Estimation Engine (`admin/quotes.php`, `admin/quote_details.php`)
  - Portfolio Projects (`admin/projects.php`)
  - Services Catalog (`admin/services.php`)
  - Lookbook Visual Gallery (`admin/gallery.php`)
  - Client Testimonials (`admin/testimonials.php`)
- **Restricted Access**: Blocked from User Management, Editorial Blog, Studio Team, Website Settings, and System Tools.

#### 🛎️ Receptionist (`receptionist`)
- **Front-Desk & Customer Relationship Authority**:
  - **Leads / CRM**: Add and update client leads (deletion blocked).
  - **Contact Inquiries**: View and update inquiry statuses (deletion blocked).
  - **Consultations**: Schedule, edit, and manage appointment statuses (deletion blocked).
  - **Quotations**: Create and view quotation dockets (deletion & internal pricing estimation adjustments blocked).
  - **Projects / Portfolio**: **View-Only** access.
  - **Services**: **View-Only** access.
  - **Visual Gallery**: **View-Only** access.
- **Restricted Access**: Blocked from User Management, Testimonials, Editorial Blog, Studio Team, Website Settings, and System Tools.

---

### 2. View-Only Protections & Server-Side Security

Hiding buttons in the UI is not enough for true security. Version 1.8.0 implements multi-layer authorization:

- **Server-Side Action Guards**: Direct `POST` submissions (create, edit, delete, toggle) to view-only pages (`projects.php`, `services.php`, `gallery.php`) are verified on the server. If a Receptionist sends a request, it is denied without touching the database.
- **Deletion Locks**: Deletion endpoints across Leads, Contact Inquiries, Consultations, and Quotes strictly prohibit Receptionist execution.
- **Estimation Engine Protection**: Only Super Admins and Admins can modify cost multipliers, room rates, and discounts in `admin/quote_details.php`. Receptionists see a dedicated View-Only indicator.
- **Direct URL Redirection**: Attempting to bypass the menu by directly opening restricted pages (`users.php`, `blog.php`, `team.php`, `settings.php`, `testimonials.php`) triggers an immediate redirect back to the dashboard.

---

### 3. Dedicated Staff Profile Management (`admin/profile.php`)

All three roles now have a dedicated staff profile management workspace:
- Accessible directly from the sidebar user capsule.
- Enables staff to view their account metadata, role badge, and registration timestamp.
- Allows staff to update their display name, email address, and account password securely with password verification.

---

### 4. Dynamic Role-Aware Sidebar Navigation

The admin sidebar (`admin/includes/sidebar.php`) automatically updates according to the active user's permissions:
- **Profile Capsule**: Displays the staff member's initial, name, and color-coded role badge (`Super Admin`, `Admin`, `Receptionist`).
- **Dynamic Link Filtering**: Menu items for restricted modules (Users, Blog, Team, Testimonials, Settings) are hidden from roles that lack authorization.
- **Real-Time Badges**: Live counters for unread leads, new quotes, and pending consultations are preserved across all allowed roles.

---

### 5. Database Schema & Self-Healing Migration

- **Database Extension**: The `admin_users.role` column has been extended from `ENUM('admin', 'super_admin')` to `ENUM('admin', 'super_admin', 'receptionist')`.
- **Zero Data Loss**: Existing Super Admin and Admin records were preserved without corruption or conversion.
- **Self-Healing Integration**: Step 8 was added to `includes/system_repair.php` to automatically check and maintain the role column schema on any deployment.

---

### 6. Centralized RBAC Core Helpers (`includes/config.php`)

New helper functions provide consistent authorization across all endpoints:
- `getAdminRole()` — Returns active admin role string.
- `getAdminRoleLabel($role)` — Returns human-readable label (`Super Admin`, `Admin`, `Receptionist`).
- `isSuperAdmin()` — Returns `true` if logged-in user is Super Admin.
- `isAdminRole()` — Returns `true` if logged-in user is Admin.
- `isReceptionist()` — Returns `true` if logged-in user is Receptionist.
- `hasRole($allowedRoles)` — Validates if current admin has one of the specified roles.
- `requireRoles($allowedRoles, $redirectUrl)` — Enforces role requirement with redirect fallback.
- `requireSuperAdmin($redirectUrl)` — Enforces Super Admin requirement.

---

### 7. Verification & Automated Test Suite

An automated test suite (`scratch/test_rbac_full.php`) was executed via PHP CLI:
- **Core Functions**: Validated role helper functions and role labels.
- **Database Schema**: Validated `admin_users.role` ENUM definition and user preservation.
- **Staff Management**: Validated Receptionist creation, role assignment, and rejection of invalid roles.
- **Server Guards**: Validated Receptionist blocks on modifications and deletions.
- **Access Matrix**: Tested 14 administrative endpoints across 3 roles (42 permission scenarios).
- **Result**: **87 / 87 tests passed (100% pass rate)**.

