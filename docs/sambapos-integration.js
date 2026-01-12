/**
 * SambaPOS JScript Integration for Loyalty Hub
 *
 * This file contains example JScript code for integrating SambaPOS
 * with the Loyalty Hub WordPress plugin.
 *
 * CONFIGURATION:
 * - Set API_BASE_URL to your WordPress site URL
 * - Set API_KEY to your hotel's API key from Loyalty Hub admin
 *
 * REQUIRED SAMBAPOS SETUP:
 * 1. Ticket States: CustomerID, CustomerName, CustomerTier, DiscountType, WetDiscount, DryDiscount
 * 2. Product Tag: "Discount Exclude" with values "Yes"/"No"
 * 3. Calculation Types with Newbook codes in names (#2303, #3303, #2305, #3305, #2306, #3306)
 * 4. Automation rules to trigger these scripts
 */

// =============================================================================
// CONFIGURATION
// =============================================================================

var API_BASE_URL = 'https://loyalty.yourdomain.com/wp-json/loyalty/v1';
var API_KEY = 'your-64-character-api-key-here';

// =============================================================================
// CUSTOMER IDENTIFICATION (Triggered on RFID/QR scan)
// =============================================================================

/**
 * Identify customer by scanned code
 *
 * Trigger: Automation command on RFID/QR scan
 * Sets ticket states with tier and discount information.
 *
 * The API accepts any identifier type (RFID, QR, or email) in a single field,
 * so this works with both RFID fobs and QR codes from the numberpad.
 *
 * @param {string} scannedCode - The scanned RFID or QR code
 */
function identifyCustomer(scannedCode) {
    try {
        // Build API request - uses generic 'identifier' field
        var url = API_BASE_URL + '/identify';
        var payload = JSON.stringify({
            identifier: scannedCode
        });

        // Make HTTP request
        var response = api.HttpPost(url, payload, 'application/json', 'X-API-Key:' + API_KEY);

        if (!response || response.indexOf('error') > -1) {
            dlg.ShowMessage('Customer not found');
            return;
        }

        // Parse response
        var data = JSON.parse(response);

        // Set ticket states
        api.UpdateTicketState('CustomerID', data.customer_id.toString());
        api.UpdateTicketState('CustomerName', data.name);
        api.UpdateTicketState('CustomerTier', data.tier);
        api.UpdateTicketState('DiscountType', data.discount_type);
        api.UpdateTicketState('WetDiscount', data.wet_discount.toString());
        api.UpdateTicketState('DryDiscount', data.dry_discount.toString());

        // Build welcome message
        var message = 'Welcome back, ' + data.name + '!\n';
        message += 'Tier: ' + data.tier + '\n';
        message += 'Discount: ' + data.wet_discount + '% drinks / ' + data.dry_discount + '% food';

        // Show available promos if any (not shown for staff)
        if (data.available_promos && data.available_promos.length > 0) {
            showAvailablePromos(data.name, data.tier, data.available_promos);
        } else {
            dlg.ShowMessage(message);
        }

        // Show next tier info if available (not shown for staff)
        if (data.next_tier) {
            var nextTierMsg = '\n\n' + data.next_tier.visits_to_go + ' more visits to reach ' + data.next_tier.next_tier + ' tier!';
            // Could show this as a separate notification
        }

    } catch (ex) {
        dlg.ShowMessage('Error identifying customer: ' + ex.message);
    }
}

/**
 * Show available promos dialog
 *
 * Displays an AskQuestion dialog with available promos
 * when a loyalty member scans their card.
 *
 * @param {string} customerName - Customer's name
 * @param {string} tier - Customer's tier
 * @param {array} promos - Available promos array
 */
function showAvailablePromos(customerName, tier, promos) {
    // Build promo list
    var promoList = '';
    for (var i = 0; i < promos.length; i++) {
        var p = promos[i];
        promoList += '\n[' + p.code + '] ' + p.name;
        if (p.description) {
            promoList += '\n    ' + p.description;
        }
        promoList += '\n';
    }

    // Build message
    var message = 'Welcome back, ' + customerName + '! (' + tier + ' Tier)\n';
    message += '\nAvailable promos:' + promoList;

    // Show dialog with options
    var result = dlg.AskQuestion(message, 'OK,Apply First Code');

    if (result == 1) {
        // User chose to apply first promo
        applyPromoCode(promos[0].code);
    }
}

// =============================================================================
// PROMO CODE APPLICATION
// =============================================================================

