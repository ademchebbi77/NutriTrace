# NutriTrace - Complete Project Overview

## 🎯 Project Purpose

**NutriTrace** is a food traceability platform that allows anyone to track a product's journey from farm to plate, understand its environmental footprint, and verify if its claims (organic, local, fair trade) are genuine.

> *"Where does this product come from, what happened to it, what is its impact, and can we trust what it claims?"*

## 🏗️ Technical Stack

- **Backend**: Laravel 13 (PHP 8.3+)
- **Database**: MySQL 8 / MariaDB 10.4+
- **Frontend**: Blade templates, Vite, Bootstrap 4 & 5
- **JavaScript Libraries**: jQuery, Chart.js, DataTables, Leaflet (maps)
- **CSS**: Custom CSS based on Start Bootstrap templates
- **Authentication**: Laravel Breeze
- **QR Codes**: SimpleSoftwareIO Simple QR Code

## 📁 Project Structure

### Core Application Folders

```
NutriTrace/
├── app/                          # Application core
│   ├── Console/Commands/         # Artisan commands
│   │   ├── ExpireCertifications.php    # Daily job to expire old certifications
│   │   ├── MakeAdmin.php               # Create admin users
│   │   ├── RefreshScores.php           # Recalculate all lot scores
│   │   └── VerifyUserEmail.php         # Manually verify emails
│   │
│   ├── Enums/                    # Enumeration types for status/types
│   │   ├── AccountStatus.php           # PENDING, APPROVED, REJECTED
│   │   ├── CertificationStatus.php     # PENDING, APPROVED, REJECTED, EXPIRED
│   │   ├── CertificationType.php       # BIO, COMMERCE_EQUITABLE, LABEL_ROUGE, etc.
│   │   ├── LotStatus.php               # CREATED, IN_TRANSIT, IN_TRANSFORMATION, etc.
│   │   ├── ProductStatus.php           # DRAFT, PUBLISHED, ARCHIVED
│   │   ├── TransferStatus.php          # PENDING, ACCEPTED, REJECTED
│   │   ├── TransportStatus.php         # SCHEDULED, IN_PROGRESS, DELIVERED
│   │   ├── TransportType.php           # TRUCK, VAN, SHIP, PLANE, TRAIN
│   │   ├── Unit.php                    # KG, L, UNIT
│   │   └── UserRole.php                # ADMIN, PRODUCTEUR, TRANSFORMATEUR, DISTRIBUTEUR, CONSOMMATEUR
│   │
│   ├── Http/
│   │   ├── Controllers/          # Request handlers
│   │   │   ├── Admin/                  # Admin panel controllers
│   │   │   │   ├── ApprovalController.php        # Approve/reject user accounts
│   │   │   │   ├── AuditLogController.php        # View audit logs
│   │   │   │   ├── CategoryController.php        # Manage categories
│   │   │   │   ├── CertificationReviewController.php  # Review certifications
│   │   │   │   ├── DashboardController.php       # Admin dashboard
│   │   │   │   ├── ReportController.php          # Handle user reports
│   │   │   │   ├── SettingsController.php        # Calculation settings
│   │   │   │   └── UserController.php            # Manage users
│   │   │   │
│   │   │   ├── Api/                    # API endpoints
│   │   │   │   └── TraceController.php           # Public lot trace API
│   │   │   │
│   │   │   ├── Auth/                   # Authentication
│   │   │   ├── Consumer/               # Consumer-specific views
│   │   │   ├── Producer/               # Producer-specific views
│   │   │   ├── PublicSite/             # Public website controllers
│   │   │   │   ├── CatalogController.php         # Product catalog
│   │   │   │   ├── CompareController.php         # Compare products
│   │   │   │   ├── FeedbackController.php        # Contact form
│   │   │   │   ├── HomeController.php            # Homepage
│   │   │   │   └── TraceController.php           # Public lot trace page
│   │   │   │
│   │   │   ├── CertificationController.php       # Manage certifications
│   │   │   ├── DistributionController.php        # Distribution management
│   │   │   ├── LotController.php                 # Lot management
│   │   │   ├── OrganizationController.php        # Organization profiles
│   │   │   ├── ProductController.php             # Product CRUD
│   │   │   ├── ProductionController.php          # Production management
│   │   │   ├── TransferController.php            # Lot transfers
│   │   │   ├── TransformationController.php      # Product transformation
│   │   │   └── TransportController.php           # Transport management
│   │   │
│   │   ├── Middleware/
│   │   │   ├── EnsureAccountIsActive.php         # Block inactive accounts
│   │   │   └── EnsureUserHasRole.php             # Role-based access
│   │   │
│   │   └── Requests/             # Form validation
│   │       ├── Admin/
│   │       ├── Auth/
│   │       ├── CertificationRequest.php
│   │       ├── ImpactRequest.php
│   │       ├── LotUpdateRequest.php
│   │       ├── ProductionRequest.php
│   │       ├── ProductRequest.php
│   │       ├── SendLotRequest.php
│   │       ├── TransformationRequest.php
│   │       └── TransportUpdateRequest.php
│   │
│   ├── Models/                   # Database models
│   │   ├── AuditLog.php               # Tracks admin actions
│   │   ├── Category.php               # Product categories
│   │   ├── Certification.php          # Bio, organic, fair trade certifications
│   │   ├── Distribution.php           # Distributions to stores
│   │   ├── EnvironmentalImpact.php    # CO2, water, energy footprint
│   │   ├── Lot.php                    # CENTRAL MODEL - batch of products
│   │   ├── LotTransfer.php            # Transfer between actors
│   │   ├── Organization.php           # Company/farm details
│   │   ├── Product.php                # Product definitions
│   │   ├── Production.php             # Production records
│   │   ├── Report.php                 # User reports/complaints
│   │   ├── Review.php                 # Consumer reviews
│   │   ├── Setting.php                # Configuration settings
│   │   ├── TraceabilityEvent.php      # Blockchain-like event log
│   │   ├── Transformation.php         # Product transformations
│   │   ├── TransformationInput.php    # Input lots for transformations
│   │   ├── Transport.php              # Transport records
│   │   └── User.php                   # User accounts
│   │
│   ├── Observers/                # Model event listeners
│   │   ├── CertificationObserver.php   # Recalculate trust score on cert changes
│   │   ├── DistributionObserver.php    # Create traceability events
│   │   ├── ProductionObserver.php      # Create lot on production
│   │   ├── ReportObserver.php          # Log report creation
│   │   ├── TransformationObserver.php  # Create output lot, log events
│   │   ├── TransformationInputObserver.php
│   │   └── TransportObserver.php       # Log transport events
│   │
│   ├── Policies/                 # Authorization rules
│   │   ├── CategoryPolicy.php
│   │   ├── CertificationPolicy.php
│   │   ├── DistributionPolicy.php
│   │   ├── LotPolicy.php
│   │   ├── OrganizationPolicy.php
│   │   ├── ProductionPolicy.php
│   │   ├── ProductPolicy.php
│   │   ├── TransformationPolicy.php
│   │   └── TransportPolicy.php
│   │
│   ├── Services/                 # Business logic
│   │   ├── Scoring/                    # Scoring algorithms
│   │   │   ├── FootprintCalculator.php        # Environmental impact
│   │   │   ├── GreenwashingWarnings.php       # Detect false claims
│   │   │   ├── LocalRule.php                  # Local product validation
│   │   │   ├── LotScoreManager.php            # Update cached scores
│   │   │   └── TrustScoreCalculator.php       # Transparency score
│   │   │
│   │   ├── Traceability/               # Blockchain-like traceability
│   │   │   ├── ChainVerifier.php              # Verify event integrity
│   │   │   ├── JourneyBuilder.php             # Build product journey
│   │   │   └── TraceabilityRecorder.php       # Record events with hash
│   │   │
│   │   ├── AccountApprovalService.php  # Approve/reject accounts
│   │   ├── AuditLogger.php             # Log admin actions
│   │   ├── CertificationService.php    # Certification management
│   │   ├── GeoDistance.php             # Haversine distance calculation
│   │   ├── LotDispatcher.php           # Send lots to actors
│   │   ├── LotInsights.php             # Lot statistics
│   │   ├── LotNumberGenerator.php      # Generate LOT-YYYY-NNN
│   │   ├── LotReceptionService.php     # Accept/reject lot reception
│   │   ├── ProductionService.php       # Create productions
│   │   ├── ProductService.php          # Product management
│   │   ├── ReportService.php           # Handle reports
│   │   ├── SaleService.php             # Record sales
│   │   ├── SettingsRepository.php      # Dynamic settings
│   │   ├── TransformationService.php   # Transform lots
│   │   └── UserRegistrar.php           # User registration
│   │
│   └── helpers.php               # Global helper functions
│
├── config/                       # Configuration files
│   ├── footprint.php             # Environmental calculation factors
│   ├── menu.php                  # Role-based navigation menus
│   └── trust.php                 # Trust score weights
│
├── database/
│   ├── factories/                # Model factories for testing
│   ├── migrations/               # Database schema
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 0001_01_01_000001_create_cache_table.php
│   │   ├── 0001_01_01_000002_create_jobs_table.php
│   │   ├── 2026_10_05_000001_create_organizations_table.php
│   │   ├── 2026_10_05_000002_create_audit_logs_table.php
│   │   ├── 2026_10_06_000001_create_categories_table.php
│   │   ├── 2026_10_06_000002_create_products_table.php
│   │   ├── 2026_10_06_000003_create_productions_table.php
│   │   ├── 2026_10_06_000004_create_lots_table.php
│   │   ├── 2026_10_07_000001_create_logistics_tables.php  # Transports, transfers, distributions
│   │   ├── 2026_10_08_000001_create_traceability_events_table.php
│   │   ├── 2026_10_09_000001_create_sustainability_tables.php  # Certifications, impacts
│   │   └── 2026_10_10_000001_create_community_tables.php  # Reviews, reports
│   │
│   └── seeders/                  # Demo data
│       ├── DatabaseSeeder.php           # Main seeder
│       ├── VolumeSeeder.php             # Extended demo data
│       └── ... (various seeders)
│
├── resources/
│   ├── assets/                   # Frontend assets (processed by Vite)
│   │   ├── css/
│   │   │   ├── admin/                  # Bootstrap 4 for back office
│   │   │   └── public/                 # Bootstrap 5 for public site
│   │   ├── img/                        # Images
│   │   └── js/
│   │       ├── admin/                  # Back office JavaScript
│   │       └── public/                 # Public site JavaScript
│   │
│   ├── lang/fr/                  # French translations
│   │   ├── auth.php
│   │   ├── certifications.php
│   │   ├── distributions.php
│   │   ├── lots.php
│   │   ├── products.php
│   │   ├── productions.php
│   │   ├── transformations.php
│   │   ├── transports.php
│   │   └── ...
│   │
│   └── views/                    # Blade templates
│       ├── admin/                      # Admin panel views
│       ├── auth/                       # Login, register, password reset
│       ├── certifications/             # Certification management
│       ├── consumer/                   # Consumer-specific views
│       ├── distributions/              # Distribution views
│       ├── impacts/                    # Environmental impact views
│       ├── labels/                     # QR code labels
│       ├── layouts/                    # Master layouts
│       │   ├── admin.blade.php               # Back office layout (Bootstrap 4)
│       │   ├── auth.blade.php                # Auth pages layout
│       │   └── public.blade.php              # Public site layout (Bootstrap 5)
│       ├── lots/                       # Lot management views
│       ├── organization/               # Organization profile
│       ├── pages/                      # Static pages
│       ├── partials/                   # Reusable components
│       │   ├── flash.blade.php               # Flash messages
│       │   ├── footer.blade.php              # Back office footer
│       │   ├── public-footer.blade.php       # Public footer
│       │   ├── public-navbar.blade.php       # Public navigation
│       │   ├── sidebar.blade.php             # Back office sidebar
│       │   ├── timeline.blade.php            # Event timeline
│       │   ├── topbar.blade.php              # Back office top bar
│       │   └── trust-breakdown.blade.php     # Trust score details
│       ├── producer/                   # Producer-specific views
│       ├── productions/                # Production views
│       ├── products/                   # Product management views
│       ├── profile/                    # User profile
│       ├── public/                     # Public website
│       │   ├── catalog.blade.php             # Product catalog
│       │   ├── compare.blade.php             # Product comparison
│       │   ├── feedback.blade.php            # Contact form
│       │   ├── home.blade.php                # Homepage
│       │   ├── how-it-works.blade.php        # Methodology page
│       │   └── trace.blade.php               # Lot traceability page
│       ├── receptions/                 # Lot reception views
│       ├── transformations/            # Transformation views
│       └── transports/                 # Transport views
│
├── routes/
│   ├── api.php                   # API routes (public trace endpoint)
│   ├── auth.php                  # Authentication routes
│   ├── console.php               # Artisan commands
│   ├── web.php                   # Web routes (public + dashboard)
│   └── modules/                  # Module-specific routes
│       ├── certifications.php
│       ├── community.php               # Reviews, reports
│       ├── distributions.php
│       ├── impacts.php
│       ├── lots.php
│       ├── productions.php
│       ├── products.php
│       ├── transformations.php
│       └── transports.php
│
├── tests/                        # 144 tests
│   ├── Feature/                  # Integration tests
│   └── Unit/                     # Unit tests
│
├── public/                       # Public web root
│   ├── build/                    # Compiled Vite assets
│   ├── favicon.ico
│   └── index.php                 # Application entry point
│
└── storage/
    ├── app/
    │   ├── private/              # Private files (certification docs)
    │   └── public/               # Public files (product images, logos)
    └── logs/                     # Application logs
```

