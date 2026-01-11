# SambaPOS Loyalty Hub

A centralized WordPress plugin for managing customer loyalty across multiple hotel venues with SambaPOS integration.

## Features

- **Centralized Customer Database** - One account works at all hotels
- **RFID + QR Code Support** - Customers can use physical fobs or app QR codes
- **Per-Hotel Tier Configuration** - Each venue sets their own thresholds AND discount rates
- **Global Visit Counting** - Visits to ANY hotel count toward tier progression
- **Best-Tier Calculation** - Customers get the best tier between home and visiting hotel
- **Staff Discounts** - Separate tier with manual assignment and dedicated account codes
- **Promo System** - Loyalty bonuses (multipliers) and promo codes (fixed discounts)
- **Product Preference Tracking** - Track purchases for personalized notifications
- **Offline Support** - Sync endpoint for caching customers locally

## Requirements

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+
- SambaPOS 5.x with JScript support

## Installation

1. Upload the `loyalty-hub` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Loyalty Hub > Hotels** to add your first hotel
4. Configure tier thresholds and discount rates in **Loyalty Hub > Tiers**
5. Copy the hotel API key for use in SambaPOS

## Configuration

### Adding a Hotel

1. Navigate to **Loyalty Hub > Hotels**
2. Click "Add New"
3. Enter the hotel name and optional address
4. Save - an API key will be generated automatically
5. Copy the API key for SambaPOS configuration

### Configuring Tiers

Each hotel can set different:
- **Visit thresholds** - How many visits to qualify for each tier
- **Discount rates** - Wet (drinks) and dry (food) percentages per tier
- **Staff rates** - Separate discounts for staff members

Example configuration for "High Street Hotel":
| Tier | Visits Required | Wet % | Dry % |
|------|-----------------|-------|-------|
| Member | 0 | 5% | 10% |
| Loyalty | 4 in 28 days | 8% | 15% |
| Regular | 8 in 28 days | 12% | 20% |
| Staff | Manual | 25% | 30% |

### Tier Calculation Logic

1. Visits count **globally** (all hotels combined)
2. Customer's tier = **best of** (home hotel threshold, visiting hotel threshold)
3. Discount rates = from the **visiting hotel** for that tier

This means:
- Registering at a venue with lower thresholds benefits the customer everywhere
- Each venue controls their own discount margins
- Cross-hotel visits are encouraged

## API Reference

### Authentication

All endpoints require the `X-API-Key` header with a valid hotel API key.

```
X-API-Key: your-64-character-api-key
```

### Endpoints

#### POST /wp-json/loyalty/v1/identify

Look up a customer by any identifier (RFID, QR code, or email).

**Request:**
```json
{
  "identifier": "1234567890"
}
```

The identifier can be an RFID code, QR code, or email address. The system searches in this order:
1. Identifiers table (RFID fobs and QR codes)
2. Customer email

**Response:**
```json
{
  "customer_id": 123,
  "name": "John Smith",
  "tier": "Loyalty",
  "is_staff": false,
  "wet_discount": 8.00,
  "dry_discount": 15.00,
  "discount_type": "discount",
  "total_visits": 5,
  "period_days": 28,
  "home_hotel": "Number Four",
  "available_promos": [
    {
      "code": "LUNCH2X",
      "name": "Double Lunch Discount",
      "description": "Double your loyalty discount 11:30-14:30"
    }
  ],
  "next_tier": {
    "next_tier": "Regular",
    "visits_to_go": 3
  }
}
```

Note: `available_promos` and `next_tier` are not returned for staff members.

#### POST /wp-json/loyalty/v1/transaction

Log a completed transaction.

**Request:**
```json
{
  "customer_id": 123,
  "ticket_id": "T-12345",
  "total_amount": 45.50,
  "wet_total": 25.00,
  "dry_total": 20.50,
  "discount_amount": 6.83,
  "discount_type": "discount",
  "tier_at_visit": "Loyalty",
  "promo_code": null,
  "items": [
    {
      "product_name": "Pint of Lager",
      "product_group": "Drinks",
      "quantity": 2,
      "price": 12.50,
      "is_wet": 1
    }
  ]
}
```

#### POST /wp-json/loyalty/v1/register

Register a new customer.