/**
 * Apply a promo code to the current ticket
 *
 * Trigger: Automation command with promo code parameter
 *
 * @param {string} promoCode - The promo code to apply
 */
function applyPromoCode(promoCode) {
    try {
        // Get current customer info from ticket states
        var customerId = api.GetTicketState('CustomerID');
        var baseWet = parseFloat(api.GetTicketState('WetDiscount')) || 0;
        var baseDry = parseFloat(api.GetTicketState('DryDiscount')) || 0;

        // Build API request
        var url = API_BASE_URL + '/promos/apply';
        var payload = JSON.stringify({
            code: promoCode,
            customer_id: customerId ? parseInt(customerId) : null,
            base_wet_discount: baseWet,
            base_dry_discount: baseDry
        });

        // Make HTTP request
        var response = api.HttpPost(url, payload, 'application/json', 'X-API-Key:' + API_KEY);

        if (!response) {
            dlg.ShowMessage('Error applying promo code');
            return;
        }

        var data = JSON.parse(response);

        if (data.error) {
            dlg.ShowMessage('Promo error: ' + data.error);
            return;
        }

        // Update ticket states with promo discount
        api.UpdateTicketState('DiscountType', data.discount_type);
        api.UpdateTicketState('WetDiscount', data.wet_discount.toString());
        api.UpdateTicketState('DryDiscount', data.dry_discount.toString());
        api.UpdateTicketState('PromoCode', data.promo_code);

        // Show confirmation
        dlg.ShowMessage('Promo "' + data.promo_name + '" applied!\n' +
            'Discount: ' + data.wet_discount + '% drinks / ' + data.dry_discount + '% food');

    } catch (ex) {
        dlg.ShowMessage('Error applying promo: ' + ex.message);
    }
}

/**
 * Validate a promo code before applying
 *
 * @param {string} promoCode - The promo code to validate
 * @returns {boolean} Whether the promo is valid
 */
function validatePromoCode(promoCode) {
    try {
        var customerId = api.GetTicketState('CustomerID');
        var ticketTotal = api.GetTicketTotal();

        var url = API_BASE_URL + '/promos/validate';
        var payload = JSON.stringify({
            code: promoCode,
            customer_id: customerId ? parseInt(customerId) : null,
            total_amount: ticketTotal
        });

        var response = api.HttpPost(url, payload, 'application/json', 'X-API-Key:' + API_KEY);
        var data = JSON.parse(response);

        if (!data.valid) {
            dlg.ShowMessage(data.error);
            return false;
        }

        return true;

    } catch (ex) {
        dlg.ShowMessage('Error validating promo: ' + ex.message);
        return false;
    }
}

/**
 * Prompt staff to enter a promo code
 *
 * Trigger: Automation command button on POS screen
 *
 * Shows a numberpad/keyboard dialog for staff to enter a promo code,
 * then validates and applies it to the current ticket.
 *
 * Works for:
 * - Promo codes (promo_code type) - Anyone can use, REPLACES any existing discount
 * - Does NOT work for Customer Promos (loyalty_bonus) - those are auto-applied
 */
function enterPromoCode() {
    try {
        // Prompt for promo code using SambaPOS numberpad
        var promoCode = dlg.AskQuestion('Enter Promo Code:', '', 'Keyboard');

        if (!promoCode || promoCode === '') {
            return; // User cancelled
        }

        // Trim and uppercase
        promoCode = promoCode.toString().trim().toUpperCase();

        if (promoCode === '') {
            dlg.ShowMessage('No promo code entered');
            return;
        }

        // Validate first
        if (!validatePromoCode(promoCode)) {
            return; // Error already shown by validatePromoCode
        }

        // Apply the promo
        applyPromoCode(promoCode);

    } catch (ex) {
        dlg.ShowMessage('Error: ' + ex.message);
    }
}

/**
 * Clear promo code from ticket (revert to tier discount)
 *
 * Trigger: Automation command button
 *
 * Removes any applied promo code and reverts to the customer's
 * base tier discount (or auto-applied customer promo if one exists).
 */
