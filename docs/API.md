# Loyalty Hub API Reference

Base URL: `https://your-site.com/wp-json/loyalty/v1`

## Authentication

All endpoints require the `X-API-Key` header with a valid hotel API key.

```
X-API-Key: your-64-character-api-key
Content-Type: application/json
```

---

## POST /identify

Look up a customer by RFID, QR code, or email. Returns tier, discount rates, and available promos.

**Request:**
```json
{
    "identifier": "ABC123456"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| identifier | string | Yes | RFID code, QR code, or email address |

**Response (200):**
```json
{
    "customer_id": 42,
    "name": "John Smith",
    "email": "john@example.com",
    "tier": "Loyalty",
    "is_staff": false,
    "wet_discount": 15,
    "dry_discount": 20,
    "base_wet_discount": 10,
    "base_dry_discount": 15,
    "discount_type": "discount",
    "applied_customer_promo": {
        "id": 5,
        "code": "CP_LUNCH2X",
        "name": "Lunch Boost",
        "description": "Extra 5% at lunch",
        "type": "loyalty_bonus",
        "wet_discount": 0,
        "dry_discount": 0,
        "bonus_multiplier": 0,
        "bonus_add_wet": 5,
        "bonus_add_dry": 5,
        "min_spend": 0,
        "valid_until": "2024-12-31 23:59:59"
    },
    "total_visits": 6,
    "period_days": 28,
    "home_hotel": "High Street Hotel",
    "available_promos": [
        {
            "id": 10,
            "code": "SUMMER24",
            "name": "Summer Special",
            "description": "15% off everything",
            "type": "promo_code",
            "wet_discount": 15,
            "dry_discount": 15,
            "bonus_multiplier": 0,
            "bonus_add_wet": 0,
            "bonus_add_dry": 0,
            "min_spend": 0,
            "valid_until": "2024-08-31 23:59:59"
        }
    ],
    "next_tier": {
        "next_tier": "Regular",
        "visits_required": 8,
        "visits_to_go": 2
    },
    "matched_identifier_type": "rfid",
    "identifier_label": "Primary RFID Fob"
}
```

| Field | Description |
|-------|-------------|
| wet_discount | Final drink discount % (includes any auto-applied customer promo) |
| dry_discount | Final food discount % (includes any auto-applied customer promo) |
| base_wet_discount | Tier-only drink discount (before customer promo) |
| base_dry_discount | Tier-only food discount (before customer promo) |
| discount_type | `"discount"` (loyalty), `"staff"` (staff rates) |
| applied_customer_promo | Auto-applied loyalty_bonus promo (null if none) |
| available_promos | Promo codes staff can manually apply (promo_code type only) |

**Error Response (404):**
```json
{
    "code": "customer_not_found",
    "message": "Customer not found",
    "data": { "status": 404 }
}
```

---

## POST /transaction

Log a completed sale for tier tracking and reporting.

**Request:**
```json
{
    "customer_id": 42,
    "ticket_id": "T-12345",
    "total_amount": 45.50,
    "wet_total": 18.00,
    "dry_total": 27.50,
    "wet_discount_amount": 2.70,
    "dry_discount_amount": 5.50,
    "discount_type": "discount",
    "tier_at_visit": "Loyalty",
    "promo_code": null,
    "items": [
        {
            "product_name": "Guinness",
            "product_group": "Drinks",
            "quantity": 2,
            "price": 5.50,
            "stock_type": "wet"
        },
        {
            "product_name": "Fish & Chips",
            "product_group": "Mains",
            "quantity": 1,
            "price": 14.95,
            "stock_type": "dry"
        }
    ]
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| customer_id | int | Yes | Customer ID from /identify |
| ticket_id | string | No | SambaPOS ticket ID |
| total_amount | float | Yes | Final ticket total |
| wet_total | float | No | Drinks total before discount |
| dry_total | float | No | Food total before discount |
| wet_discount_amount | float | No | Drinks discount amount applied |
| dry_discount_amount | float | No | Food discount amount applied |
| discount_type | string | No | `"discount"`, `"promo"`, or `"staff"` |
| tier_at_visit | string | No | Tier used for this transaction |
| promo_code | string | No | Promo code if one was used |
| items | array | No | Line items for preference tracking |

**Items array fields:**

| Field | Type | Description |
|-------|------|-------------|
| product_name | string | Product name |
| product_group | string | Product group/category |
| quantity | float | Quantity purchased |
| price | float | Unit price |
| stock_type | string | `"wet"` (drinks) or `"dry"` (food) |

**Response (201):**
```json
{
    "success": true,
    "transaction_id": 789,
    "message": "Transaction logged successfully"
}
```

---

## POST /register

Register a new customer. Home hotel is set to the hotel making the API call.

**Request:**
```json
{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "07700123456",
    "dob": "1990-05-15",
    "rfid_code": "RFID123456"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | Yes | Customer name |
| email | string | No | Email address |
| phone | string | No | Phone number |
| dob | string | No | Date of birth (YYYY-MM-DD) |
| rfid_code | string | No | RFID fob code |

**Response (201):**
```json
{
    "success": true,
    "customer_id": 43,
    "qr_code": "LHAB1234XYZ567",
    "name": "Jane Doe",
    "home_hotel": "High Street Hotel",
    "tier": "Member",
    "message": "Customer registered successfully"
}
```

**Error Response (409):**
```json
{
    "code": "duplicate_email",
    "message": "A customer with this email already exists",
    "data": { "status": 409 }
}
```

---

## GET /sync

Bulk sync customers for offline caching. Returns all customers with their identifiers.

**Query Parameters:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| updated_since | string | No | ISO 8601 datetime - only return records updated after this time |

**Example:** `GET /sync?updated_since=2024-01-15T10:30:00`

**Response (200):**
```json
{
    "customers": [
        {
            "id": 42,
            "name": "John Smith",
            "email": "john@example.com",
            "qr_code": "LHXYZ123ABC456",
            "is_staff": false,
            "home_hotel_id": 1,
            "updated_at": "2024-01-20 14:30:00",
            "rfid_fobs": [
                {
                    "identifier_type": "rfid",
                    "identifier_value": "ABC123456",
                    "label": "Primary RFID Fob"
                },
                {
                    "identifier_type": "rfid",
                    "identifier_value": "DEF789012",
                    "label": "Wife's fob"
                }
            ]
        }
    ],
    "count": 1,
    "sync_time": "2024-01-20 15:00:00"
}
```

---

## POST /identifier/add

Add an additional RFID fob to an existing customer. Useful for couples/families.

**Request:**
```json
{
    "customer_id": 42,
    "identifier": "NEWRFID789",
    "label": "Husband's fob"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| customer_id | int | Yes | Customer ID |
| identifier | string | Yes | The RFID code to add |
| label | string | No | Friendly name for the fob |

**Response (201):**
```json
{
    "success": true,
    "identifier_id": 15,
    "customer_id": 42,
    "type": "rfid",
    "value": "NEWRFID789",
    "label": "Husband's fob",
    "message": "RFID fob added successfully"
}
```

**Error Response (409):**
```json
{
    "code": "duplicate_rfid",
    "message": "This RFID code is already registered",
    "data": { "status": 409 }
}
```

---

## POST /identifier/replace

Replace an existing RFID fob with a new code. Useful when a customer loses their fob.

**Request:**
```json
{
    "customer_id": 42,
    "identifier_id": 15,
    "new_identifier": "REPLACEMENT123"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| customer_id | int | Yes | Customer ID |
| identifier_id | int | Yes | ID of the existing fob to replace |
| new_identifier | string | Yes | The new RFID code |

**Response (200):**
```json
{
    "success": true,
    "identifier_id": 15,
    "customer_id": 42,
    "old_value": "OLDLOSTFOB456",
    "new_value": "REPLACEMENT123",
    "label": "Primary RFID Fob",
    "message": "RFID fob replaced successfully"
}
```

**Error Response (404):**
```json
{
    "code": "identifier_not_found",
    "message": "RFID fob not found for this customer",
    "data": { "status": 404 }
}
```

**Error Response (409):**
```json
{
    "code": "duplicate_rfid",
    "message": "This RFID code is already registered to another customer",
    "data": { "status": 409 }
}
```

---

## POST /promos/validate

Check if a promo code is valid without applying it.

**Request:**
```json
{
    "code": "SUMMER24",
    "customer_id": 42,
    "total_amount": 25.00
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| code | string | Yes | Promo code to validate |
| customer_id | int | No | Customer ID (for membership-required promos) |
| total_amount | float | No | Transaction total (for min_spend check) |

**Response (200) - Valid:**
```json
{
    "valid": true,
    "promo": {
        "id": 10,
        "code": "SUMMER24",
        "name": "Summer Special",
        "description": "15% off everything",
        "type": "promo_code",
        "wet_discount": 15,
        "dry_discount": 15,
        "bonus_multiplier": 0,
        "bonus_add_wet": 0,
        "bonus_add_dry": 0,
        "min_spend": 0,
        "valid_until": "2024-08-31 23:59:59"
    }
}
```

**Response (400) - Invalid:**
```json
{
    "valid": false,
    "error": "This promo has expired"
}
```

Possible error messages:
- `"Promo code not found"`
- `"This promo code is no longer active"`
- `"This promo is not valid at this location"`
- `"This promo is not yet active"`
- `"This promo has expired"`
- `"This promo is not valid at this time of day"`
- `"This promo is not valid today"`
- `"This promo requires membership. Please scan your card first."`
- `"Minimum spend of X required"`
- `"This promo has reached its usage limit"`

---

## POST /promos/apply

Apply a promo code and get the discount rates to use.

**Request:**
```json
{
    "code": "SUMMER24",
    "customer_id": 42,
    "base_wet_discount": 10,
    "base_dry_discount": 15
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| code | string | Yes | Promo code to apply |
| customer_id | int | No | Customer ID (required for loyalty_bonus type) |
| base_wet_discount | float | No | Current tier wet discount (for loyalty_bonus calculation) |
| base_dry_discount | float | No | Current tier dry discount (for loyalty_bonus calculation) |

**Response (200) - Promo Code Type:**
```json
{
    "success": true,
    "discount_type": "promo",
    "wet_discount": 15,
    "dry_discount": 15,
    "promo_code": "SUMMER24",
    "promo_name": "Summer Special",
    "type": "promo_code"
}
```

**Response (200) - Loyalty Bonus Type:**
```json
{
    "success": true,
    "discount_type": "discount",
    "wet_discount": 20,
    "dry_discount": 30,
    "promo_code": "LUNCH2X",
    "promo_name": "Double Lunch Discount",
    "type": "loyalty_bonus"
}
```

| Field | Description |
|-------|-------------|
| discount_type | `"promo"` = uses #2305/#3305 codes, `"discount"` = uses #2303/#3303 codes |
| wet_discount | Drink discount % to apply |
| dry_discount | Food discount % to apply |
| type | `"promo_code"` or `"loyalty_bonus"` |

**Error Response (400):**
```json
{
    "code": "promo_invalid",
    "message": "This promo has expired",
    "data": { "status": 400 }
}
```

---

## Discount Type → Account Code Mapping

| discount_type | Wet Account | Dry Account | When Used |
|---------------|-------------|-------------|-----------|
| `"discount"` | #2303 | #3303 | Tier discount + loyalty bonuses |
| `"promo"` | #2305 | #3305 | Promo codes (one-off) |
| `"staff"` | #2306 | #3306 | Staff discounts |

---

## Error Response Format

All errors follow this format:

```json
{
    "code": "error_code",
    "message": "Human readable message",
    "data": {
        "status": 400
    }
}
```

| HTTP Status | Meaning |
|-------------|---------|
| 400 | Bad request / Validation error |
| 401 | Missing or invalid API key |
| 403 | Hotel is inactive |
| 404 | Resource not found |
| 409 | Conflict (duplicate) |
| 500 | Server error |