## 🔄 Core Data Flow

### The LOT is the Central Object

Everything in NutriTrace revolves around **lots** (batches):

```
Product → Production → Lot → Transformation → Transport → Distribution → Sale
                        ↓
          Certifications, Environmental Impact, Traceability Events, Reviews, Reports
```

### Key Workflows

#### 1. **Production Flow** (Producer)
```
Create Product → Record Production → Lot Auto-Created → Add Certifications → Send to Transformer/Distributor
```

#### 2. **Transformation Flow** (Transformer)
```
Receive Lot(s) → Create Transformation → New Output Lot Created → Send to Distributor
```

#### 3. **Distribution Flow** (Distributor)
```
Receive Lot → Put in Store → Record Sales → Lot Status: SOLD_OUT
```

#### 4. **Consumer Flow**
```
Scan QR Code → View Lot Trace Page → See Complete Journey → Check Environmental Impact → Read Reviews
```

## 🎯 Key Features

### 1. **Traceability System** (Blockchain-like)
- Every event creates a `TraceabilityEvent` with SHA-256 hash of previous event
- Immutable chain - any tampering is detected
- Complete journey reconstruction from farm to consumer
- Events: `production_created`, `lot_sent`, `lot_received`, `transformation_completed`, etc.

### 2. **Environmental Scoring**
**Footprint Calculation** (`FootprintCalculator`)
- **CO2 emissions**: production energy + fertilizers + transport
- **Water usage**: declared or calculated
- **Energy consumption**: electricity, fuel
- **Distance traveled**: calculated via Haversine formula

