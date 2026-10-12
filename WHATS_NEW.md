# What's New in Creative Touch Interiors

## Version 2.7.0 — Base Spatial Execution Rate Standardized to ₹500/sq.ft

Base spatial execution rate standardized to ₹500/sq.ft for new quotations while preserving budget-before-GST calculation and historical quotation values.

### KEY HIGHLIGHTS:
- **Authoritative Base Spatial Execution Rate**: Standardized `BASE_SPATIAL_RATE = 500.00` (₹500/sq.ft) centrally in `includes/config.php` and accessed via `getBaseSpatialRate()` and `calculateBaseSpatialCost()`.
- **Single Source of Truth**: All new quotation calculations across consultation intake (`consultation.php`), live estimation JS (`window.BASE_SPATIAL_RATE`), client portal (`quote_details.php`), administrative pricing workbench (`admin/quote_details.php`, `admin/quotes.php`), and PDF quotation engine (`quote_pdf.php`) read from this single authoritative rate.
- **Stage 06 UI Breakdown**: Cost breakdown explicitly itemizes:
  - Base Spatial Execution (`<Area> sq.ft × Base rate: ₹500/sq.ft = <Base Spatial Cost>`)
  - Design Level (`<Name> (<Multiplier>×)`)
  - Adjusted Spatial Cost
  - Selected Capability Modules
  - Project Cost Before GST
  - Statutory GST 18%
  - Final Payable
- **Admin Panel Transparency**: Specifications grid and Quotes list display Area, Base Rate, Design Level, Multiplier, Adjusted Spatial Cost, Capability Cost, Project Cost Before GST, GST, Final Payable, and Budget Alignment.
- **Historical Quotation Preservation**: Historical quotes created under prior rates (₹1,800/sq.ft or ₹1,200/sq.ft) retain their stored historical rates, base costs, and grand totals with zero destructive database updates.
- **Pre-GST Budget Rule Intact**: Budget comparison logic strictly compares Project Cost Before GST against the selected Budget Maximum.

---

## Version 2.6.0 — User-Selectable Design Level / Multiplier System & Pre-GST Budget Rule

This feature release implements an explicit, customer-selected design level system (`Essential Economy — 1.00×`, `Signature Premium — 1.35×`, `Ultra Palatial — 1.75×`) across the entire quotation lifecycle. It enforces zero pre-selected defaults, strict server-side multiplier whitelisting, and financial pre-GST budget comparison rules across the quotation flow (`consultation.php`), quotation engine (`includes/quote_engine.php`), client portal (`quote_details.php`), administrative management (`admin/quote_details.php`, `admin/quotes.php`), and quotation PDF proposals (`quote_pdf.php`).

### KEY HIGHLIGHTS:
- **Explicit 3-Tier Design Level Cards in Stage 04**:
  - **Essential Economy — 1.00×**: Foundational spatial architecture and value-focused execution.
  - **Signature Premium — 1.35×**: Curated luxury finishes, elevated materials, and artisan accents.
  - **Ultra Palatial — 1.75×**: Bespoke architectural execution, rare stones, and grand craftsmanship.
- **Zero Default Multiplier Selection**: Multipliers are never pre-selected by default. Customers must deliberately choose one design level before proceeding from Stage 04 to Stage 05.
- **Authoritative Server-Side Whitelist**: Multipliers submitted via JavaScript are strictly verified. Only `1.00`, `1.35`, and `1.75` are accepted; arbitrary or unauthorized multipliers (such as 2.50×) are rejected.
- **Pre-GST Budget Target Rule**:
  - `Base Spatial Cost → × selected multiplier → + selected capability costs = Project Cost Before GST → compare against selected Budget Maximum → calculate 18% GST → Final Payable = Project Cost Before GST + GST`.
  - Budget comparison targets **Project Cost Before GST** (Subtotal). GST (18%) is calculated afterward so that projects within pre-GST budgets are never falsely marked as over-budget.
