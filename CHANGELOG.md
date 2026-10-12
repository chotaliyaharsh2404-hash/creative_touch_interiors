# Creative Touch Interiors — Changelog

All notable changes to the **Creative Touch Interiors** platform are documented in this file.

The project follows [Semantic Versioning](https://semver.org/):
- **MAJOR (`vX.0.0`)**: Major breaking architectural or application changes.
- **MINOR (`vx.Y.0`)**: Significant new features or modules added in a backwards-compatible manner.
- **PATCH (`vx.y.Z`)**: Backwards-compatible bug fixes, security patches, and minor UI enhancements.


## [v2.7.0] — Base Spatial Execution Rate Standardized to ₹500/sq.ft

Base spatial execution rate standardized to ₹500/sq.ft for new quotations while preserving budget-before-GST calculation and historical quotation values.

### Changed
- **Authoritative Base Spatial Execution Rate**: Standardized `BASE_SPATIAL_RATE = 500.00` (₹500/sq.ft) centrally in `includes/config.php` and calculation layer `getBaseSpatialRate()` / `calculateBaseSpatialCost()`.
- **Single Source of Truth**: All new quotation calculations across consultation intake (`consultation.php`), client estimation JS (`window.BASE_SPATIAL_RATE`), client portal (`quote_details.php`), administrative pricing workbench (`admin/quote_details.php`, `admin/quotes.php`), and PDF quotation engine (`quote_pdf.php`) now read from this single authoritative rate.
- **Stage 06 UI Breakdown**: Updated cost review components to clearly display Base Spatial Execution (`<Area> sq.ft × Base rate: ₹500/sq.ft = <Base Spatial Cost>`), Design Level (`<Name> (<Multiplier>×)`), Adjusted Spatial Cost, Selected Capability Modules, Project Cost Before GST, Statutory GST 18%, and Final Payable.
- **Admin Workspace Calculation Transparency**: Admin Quote Details specifications grid and Quotes list display Area, Base Rate, Design Level, Multiplier, Adjusted Spatial Cost, Capability Cost, Project Cost Before GST, GST, Final Payable, and Budget Alignment.
- **PDF Proposal Docket**: Updated `quote_pdf.php` to display Base Spatial Execution (`Area × Base Rate × Multiplier`) and sanitize literal HTML entities (`&times;` → `×`).

### Preserved
- **Historical Quotation Integrity**: Zero recalculation or alteration of existing finalized quotations. All historical quotations retain their original stored `base_rate_per_sqft`, `base_cost`, and `estimated_total`.
- **Pre-GST Budget Rule**: Budget comparison logic strictly compares Project Cost Before GST against selected Budget Maximum. GST remains separate.
- **User-Selectable Multipliers**: Full v2.6.0 design-level system (`Essential Economy 1.00×`, `Signature Premium 1.35×`, `Ultra Palatial 1.75×`) with strict zero-default enforcement and server whitelisting.
- **Capability Pricing & Filtering**: Stage 05 capability module prices, typology filtering, and zero-default selection remain unchanged.
- **Zero Database Schema Migrations**: Fully backwards-compatible; utilizes existing schema without destructive migrations.

## [v2.6.0] — User-Selectable Design Level / Multiplier System & Pre-GST Budget Rule

Implementation of explicit customer design level selection (`Essential Economy — 1.00×`, `Signature Premium — 1.35×`, `Ultra Palatial — 1.75×`) with strictly enforced zero-default selection, authoritative server-side multiplier whitelisting, and financial pre-GST budget comparison rules across the quotation flow (`consultation.php`), quotation engine (`includes/quote_engine.php`), administrator management workspace (`admin/quote_details.php`, `admin/quotes.php`), client portal (`quote_details.php`), and customer quotation PDF (`quote_pdf.php`).

### Added
- **Explicit 3-Tier Design Level Selection in Stage 04**:
  - `Essential Economy — 1.00×`: Value-focused foundational spatial architecture.
  - `Signature Premium — 1.35×`: Curated luxury finishes and enhanced artisan detailing.
  - `Ultra Palatial — 1.75×`: Bespoke architectural execution with palatial materials and custom craftsmanship.
- **Zero-Default Selection Enforcement**: No multiplier is pre-selected by default. The client-side wizard validation prevents proceeding from Stage 04 to Stage 05 until an explicit design level is chosen, highlighting the selection requirement badge.
- **Strict Server-Side Multiplier Whitelist & Authoritative Recalculation**: Server rejects missing or unauthorized arbitrary multipliers (such as 2.50×) and strictly recalculates quotations using validated multipliers `1.00`, `1.35`, or `1.75`.
- **Pre-GST Budget Target Rule**:
  - Authoritative calculation order: `Base Spatial Cost → × selected multiplier → + selected capability costs = Project Cost Before GST → compare against selected Budget Maximum → calculate 18% GST → Final Payable = Project Cost Before GST + GST`.
  - Selected budget is evaluated strictly as a Pre-GST project target (`Project Cost Before GST <= Budget Maximum`). GST is added afterward and does not cause a quote within budget to be falsely flagged as over budget.
- **Real-Time Client Calculation & Stage 06 Review Sync**: Switching design levels instantly re-synthesizes pre-GST costs, tax, final payable, and budget alignment in Stage 06 review.
- **Admin Workspace Design Level & Multiplier Display**: Admin Quote Details displays `Design Level: <Name>` (e.g. Ultra Palatial), `Multiplier: <Value>×` (e.g. 1.75×), and `Project Cost (Pre-GST)` in the specifications grid. Admin quotes table displays customer-friendly tier badges with exact multipliers.
- **Quotation PDF Brand Alignment**: Displays `Design Level: <Name> (<Value>×)` and `Design Level Multiplier` breakdown row in official quotation PDF proposals without exposing technical identifiers.
- **Comprehensive Automated Test Suite (`scratch/test_design_multiplier_system.php`)**: 87 test assertions validating multiplier whitelisting, boundary calculation order, 3×3 matrix across all budget brackets, E2E database submissions, admin details, client portal, and PDF output.

### Preserved
- **Typology Capability Scope System**: Stage 05 capability filtering and opt-in selection (0 selected by default) remain 100% intact from v2.4.0.
- **Zero Database Schema Migrations**: Reused existing `package_type` and `package_multiplier` columns in `quote_requests` with zero breaking schema modifications.
- **RBAC, Security & Existing Quotation Architecture**: Preserved all existing user roles, status history workflows, and mathematical truthfulness.

## [v2.5.0] — Budget-Aware Quotation & Scope Guidance

Comprehensive calculation audit, budget-awareness engine, and transparent scope guidance system across the entire Get a Quote flow (`consultation.php`), quotation engine (`includes/quote_engine.php`), client portal (`quote_details.php`), administrator management workspace (`admin/quote_details.php`, `admin/quotes.php`), and official PDF proposals (`quote_pdf.php`). Resolves budget misalignment transparently without artificial price caps, preserving mathematical truthfulness while providing intelligent scope optimization recommendations.

### Added
- **Authoritative Budget Evaluation Engine (`evaluateQuoteBudget`)**: Centralized backend function in `includes/quote_engine.php` evaluating true mathematical estimates against customer budget windows with sensible thresholds (within budget, near budget within 15%, over budget, below target, unspecified).
- **Budget Range Limit Parser (`parseBudgetRangeLimits`)**: Standardized parser resolving min/max numerical bounds and flexibility flags across all 8 supported budget ranges.
- **Stage 06 Live Architectural Estimation & Budget Alignment Card**: Added real-time mathematical cost breakdown card in Stage 06 Review displaying Selected Budget Target, Estimated Project Cost, Target Variance, Subtotal breakdown, and dynamic Scope Guidance.
- **Dynamic Scope Guidance & Recommendations**: Provides intelligent, data-driven suggestions based on actual selections (potential savings from switching package tiers, staging non-essential capability modules, or phased spatial execution).
- **Client-Side Live Calculation Synthesis (`updateStage6BudgetReview`)**: Synthesizes area, base rates, material multipliers, package multipliers, and selected capability costs directly on the client, matching backend calculations down to the exact rupee without backend duplication.
- **Confirmation Screen Budget Alignment Capsule**: Submission docket screen evaluates budget alignment and provides consultative advisory notes for over-budget selections.
- **Admin Quote Details & Quotes List Alignment Indicators**: Displays budget alignment badges, variances, and target budget comparison rows in admin workspace.
- **Client Portal & Quotation PDF Budget Alignment**: Shows customer estimated budget and alignment badge without altering the authoritative grand investment total or visual brand system.

### Improved
- **Stage 04 Guidance Copy**: Refined explanatory text clarifying that budget selections guide scope tailoring while final estimates depend mathematically on area, finishes, packages, and capabilities.
- **Capability Cards Data Attributes**: Added `data-service-type`, `data-unit-rate`, `data-min-charge`, and `data-wastage` attributes to `.capability-item-card` elements for unified client/server calculation fidelity.
- **Design System Consistency**: Budget status pills (`#ecfdf5` green, `#fffbeb` amber, `#fef2f2` red, `#eff6ff` blue) strictly adhere to Creative Touch Interiors premium glassmorphic visual language.

### Preserved
- **Mathematical Integrity**: Absolutely NO artificial price capping or suppression of calculated totals; quotations remain 100% mathematically and financially truthful.
- **Authoritative Backend**: Central calculation formula in `includes/quote_engine.php` remains authoritative single source of truth.
- **Database Schema**: Reused existing `budget_range` and `estimated_total` columns with zero breaking migrations.
- **Typology Filtering & 0-Default**: Preserved v2.4.0 capability filtering and opt-in selection.

## [v2.4.0] — Smart Typology-Based Capability Scope Selection

Minor feature release upgrading Stage 05 (Capability Scope Selection) of the public Get a Quote flow (`consultation.php`). Dynamically filters design and execution capabilities strictly by project typology (`Residential Estate`, `Commercial / Retail`, `Executive Office`), establishes explicit opt-in selection (0 capabilities preselected by default), adds live counters and scope summaries, and enforces authoritative server-side validation.

### Added
- **Typology-based capability filtering**: Dynamically filters available capabilities based strictly on the Stage 02 selected project typology.
- **Residential capability scope**: Shows only residential-specific modules (IDs 8–15: Complete Home Interior, Modular Kitchen, Living Room, Bedrooms, Villa, etc.) plus 3D Visualization (ID 27).
- **Commercial/Retail capability scope**: Shows only commercial, hospitality, and retail modules (IDs 20–26: Retail Fit-Out, Boutiques, Showrooms, Cafés, Restaurants, Salons) plus 3D Visualization (ID 27).
- **Executive Office capability scope**: Shows only workplace and office modules (IDs 16–19: Corporate Office, Executive Suites, Workstations, Boardrooms) plus 3D Visualization (ID 27).
- **Dynamic selected capability count**: Live badge counter displaying `0 CAPABILITIES SELECTED`, `X CAPABILITIES SELECTED` updating in real-time.
- **Capability summary**: Interactive card (`YOUR CAPABILITY SCOPE`) near the bottom of Stage 05 displaying selected capabilities and counts.
- **Select All / Clear All**: Instant selection actions operating exclusively on currently visible and allowed capabilities for the active typology.
- **Capability search**: Live instant search (`🔍 Search capabilities...`) operating within the active typology set.
- **Contextual recommendation badges**: Non-intrusive `⭐ Recommended` pills based on property layout without forcing pre-selection.
- **Server-side capability compatibility validation**: Authoritative `validateTypologyServices()` engine function enforcing typology compatibility and rejecting incompatible submissions.
- **Stage 06 Review capability integration**: Stage 06 Review card dynamically lists all selected capability modules or turnkey base execution.

### Improved
- **Stage 05 usability**: Replaced confusing 20-module generic checkbox list with responsive, clean 2-column card grid.
- **Capability selection accuracy**: Customers now configure commission scopes tailored directly to their architectural scope.
- **Quote configuration experience**: Interactive glassmorphic cards with blue `#0057FF` active state, hover animations, and subtle glow.
- **Typology change handling**: Switching project typologies automatically invalidates incompatible selections and presents an informative notice banner without clearing unrelated fields.

### Security
- **Server-side typology/capability validation**: Rejects tampered POST submissions attempting to attach capabilities incompatible with selected typology.
- **Existing CSRF protection preserved**: All quote submissions require verified CSRF session tokens.
- **Existing prepared queries preserved**: All database queries use parameterized prepared statements.

### Preserved
- **Existing capability pricing**: Preserved all existing unit rates, min charges, and indicative pricing ranges.
- **Existing quotation calculations**: Zero change to base rates, material multipliers, package pricing, GST (18%), and grand totals.
- **Existing Admin Quotes & Quote Details**: Preserved admin inspection displaying only customer-selected capabilities.
- **Existing quotation PDF**: Quotation docket displays selected itemized capabilities without altering visual formatting.
- **Existing historical quotes**: Full backward compatibility ensuring historical quotes render intact.

---

## [v2.3.1] — Quotation PDF Visual & Brand System Alignment

Patch release delivering a comprehensive visual redesign of the quotation PDF docket (`quote_pdf.php`), seamlessly aligning it with Creative Touch Interiors' primary `#0057FF` brand system and ivory `#F8F7F4` architectural aesthetic.

### Added
- **Creative Touch Interiors Website-Aligned Quotation PDF Design**: Fully transformed the quotation docket to reflect the studio's contemporary architectural identity.
- **Blue #0057FF Brand System**: Integrated brand `#0057FF` accents, architectural diamond emblems, and modern typography (`Plus Jakarta Sans` / `Inter`).
- **Ivory #F8F7F4 Document Styling**: Applied `#F8F7F4` page backdrop with clean, print-friendly white surface cards and subtle blue borders.
- **Modern Card-Based Information Sections**: Redesigned Client Details and Project Parameters into dual rounded glass-inspired cards.
- **Improved Quotation Summary Presentation**: Designed an executive financial breakdown card featuring a prominent `#0057FF` Grand Investment Total highlight.
- **Brand-Consistent Tables and Status Badges**: Upgraded Room Schedule and Itemized Services tables with `#0057FF` headers and soft status pills.

### Fixed
- **Raw `&mdash;` Entity Display**: Fixed entity escaping so project scopes render clean UTF-8 em dashes (e.g., `Residential — Villa / Independent House`) without raw HTML entities.
- **Package Tier Display Mapping**: Replaced raw internal technical identifiers (e.g., `basic (1.00x)`) with canonical customer-friendly labels (`Standard Luxury`, `Signature Premium`, `Ultra Palatial`).
- **Inconsistent Visual Identity**: Eliminated legacy serif fonts (`Playfair Display`), brown/bronze accents (`#8B7355`), and heavy black tables in favor of modern brand-consistent elements.

### Preserved
- **Quotation Calculations & Math**: All base calculations, service fees, package multipliers, promotional discounts, GST, and final investment totals remain 100% intact.
- **Estimated Budget Integration**: Preserved customer-selected Estimated Budget across all 8 tiers and historical quotes.
- **Admin Workflow & Security**: Admin Quotes, Quote Details, RBAC, session guards, and CSRF protections remain unchanged.

---

## [v2.3.0] — Smart Budget & Design Preferences

Minor feature release upgrading Stage 04 of the public Get a Quote flow (`consultation.php`). Enhances budget exploration starting from ₹1 Lakh, introduces selectable visual budget cards, dynamic budget-to-finish guidance, a live design preference summary, and seamless synchronization into Stage 06 Review and executive proposal dockets.

### Added & New Features
- **Budget Window Starting from ₹1 Lakh**: Expanded client accessibility by establishing standard budget tiers starting from ₹1 Lakh rather than the previous ₹10 Lakhs threshold.
- **Eight Visual Budget Cards**: Added standard selectable budget cards: `₹1L – ₹3L`, `₹3L – ₹5L`, `₹5L – ₹10L`, `₹10L – ₹25L`, `₹25L – ₹50L`, `₹50L – ₹1Cr`, `₹1Cr+`, and `"I'm Not Sure Yet"`.
- **"I'm Not Sure Yet" Option**: Added first-class handling for undecided clients without forcing a selection, avoiding artificial ₹0 entries, and rendering supportive consultation guidance.
- **Visual Glassmorphic Budget Card Grid**: Built a multi-column responsive selector utilizing brand `#0057FF` active outlines, glassmorphic card surfaces, and SVG checkmark confirmations.
- **Smart Material-Tier Guidance**: Added real-time non-intrusive recommendation banner linking selected budget range to harmonious finish tiers (`Standard Luxury`, `Signature Premium`, or `Ultra Palatial`) without ever forcing or overwriting user choices.
- **Live Design Preference Summary**: Implemented a compact live preference card at the base of Stage 04 displaying Material Tier, Architectural Style, and Estimated Budget in real time.
- **Review Stage Synchronization**: Connected selected preferences directly into the Stage 06 Review card (`#rev_pkg`, `#rev_style`, `#rev_budget`), displaying `"Not decided yet"` when the client is undecided.

### Changed & Improved
- **Updated Stage 04 Title & Subtitle**: Renamed header to *"Material & Design Preferences"* with subtitle *"Choose your preferred finish level, design style, and budget range."* while keeping the `STAGE 04` eyebrow intact.
- **Refined Material Tier Presentation**: Streamlined tier descriptions removing technical internal jargon ("material multiplier", etc.) and added supporting badges: `Great Value` (Standard Luxury), `Most Popular` (Signature Premium), and `Luxury` (Ultra Palatial).
- **Customer-Friendly Budget Labeling**: Changed *"Budget Window"* to *"Estimated Budget Range"* with explanatory helper text: *"This helps us recommend suitable materials and design solutions."*
- **Quotation PDF Estimated Budget Display**: Updated formal quotation PDF generator (`quote_pdf.php`) to display the customer's selected Estimated Budget in the *Project Parameters* section (`Scope` &rarr; `Design Style` &rarr; `Package Tier` &rarr; `Estimated Budget` &rarr; `Status`), rendering canonical customer-friendly labels without modifying quotation math or totals.
- **Quotation Calculation & Display Synchronization**: Audited and synchronized the quotation calculation breakdown across `quote_details.php`, `admin/quote_details.php`, and `quote_pdf.php`. Added display for studio promotional discounts, package multiplier adjustments, and standard 18% GST calculation matching the authoritative `includes/quote_engine.php` logic.
- **Estimated Budget Integration in Project Specifications**: Added `Estimated Budget` spec tile in `quote_details.php` and admin views using canonical `formatBudgetDisplay()` with complete backward compatibility.
- **Header Overlap & Viewport Spacing Fix**: Resolved fixed/sticky navigation overlap on the quote hero title ("Residential Proposal") by increasing base hero spacing and introducing an active JavaScript observer to dynamically offset the floating navbar across desktop, tablet, and mobile.
- **Strict Server-Side Validation**: Enforced authoritative whitelist validation in `consultation.php` for all new budget options and legacy values while rejecting arbitrary inputs.
- **Full Backward Compatibility**: Historical quotation submissions with previous budget strings remain 100% readable and intact across the client dashboard, admin quotes index, quotation details, and exported quotation PDFs.
- **Accessibility & Keyboard Navigation**: Full compliance with ARIA radiogroups, radio roles, checked states, and Enter/Space keyboard selection.

---

## [v2.2.3] — Room Dimension Calculator UX Polish

Patch release improving the Room Dimension Calculator on the public Get a Quote page (`consultation.php`). Enhances calculation workflow, adds dynamic area summary, refines Apply button behavior, adds clear manual override handling, improves validation, and fixes duplicate plus icons and floating header overlap.

### Improved & Polish
- **Single Primary Add Room Action**: Simplified room creation to a single primary "+ ADD ANOTHER ROOM" action at the bottom of the calculator, eliminating redundant competing buttons.
- **Fixed Duplicate Plus Icon**: Resolved the duplicate plus icon display bug caused by literal "+" text preceding SVG plus icons in button markup.
- **Dynamic Area Summary**: Added an executive Area Summary card presenting real-time room-by-room area breakdown and dynamic grand total.
- **Dynamic Apply Area Action**: Upgraded the Apply button to display the exact live calculated area (e.g., `APPLY 420 SQ.FT →`) and apply values instantly to the carpet area field without reloading.
- **Manual Override Preservation**: Preserved independent manual adjustments on the Total Carpet Area field, adding contextual status pills ("✓ Auto-filled from Room Dimension Calculator" vs "Manually adjusted") and preventing automatic overwrites.
- **Room Dimension Validation**: Added live client-side and server-side validation rejecting non-numeric, 0, or negative dimensions with clean inline error notifications.
- **Decimal Dimension Support**: Enabled calculation for decimal room dimensions (e.g., 12.5 × 10.5 = 131.25 sq.ft).
- **Clear All Reset**: Added a dedicated "CLEAR ALL" action visible when dimensions exist, cleanly resetting the calculator without clearing unrelated form fields.
- **Empty State UX**: Implemented a clean empty state ("No room dimensions added yet. Add a room to calculate your approximate area.") when dimensions are unpopulated.
- **Floating Header Clearance**: Added scroll margin and dynamic navbar height calculation to ensure quotation step headers and instructions are never obscured behind the floating navigation bar.
- **Responsive Stacking**: Engineered room dimension grids and summary breakdowns to stack gracefully on tablet and mobile viewports with zero horizontal overflow.

### Compatibility & Regressions
- **Authoritative Server Validation**: Server-side validation in `includes/quote_engine.php` continues to authoritatively enforce positive numeric dimension constraints.
- **Quotation Form Integrity**: Full quote submission, turnkey estimation multipliers, PDF docket generation, and database insertions remain completely intact.
- **Zero Design System Regressions**: Preserved `#0057FF` primary palette, `#F8F7F4` background, glassmorphism cards, and typography.

---

## [v2.2.2] — Project Category Badge UI Polish

Patch release refining the visual styling of project category badges across project cards to seamlessly align with Creative Touch Interiors' light glassmorphic architectural aesthetic.

### UI Polish & Styling
- **Light Glassmorphic Badges**: Replaced heavy dark/black category badges with a premium light translucent glassmorphism pill badge across all project cards (`.spatial-project-badge-tag`).
- **Surface & Elevation**: Styled with `rgba(255, 255, 255, 0.88)` backdrop, `1px solid rgba(0, 87, 255, 0.20)` border, `10px` backdrop blur, fully rounded pill radius (`999px`), and a subtle architectural shadow (`0 4px 12px rgba(0, 30, 90, 0.08)`).
- **Brand Typography**: Updated badge label color to primary brand `#0057FF` with bold weight (`700`) and refined tracking (`0.06em`).
- **Interactive Micro-Hover**: Added smooth transition state illuminating to `rgba(255, 255, 255, 0.96)` with heightened border accent and soft glow on card interaction.
- **Image Compatibility**: Guaranteed crystal-clear text readability over both dark and high-key architectural photography without requiring large opaque blocks.

### Compatibility & Regressions
- **Universal Category Support**: Seamlessly rendered across all project categories (`RESIDENTIAL`, `COMMERCIAL`, `OFFICE`, `RETAIL`).
- **Zero Layout Shifts**: Preserved card dimensions, grid layouts, card spacing, project images, titles, descriptions, and CTA buttons.
- **Responsive Integrity**: Tested and verified on desktop, tablet, and mobile viewports with no text clipping or horizontal overflow.
- **Architectural Stability**: No changes to database schema, RBAC, header/navigation, footer, or announcement systems.

---

## [v2.2.1] — Announcement Bell Interaction Fix

Patch release fixing an issue where clicking the announcement bell button in the public website header did not open the glassmorphism dropdown.

### Fixed
- **Announcement Bell Click Interaction**: Fixed the public announcement bell button not responding to clicks.
- **Dropdown Open/Close Toggle**: Fixed announcement dropdown opening on bell click and smoothly toggling closed on second click.
- **Outside-Click & Keyboard ESC Closing**: Added robust document click and ESC key handlers to close the dropdown and restore trigger button focus.
- **Interaction Reliability**: Resolved CSS overflow clipping on `.spatial-nav-container`, added DOM ready safety checks, and ensured click events inside dropdown items don't prematurely close menus while preserving link navigation.
- **Data & Pages Preserved**: Preserved existing announcement database records, active seed announcements, and public announcements catalog and detail views.

### UI & Accessibility
- **No Visual Redesign**: Existing #0057FF / #F8F7F4 glassmorphism design preserved.
- **ARIA & Keyboard Accessibility**: Added `aria-controls="spatialAnnouncementDropdown"`, dynamic `aria-expanded="false|true"`, and visible `:focus-visible` styling.
- **Responsive Layout**: Refined dropdown alignment and `max-width` on mobile/tablet viewports.

### Security
- **RBAC**: Existing RBAC remains unchanged (Super Admin and Admin manage; Receptionist view only).
- **CSRF**: Existing CSRF protection remains unchanged.
- **Announcement Security**: Server-side validation, safe CTA sanitization, and prepared queries remain unchanged.

> **Note**: Announcements are public and do not require visitor login. Read/unread tracking is intentionally not implemented.

---

## [v2.2.0] — Public Announcement & Promotion System

Current release introducing a complete Public Announcement & Promotion System natively integrated into the Creative Touch Interiors platform, enabling high-impact visitor communication, promotional offer broadcasting, glassmorphic header interactions, and privacy-preserving anonymous view analytics.

### Added
- **Public Announcement System**: Broadcast seasonal promotions, new architectural services, festival discounts, and studio notices directly to visitors without login requirements.
- **Promotional Offers**: Flexible campaign management with discount notices, validity timeframes, and dedicated call-to-action buttons.
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

### Improved
- **Visitor Communication**: Direct, friction-free discovery of current interior packages and studio offerings.
- **Promotion Visibility**: Multi-touchpoint placement across top notification bar, header bell dropdown, public index, dedicated catalog, and footer.
- **Public Website Engagement**: Clean glassmorphic styling perfectly aligned with the `#0057FF` primary palette, `#F8F7F4` surfaces, and blur mesh aesthetics.
- **Navigation Discoverability**: Added announcements route to mobile drawer and site footer.

### Security
- **RBAC Protection**: Administrative announcement management strictly restricted to `super_admin` and `admin` roles; `receptionist` restricted to public viewing only.
- **CSRF Protection**: All mutations (create, edit, publish, archive, delete) guarded by session-bound CSRF tokens.
- **Server-Side Validation**: Rigorous validation on title, message, dates, priorities, and status values.
- **Safe CTA Sanitization**: Strict blocking of `javascript:`, `data:`, and malicious pseudo-protocols on CTA destination URLs.
- **Secure Image Uploads**: Multi-tier verification enforcing true MIME detection via `finfo_file()`, image binary verification via `getimagesize()`, 5 MB file size limit, and execution-disabled directory rules.
- **Prepared Database Queries**: 100% prepared SQL statements preventing SQL injection attacks.

> **Note**: Announcements are public and do not require visitor login. Read/unread tracking is intentionally not implemented.

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
