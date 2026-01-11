# Centralized Hotel Loyalty System - Full Plan

This document contains the complete technical specification for the loyalty system.

## What We're Building

A centralized loyalty platform across all hotels with:
- **Central WordPress site** - API, database, admin portal
- **Plugin for each hotel WordPress** - Customer-facing features
- **Updated SambaPOS scripts** - API integration with offline fallback

---

## Key Features

| Feature | Description |
|---------|-------------|
| **Centralized customers** | One account works at all hotels |
| **QR codes + RFID** | Customers can use app QR or physical fob |
| **Separate wet/dry discounts** | Different % for drinks vs food |
| **Product tracking** | Track purchases for personalization |
| **Tier-based discounts** | Member → Loyalty → Regular |
| **Loyalty promos** | Stackable bonuses for members |
| **Guest promos** | Codes that work without signup |
| **Personalized notifications** | "We miss you! How about a pint of [favorite drink]?" |
| **Promo display on scan** | Show available promos via AskQuestion dialog when member scans |
| **Offline mode** | Cached customers when internet unavailable |

---

## Discount Structure

### Loyalty Tiers (Fully Per-Hotel: Requirements AND Rates)

Each hotel defines their own tier thresholds AND discount percentages.

**Example - High Street Hotel:**
| Tier | Wet % | Dry % | Visits Required |
|------|-------|-------|-----------------|
| Member | 5% | 10% | Default |
| Loyalty | 8% | 15% | 4 in 28 days |
| Regular | 12% | 20% | 8 in 28 days |

**Example - Number Four (quieter venue, more generous):**
| Tier | Wet % | Dry % | Visits Required |
|------|-------|-------|-----------------|
| Member | 5% | 10% | Default |
| Loyalty | 10% | 20% | 2 in 28 days |
| Regular | 15% | 25% | 4 in 28 days |

### Home Hotel & Best Tier / Visiting Rates

Each customer has a **home hotel** (where they registered).

**The rule:**
- **TIER** = Best available (compare home vs visiting thresholds, pick higher tier)
- **RATES** = From the VISITING hotel for that tier

**Visits count globally** - all visits to ANY hotel count toward the total.

**Example:**
```
Customer: John
Home hotel: Number Four
Total visits (all hotels): 3 in last 28 days

Step 1 - Calculate tier at BOTH hotels:
  At Number Four: 3 >= 2 → Loyalty
  At High Street: 3 < 4  → Member

Step 2 - Pick the BEST tier:
  Loyalty > Member → John gets LOYALTY

Step 3 - Get VISITING hotel's rates for that tier:
  At High Street: Loyalty = 8%/15%

Result at High Street: LOYALTY tier @ 8%/15%
```

**Another example (visiting hotel is better):**
```
Customer: Jane
Home hotel: High Street (Loyalty: 4 visits, Regular: 8 visits)
Total visits: 5 in last 28 days

At High Street: 5 >= 4 → Loyalty
At Number Four: 5 >= 4 → Regular (threshold is 4)

Best tier = REGULAR (from Number Four)
Rates = High Street's Regular rates (12%/20%)

Result at High Street: REGULAR tier @ 12%/20%
```

**Why this works:**
- Customer always gets their best possible tier (home OR visiting)
- Each hotel controls their own discount margins via rates
- Rewards registering at venues with lower thresholds
- Also rewards visiting venues with lower thresholds
- Rates are always local - venues don't subsidize each other

### Staff Discount Tier

A separate tier for staff members:
- **Manually assigned** - Not earned by visits, admin sets `is_staff = true` on customer
- **Overrides normal tier** - When staff flag is set, uses staff rates instead of earned tier
- **Separate account codes** - Reported separately from loyalty discounts
- **Per-hotel rates** - Each hotel can set their own staff discount %

| Hotel | Staff Wet % | Staff Dry % |
|-------|-------------|-------------|
| High Street | 25% | 30% |
| Number Four | 30% | 35% |

### Newbook Account Codes (Simplified)
| Code | Purpose |
|------|---------|
| #2303 | Loyalty drink discounts (tier % + any loyalty bonuses) |
| #3303 | Loyalty food discounts (tier % + any loyalty bonuses) |
| #2305 | Promo drink discounts (one-off codes, member or guest) |
| #3305 | Promo food discounts (one-off codes, member or guest) |
| #2306 | Staff drink discounts |
| #3306 | Staff food discounts |

---

## Promo System