function clearPromoCode() {
    try {
        var customerId = api.GetTicketState('CustomerID');

        if (!customerId) {
            // No customer - just clear everything
            api.UpdateTicketState('DiscountType', '');
            api.UpdateTicketState('WetDiscount', '0');
            api.UpdateTicketState('DryDiscount', '0');
            api.UpdateTicketState('PromoCode', '');
            dlg.ShowMessage('Promo cleared. No discount applied.');
            return;
        }

        // Re-identify customer to get their base rates (with auto-applied customer promo)
        var url = API_BASE_URL + '/identify';
        var payload = JSON.stringify({
            identifier: customerId // Can use customer ID as identifier
        });

        var response = api.HttpPost(url, payload, 'application/json', 'X-API-Key:' + API_KEY);
        var data = JSON.parse(response);

        if (data.error) {
            // Fallback - just clear the promo
            api.UpdateTicketState('PromoCode', '');
            dlg.ShowMessage('Promo cleared');
            return;
        }

        // Restore tier discount (includes auto-applied customer promo if any)
        api.UpdateTicketState('DiscountType', data.discount_type);
        api.UpdateTicketState('WetDiscount', data.wet_discount.toString());
        api.UpdateTicketState('DryDiscount', data.dry_discount.toString());
        api.UpdateTicketState('PromoCode', '');

        dlg.ShowMessage('Promo cleared.\nReverted to ' + data.tier + ' discount: ' +
            data.wet_discount + '% drinks / ' + data.dry_discount + '% food');

    } catch (ex) {
        dlg.ShowMessage('Error clearing promo: ' + ex.message);
    }
}

// =============================================================================
// TRANSACTION LOGGING (Triggered on ticket close)
// =============================================================================

/**
 * Log completed transaction to Loyalty Hub
 *
 * Trigger: Automation rule on ticket closed
 * Call this after payment is complete.
 */
function logTransaction() {
    try {
        // Get ticket states
        var customerId = api.GetTicketState('CustomerID');

        // Only log if customer was identified
        if (!customerId) {
            return;
        }

        // Get ticket info
        var ticketId = api.GetTicketId();
        var totalAmount = api.GetTicketTotal();
        var discountType = api.GetTicketState('DiscountType') || 'discount';
        var tier = api.GetTicketState('CustomerTier') || 'Member';
        var promoCode = api.GetTicketState('PromoCode') || null;

        // Calculate wet/dry totals and discounts
        var wetTotal = calculateWetTotal();
        var dryTotal = calculateDryTotal();
        var wetDiscountAmount = calculateWetDiscountAmount();
        var dryDiscountAmount = calculateDryDiscountAmount();

        // Get line items for product tracking
        var items = getTicketItems();

        // Build API request
        var url = API_BASE_URL + '/transaction';
        var payload = JSON.stringify({
            customer_id: parseInt(customerId),
            ticket_id: ticketId,
            total_amount: totalAmount,
            wet_total: wetTotal,
            dry_total: dryTotal,
            wet_discount_amount: wetDiscountAmount,
            dry_discount_amount: dryDiscountAmount,
            discount_type: discountType,
            tier_at_visit: tier,
            promo_code: promoCode,
            items: items
        });

        // Make HTTP request (fire and forget)
        api.HttpPost(url, payload, 'application/json', 'X-API-Key:' + API_KEY);

    } catch (ex) {
        // Log error but don't interrupt checkout
        // Consider logging to a file or SambaPOS log
    }
}

/**
 * Calculate wet (drinks) total from ticket orders
 *
 * @returns {number} Total of wet products
 */
function calculateWetTotal() {
    // Implementation depends on your SambaPOS setup
    // Example using product tags:
    var total = 0;
    var orders = api.GetOrders();
    for (var i = 0; i < orders.length; i++) {
        var order = orders[i];
        if (api.GetProductTag(order.ProductId, 'Category') === 'Drinks') {
            total += order.Price * order.Quantity;
        }
    }
    return total;
}

/**
 * Calculate dry (food) total from ticket orders
 *
 * @returns {number} Total of dry products
 */
function calculateDryTotal() {
    var total = 0;
    var orders = api.GetOrders();
    for (var i = 0; i < orders.length; i++) {
        var order = orders[i];
        if (api.GetProductTag(order.ProductId, 'Category') !== 'Drinks') {
            total += order.Price * order.Quantity;
        }
    }
    return total;
}

/**
 * Calculate wet (drinks) discount amount applied
 *
 * @returns {number} Wet discount amount
 */
function calculateWetDiscountAmount() {
    // Get discount from wet calculations (account codes #2303, #2305, #2306)
    var calculations = api.GetCalculations();
    var discount = 0;
    for (var i = 0; i < calculations.length; i++) {
        var calc = calculations[i];
        if (calc.Name.indexOf('#2303') > -1 ||
            calc.Name.indexOf('#2305') > -1 ||
            calc.Name.indexOf('#2306') > -1) {
            discount += Math.abs(calc.Amount);
        }
    }
    return discount;
}

