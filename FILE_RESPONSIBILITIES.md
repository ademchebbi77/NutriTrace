# NutriTrace - File Responsibilities Guide

## 🎯 What Each File/Folder Does

### Root Configuration Files

| File | Purpose |
|------|---------|
| `.env` | Environment configuration (database, mail, app settings) |
| `.env.example` | Template for .env file |
| `composer.json` | PHP dependencies (Laravel, packages) |
| `package.json` | JavaScript dependencies (Vite, Bootstrap, jQuery, etc.) |
| `vite.config.js` | Frontend build configuration |
| `artisan` | Command-line interface for Laravel |
| `README.md` | Installation and usage guide |

---

## 📂 app/ - Application Core

### app/Console/Commands/ - Artisan Commands

| File | Command | Purpose |
|------|---------|---------|
| `ExpireCertifications.php` | `certifications:expire` | Runs daily at 1 AM, marks expired certifications, recalculates scores |
| `MakeAdmin.php` | `make:admin` | Creates a new admin user interactively |
| `RefreshScores.php` | `scores:refresh` | Recalculates environmental and trust scores for all lots |
| `VerifyUserEmail.php` | `user:verify {email}` | Manually verifies a user's email (dev tool) |

### app/Enums/ - Enumeration Types

| File | Values | Used For |
|------|--------|----------|
| `AccountStatus.php` | PENDING, APPROVED, REJECTED | User account approval state |
| `CertificationStatus.php` | PENDING, APPROVED, REJECTED, EXPIRED | Certification review state |
| `CertificationType.php` | BIO, COMMERCE_EQUITABLE, LABEL_ROUGE, AOC, AOP, IGP, etc. | Types of certifications |
| `DataSource.php` | MEASURED, DECLARED, CALCULATED | Origin of environmental data |
| `DistributionStatus.php` | SENT, IN_STORE, SOLD_OUT | Distribution state |
| `EventType.php` | production_created, lot_sent, transformation_completed, etc. | Traceability event types |
| `LotStatus.php` | CREATED, IN_TRANSIT, IN_TRANSFORMATION, DISTRIBUTED, IN_STORE, SOLD_OUT, REJECTED, CONSUMED | Lot lifecycle states |
| `ProductionMethod.php` | CONVENTIONAL, ORGANIC, BIODYNAMIC, etc. | Farming methods |
| `ProductStatus.php` | DRAFT, PUBLISHED, ARCHIVED | Product visibility |
| `ReportStatus.php` | SUBMITTED, UNDER_REVIEW, RESOLVED, DISMISSED | Report investigation state |
| `ReportType.php` | FALSE_CLAIM, QUALITY_ISSUE, MISSING_INFO, etc. | Types of consumer reports |
| `TransferStatus.php` | PENDING, ACCEPTED, REJECTED | Lot transfer state |
| `TransportStatus.php` | SCHEDULED, IN_PROGRESS, DELIVERED | Transport state |
| `TransportType.php` | TRUCK, VAN, SHIP, PLANE, TRAIN | Transport modes |
| `Unit.php` | KG, L, UNIT | Product units |
| `UserRole.php` | ADMIN, PRODUCTEUR, TRANSFORMATEUR, DISTRIBUTEUR, CONSOMMATEUR | User roles |

### app/Http/Controllers/ - Request Handlers

#### Admin Controllers (app/Http/Controllers/Admin/)

| File | Routes Prefix | Purpose |
|------|--------------|---------|
| `ApprovalController.php` | `/admin/approvals` | Approve/reject user account registrations |
| `AuditLogController.php` | `/admin/audit-logs` | View admin action history |
| `CategoryController.php` | `/admin/categories` | CRUD for product categories |
| `CertificationReviewController.php` | `/admin/certifications` | Review certification submissions |
| `DashboardController.php` | `/admin/dashboard` | Admin dashboard with statistics |
| `ReportController.php` | `/admin/reports` | Investigate consumer reports |
| `SettingsController.php` | `/admin/settings` | Configure calculation parameters |
| `UserController.php` | `/admin/users` | Manage users (activate/deactivate) |

#### API Controllers (app/Http/Controllers/Api/)

| File | Endpoint | Purpose |
|------|----------|---------|
| `TraceController.php` | `GET /api/v1/trace/{token}` | Public lot trace API (JSON) |

#### Auth Controllers (app/Http/Controllers/Auth/)

Laravel Breeze default authentication controllers:
- `AuthenticatedSessionController.php` - Login/logout
- `RegisteredUserController.php` - Registration
- `PasswordController.php` - Password change
- `PasswordResetLinkController.php` - Forgot password
- `EmailVerificationPromptController.php` - Email verification
- etc.