**Grading**: A (best) to E (worst) based on thresholds

### 3. **Trust Score** (0-100 points)
- **Complete Journey** (25 pts): origin known, geolocated, documented transport
- **Verified Actors** (20 pts): organizations verified by admin
- **Valid Certifications** (20 pts): bio, organic, fair trade with documents
- **Environmental Data** (20 pts): all 6 indicators provided
- **Chain Integrity** (15 pts): hash chain intact

**Penalties**:
- -5 pts per expired certification
- -10 pts per rejected certification
- -5 pts per open report
- -5 pts per date/location inconsistency

### 4. **Greenwashing Detection**
- **Local Rule**: Distance ≤ 150km from farm to sale
- **Warning**: "Local" claim with >250km distance
- Certification validation (must not be expired/rejected)
- Automatic alerts on product trace page

### 5. **Multi-Role System**

| Role | Permissions |
|------|------------|
| **Admin** | Approve accounts, review certifications, manage categories, view audit logs |
| **Producteur** (Producer) | Create products, record productions, send lots, add certifications |
| **Transformateur** (Transformer) | Receive lots, transform into new products, send outputs |
| **Distributeur** (Distributor) | Receive lots, put in store, record sales |
| **Consommateur** (Consumer) | View traces, write reviews, report issues, bookmark products |