/**
 * Calculate dry (food) discount amount applied
 *
 * @returns {number} Dry discount amount
 */
function calculateDryDiscountAmount() {
    // Get discount from dry calculations (account codes #3303, #3305, #3306)
    var calculations = api.GetCalculations();
    var discount = 0;
    for (var i = 0; i < calculations.length; i++) {
        var calc = calculations[i];
        if (calc.Name.indexOf('#3303') > -1 ||
            calc.Name.indexOf('#3305') > -1 ||
            calc.Name.indexOf('#3306') > -1) {
            discount += Math.abs(calc.Amount);
        }
    }
    return discount;
}

/**
 * Get ticket line items for product tracking
 *
 * Uses product's Stock Type tag to determine wet/dry classification.
 * Matches SambaPOS product configuration.
 *
 * @returns {array} Array of item objects
 */
function getTicketItems() {
    var items = [];
    var orders = api.GetOrders();

    for (var i = 0; i < orders.length; i++) {
        var order = orders[i];
        var category = api.GetProductTag(order.ProductId, 'Category') || 'Other';
        // Use Stock Type tag from product - defaults to 'dry' if not set
        var stockType = api.GetProductTag(order.ProductId, 'Stock Type') || 'dry';
        stockType = stockType.toLowerCase();
        if (stockType !== 'wet' && stockType !== 'dry') {
            stockType = 'dry';
        }

        items.push({
            product_name: order.ProductName,
            product_group: category,
            quantity: order.Quantity,
            price: order.Price,
            stock_type: stockType
        });
    }

    return items;
}

// =============================================================================
// CUSTOMER REGISTRATION
// =============================================================================

/**
 * Register a new customer
 *
 * Trigger: Automation command for new customer signup
 */
function registerCustomer() {
    // Get customer details via dialog
    var name = dlg.AskQuestion('Enter customer name:', '', 'text');
    if (!name) return;

    var email = dlg.AskQuestion('Enter email (optional):', '', 'text');
    var phone = dlg.AskQuestion('Enter phone (optional):', '', 'text');
    var rfidCode = dlg.AskQuestion('Scan RFID fob:', '', 'text');

    try {
        var url = API_BASE_URL + '/register';
        var payload = JSON.stringify({
            name: name,
            email: email || null,
            phone: phone || null,
            rfid_code: rfidCode || null
        });

        var response = api.HttpPost(url, payload, 'application/json', 'X-API-Key:' + API_KEY);
        var data = JSON.parse(response);

        if (data.error) {
            dlg.ShowMessage('Registration error: ' + data.error);
            return;
        }

        // Set ticket states for new customer
        api.UpdateTicketState('CustomerID', data.customer_id.toString());
        api.UpdateTicketState('CustomerName', data.name);
        api.UpdateTicketState('CustomerTier', 'Member');
        api.UpdateTicketState('DiscountType', 'discount');

        dlg.ShowMessage('Welcome, ' + data.name + '!\n' +
            'Your QR code: ' + data.qr_code + '\n' +
            'Home hotel: ' + data.home_hotel);

    } catch (ex) {
        dlg.ShowMessage('Error registering customer: ' + ex.message);
    }
}

// =============================================================================
// OFFLINE CACHE SYNC
// =============================================================================

/**
 * Sync customer cache for offline mode
 *
 * Trigger: Automation rule on workperiod start or scheduled
 *
 * Downloads all customers with their identifiers to local cache.
 * Each identifier (RFID fob, QR code) is stored as a lookup key
 * pointing to the customer data, supporting multiple fobs per customer.
 *
 * In offline mode, use getCachedCustomer() instead of API calls.
 */