#### Consumer Controllers (app/Http/Controllers/Consumer/)

| File | Purpose |
|------|---------|
| `AreaController.php` | Consumer-specific dashboard showing nearby products |

#### Producer Controllers (app/Http/Controllers/Producer/)

| File | Purpose |
|------|---------|
| `DashboardController.php` | Producer-specific dashboard with production stats |

#### Public Site Controllers (app/Http/Controllers/PublicSite/)

| File | Route | Purpose |
|------|-------|---------|
| `HomeController.php` | `/` | Homepage with featured products |
| `CatalogController.php` | `/catalog` | Product catalog with filters |
| `TraceController.php` | `/trace/{token}` | Public lot traceability page |
| `CompareController.php` | `/compare` | Compare products side-by-side |
| `FeedbackController.php` | `/feedback` | Contact form |

#### Main Module Controllers

| File | Routes Prefix | Purpose |
|------|--------------|---------|
| `ProductController.php` | `/products` | CRUD for products |
| `ProductionController.php` | `/productions` | Record harvests/productions |
| `LotController.php` | `/lots` | View lots, generate QR codes |
| `TransferController.php` | `/transfers` | Send/receive lots (producer ↔ transformer) |
| `ReceptionController.php` | `/receptions` | Accept/reject incoming lots |
| `TransformationController.php` | `/transformations` | Transform lots into new products |
| `TransportController.php` | `/transports` | Record transport details |
| `DistributionController.php` | `/distributions` | Distribute to stores, record sales |
| `CertificationController.php` | `/certifications` | Manage certifications |
| `ImpactController.php` | `/impacts` | View/update environmental data |
| `LabelController.php` | `/labels` | Generate QR code labels (PDF) |
| `OrganizationController.php` | `/organization` | Edit organization profile |
| `ProfileController.php` | `/profile` | Edit user profile |
| `DashboardController.php` | `/dashboard` | Role-based dashboard redirect |
| `RoleDashboardController.php` | `/producer/dashboard`, etc. | Role-specific dashboards |
| `AccountStatusController.php` | `/account-status` | Pending account info page |

### app/Http/Middleware/

| File | Purpose |
|------|---------|
| `EnsureAccountIsActive.php` | Block access if account is rejected or inactive |
| `EnsureUserHasRole.php` | Restrict routes by role |

### app/Http/Requests/ - Form Validation

#### Admin Requests

| File | Validates |
|------|----------|
| `CategoryRequest.php` | Category creation/update |
| `RejectAccountRequest.php` | Account rejection with reason |

#### Auth Requests

| File | Validates |
|------|----------|
| `LoginRequest.php` | Login credentials |
| `RegisterRequest.php` | Registration (email, password, organization) |

#### Module Requests

| File | Validates |
|------|----------|
| `ProductRequest.php` | Product creation/update |
| `ProductionRequest.php` | Production recording |
| `LotUpdateRequest.php` | Lot expiration date |
| `SendLotRequest.php` | Lot sending (recipient, transport) |
| `RejectHandoverRequest.php` | Lot rejection with reason |
| `TransformationRequest.php` | Transformation (inputs, outputs) |
| `TransportUpdateRequest.php` | Transport details |
| `CertificationRequest.php` | Certification submission |
| `ImpactRequest.php` | Environmental impact data |
| `OrganizationUpdateRequest.php` | Organization profile |
| `ProfileUpdateRequest.php` | User profile |

### app/Http/Resources/

| File | Purpose |
|------|---------|
| `TraceResource.php` | Format lot data for API response |

### app/Models/ - Database Models

| File | Represents | Key Relationships |
|------|------------|-------------------|
| `User.php` | Users (all roles) | has Organization, holds Lots, created Products |
| `Organization.php` | Companies/farms | belongs to User |
| `Category.php` | Product categories | has many Products |
| `Product.php` | Product definitions | belongs to Category, has Productions, Lots, Certifications |
| `Production.php` | Harvest/production events | belongs to Product, Producer; has one Lot |
| `Lot.php` | **CENTRAL MODEL** - Batches | belongs to Product, Production, Transformation; has holder, events, impact, certifications |
| `LotTransfer.php` | Lot transfers | belongs to Lot, from/to Users, has Transport |
| `Transport.php` | Shipments | belongs to Lot, LotTransfer, or Distribution |
| `Transformation.php` | Processing | has input Lots, creates output Lot |
| `TransformationInput.php` | Ingredients | links Lots to Transformations |
| `Distribution.php` | Store distributions | belongs to Lot, sender, distributor |
| `TraceabilityEvent.php` | Event log | belongs to Lot, immutable, hash chain |
| `EnvironmentalImpact.php` | Cached scores | belongs to Lot |
| `Certification.php` | Certifications | polymorphic: Product or Lot |
| `Review.php` | Consumer reviews | belongs to Lot, Product, User |
| `Report.php` | Consumer reports | polymorphic: Product or Lot |
| `AuditLog.php` | Admin actions | tracks who did what |
| `Setting.php` | Dynamic config | key-value settings |