### Two Types
1. **Loyalty bonuses** - Require customer scan, ADD to tier % (same #2303/#3303 codes)
2. **Promo codes** - One-off discounts, work for anyone (uses #2305/#3305 codes)

**Loyalty bonuses** increase the existing loyalty discount:
- Customer scans → gets 10% loyalty discount
- Loyalty bonus "LUNCH2X" doubles it → 20% total
- Still uses #2303/#3303 codes (just higher %)

**Promo codes** are separate one-off discounts:
- Can be used by members OR guests
- If member uses promo code, it REPLACES their loyalty discount
- Uses #2305/#3305 codes for reporting

### Promo Features
- Time/day restrictions (e.g., "Wednesdays only", "11:30-14:30")
- Min spend thresholds
- Usage limits (per customer or total)
- Stacking options: additive, replace, or best-wins
- Targeted assignment to specific customers

### Example Promos
- `LUNCH2X` - Double loyalty discount at lunch (loyalty)
- `SUMMER24` - 15% off for festival weekend (guest)
- Free pudding when spend £20 (targeted to lapsed members)

---

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    SAMBAPOS (Each Hotel)                │
│  • Calls API on customer scan                           │
│  • Sets ticket states: WetDiscount, DryDiscount, etc.   │
│  • Single unified discount rule uses states             │
│  • Logs transactions with line items                    │
└─────────────────────────┬───────────────────────────────┘
                          │ API
┌─────────────────────────▼───────────────────────────────┐
│              CENTRAL WORDPRESS (loyalty.domain.com)     │
│  • REST API for POS integration                         │
│  • Customer database with preferences                   │
│  • Promo management                                     │
│  • Tier calculation                                     │
│  • Notifications (email)                                │
└─────────────────────────┬───────────────────────────────┘
                          │ API
┌─────────────────────────▼───────────────────────────────┐
│              HOTEL WORDPRESS SITES                      │
│  • Customer login/registration                          │
│  • QR code display                                      │
│  • Points/tier dashboard                                │
│  • Available promos                                     │
└─────────────────────────────────────────────────────────┘
```

---

## SambaPOS Changes

### New Product Tag
- `Discount Exclude` = "Yes" → Product won't receive discounts

### Key Ticket States (Simplified)
| State | Values | Purpose |
|-------|--------|---------|
| `CustomerID` | string | Unique customer identifier |
| `CustomerName` | string | Display name |
| `CustomerTier` | Member/Loyalty/Regular/Staff | Current tier |
| `DiscountType` | "discount" / "promo" / "staff" | Determines which account codes |
| `WetDiscount` | decimal | Drink discount % to apply |
| `DryDiscount` | decimal | Food discount % to apply |

**How DiscountType maps to account codes:**
- `discount` → #2303/#3303 (loyalty tier discounts, including any loyalty bonuses)
- `promo` → #2305/#3305 (one-off promo codes, member or guest)
- `staff` → #2306/#3306 (staff discounts)

### Unified Discount Rule
- Single rule replaces 3 tier-specific rules
- Reads % from ticket states, not hardcoded
- Includes `(PT.Discount Exclude=)` to skip tagged products
- Rounds to nearest £0.05

### Promo Display on Scan (AskQuestion)
When loyalty member scans fob, API returns available promos. JScript shows dialog:

```
┌─────────────────────────────────────────┐
│  Welcome back, John! (Loyalty Tier)     │
│                                         │
│  You have 2 available promos:           │
│                                         │
│  [LUNCH2X] Double discount at lunch     │
│  [BIRTHDAY] 20% extra - expires today!  │
│                                         │
│            [ OK ]  [ Apply Code ]       │
└─────────────────────────────────────────┘
```

**Flow:**
1. Customer scans RFID/QR
2. API `/identify` returns `available_promos[]` array
3. If promos exist, `dlg.AskQuestion()` displays them
4. Staff can tap "Apply Code" → auto-fills first promo
5. Or staff enters different code manually later

---

## Database Tables (Key Ones)

```
loyalty_customers     - Customer profiles + home_hotel_id + is_staff flag
loyalty_hotels        - Hotel configs, API keys
loyalty_tiers         - Global tier names (Member/Loyalty/Regular) for consistency
loyalty_hotel_tiers   - Per-hotel: tier thresholds AND discount rates
loyalty_hotel_staff_rates - Per-hotel: staff discount rates
loyalty_transactions  - Visit/spend history with wet/dry totals (per hotel)
loyalty_transaction_items - Line item details for personalization
loyalty_customer_preferences - Aggregated favorite products (per hotel)
loyalty_promos        - Promo definitions (can be global or per-hotel)
loyalty_customer_promos - Targeted promo assignments
loyalty_promo_usage   - Tracks each promo redemption (for reporting)
```

---

## API Endpoints

| Endpoint | Purpose |
|----------|---------|
| `POST /identify` | Look up customer, calculate best tier (home vs visiting), return discounts + promos |
| `POST /transaction` | Log sale with line items |
| `POST /register` | Customer self-signup (sets home_hotel_id) |
| `GET /sync` | Bulk sync for offline cache |
| `POST /promos/validate` | Check if promo code is valid |
| `POST /promos/apply` | Apply promo to transaction |

---

## Implementation Phases

### Phase 1: Core Backend ✅
- WordPress + central plugin
- Database schema
- Basic API endpoints (/identify, /transaction)
- Admin: Hotels, Customers, Discount Bands

### Phase 2: SambaPOS Integration
- Update JScript for API calls
- Create `Discount Exclude` product tag
- Unified discount rule with ticket states
- Offline caching

### Phase 3: Promo System ✅
- Promo API endpoints
- Loyalty + guest promo support
- Admin promo management

### Phase 4: Hotel Site Plugin
- Customer login/registration
- QR code display
- Dashboard with points/promos

### Phase 5: Personalization
- Product preference tracking
- "Miss you" notifications with favorite items
- Birthday/tier upgrade notifications

### Phase 6: Rollout
- Deploy central site
- Configure first hotel
- Staff training
- Gradual rollout

---

## Quick Reference: Discount Flows

**Staff member:**
1. Scan RFID → API returns `is_staff = true`
2. DiscountType = "staff", WetDiscount/DryDiscount = staff rates
3. Result: Staff %, uses #2306/#3306

**Loyalty member (no promo):**
1. Scan RFID → API returns tier + discount %
2. DiscountType = "discount", WetDiscount/DryDiscount = tier rates
3. Result: Loyalty %, uses #2303/#3303

**Loyalty member with loyalty bonus:**
1. Scan RFID → Tier discount set (e.g., 10%/20%)
2. Apply loyalty bonus code → Increases % (e.g., +10%)
3. DiscountType = "discount", WetDiscount = 20%, DryDiscount = 30%
4. Result: Higher %, still uses #2303/#3303

**Anyone with promo code (member or guest):**
1. Enter promo code
2. If member was scanned, loyalty discount is REPLACED
3. DiscountType = "promo", WetDiscount/DryDiscount = promo rates
4. Result: Promo %, uses #2305/#3305