### 6. **Account Approval System**
- Professionals register with organization details
- Account status: PENDING → (Admin reviews) → APPROVED/REJECTED
- Email notification on approval/rejection
- Inactive accounts cannot access back office

### 7. **Certification Management**
- Types: BIO, COMMERCE_EQUITABLE, LABEL_ROUGE, AOC, AOP, IGP, etc.
- Upload supporting documents (stored privately)
- Admin review: PENDING → APPROVED/REJECTED
- Auto-expiration based on expiry date
- Command: `php artisan certifications:expire` (runs daily at 1 AM)

### 8. **Public API**
```
GET /api/v1/trace/{public_token}
```
Returns JSON with:
- Lot details
- Complete journey (farm to store)
- Environmental impact
- Trust score
- Certifications
- Warnings

Rate limit: 60 requests/minute

## 🗄️ Database Schema Overview

### Core Tables

**users**
- Authentication & roles
- Organization membership
- Account status (pending/approved/rejected)

**organizations**
- Company/farm details
- Geographic coordinates
- Verification status

**categories** → **products** → **productions** → **lots**
- Categories: Fruits, Vegetables, Dairy, Meat, etc.
- Products: Olive Oil, Cheese, Tomatoes, etc.
- Productions: Harvest/production records
- Lots: Batches with quantity, status, holder