### app/Observers/ - Model Event Listeners

| File | Triggers On | Actions |
|------|------------|---------|
| `ProductionObserver.php` | Production created | Creates Lot, records event, calculates scores |
| `TransformationObserver.php` | Transformation created | Creates output Lot, records events, recalculates scores |
| `TransformationInputObserver.php` | Input added | Updates input lot quantities |
| `TransportObserver.php` | Transport created | Calculates emissions, records event |
| `DistributionObserver.php` | Distribution created | Records event, updates lot status |
| `CertificationObserver.php` | Cert status changed | Recalculates trust scores |
| `ReportObserver.php` | Report created | Logs to audit |

### app/Policies/ - Authorization Rules

| File | Controls Access To |
|------|-------------------|
| `ProductPolicy.php` | Product CRUD operations |
| `ProductionPolicy.php` | Production recording |
| `LotPolicy.php` | Lot viewing, sending, updating |
| `TransformationPolicy.php` | Transformations |
| `TransportPolicy.php` | Transport management |
| `DistributionPolicy.php` | Distributions |
| `CertificationPolicy.php` | Certification management |
| `OrganizationPolicy.php` | Organization profile editing |
| `CategoryPolicy.php` | Category management (admin only) |

### app/Services/ - Business Logic

#### Core Services

| File | Purpose |
|------|---------|
| `UserRegistrar.php` | Handle user registration (consumers auto-approved, professionals pending) |
| `AccountApprovalService.php` | Approve/reject professional accounts, send notifications |
| `ProductService.php` | Product creation, image upload |
| `ProductionService.php` | Record productions, auto-create lots |
| `LotDispatcher.php` | Send lots to other actors (transfers, distributions) |
| `LotReceptionService.php` | Accept/reject incoming lots |
| `TransformationService.php` | Transform input lots into output lot |
| `SaleService.php` | Record sales, update quantities |
| `CertificationService.php` | Submit certifications for review |
| `ReportService.php` | Submit and investigate reports |
| `AuditLogger.php` | Log admin actions |
| `LotNumberGenerator.php` | Generate unique LOT-YYYY-NNN numbers |
| `LotInsights.php` | Calculate lot statistics |
| `LotInsightsBuilder.php` | Build insights from journey |
| `GeoDistance.php` | Haversine distance calculation |
| `SettingsRepository.php` | Get/set dynamic configuration |

#### Scoring Services (app/Services/Scoring/)

| File | Purpose |
|------|---------|
| `FootprintCalculator.php` | Calculate CO2, water, energy, distance; assign grade A-E |
| `TrustScoreCalculator.php` | Calculate transparency score (0-100) with breakdown |
| `GreenwashingWarnings.php` | Detect false claims (local, bio, etc.) |
| `LocalRule.php` | Validate "local" claim (distance ≤ 150km) |
| `LotScoreManager.php` | Update cached scores in environmental_impacts table |

#### Traceability Services (app/Services/Traceability/)

| File | Purpose |
|------|---------|
| `TraceabilityRecorder.php` | Record events with SHA-256 hash chain |
| `ChainVerifier.php` | Verify hash chain integrity |
| `JourneyBuilder.php` | Reconstruct complete product journey |

### app/helpers.php

Global helper functions:
- `format_quantity($quantity, $unit)` - Format quantities with units
- `format_distance($km)` - Format distances
- `grade_color($grade)` - Get Bootstrap color for grade
- `trust_score_color($score)` - Get Bootstrap color for trust score

---

## 📂 config/ - Configuration

| File | Contains |
|------|----------|
| `app.php` | App name, environment, locale |
| `database.php` | Database connections |
| `mail.php` | Email configuration |
| `auth.php` | Authentication guards, providers |
| `footprint.php` | **Environmental calculation factors** |
| `trust.php` | **Trust score weights and penalties** |
| `menu.php` | **Role-based sidebar navigation** |
| `services.php` | Third-party service configs |
| `filesystems.php` | Storage disks (public, private) |

---

## 📂 database/

### database/migrations/