**Request:**
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "phone": "07123456789",
  "rfid_code": "9876543210"
}
```

#### GET /wp-json/loyalty/v1/sync

Bulk sync customers for offline caching.

**Query Parameters:**
- `updated_since` (optional): ISO 8601 datetime for incremental sync

**Response:**
```json
{
  "customers": [
    {
      "id": 123,
      "name": "John Smith",
      "email": "john@example.com",
      "is_staff": false,
      "home_hotel_id": 1,
      "identifiers": [
        {
          "identifier_type": "rfid",
          "identifier_value": "1234567890",
          "label": "Primary RFID Fob"
        },
        {
          "identifier_type": "qr",
          "identifier_value": "LH1A2B3C4D5E6F",
          "label": "Primary QR Code"
        }
      ]
    }
  ],
  "count": 150,
  "sync_time": "2024-01-15 10:30:00"
}
```

#### POST /wp-json/loyalty/v1/identifier/add

Add an additional identifier (RFID fob) to an existing customer. Useful for couples sharing an account.

**Request:**
```json
{
  "customer_id": 123,
  "identifier": "9876543210",
  "type": "rfid",
  "label": "Wife's fob"
}
```

**Response:**
```json
{
  "success": true,
  "identifier_id": 456,
  "customer_id": 123,
  "type": "rfid",
  "value": "9876543210",
  "label": "Wife's fob",
  "message": "Identifier added successfully"
}
```

#### POST /wp-json/loyalty/v1/promos/validate

Check if a promo code is valid.

**Request:**
```json
{
  "code": "LUNCH2X",
  "customer_id": 123,
  "total_amount": 45.50
}
```

#### POST /wp-json/loyalty/v1/promos/apply

Apply a promo code and get discount rates.

**Request:**
```json
{
  "code": "LUNCH2X",
  "customer_id": 123,
  "base_wet_discount": 8.00,
  "base_dry_discount": 15.00
}
```

## Newbook Account Codes

The system uses specific account codes in calculation names for Newbook reporting:

| Code | Purpose | Discount Type |
|------|---------|---------------|
| #2303 | Loyalty drink discounts | discount |
| #3303 | Loyalty food discounts | discount |
| #2305 | Promo drink discounts | promo |
| #3305 | Promo food discounts | promo |
| #2306 | Staff drink discounts | staff |
| #3306 | Staff food discounts | staff |

## SambaPOS Integration

### Ticket States

The following ticket states should be set by JScript:

| State | Values | Purpose |
|-------|--------|---------|
| CustomerID | string | Unique customer identifier |
| CustomerName | string | Display name |
| CustomerTier | Member/Loyalty/Regular/Staff | Current tier |
| DiscountType | discount / promo / staff | Determines account codes |
| WetDiscount | decimal | Drink discount % to apply |
| DryDiscount | decimal | Food discount % to apply |

### Example JScript Integration

See the `/docs/sambapos-integration.js` file for complete JScript examples.

## Promo Types

### Loyalty Bonus
- Multiplies the customer's existing tier discount
- Requires membership (customer must be identified first)
- Uses same account codes as tier discount (#2303/#3303)
- Example: "LUNCH2X" with multiplier 2.0 doubles the loyalty discount

### Promo Code
- Fixed percentage discount
- Works for members AND guests
- Uses separate account codes (#2305/#3305)
- If a member uses this, it REPLACES their tier discount
- Example: "SUMMER24" with 15% wet and 15% dry

## Development

### File Structure

```
loyalty-hub/
├── loyalty-hub.php          # Main plugin file
├── includes/
│   ├── class-database.php   # Database schema and operations
│   ├── class-api.php        # REST API endpoints
│   ├── class-tier-calculator.php  # Tier calculation logic
│   └── class-promo-handler.php    # Promo validation and application
├── admin/
│   ├── class-admin.php      # Admin menu and handlers
│   ├── views/               # Admin page templates
│   ├── css/                 # Admin styles
│   └── js/                  # Admin JavaScript
└── docs/                    # Documentation
```

### Database Tables

- `loyalty_hotels` - Hotel/venue configurations
- `loyalty_tiers` - Global tier definitions
- `loyalty_hotel_tiers` - Per-hotel tier thresholds and rates
- `loyalty_hotel_staff_rates` - Per-hotel staff discount rates
- `loyalty_customers` - Customer profiles
- `loyalty_transactions` - Visit/spend history
- `loyalty_transaction_items` - Line items for preference tracking
- `loyalty_customer_preferences` - Aggregated favorites
- `loyalty_promos` - Promo definitions
- `loyalty_customer_promos` - Targeted promo assignments
- `loyalty_promo_usage` - Promo redemption tracking

## License

GPL v2 or later

## Support

For issues and feature requests, please use the GitHub issues page.