- **Immediate Review & Synthesis**: Changing design levels re-calculates the estimate in real time across Stage 06 review.
- **Admin Workspace Details**: Admin Quote Details displays `Design Level: <Name>` (e.g. Ultra Palatial), `Multiplier: <Value>×` (e.g. 1.75×), and `Project Cost (Pre-GST)`.
- **Quotation PDF Proposals**: Customer PDF proposals display customer-friendly Design Level and Multiplier without internal technical jargon.
- **100% Backwards Compatible**: Typology-based capability filtering (v2.4.0) and truth-in-pricing budget evaluation (v2.5.0) remain fully intact.

---

## Version 2.5.0 — Budget-Aware Quotation & Scope Guidance

This major calculation audit and feature release resolves the budget alignment challenge across the entire Get a Quote flow (`consultation.php`), quotation engine (`includes/quote_engine.php`), client portal (`quote_details.php`), administrative quote management (`admin/quote_details.php`, `admin/quotes.php`), and PDF quotation proposals (`quote_pdf.php`). The system preserves mathematical and financial truthfulness without artificially capping or distorting actual costs, while giving customers and administrators full transparency over budget alignment, variance, and data-driven scope recommendations.

### NEW & IMPROVED:
- **Mathematical Truthfulness Guaranteed**: Never artificially reduces or caps calculated estimates. Actual investment figures reflect true architectural reality based on area, finish tiers, package multipliers, and selected capabilities.
- **Authoritative Budget Evaluation Engine (`evaluateQuoteBudget`)**: Centralized calculation engine in `includes/quote_engine.php` comparing true costs against budget targets with sensible thresholds (`Within Budget`, `Slightly Above Budget` [≤ 15%], `Over Selected Budget` [> 15%], and `Budget Not Specified`).
- **Live Estimation & Budget Alignment Card in Stage 06**: Interactive mathematical breakdown displayed in Stage 06 Review before submission, showing Selected Budget, Estimated Project Cost, Target Variance, Cost Components, and dynamic recommendations.
- **Smart Scope Guidance**: Real-time actionable guidance suggesting package tier optimizations (e.g. savings from switching from Ultra Palatial to Signature Premium), staging of non-essential capability modules, and spatial milestone phasing.
- **Client-Side Calculation Synthesis (`updateStage6BudgetReview`)**: Instant recalculation on the client matching the backend engine to the exact rupee without discrepancies.
- **Refined Stage 04 Explanatory Guidance**: Communicates clearly to customers that budget choices guide scope tailoring while final estimates depend mathematically on specifications.
- **Docket Confirmation Capsule**: Submission docket evaluates alignment and adds consultative guidance notes for over-budget selections.
- **Admin Quotes & Details Integration**: Displays budget alignment badges, variances, and target budget comparison rows across admin quote workspaces.
- **PDF Proposal Integration**: Official PDF proposals include budget alignment indicators without altering calculations or breaking v2.3.1 brand styling.

---

## Version 2.4.0 — Smart Typology-Based Capability Scope Selection

This feature release upgrades Stage 05 (Capability Scope Selection) of the public Get a Quote flow (`consultation.php`). It dynamically connects Stage 02's selected project typology (`Residential Estate`, `Commercial / Retail`, or `Executive Office`) with Stage 05 capability options, enforces an explicit 0-preselected default, adds live counters and scope summaries, introduces in-typology search, and secures the workflow with authoritative server-side validation.