| File | Creates Tables |
|------|---------------|
| `0001_01_01_000000_create_users_table.php` | users, password_reset_tokens, sessions |
| `0001_01_01_000001_create_cache_table.php` | cache, cache_locks |
| `0001_01_01_000002_create_jobs_table.php` | jobs, job_batches, failed_jobs |
| `2026_10_05_000001_create_organizations_table.php` | organizations |
| `2026_10_05_000002_create_audit_logs_table.php` | audit_logs |
| `2026_10_06_000001_create_categories_table.php` | categories |
| `2026_10_06_000002_create_products_table.php` | products |
| `2026_10_06_000003_create_productions_table.php` | productions |
| `2026_10_06_000004_create_lots_table.php` | lots |
| `2026_10_07_000001_create_logistics_tables.php` | transports, lot_transfers, distributions |
| `2026_10_08_000001_create_traceability_events_table.php` | traceability_events |
| `2026_10_09_000001_create_sustainability_tables.php` | certifications, environmental_impacts |
| `2026_10_10_000001_create_community_tables.php` | reviews, reports, settings, favorites, lot_views |

### database/factories/

Model factories for testing - generate fake data for each model

### database/seeders/

| File | Seeds |
|------|-------|
| `DatabaseSeeder.php` | Main seeder - calls all others |
| `UserSeeder.php` | Demo users (admin, producers, transformers, distributors, consumers) |
| `OrganizationSeeder.php` | Demo organizations |
| `CategorySeeder.php` | Product categories |
| `ProductSeeder.php` | Demo products |
| `ProductionSeeder.php` | Demo productions (creates lots via observer) |
| `TransformationSeeder.php` | Demo transformations |
| `TransportSeeder.php` | Demo transports |
| `DistributionSeeder.php` | Demo distributions |
| `CertificationSeeder.php` | Demo certifications |
| `ReviewSeeder.php` | Demo reviews |
| `ReportSeeder.php` | Demo reports |
| `VolumeSeeder.php` | Extended demo data (adds more products, lots, etc.) |

---

## 📂 resources/

### resources/assets/

#### resources/assets/css/

```
css/
├── admin/
│   ├── app.css              # Back office main styles (Bootstrap 4)
│   ├── sb-admin-2.css       # SB Admin 2 template
│   └── sb-admin-2.LICENSE   # Template license
└── public/
    ├── app.css              # Public site main styles (Bootstrap 5)
    ├── landing.css          # Landing Page template
    └── landing.LICENSE      # Template license
```

#### resources/assets/js/

```
js/
├── admin/
│   ├── app.js               # Back office main script (loads jQuery, Bootstrap 4, plugins)
│   └── sb-admin-2.js        # SB Admin 2 scripts
└── public/
    └── app.js               # Public site main script (Bootstrap 5, Leaflet)
```

#### resources/assets/img/

Images used in templates (backgrounds, illustrations, etc.)

### resources/lang/fr/ - French Translations

| File | Translates |
|------|-----------|
| `auth.php` | Authentication messages |
| `pagination.php` | Pagination labels |
| `validation.php` | Validation error messages |
| `certifications.php` | Certification module |
| `distributions.php` | Distribution module |
| `lots.php` | Lot module |
| `products.php` | Product module |
| `productions.php` | Production module |
| `transformations.php` | Transformation module |
| `transports.php` | Transport module |
| etc. |

### resources/views/ - Blade Templates

#### Layout Templates

| File | Used For |
|------|----------|
| `layouts/app.blade.php` | Back office layout (Bootstrap 4, sidebar, topbar) |
| `layouts/public.blade.php` | Public site layout (Bootstrap 5, navbar, footer) |
| `layouts/auth.blade.php` | Authentication pages |

#### Partials (Reusable Components)

| File | Purpose |
|------|---------|
| `partials/sidebar.blade.php` | Back office sidebar navigation (role-based) |
| `partials/topbar.blade.php` | Back office top bar (user menu, notifications) |
| `partials/footer.blade.php` | Back office footer |
| `partials/public-navbar.blade.php` | Public site navigation bar |
| `partials/public-footer.blade.php` | Public site footer |
| `partials/flash.blade.php` | Flash message alerts |
| `partials/timeline.blade.php` | Traceability event timeline |
| `partials/trust-breakdown.blade.php` | Trust score breakdown display |

#### Module Views

Each module has its own folder:

