# NutriTrace - Key Concepts Quick Reference

## 🎯 The LOT is Everything

The **Lot** (batch) is the heart of NutriTrace. Everything revolves around it:

```
A LOT is a batch of products that:
- Has a unique number (LOT-2026-013)
- Has a public QR code token
- Tracks quantity, status, and current holder
- Records its complete journey
- Calculates environmental impact
- Has a trust/transparency score
```

## 🔄 Lot Lifecycle

```
┌─────────────┐
│  CREATED    │  ← Lot is born from Production or Transformation
└──────┬──────┘
       │
       ├→ Send to Transformer/Distributor
       ↓
┌─────────────┐
│ IN_TRANSIT  │  ← Lot is on the road
└──────┬──────┘
       │
       ├→ Receiver accepts
       ↓
┌─────────────┐
│   CREATED   │  ← Back at rest with new holder
└──────┬──────┘     (or IN_TRANSFORMATION if at transformer)
       │
       ├→ Transformation (creates new lot)
       │  OR
       ├→ Distribution to store
       ↓
┌─────────────┐
│ DISTRIBUTED │
└──────┬──────┘
       │
       ├→ Put in store
       ↓
┌─────────────┐
│  IN_STORE   │
└──────┬──────┘
       │
       ├→ Sales recorded
       ↓
┌─────────────┐
│  SOLD_OUT   │  ← Journey complete!
└─────────────┘
```

## 🏢 User Roles & What They Can Do

### 1. **ADMIN** (Administrateur)
- Approve/reject professional accounts
- Review certifications
- Manage product categories
- View audit logs
- Configure calculation settings
- See everything

### 2. **PRODUCTEUR** (Producer/Farmer)
- Create products
- Record productions (harvest, milking, etc.)
- Lots auto-created from productions
- Add certifications (bio, organic, etc.)
- Send lots to transformers/distributors
- Track their lots

### 3. **TRANSFORMATEUR** (Transformer/Processor)
- Receive lots from producers
- Transform them into new products
  - Example: Olives → Olive Oil
  - Example: Milk → Cheese
- New output lot auto-created
- Send transformed lots to distributors

### 4. **DISTRIBUTEUR** (Distributor/Retailer)
- Receive lots from producers/transformers
- Put lots in store
- Record sales to consumers
- Track inventory

### 5. **CONSOMMATEUR** (Consumer)
- Scan QR codes on products
- View complete product journey
- See environmental impact
- Check certifications
- Read/write reviews
- Report suspicious products
- Bookmark favorite products

## 📦 Key Models & Their Relationships

```
User
├── has one Organization
├── holds many Lots (current_holder_id)
└── created many Products

Organization
├── belongs to User
├── has name, city, coordinates
└── is_verified by admin

Product
├── belongs to Category (Fruits, Dairy, etc.)
├── has many Productions
├── has many Lots
└── has many Certifications

Production (harvest/milking event)
├── belongs to Product
├── created by Producer (User)
└── creates ONE Lot (auto via Observer)

Lot (THE CENTRAL MODEL)
├── belongs to Product
├── belongs to Production (if from farm)
├── belongs to Transformation (if transformed)
├── has current_holder (User)
├── has many Transports
├── has many Transfers
├── has many Distributions
├── has many TraceabilityEvents
├── has one EnvironmentalImpact
├── has many Certifications
├── has many Reviews
└── has many Reports

Transformation
├── created by Transformer (User)
├── has many input Lots (via TransformationInputs)
└── creates ONE output Lot (auto via Observer)

Transport
├── belongs to Lot
├── has mode (truck, van, ship, etc.)
├── has distance (km)
└── calculates emissions

Distribution
├── belongs to Lot
├── from Producer/Transformer to Distributor
└── tracks sales

TraceabilityEvent
├── belongs to Lot
├── immutable record of what happened
├── has previous_hash (blockchain-like)
└── types: production_created, lot_sent, lot_received, 
           transformation_completed, etc.

EnvironmentalImpact (cached scores)
├── belongs to Lot
├── co2, water, energy, distance
├── grade (A to E)
├── trust_score (0-100)
└── trust_score_breakdown (JSON explanation)

Certification
├── polymorphic: belongs to Product OR Lot
├── type (BIO, COMMERCE_EQUITABLE, etc.)
├── status (PENDING, APPROVED, REJECTED, EXPIRED)
├── has document_path (private storage)
└── expiry_date
```