### NEW:
- **Typology-Aware Capability Filtering**: Shows only residential capabilities for residential projects, retail/hospitality capabilities for commercial projects, and workplace capabilities for executive office commissions.
- **Explicit Customer Opt-In**: 0 capabilities selected by default. Customers deliberately choose the design and execution modules relevant to their project.
- **Dynamic Typology Stage Headings**: Header titles dynamically shift to `CAPABILITY SCOPE — RESIDENTIAL`, `CAPABILITY SCOPE — COMMERCIAL & RETAIL`, and `CAPABILITY SCOPE — EXECUTIVE OFFICE`.
- **Live Live Selection Counter**: Visual counter badge (`0 CAPABILITIES SELECTED`, `X CAPABILITIES SELECTED`) updating in real time.
- **Scoped Select All & Clear All Controls**: Allows instant bulk selection/clearing exclusively within the allowed typology capabilities.
- **Live In-Typology Capability Search**: Integrated search bar (`🔍 Search capabilities...`) searching only within the allowed typology set.
- **Selected Capability Scope Summary**: Interactive card (`YOUR CAPABILITY SCOPE`) near the bottom of Stage 05 itemizing chosen modules with checkmarks.
- **Typology Switch Protection**: Changing typology in Stage 02 cleanly clears incompatible selections, preserves valid ones, and shows an explanatory notice banner.
- **Stage 06 Review Sync**: Displays itemized capability list in the final review summary before submission.

### SECURITY & VALIDATION:
- **Authoritative Server-Side Validation**: `validateTypologyServices()` verifies that every submitted capability exists, is active, and is strictly compatible with the submitted project typology.
- **Tamper Rejection**: Malicious or inconsistent POST submissions with invalid capability IDs are rejected server-side.

---

## Version 2.3.1 — Quotation PDF Visual & Brand System Alignment