function syncCustomerCache() {
    try {
        var lastSync = api.GetSettingValue('LoyaltyLastSync') || '';

        var url = API_BASE_URL + '/sync';
        if (lastSync) {
            url += '?updated_since=' + encodeURIComponent(lastSync);
        }

        var response = api.HttpGet(url, 'X-API-Key:' + API_KEY);
        var data = JSON.parse(response);

        // Store customers in local cache
        // Each identifier (RFID/QR) becomes a lookup key pointing to customer data
        for (var i = 0; i < data.customers.length; i++) {
            var customer = data.customers[i];

            // Store customer data by ID
            api.SetSettingValue('LoyaltyCustomer_' + customer.id, JSON.stringify(customer));

            // Create lookup entries for each identifier (RFID, QR, etc.)
            if (customer.identifiers && customer.identifiers.length > 0) {
                for (var j = 0; j < customer.identifiers.length; j++) {
                    var ident = customer.identifiers[j];
                    // Store customer ID reference by identifier value
                    api.SetSettingValue('LoyaltyCache_' + ident.identifier_value, customer.id.toString());
                }
            }
        }

        // Update last sync time
        api.SetSettingValue('LoyaltyLastSync', data.sync_time);

        dlg.ShowMessage('Synced ' + data.count + ' customers');

    } catch (ex) {
        dlg.ShowMessage('Error syncing cache: ' + ex.message);
    }
}

/**
 * Look up customer from local cache (offline mode)
 *
 * Supports any identifier type (RFID, QR, email) - same as online API.
 * Handles multiple RFID fobs per customer.
 *
 * @param {string} identifier - The scanned RFID/QR code
 * @returns {object|null} Customer data or null if not found
 */
function getCachedCustomer(identifier) {
    // Look up customer ID from identifier
    var customerId = api.GetSettingValue('LoyaltyCache_' + identifier);
    if (!customerId) {
        return null;
    }

    // Get full customer data by ID
    var cached = api.GetSettingValue('LoyaltyCustomer_' + customerId);
    if (cached) {
        return JSON.parse(cached);
    }
    return null;
}

// =============================================================================
// IDENTIFIER MANAGEMENT
// =============================================================================

/**
 * Add an additional RFID fob to current customer
 *
 * Trigger: Automation command with RFID scan while customer is on ticket
 * Useful for couples/families sharing a loyalty account
 *
 * @param {string} newRfidCode - The new RFID code to add
 * @param {string} label - Optional friendly label (e.g., "Wife's fob")
 */
function addCustomerFob(newRfidCode, label) {
    try {
        var customerId = api.GetTicketState('CustomerID');

        if (!customerId) {
            dlg.ShowMessage('No customer on ticket. Identify customer first.');
            return;
        }

        var url = API_BASE_URL + '/identifier/add';
        var payload = JSON.stringify({
            customer_id: parseInt(customerId),
            identifier: newRfidCode,
            type: 'rfid',
            label: label || null
        });

        var response = api.HttpPost(url, payload, 'application/json', 'X-API-Key:' + API_KEY);
        var data = JSON.parse(response);

        if (data.error) {
            dlg.ShowMessage('Error: ' + data.error);
            return;
        }

        var customerName = api.GetTicketState('CustomerName');
        dlg.ShowMessage('Added RFID fob for ' + customerName + '\n' +
            (label ? 'Label: ' + label : ''));

    } catch (ex) {
        dlg.ShowMessage('Error adding fob: ' + ex.message);
    }
}

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

/**
 * Clear customer from ticket
 *
 * Trigger: Automation command to remove customer
 */
function clearCustomer() {
    api.UpdateTicketState('CustomerID', '');
    api.UpdateTicketState('CustomerName', '');
    api.UpdateTicketState('CustomerTier', '');
    api.UpdateTicketState('DiscountType', '');
    api.UpdateTicketState('WetDiscount', '0');
    api.UpdateTicketState('DryDiscount', '0');
    api.UpdateTicketState('PromoCode', '');

    dlg.ShowMessage('Customer cleared from ticket');
}

/**
 * Show current customer info
 *
 * Trigger: Automation command to view customer
 */
function showCustomerInfo() {
    var customerId = api.GetTicketState('CustomerID');

    if (!customerId) {
        dlg.ShowMessage('No customer on ticket');
        return;
    }

    var name = api.GetTicketState('CustomerName');
    var tier = api.GetTicketState('CustomerTier');
    var wet = api.GetTicketState('WetDiscount');
    var dry = api.GetTicketState('DryDiscount');
    var discountType = api.GetTicketState('DiscountType');
    var promo = api.GetTicketState('PromoCode');

    var message = 'Customer: ' + name + '\n';
    message += 'ID: ' + customerId + '\n';
    message += 'Tier: ' + tier + '\n';
    message += 'Discount Type: ' + discountType + '\n';
    message += 'Wet Discount: ' + wet + '%\n';
    message += 'Dry Discount: ' + dry + '%';

    if (promo) {
        message += '\nPromo Code: ' + promo;
    }

    dlg.ShowMessage(message);
}