## 🚚 Three Ways to Send a Lot

### 1. **Transfer** (Producer/Transformer → Transformer)
```php
// Service: LotDispatcher
Producer sends lot → Transformer receives → Transforms → New lot created
```

### 2. **Distribution** (Producer/Transformer → Distributor)
```php
// Service: LotDispatcher
Producer/Transformer sends lot → Distributor receives → Puts in store → Sells
```

### 3. **Both create Transport**
```php
Transport {
    mode: TRUCK/VAN/SHIP/PLANE/TRAIN
    distance_km: calculated via Haversine or manual
    emissions_kg: distance × weight × emission_factor
}
```

## 🌱 Environmental Impact Calculation

### Components

**1. Production Impact**
```
= (energy × electricity_factor) 
+ (fertilizers × fertilizer_factor) 
+ (pesticides × pesticide_factor)
```

**2. Transformation Impact**
```
= energy × electricity_factor
+ inherited impact from input lots (proportional)
```

**3. Transport Impact**
```
= distance (km) × weight (tonnes) × transport_mode_factor
```

**4. Total Lot Impact**
```
CO2 = production + transformation + transport
Water = sum of declared/measured water usage
Energy = sum of energy consumption
Distance = sum of all transport distances
```

### Grading (A to E)

Each indicator scored 0-100:
- 100 = below "best" threshold
- 0 = above "worst" threshold
- Linear interpolation between

Final score = weighted average:
- CO2: 40%
- Water: 20%
- Energy: 20%
- Distance: 20%

Grades:
- **A**: 80-100 (excellent)
- **B**: 60-79 (good)
- **C**: 40-59 (fair)
- **D**: 20-39 (poor)
- **E**: 0-19 (very poor)

## 🔍 Trust Score (Transparency Score)

**Out of 100 points:**

| Category | Points | What it checks |
|----------|--------|---------------|
| **Complete Journey** | 25 | Origin known, geolocated, transport documented, distribution recorded, transformation described |
| **Verified Actors** | 20 | % of organizations in journey verified by admin |
| **Valid Certifications** | 20 | No certs = 0 pts, certs without docs = 60%, certs with docs = 100% |
| **Environmental Data** | 20 | How many of 6 indicators (CO2, water, energy, distance, etc.) are provided |
| **Chain Integrity** | 15 | SHA-256 hash chain intact? |

**Penalties:**
- -5 pts per expired certification
- -10 pts per rejected certification
- -5 pts per open report
- -5 pts per date/location inconsistency
- Max 40 points penalty

## 🚨 Greenwashing Detection

### Local Rule
```
A product is "local" if:
  Distance from ALL origin farms to sale point ≤ 150 km

Warning triggered if:
  Product claims "local" but distance > 250 km
```

### Other Warnings
- "Bio" claim without valid BIO certification
- Expired certifications still displayed
- Rejected certifications not removed
- Missing transport documentation
- Inconsistent dates in journey
- Impossible locations

## 🔗 Blockchain-Like Traceability

### How It Works

Every event creates a hash chain:

```
Event 1: hash_1 = SHA256(null + event_data_1 + timestamp)
Event 2: hash_2 = SHA256(hash_1 + event_data_2 + timestamp)
Event 3: hash_3 = SHA256(hash_2 + event_data_3 + timestamp)
...
```

**Immutable**: Any change to past events breaks the chain!

### Event Types
- `production_created`
- `lot_sent`
- `lot_received`
- `lot_rejected`
- `transformation_started`
- `transformation_completed`
- `transport_delivered`
- `lot_put_in_store`
- `sale_recorded`
- `impact_measured`

### Verification
```php
ChainVerifier::verify($lot)
// Returns: true if intact, false if tampered
```

## 📍 Distance Calculation

### Haversine Formula
```php
GeoDistance::calculate($lat1, $lon1, $lat2, $lon2)
// Returns: distance in kilometers (as the crow flies)
```

Used for:
- Automatic distance in transports
- Local rule validation
- Journey visualization

## 🎫 QR Code System