**lot_transfers**
- Sender → Receiver
- Status: PENDING/ACCEPTED/REJECTED
- Transport link

**transports**
- Transport details (mode, distance, emissions)
- Linked to transfers/distributions

**transformations**
- Input lots (via `transformation_inputs`)
- Output lot
- Transformation details

**distributions**
- Lot sent to distributor
- Put in store date
- Sales records

**traceability_events**
- Immutable event log
- SHA-256 hash chain
- JSON data payload

**environmental_impacts**
- CO2, water, energy, distance
- Grade (A-E)
- Data sources (measured/declared/calculated)

**certifications**
- Polymorphic: attached to Product or Lot
- Type, issuer, expiry date
- Document storage
- Status (pending/approved/rejected/expired)

**reviews** & **reports**
- Consumer feedback
- Report investigation

## 🎨 Frontend Architecture

### Two Separate UI Systems

**Back Office** (Bootstrap 4 + jQuery)
- Template: SB Admin 2 (MIT license)
- Used by: All authenticated users
- Features: Dashboards, tables, charts, forms
- Entry points: `resources/assets/{css,js}/admin/app.*`

**Public Site** (Bootstrap 5)
- Templates: Landing Page + Shop Homepage/Item (MIT license)
- Used by: Public visitors
- Features: Product catalog, trace pages, comparison
- Entry points: `resources/assets/{css,js}/public/app.*`

### JavaScript Libraries (via npm)
- **jQuery** (back office only)
- **Chart.js**: Environmental impact charts
- **DataTables**: Sortable tables
- **Leaflet**: Interactive maps
- **Bootstrap Icons** & **Font Awesome**: Icons
- **Fonts**: Nunito, Lato (via @fontsource)

### Vite Build System
4 entry points in `vite.config.js`:
```javascript
'resources/assets/css/admin/app.css'
'resources/assets/js/admin/app.js'
'resources/assets/css/public/app.css'
'resources/assets/js/public/app.js'
```

Build: `npm run build`
Dev: `npm run dev`

## 🧪 Testing

**144 tests** covering:
- Authentication & roles
- Account approval workflow
- All modules (products, lots, transformations, etc.)
- Complete transfer workflow
- Observer event creation
- Journey reconstruction
- Hash chain integrity
- Footprint calculation
- Trust score calculation
- Greenwashing warnings
- Certification expiration
- Public pages & API

Run tests: `php artisan test`
Database: SQLite in-memory (auto-configured)

## 🛠️ Important Services

### Scoring Services

**FootprintCalculator**
- Calculates CO2, water, energy, distance
- Aggregates from production through transformation and transport
- Assigns grade A-E

**TrustScoreCalculator**
- 5 categories, 100 points total
- Returns score + explanation breakdown

**GreenwashingWarnings**
- Checks "local" claim validity
- Verifies certification status
- Detects expired/rejected certifications

**LotScoreManager**
- Updates cached scores in `environmental_impacts` table
- Triggered by observers on data changes

### Traceability Services

**TraceabilityRecorder**
- Single entry point for recording events
- Calculates SHA-256 hash: `hash_sha256(previous_hash + event_data + timestamp)`
- Ensures immutability

**ChainVerifier**
- Verifies integrity of hash chain
- Returns tampering detection

**JourneyBuilder**
- Reconstructs complete product journey
- Follows transformation tree backwards to farms
- Returns structured journey data

### Business Services

**LotDispatcher**
- Sends lot to another actor
- Creates transfer/distribution + transport
- Updates lot status to IN_TRANSIT

