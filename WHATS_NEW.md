# What's New in Creative Touch Interiors

## Version 1.6.0 — Enterprise Role-Based Access Control (RBAC) & Security Release

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

Hiding buttons in the UI is not enough for true security. Version 1.6.0 implements multi-layer authorization:

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
- **Migration Script**: Stored in `migrations/add_receptionist_role.sql` for automated deployments.
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