Each lot has:
```php
$lot->public_token  // e.g., "a3f8c9d2e1b4f7a6..."
$lot->publicUrl()   // https://nutritrace.test/trace/a3f8c9d2e1b4f7a6
```

QR code generated via:
```php
QrCode::size(300)->generate($lot->publicUrl())
```

Consumer scans → sees complete journey!

## 🔐 Authorization Pattern

Every controller action uses **Policies**:

```php
public function update(Request $request, Lot $lot)
{
    $this->authorize('update', $lot);  // ← Policy check
    
    // ... business logic
}
```

Policy checks:
- Is user authenticated?
- Does user have correct role?
- Does user own/hold this resource?
- Is account active and approved?

## 📨 Important Observers

**Observers** automatically trigger on model events:

```php
ProductionObserver::created($production)
→ Creates Lot
→ Creates TraceabilityEvent (production_created)
→ Calculates initial scores

TransformationObserver::created($transformation)
→ Creates output Lot
→ Creates TraceabilityEvents for all inputs
→ Creates transformation_completed event
→ Recalculates scores

TransportObserver::created($transport)
→ Creates TraceabilityEvent
→ Calculates emissions
→ Recalculates scores

CertificationObserver::updated($certification)
→ Recalculates trust scores for related lots
```

## 📅 Scheduled Tasks

```php
// app/Console/Kernel.php
$schedule->command('certifications:expire')
         ->dailyAt('01:00');  // Runs every day at 1 AM
```

What it does:
1. Finds certifications where expiry_date < today
2. Changes status to EXPIRED
3. Recalculates trust scores for affected lots

## 🌐 API Endpoint

```http
GET /api/v1/trace/{public_token}
```

**Response:**
```json
{
  "lot": { /* lot details */ },
  "journey": {
    "origins": [ /* farms */ ],
    "transformations": [ /* processing steps */ ],
    "transports": [ /* shipments */ ],
    "distributions": [ /* stores */ ]
  },
  "environmental_impact": {
    "co2_kg": 127.5,
    "grade": "B",
    /* ... */
  },
  "trust_score": {
    "score": 78,
    "breakdown": { /* details */ }
  },
  "certifications": [ /* valid certs */ ],
  "warnings": [ /* greenwashing alerts */ ]
}
```

**Rate limit:** 60 requests/minute

## 🧪 Testing Strategy

Tests cover:
1. **Authentication**: Login, registration, email verification
2. **Authorization**: Role-based access, policies
3. **Workflows**: Complete production → transformation → distribution → sale
4. **Business Logic**: Score calculation, greenwashing detection
5. **Integrity**: Hash chain verification
6. **API**: Public endpoint responses
7. **Edge Cases**: Invalid data, tampering attempts

Run: `php artisan test`

## 🎨 UI Design Pattern

### Back Office (Bootstrap 4)
- Sidebar navigation (role-based menu)
- Top bar (user menu, notifications)
- Cards for content sections
- DataTables for listings
- Chart.js for graphs
- Modals for confirmations

### Public Site (Bootstrap 5)
- Top navigation bar
- Hero section with search
- Product cards in grid
- Timeline for journey
- Leaflet maps for locations
- Badges for certifications

## 🔧 Configuration Files

**config/footprint.php**
```php
'emission_factors' => [
    'truck' => 0.062,  // kg CO2 per tonne-km
    'van' => 0.158,
    'ship' => 0.011,
    // ...
]
```

**config/trust.php**
```php
'weights' => [
    'complete_journey' => 25,
    'verified_actors' => 20,
    'valid_certifications' => 20,
    // ...
]
```

**config/menu.php**
```php
'producteur' => [
    ['route' => 'producer.dashboard', 'icon' => 'tachometer-alt', 'label' => 'Tableau de bord'],
    ['route' => 'products.index', 'icon' => 'apple-alt', 'label' => 'Mes produits'],
    // ...
]
```

## 📚 Helper Functions

```php
// app/helpers.php

format_quantity($quantity, $unit)
// 5000 kg → "5 000 kg"

format_distance($km)
// 127.5 → "128 km"

grade_color($grade)
// 'A' → 'success', 'E' → 'danger'

trust_score_color($score)
// 80+ → 'success', <40 → 'danger'
```

---

This covers the essential concepts! For implementation details, check the full codebase or PROJECT_OVERVIEW.md 🚀
