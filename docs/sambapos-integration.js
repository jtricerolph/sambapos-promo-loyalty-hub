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
 * Identify customer by RFID code
 *
 * Trigger: Automation command on RFID scan
 * Sets ticket states with tier and discount information
 *
 * @param {string} rfidCode - The scanned RFID code
 */
function identifyCustomer(rfidCode) {
    try {
        // Build API request
        var url = API_BASE_URL + '/identify';
        var payload = JSON.stringify({
            rfid_code: rfidCode
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

        // Show available promos if any
        if (data.available_promos && data.available_promos.length > 0) {
            showAvailablePromos(data.name, data.tier, data.available_promos);
        } else {
            dlg.ShowMessage(message);
        }

        // Show next tier info if available
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

        // Calculate wet/dry totals and discount
        var wetTotal = calculateWetTotal();
        var dryTotal = calculateDryTotal();
        var discountAmount = calculateDiscountAmount();

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
            discount_amount: discountAmount,
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
 * Calculate total discount amount applied
 *
 * @returns {number} Total discount amount
 */
function calculateDiscountAmount() {
    // Get discount from calculations
    var calculations = api.GetCalculations();
    var discount = 0;
    for (var i = 0; i < calculations.length; i++) {
        var calc = calculations[i];
        // Check for loyalty/promo/staff calculation types
        if (calc.Name.indexOf('#2303') > -1 ||
            calc.Name.indexOf('#3303') > -1 ||
            calc.Name.indexOf('#2305') > -1 ||
            calc.Name.indexOf('#3305') > -1 ||
            calc.Name.indexOf('#2306') > -1 ||
            calc.Name.indexOf('#3306') > -1) {
            discount += Math.abs(calc.Amount);
        }
    }
    return discount;
}

/**
 * Get ticket line items for product tracking
 *
 * @returns {array} Array of item objects
 */
function getTicketItems() {
    var items = [];
    var orders = api.GetOrders();

    for (var i = 0; i < orders.length; i++) {
        var order = orders[i];
        var category = api.GetProductTag(order.ProductId, 'Category') || 'Other';

        items.push({
            product_name: order.ProductName,
            product_group: category,
            quantity: order.Quantity,
            price: order.Price,
            is_wet: category === 'Drinks' ? 1 : 0
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
 * Downloads all customers to local cache for offline lookup.
 * In offline mode, use cached data instead of API calls.
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

        // Store customers in local setting or database
        // Implementation depends on your SambaPOS version
        for (var i = 0; i < data.customers.length; i++) {
            var customer = data.customers[i];
            // Store in local cache - e.g., SambaPOS setting or custom table
            api.SetSettingValue('LoyaltyCache_' + customer.rfid_code, JSON.stringify(customer));
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
 * @param {string} rfidCode - The scanned RFID code
 * @returns {object|null} Customer data or null if not found
 */
function getCachedCustomer(rfidCode) {
    var cached = api.GetSettingValue('LoyaltyCache_' + rfidCode);
    if (cached) {
        return JSON.parse(cached);
    }
    return null;
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