This release delivers a comprehensive visual redesign of the quotation PDF docket (`quote_pdf.php`), bringing it into complete alignment with the Creative Touch Interiors website visual identity and design system (#0057FF primary brand palette, #F8F7F4 ivory document backdrop, modern rounded cards, clean typography, and print-ready aesthetics).

### NEW & IMPROVED:
- **Creative Touch Interiors Website-Aligned Quotation PDF**: Redesigned the quotation proposal docket to mirror the website's clean, modern architectural look and feel.
- **Brand Palette & Emblem Integration**: Featuring primary `#0057FF` accents, architectural diamond emblems, and modern sans-serif typography (`Plus Jakarta Sans` / `Inter`).
- **Modern Card-Based Information Sections**: Client Details and Project Parameters rendered in dual rounded white cards with soft blue borders and status pills.
- **Executive Quotation Summary**: Financial breakdown card with prominent `#0057FF` Grand Investment Total highlight banner.
- **Clean Scope Rendering**: Corrected HTML entity decoding ensuring project scopes render clean UTF-8 em dashes (e.g., `Residential — Villa / Independent House`) without raw `&mdash;` tokens.
- **Customer-Facing Package Tiers**: Automatically translates internal identifiers to canonical customer-friendly labels (`Standard Luxury`, `Signature Premium`, `Ultra Palatial`).
- **Turnkey Milestones & Signatures**: Clean modern 4-stage turnkey payment schedule and dual architectural/client signature verification blocks.
- **Print-Friendly A4 Layout**: Designed for optimal print and PDF output with zero horizontal overflow and clean page break boundaries.

---

## Version 2.3.0 — Smart Budget & Design Preferences

This feature release significantly upgrades Stage 04 of the public Get a Quote flow (`consultation.php`). It transforms budget selection from a basic dropdown starting at ₹10 Lakhs into an accessible, visual, customer-friendly interface starting from ₹1 Lakh, intelligently interconnected with material finishes and live design preferences while preserving the authoritative `#0057FF`, `#F8F7F4`, and glassmorphism design system.

### NEW:
- **Budget Ranges Starting from ₹1 Lakh**: Upgraded the budget entry barrier down to ₹1 Lakh, expanding accessibility for diverse spatial scopes.
- **Eight Discrete Budget Options**: Added `₹1L – ₹3L`, `₹3L – ₹5L`, `₹5L – ₹10L`, `₹10L – ₹25L`, `₹25L – ₹50L`, `₹50L – ₹1Cr`, `₹1Cr+`, and `"I'm Not Sure Yet"` options.
- **"I'm Not Sure Yet" Preference Handling**: Added dedicated option for undecided visitors, safely stored as a distinct state without treating budget as ₹0 or generating fake quotes.
- **Visual Budget Selection Interface**: Replaced standard select dropdown with a responsive, glassmorphic card grid featuring brand `#0057FF` active states and checkmark confirmation.
- **Budget-Based Material-Tier Recommendations**: Dynamic guidance that suggests harmonious finish tiers (e.g. `Standard Luxury`, `Signature Premium`, or `Ultra Palatial`) based on selected budget.
- **Live Design Preference Summary**: Real-time summary card updating instantaneously as the visitor toggles material tiers, architectural styles, or budget preferences.
- **Connected Preferences to Final Review**: Seamlessly synchronized selected material tier, architectural style, and estimated budget range into Stage 06 Review and proposal dockets.

### IMPROVED:
- **Clearer Stage 04 Wording**: Re-titled to "Material & Design Preferences" with the customer-oriented subtitle: *"Choose your preferred finish level, design style, and budget range."*
- **More Customer-Friendly Budget Selection**: Renamed section to "Estimated Budget Range" with clear helper copy: *"This helps us recommend suitable materials and design solutions."*
- **Better Responsive Experience**: Multi-column desktop grid smoothly transitions to comfortable 2-column mobile layout with zero horizontal overflow.
- **Better Relationship Between Budget and Material Tier**: Contextual guidance helps clients discover optimal finishes without restricting or forcing their tier selection.
- **Accessibility & Keyboard Support**: Full ARIA `role="radiogroup"`, `role="radio"`, `aria-checked`, and Enter/Space keyboard selection support.

---

## Version 2.2.3 — Room Dimension Calculator UX Polish

This patch release improves the existing Room Dimension Calculator in the Creative Touch Interiors public Get a Quote / quotation form (`consultation.php`). It provides a clearer, cleaner, and more professional calculation workflow while preserving the established primary `#0057FF`, `#F8F7F4`, and glassmorphism design system.

### IMPROVED:
- **Cleaner Room Dimension Calculator Workflow**: Established a clear visual hierarchy with one primary "+ ADD ANOTHER ROOM" action at the bottom of the room list.
- **Clear Dynamic Area Summary**: Introduced a real-time Area Summary displaying room-by-room area breakdown and dynamic grand total.
- **Improved Apply Area Action**: Enhanced the Apply button to display live calculated values (e.g. `APPLY 420 SQ.FT →`) and seamlessly transfer totals to the main carpet area field without reloading.
- **Better Connection Between Calculated Area and Carpet Area**: Provided a clear, direct transfer between the dimension calculator and the authoritative carpet area input.
- **Added Clearer Manual Override Behavior**: Main carpet area displays contextual status ("✓ Auto-filled from Room Dimension Calculator" vs "Manually adjusted") and prevents accidental calculator overwrites when manually edited.
- **Improved Room Validation**: Added live client-side and server-side validation rejecting non-numeric, 0, or negative dimensions with clear inline error messages.
- **Decimal Values Support**: Enabled accurate calculation for reasonable decimal dimensions (e.g., 12.5 × 10.5 = 131.25 sq.ft).
- **Clear All Functionality**: Added a dedicated "CLEAR ALL" action that resets room dimensions and removes extra rooms while safely preserving other quotation form fields.
- **Fixed Duplicate Room Action Icon**: Eliminated the redundant literal plus prefix from button labels, resolving the "+ + ADD ROOM" / "+ + ADD ANOTHER ROOM" duplicate icon display bug.
- **Improved Responsive Behavior**: Room dimension fields and summary elements stack cleanly on tablet and mobile viewports with zero horizontal overflow.
- **Improved Quotation Form Spacing Around Sticky Header**: Added appropriate top clearance and scroll offset ensuring stage headers, instructions, and calculator inputs never sit underneath the floating/sticky header.

---

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