```
views/
├── products/           # index, create, edit, show
├── productions/        # index, create, show
├── lots/              # index, show, send, label
├── transfers/         # sent, received
├── receptions/        # index, show (accept/reject)
├── transformations/   # index, create, show
├── transports/        # index, create, edit, show
├── distributions/     # index, create, show, sales
├── certifications/    # index, create, edit, show
├── impacts/           # show, edit
├── labels/            # qr.blade.php (PDF)
├── organization/      # edit
├── profile/           # edit
└── ...
```

#### Role-Specific Views

```
views/
├── admin/             # approval, audit-logs, categories, certifications, reports, settings, users
├── producer/          # dashboard
├── consumer/          # dashboard, area
└── pages/             # how-it-works
```

#### Public Views

```
views/public/
├── home.blade.php            # Homepage
├── catalog.blade.php         # Product catalog
├── trace.blade.php           # Lot traceability page
├── compare.blade.php         # Product comparison
└── feedback.blade.php        # Contact form
```

---

## 📂 routes/

| File | Contains |
|------|----------|
| `web.php` | Public routes, dashboard redirect |
| `auth.php` | Authentication routes (Laravel Breeze) |
| `api.php` | API routes (`/api/v1/trace/{token}`) |
| `console.php` | Closure-based console commands |
| `modules/products.php` | Product module routes |
| `modules/productions.php` | Production module routes |
| `modules/lots.php` | Lot, transfer, reception, label routes |
| `modules/transformations.php` | Transformation module routes |
| `modules/transports.php` | Transport module routes |
| `modules/distributions.php` | Distribution, sale routes |
| `modules/certifications.php` | Certification module routes |
| `modules/impacts.php` | Environmental impact routes |
| `modules/community.php` | Review, report routes |

---

## 📂 tests/

### tests/Feature/ - Integration Tests

Test complete workflows:
- Authentication (register, login, email verification)
- Account approval (pending, approved, rejected)
- Module workflows (create product → production → send lot → receive → transform → distribute → sell)
- Role-based access
- Public pages

### tests/Unit/ - Unit Tests

Test individual components:
- Services (FootprintCalculator, TrustScoreCalculator, etc.)
- Helpers
- Business logic

### tests/Concerns/

| File | Purpose |
|------|---------|
| `BuildsJourneys.php` | Trait to build complete product journey for testing |

---

## 📂 public/ - Web Root

| Item | Purpose |
|------|---------|
| `index.php` | Application entry point |
| `build/` | Compiled Vite assets (CSS, JS, images) |
| `favicon.ico` | Site favicon |
| `.htaccess` | Apache rewrite rules |

---

## 📂 storage/

```
storage/
├── app/
│   ├── private/              # Private files (certification documents)
│   └── public/               # Public files (product images, logos)
│       └── ... (symlinked to public/storage)
├── framework/                # Framework cache, sessions, views
├── logs/
│   └── laravel.log          # Application logs (includes emails when MAIL_MAILER=log)
└── ...
```

---

## 🔧 Other Important Files

| File | Purpose |
|------|---------|
| `.gitignore` | Files to exclude from Git |
| `.editorconfig` | Editor configuration |
| `.gitattributes` | Git file handling |
| `.npmrc` | npm configuration |
| `phpunit.xml` | PHPUnit testing configuration |
| `bootstrap/app.php` | Bootstrap Laravel application |
| `bootstrap/cache/` | Bootstrap cache |

---

## 🎯 Quick File Finder

**Need to modify...**

| What | Where to Look |
|------|--------------|
| **Environmental calculation factors** | `config/footprint.php` |
| **Trust score weights** | `config/trust.php` |
| **User roles or permissions** | `app/Enums/UserRole.php`, `app/Policies/` |
| **Database schema** | `database/migrations/` |
| **How lots are created** | `app/Observers/ProductionObserver.php`, `app/Observers/TransformationObserver.php` |
| **How lots are sent** | `app/Services/LotDispatcher.php` |
| **How lots are received** | `app/Services/LotReceptionService.php` |
| **How transformations work** | `app/Services/TransformationService.php` |
| **How scores are calculated** | `app/Services/Scoring/` |
| **How traceability works** | `app/Services/Traceability/` |
| **Navigation menu** | `config/menu.php` |
| **Email templates** | Laravel uses notifications, check `app/Notifications/` |
| **Translation strings** | `resources/lang/fr/` |
| **Public homepage** | `resources/views/public/home.blade.php`, `app/Http/Controllers/PublicSite/HomeController.php` |
| **Admin dashboard** | `resources/views/admin/dashboard.blade.php`, `app/Http/Controllers/Admin/DashboardController.php` |
| **API response format** | `app/Http/Resources/TraceResource.php` |

---

This guide should help you navigate the codebase! 🗺️