**LotReceptionService**
- Accept/reject incoming lot
- Updates lot holder on acceptance
- Returns lot to sender on rejection

**TransformationService**
- Validates input lots (same holder)
- Creates transformation record
- Auto-creates output lot via observer
- Marks input lots as consumed

**SaleService**
- Records sales
- Updates lot quantity
- Marks as SOLD_OUT when quantity = 0

## 📋 Demo Data

### Demo Accounts (all password: `password`)

| Email | Role | Organization |
|-------|------|--------------|
| admin@nutritrace.test | Admin | - |
| producteur@nutritrace.test | Producer | Domaine Chaâl, Sfax (olives) |
| transformateur@nutritrace.test | Transformer | Huilerie Sidi Mansour, Sfax |
| distributeur@nutritrace.test | Distributor | Marché Vert Tunis |
| consommateur@nutritrace.test | Consumer | - |

Plus 20+ additional accounts (producteur2-9, transformateur2-5, distributeur2-5, consommateur2-8)

### Sample Data
- 23 products
- 57 lots
- 300+ traceability events
- 19 certifications (verified, pending, expired, rejected)
- 60+ reviews
- 9 reports

### Interesting Lots to Explore

| Lot Number | Description |
|------------|-------------|
| LOT-2025-006 | Olive oil - reference journey: 5,000kg olives → 900L oil → 270km transport to Tunis |
| LOT-2025-003 | Table olives - "local" claim contradicted by distance, report under investigation |
| LOT-2026-013 | Béja cheese - truly local, from transformation |
| LOT-2026-004 | Tomatoes - "bio" claim without valid certificate, rejected cert |
| LOT-2026-008 | Thyme honey - perfect trust score, measured data |
| LOT-2026-002 | Milk - lot in transit, awaiting transformer reception |

Load demo data: `php artisan migrate:fresh --seed`
Add extended data: `php artisan db:seed --class=VolumeSeeder`

## 🔧 Useful Commands

| Command | Purpose |
|---------|---------|
| `php artisan serve` | Start development server |
| `npm run dev` | Watch and compile frontend assets |
| `npm run build` | Build production assets |
| `php artisan migrate:fresh --seed` | Reset DB with demo data |
| `php artisan test` | Run all tests |
| `php artisan make:admin` | Create admin user |
| `php artisan user:verify {email}` | Manually verify email |
| `php artisan certifications:expire` | Expire old certifications |
| `php artisan scores:refresh` | Recalculate all scores |
| `php artisan schedule:work` | Run scheduler (certification expiration) |

## 🌍 Localization

- Default locale: French (`fr`)
- Translation files: `lang/fr/*.php`
- All user-facing text is translatable
- Date formats: French standard

## 🔐 Security Features

- Email verification required
- Role-based authorization (Policies)
- CSRF protection
- Password hashing (bcrypt)
- Private file storage for certification documents
- Rate limiting on API (60 req/min)
- Audit logging for admin actions
- Hash chain integrity verification

## 📊 Calculation Settings

### Editable via Admin Panel
- Emission factors (CO2 per mode of transport)
- Environmental thresholds (best/worst values)
- Trust score weights
- Local distance threshold (default: 150km)

Settings stored in `settings` table, override `config/footprint.php` and `config/trust.php`

## 🎓 Educational Purpose

> **Important**: Emission factors are simplified pedagogical values, not certified carbon footprints. They provide orders of magnitude for learning purposes.

## 📝 License

- Laravel: MIT
- Start Bootstrap templates: MIT (licenses in `resources/assets/css/`)
- Custom code: Team project

## 🚀 Module Distribution (Team Project)

| Team Member | Modules | Route Files |
|-------------|---------|-------------|
| Member 1 | Product, Production | `products.php`, `productions.php` |
| Member 2 | Lot (transfers, QR labels), Transformation | `lots.php`, `transformations.php` |
| Member 3 | Transport, Distribution (sales) | `transports.php`, `distributions.php` |
| Member 4 | Certification, Environmental Impact | `certifications.php`, `impacts.php` |
| Member 5 | Traceability, Reviews/Reports, Public site | `community.php`, public routes |

Each module has: routes, controller, request, policy, service, views, translations, tests

---

**NutriTrace** empowers consumers to make informed food choices by providing transparent, verifiable information about product origins, environmental impact, and sustainability certifications. 🌱
