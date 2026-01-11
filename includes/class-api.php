<?php
/**
 * REST API Handler for Loyalty Hub
 *
 * Provides REST API endpoints for SambaPOS integration.
 * All endpoints are registered under the /wp-json/loyalty/v1/ namespace.
 *
 * ==========================================================================
 * API AUTHENTICATION
 * ==========================================================================
 *
 * All endpoints require authentication via the X-API-Key header.
 * The API key must match a hotel's api_key in the loyalty_hotels table.
 *
 * The hotel_id associated with the API key is automatically used as the
 * "visiting hotel" for tier calculations.
 *
 * Example request:
 *   POST /wp-json/loyalty/v1/identify
 *   Headers:
 *     X-API-Key: abc123...
 *     Content-Type: application/json
 *   Body:
 *     {"rfid_code": "1234567890"}
 *
 * ==========================================================================
 * ENDPOINT OVERVIEW
 * ==========================================================================
 *
 * POST /identify      - Look up customer by RFID/QR, return tier + discounts
 * POST /transaction   - Log a completed sale with line items
 * POST /register      - Register a new customer
 * GET  /sync          - Bulk sync customers for offline cache
 * POST /promos/validate - Check if a promo code is valid
 * POST /promos/apply  - Apply a promo to a transaction
 *
 * ==========================================================================
 * SAMBAPOS INTEGRATION FLOW
 * ==========================================================================
 *
 * 1. Customer scans RFID fob or QR code
 * 2. SambaPOS JScript calls POST /identify with the code
 * 3. API returns:
 *    - tier (Member/Loyalty/Regular/Staff)
 *    - wet_discount, dry_discount (percentages)
 *    - discount_type (discount/promo/staff)
 *    - available_promos (array of promos to show)
 * 4. JScript sets ticket states: CustomerID, CustomerTier, WetDiscount, etc.
 * 5. SambaPOS calculation rule applies discount using the state values
 * 6. On ticket close, SambaPOS calls POST /transaction to log the sale
 *
 * @package Loyalty_Hub
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Loyalty_Hub_API {

    /**
     * API namespace for all endpoints
     *
     * @var string
     */
    const NAMESPACE = 'loyalty/v1';

    /**
     * Register all REST API routes
     *
     * Called from the main plugin class on rest_api_init action.
     *
     * @since 1.0.0
     * @return void
     */
    public static function register_routes() {

        /*
         * =================================================================
         * POST /identify
         * =================================================================
         * Look up a customer by any identifier (RFID, QR code, or email).
         * Returns tier information and discount rates.
         *
         * Request body:
         *   identifier: string - RFID code, QR code, or email address
         *                        (searches all identifier types)
         *
         * Response:
         *   customer_id: int
         *   name: string
         *   tier: string (Member/Loyalty/Regular/Staff)
         *   is_staff: bool
         *   wet_discount: float
         *   dry_discount: float
         *   discount_type: string (discount/staff)
         *   available_promos: array
         *   next_tier: object (null for staff)
         */
        register_rest_route(self::NAMESPACE, '/identify', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'identify_customer'),
            'permission_callback' => array(__CLASS__, 'authenticate_api_key'),
            'args'                => array(
                'identifier' => array(
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'RFID code, QR code, or email address',
                ),
            ),
        ));

        /*
         * =================================================================
         * POST /transaction
         * =================================================================
         * Log a completed transaction for tier tracking and reporting.
         *
         * Request body:
         *   customer_id: int
         *   ticket_id: string (SambaPOS ticket ID)
         *   total_amount: float
         *   wet_total: float (drinks total before discount)
         *   dry_total: float (food total before discount)
         *   discount_amount: float (total discount applied)
         *   discount_type: string (discount/promo/staff)
         *   tier_at_visit: string (tier used for this transaction)
         *   promo_code: string (optional, if promo was applied)
         *   items: array (optional, line items for preference tracking)
         */
        register_rest_route(self::NAMESPACE, '/transaction', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'log_transaction'),
            'permission_callback' => array(__CLASS__, 'authenticate_api_key'),
            'args'                => array(
                'customer_id' => array(
                    'type'     => 'integer',
                    'required' => true,
                ),
                'ticket_id' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'total_amount' => array(
                    'type'     => 'number',
                    'required' => true,
                ),
                'wet_total' => array(
                    'type'     => 'number',
                    'required' => false,
                    'default'  => 0,
                ),
                'dry_total' => array(
                    'type'     => 'number',
                    'required' => false,
                    'default'  => 0,
                ),
                'discount_amount' => array(
                    'type'     => 'number',
                    'required' => false,
                    'default'  => 0,
                ),
                'discount_type' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'tier_at_visit' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'promo_code' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'items' => array(
                    'type'     => 'array',
                    'required' => false,
                    'default'  => array(),
                ),
            ),
        ));

        /*
         * =================================================================
         * POST /register
         * =================================================================
         * Register a new customer.
         * Home hotel is set to the hotel making the API call.
         *
         * Request body:
         *   name: string (required)
         *   email: string (optional)
         *   phone: string (optional)
         *   dob: string (optional, YYYY-MM-DD)
         *   rfid_code: string (optional, physical fob)
         */
        register_rest_route(self::NAMESPACE, '/register', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'register_customer'),
            'permission_callback' => array(__CLASS__, 'authenticate_api_key'),
            'args'                => array(
                'name' => array(
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'email' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_email',
                ),
                'phone' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'dob' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'rfid_code' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        /*
         * =================================================================
         * GET /sync
         * =================================================================
         * Bulk sync customers for offline caching.
         * Returns all active customers with their identifiers.
         *
         * Query params:
         *   updated_since: string (optional, ISO 8601 datetime)
         *                  Only return customers updated after this time
         *
         * Response:
         *   customers: array of {id, name, email, is_staff, identifiers: [...]}
         *   sync_time: string (current server time for next sync)
         */
        register_rest_route(self::NAMESPACE, '/sync', array(
            'methods'             => 'GET',
            'callback'            => array(__CLASS__, 'sync_customers'),
            'permission_callback' => array(__CLASS__, 'authenticate_api_key'),
            'args'                => array(
                'updated_since' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        /*
         * =================================================================
         * POST /identifier/add
         * =================================================================
         * Add an additional RFID fob to an existing customer.
         * Useful for couples or families sharing an account.
         *
         * Note: QR codes are auto-generated on customer creation and
         * stored on the customers table. Only RFID fobs can be added.
         *
         * Request body:
         *   customer_id: int (required)
         *   identifier: string (required, the RFID code)
         *   label: string (optional, friendly name like "Wife's fob")
         *
         * Response:
         *   success: bool
         *   identifier_id: int
         */
        register_rest_route(self::NAMESPACE, '/identifier/add', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'add_identifier'),
            'permission_callback' => array(__CLASS__, 'authenticate_api_key'),
            'args'                => array(
                'customer_id' => array(
                    'type'     => 'integer',
                    'required' => true,
                ),
                'identifier' => array(
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'type' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'default'           => 'rfid',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'label' => array(
                    'type'              => 'string',
                    'required'          => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        /*
         * =================================================================
         * POST /promos/validate
         * =================================================================
         * Check if a promo code is valid.
         *
         * Request body:
         *   code: string (promo code)
         *   customer_id: int (optional, for personalized promos)
         *   total_amount: float (optional, for min_spend check)
         *
         * Response:
         *   valid: bool
         *   promo: object (if valid)
         *   error: string (if invalid)
         */
        register_rest_route(self::NAMESPACE, '/promos/validate', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'validate_promo'),
            'permission_callback' => array(__CLASS__, 'authenticate_api_key'),
            'args'                => array(
                'code' => array(
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'customer_id' => array(
                    'type'     => 'integer',
                    'required' => false,
                ),
                'total_amount' => array(
                    'type'     => 'number',
                    'required' => false,
                ),
            ),
        ));

        /*
         * =================================================================
         * POST /promos/apply
         * =================================================================
         * Apply a promo code and get the discount rates.
         *
         * For loyalty_bonus type: Returns boosted tier discount
         * For promo_code type: Returns promo discount rates
         *
         * Request body:
         *   code: string (promo code)
         *   customer_id: int (optional, required for loyalty_bonus)
         *   base_wet_discount: float (optional, current tier discount)
         *   base_dry_discount: float (optional, current tier discount)
         *
         * Response:
         *   success: bool
         *   discount_type: string (discount/promo)
         *   wet_discount: float
         *   dry_discount: float
         */
        register_rest_route(self::NAMESPACE, '/promos/apply', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'apply_promo'),
            'permission_callback' => array(__CLASS__, 'authenticate_api_key'),
            'args'                => array(
                'code' => array(
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'customer_id' => array(
                    'type'     => 'integer',
                    'required' => false,
                ),
                'base_wet_discount' => array(
                    'type'     => 'number',
                    'required' => false,
                    'default'  => 0,
                ),
                'base_dry_discount' => array(
                    'type'     => 'number',
                    'required' => false,
                    'default'  => 0,
                ),
            ),
        ));
    }

    /**
     * Authenticate API request using X-API-Key header
     *
     * Validates the API key against the loyalty_hotels table.
     * Sets the hotel_id on the request for use by endpoint callbacks.
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request The REST request object
     *
     * @return bool|WP_Error True if authenticated, WP_Error if not
     */
    public static function authenticate_api_key($request) {
        global $wpdb;

        // Get API key from header
        $api_key = $request->get_header('X-API-Key');

        if (empty($api_key)) {
            return new WP_Error(
                'missing_api_key',
                'X-API-Key header is required',
                array('status' => 401)
            );
        }

        // Look up hotel by API key
        $hotel = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, is_active
             FROM {$wpdb->prefix}loyalty_hotels
             WHERE api_key = %s",
            $api_key
        ));

        if (!$hotel) {
            return new WP_Error(
                'invalid_api_key',
                'Invalid API key',
                array('status' => 401)
            );
        }

        if (!$hotel->is_active) {
            return new WP_Error(
                'hotel_inactive',
                'Hotel is not active',
                array('status' => 403)
            );
        }

        // Store hotel_id on request for use by endpoint callbacks
        $request->set_param('_hotel_id', $hotel->id);
        $request->set_param('_hotel_name', $hotel->name);

        return true;
    }

    /**
     * POST /identify - Identify customer by RFID, QR code, or email
     *
     * Main endpoint for SambaPOS customer lookup.
     * Returns tier, discount rates, and available promos.
     *
     * Search order:
     * 1. RFID fobs (identifiers table)
     * 2. QR code (customers.qr_code column)
     * 3. Email (customers.email column)
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request The REST request
     *
     * @return WP_REST_Response|WP_Error Response with customer data
     */
    public static function identify_customer($request) {
        global $wpdb;

        $identifier = $request->get_param('identifier');
        $hotel_id = $request->get_param('_hotel_id');

        // Search for customer by identifier
        // Priority: 1) RFID fobs (identifiers table), 2) QR code (customer table), 3) Email
        $customer = null;
        $matched_type = null;
        $matched_label = null;

        // First, check the identifiers table for RFID fobs
        $identifier_record = $wpdb->get_row($wpdb->prepare(
            "SELECT ci.customer_id, ci.identifier_type, ci.label
             FROM {$wpdb->prefix}loyalty_customer_identifiers ci
             WHERE ci.identifier_value = %s AND ci.is_active = 1 AND ci.identifier_type = 'rfid'",
            $identifier
        ));

        if ($identifier_record) {
            // Found RFID in identifiers table, get the customer
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}loyalty_customers
                 WHERE id = %d AND is_active = 1",
                $identifier_record->customer_id
            ));
            if ($customer) {
                $matched_type = 'rfid';
                $matched_label = $identifier_record->label;
            }
        }

        // If not found, try QR code lookup on customers table
        if (!$customer) {
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}loyalty_customers
                 WHERE qr_code = %s AND is_active = 1",
                $identifier
            ));
            if ($customer) {
                $matched_type = 'qr';
            }
        }

        // If not found, try email lookup
        if (!$customer) {
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}loyalty_customers
                 WHERE email = %s AND is_active = 1",
                $identifier
            ));
            if ($customer) {
                $matched_type = 'email';
            }
        }

        if (!$customer) {
            return new WP_Error(
                'customer_not_found',
                'Customer not found',
                array('status' => 404)
            );
        }

        // Calculate tier and discounts
        $tier_result = Loyalty_Hub_Tier_Calculator::calculate($customer->id, $hotel_id);

        if (is_wp_error($tier_result)) {
            return $tier_result;
        }

        // Get available promos for this customer (not for staff)
        $available_promos = array();
        if (!$tier_result['is_staff']) {
            $available_promos = Loyalty_Hub_Promo_Handler::get_available_promos(
                $customer->id,
                $hotel_id
            );
        }

        // Get next tier info (only for non-staff)
        $next_tier_info = null;
        if (!$tier_result['is_staff']) {
            $next_tier_info = Loyalty_Hub_Tier_Calculator::get_next_tier_info(
                $customer->id,
                $hotel_id
            );
        }

        // Build response
        $response = array(
            'customer_id'      => $customer->id,
            'name'             => $customer->name,
            'email'            => $customer->email,
            'tier'             => $tier_result['tier'],
            'is_staff'         => $tier_result['is_staff'],
            'wet_discount'     => $tier_result['wet_discount'],
            'dry_discount'     => $tier_result['dry_discount'],
            'discount_type'    => $tier_result['discount_type'],
            'total_visits'     => $tier_result['total_visits'],
            'period_days'      => $tier_result['period_days'],
            'home_hotel'       => $tier_result['home_hotel'],
            'available_promos' => $available_promos,
            'next_tier'        => $next_tier_info,
        );

        // Add identifier info
        if ($matched_type) {
            $response['matched_identifier_type'] = $matched_type;
            if ($matched_label) {
                $response['identifier_label'] = $matched_label;
            }
        }

        return new WP_REST_Response($response, 200);
    }

    /**
     * POST /transaction - Log a completed transaction
     *
     * Records sales data for tier calculation and reporting.
     * Also updates customer product preferences.
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request The REST request
     *
     * @return WP_REST_Response|WP_Error Response confirming transaction logged
     */
    public static function log_transaction($request) {
        global $wpdb;

        $hotel_id = $request->get_param('_hotel_id');
        $customer_id = $request->get_param('customer_id');

        // Verify customer exists
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}loyalty_customers WHERE id = %d",
            $customer_id
        ));

        if (!$customer) {
            return new WP_Error(
                'customer_not_found',
                'Customer not found',
                array('status' => 404)
            );
        }

        // Insert transaction
        $transaction_data = array(
            'customer_id'     => $customer_id,
            'hotel_id'        => $hotel_id,
            'ticket_id'       => $request->get_param('ticket_id'),
            'total_amount'    => floatval($request->get_param('total_amount')),
            'wet_total'       => floatval($request->get_param('wet_total')),
            'dry_total'       => floatval($request->get_param('dry_total')),
            'discount_amount' => floatval($request->get_param('discount_amount')),
            'discount_type'   => $request->get_param('discount_type'),
            'tier_at_visit'   => $request->get_param('tier_at_visit'),
            'promo_code'      => $request->get_param('promo_code'),
        );

        $wpdb->insert(
            $wpdb->prefix . 'loyalty_transactions',
            $transaction_data,
            array('%d', '%d', '%s', '%f', '%f', '%f', '%f', '%s', '%s', '%s')
        );

        $transaction_id = $wpdb->insert_id;

        if (!$transaction_id) {
            return new WP_Error(
                'transaction_failed',
                'Failed to log transaction',
                array('status' => 500)
            );
        }

        // Log line items if provided
        $items = $request->get_param('items');
        if (!empty($items) && is_array($items)) {
            foreach ($items as $item) {
                $wpdb->insert(
                    $wpdb->prefix . 'loyalty_transaction_items',
                    array(
                        'transaction_id' => $transaction_id,
                        'product_name'   => sanitize_text_field($item['product_name'] ?? ''),
                        'product_group'  => sanitize_text_field($item['product_group'] ?? ''),
                        'quantity'       => floatval($item['quantity'] ?? 1),
                        'price'          => floatval($item['price'] ?? 0),
                        'is_wet'         => intval($item['is_wet'] ?? 0),
                    ),
                    array('%d', '%s', '%s', '%f', '%f', '%d')
                );

                // Update customer preferences
                self::update_customer_preference(
                    $customer_id,
                    $hotel_id,
                    sanitize_text_field($item['product_name'] ?? ''),
                    sanitize_text_field($item['product_group'] ?? ''),
                    intval($item['quantity'] ?? 1)
                );
            }
        }

        // Track promo usage if a promo was used
        $promo_code = $request->get_param('promo_code');
        if (!empty($promo_code)) {
            Loyalty_Hub_Promo_Handler::record_usage(
                $promo_code,
                $customer_id,
                $transaction_id,
                floatval($request->get_param('discount_amount'))
            );
        }

        return new WP_REST_Response(array(
            'success'        => true,
            'transaction_id' => $transaction_id,
            'message'        => 'Transaction logged successfully',
        ), 201);
    }

    /**
     * Update customer product preference
     *
     * Increments the purchase count for a product and updates
     * the last_purchased timestamp. Creates the record if new.
     *
     * @since 1.0.0
     *
     * @param int    $customer_id   Customer ID
     * @param int    $hotel_id      Hotel ID
     * @param string $product_name  Product name
     * @param string $product_group Product group/category
     * @param int    $quantity      Quantity purchased
     */
    private static function update_customer_preference($customer_id, $hotel_id, $product_name, $product_group, $quantity) {
        global $wpdb;

        if (empty($product_name)) {
            return;
        }

        $table = $wpdb->prefix . 'loyalty_customer_preferences';

        // Try to update existing preference
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE $table
             SET purchase_count = purchase_count + %d,
                 last_purchased = NOW()
             WHERE customer_id = %d
               AND hotel_id = %d
               AND product_name = %s",
            $quantity,
            $customer_id,
            $hotel_id,
            $product_name
        ));

        // If no row updated, insert new preference
        if ($updated === 0) {
            $wpdb->insert(
                $table,
                array(
                    'customer_id'    => $customer_id,
                    'hotel_id'       => $hotel_id,
                    'product_name'   => $product_name,
                    'product_group'  => $product_group,
                    'purchase_count' => $quantity,
                    'last_purchased' => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%s', '%d', '%s')
            );
        }
    }

    /**
     * POST /register - Register a new customer
     *
     * Creates a new customer account.
     * Home hotel is set to the hotel making the API call.
     * QR code is stored on customer table (single per customer).
     * RFID fobs are stored in identifiers table (multiple allowed).
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request The REST request
     *
     * @return WP_REST_Response|WP_Error Response with new customer data
     */
    public static function register_customer($request) {
        global $wpdb;

        $hotel_id = $request->get_param('_hotel_id');
        $name = $request->get_param('name');
        $email = $request->get_param('email');
        $rfid_code = $request->get_param('rfid_code');

        // Check for duplicate email (only among active customers)
        if (!empty($email)) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}loyalty_customers WHERE email = %s AND is_active = 1",
                $email
            ));
            if ($existing) {
                return new WP_Error(
                    'duplicate_email',
                    'A customer with this email already exists',
                    array('status' => 409)
                );
            }
        }

        // Check for duplicate RFID in identifiers table
        if (!empty($rfid_code)) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}loyalty_customer_identifiers WHERE identifier_value = %s",
                $rfid_code
            ));
            if ($existing) {
                return new WP_Error(
                    'duplicate_rfid',
                    'This RFID code is already registered',
                    array('status' => 409)
                );
            }
        }

        // Generate unique QR code (stored on customer table)
        $qr_code = self::generate_qr_code();

        // Insert customer with QR code
        $customer_data = array(
            'home_hotel_id' => $hotel_id,
            'name'          => $name,
            'email'         => $email,
            'phone'         => $request->get_param('phone'),
            'dob'           => $request->get_param('dob'),
            'qr_code'       => $qr_code,
            'is_staff'      => 0,
            'is_active'     => 1,
        );

        $wpdb->insert(
            $wpdb->prefix . 'loyalty_customers',
            $customer_data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d')
        );

        $customer_id = $wpdb->insert_id;

        if (!$customer_id) {
            return new WP_Error(
                'registration_failed',
                'Failed to register customer',
                array('status' => 500)
            );
        }

        // Insert RFID fob into identifiers table if provided
        if (!empty($rfid_code)) {
            $wpdb->insert(
                $wpdb->prefix . 'loyalty_customer_identifiers',
                array(
                    'customer_id'      => $customer_id,
                    'identifier_type'  => 'rfid',
                    'identifier_value' => $rfid_code,
                    'label'            => 'Primary RFID Fob',
                    'is_active'        => 1,
                ),
                array('%d', '%s', '%s', '%s', '%d')
            );
        }

        // Get the home hotel name
        $hotel = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}loyalty_hotels WHERE id = %d",
            $hotel_id
        ));

        return new WP_REST_Response(array(
            'success'     => true,
            'customer_id' => $customer_id,
            'qr_code'     => $qr_code,
            'name'        => $name,
            'home_hotel'  => $hotel ? $hotel->name : null,
            'tier'        => 'Member',
            'message'     => 'Customer registered successfully',
        ), 201);
    }

    /**
     * Generate a unique QR code for a customer
     *
     * Creates a random alphanumeric string that can be used
     * to identify the customer via QR code in the app.
     * Checks the customers table for uniqueness.
     *
     * @since 1.0.0
     *
     * @return string Unique QR code
     */
    private static function generate_qr_code() {
        global $wpdb;

        // Generate unique code - check customers table
        do {
            $qr_code = 'LH' . strtoupper(wp_generate_password(12, false, false));
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}loyalty_customers WHERE qr_code = %s",
                $qr_code
            ));
        } while ($exists);

        return $qr_code;
    }

    /**
     * GET /sync - Bulk sync customers for offline caching
     *
     * Returns all active customers with their QR codes and RFID fobs.
     * Optionally filtered by updated_since for incremental syncs.
     * QR code comes from customer table, RFID fobs from identifiers table.
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request The REST request
     *
     * @return WP_REST_Response Response with customer list
     */
    public static function sync_customers($request) {
        global $wpdb;

        $updated_since = $request->get_param('updated_since');

        // Build customer query (qr_code now on customer table)
        $sql = "SELECT id, name, email, qr_code, is_staff, home_hotel_id, updated_at
                FROM {$wpdb->prefix}loyalty_customers
                WHERE is_active = 1";

        if (!empty($updated_since)) {
            $sql .= $wpdb->prepare(" AND updated_at > %s", $updated_since);
        }

        $sql .= " ORDER BY id ASC";

        $customers = $wpdb->get_results($sql);

        // Attach RFID fobs to each customer (QR code is already on customer)
        foreach ($customers as &$customer) {
            $rfid_fobs = $wpdb->get_results($wpdb->prepare(
                "SELECT identifier_type, identifier_value, label
                 FROM {$wpdb->prefix}loyalty_customer_identifiers
                 WHERE customer_id = %d AND is_active = 1 AND identifier_type = 'rfid'",
                $customer->id
            ));
            $customer->rfid_fobs = $rfid_fobs;
        }

        return new WP_REST_Response(array(
            'customers' => $customers,
            'count'     => count($customers),
            'sync_time' => current_time('mysql'),
        ), 200);
    }

    /**
     * POST /identifier/add - Add an RFID fob to a customer
     *
     * Allows adding additional RFID fobs to an existing customer.
     * Useful for couples or families sharing a loyalty account.
     * Note: QR codes are auto-generated on customer table, not added via API.
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request The REST request
     *
     * @return WP_REST_Response|WP_Error Response with identifier data
     */
    public static function add_identifier($request) {
        global $wpdb;

        $customer_id = $request->get_param('customer_id');
        $identifier = $request->get_param('identifier');
        $label = $request->get_param('label');

        // Only RFID fobs can be added via API (QR codes are on customer table)
        $type = 'rfid';

        // Check customer exists
        $customer = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}loyalty_customers WHERE id = %d AND is_active = 1",
            $customer_id
        ));

        if (!$customer) {
            return new WP_Error(
                'customer_not_found',
                'Customer not found',
                array('status' => 404)
            );
        }

        // Check RFID doesn't already exist
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}loyalty_customer_identifiers WHERE identifier_value = %s",
            $identifier
        ));

        if ($existing) {
            return new WP_Error(
                'duplicate_rfid',
                'This RFID code is already registered',
                array('status' => 409)
            );
        }

        // Insert RFID fob
        $wpdb->insert(
            $wpdb->prefix . 'loyalty_customer_identifiers',
            array(
                'customer_id'      => $customer_id,
                'identifier_type'  => $type,
                'identifier_value' => $identifier,
                'label'            => $label ?: null,
                'is_active'        => 1,
            ),
            array('%d', '%s', '%s', '%s', '%d')
        );

        $identifier_id = $wpdb->insert_id;

        if (!$identifier_id) {
            return new WP_Error(
                'insert_failed',
                'Failed to add RFID fob',
                array('status' => 500)
            );
        }

        // Update customer's updated_at timestamp for sync purposes
        $wpdb->update(
            $wpdb->prefix . 'loyalty_customers',
            array('updated_at' => current_time('mysql')),
            array('id' => $customer_id),
            array('%s'),
            array('%d')
        );

        return new WP_REST_Response(array(
            'success'       => true,
            'identifier_id' => $identifier_id,
            'customer_id'   => $customer_id,
            'type'          => $type,
            'value'         => $identifier,
            'label'         => $label,
            'message'       => 'RFID fob added successfully',
        ), 201);
    }

    /**
     * POST /promos/validate - Validate a promo code
     *
     * Checks if a promo code is valid for use.
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request The REST request
     *
     * @return WP_REST_Response Response with validation result
     */
    public static function validate_promo($request) {
        $code = $request->get_param('code');
        $customer_id = $request->get_param('customer_id');
        $total_amount = $request->get_param('total_amount');
        $hotel_id = $request->get_param('_hotel_id');

        $result = Loyalty_Hub_Promo_Handler::validate(
            $code,
            $customer_id,
            $hotel_id,
            $total_amount
        );

        return new WP_REST_Response($result, $result['valid'] ? 200 : 400);
    }

    /**
     * POST /promos/apply - Apply a promo code
     *
     * Gets the discount rates for a promo code.
     * For loyalty_bonus: Returns boosted tier discount.
     * For promo_code: Returns the promo's fixed discount.
     *
     * @since 1.0.0
     *
     * @param WP_REST_Request $request The REST request
     *
     * @return WP_REST_Response Response with discount rates
     */
    public static function apply_promo($request) {
        $code = $request->get_param('code');
        $customer_id = $request->get_param('customer_id');
        $base_wet = $request->get_param('base_wet_discount');
        $base_dry = $request->get_param('base_dry_discount');
        $hotel_id = $request->get_param('_hotel_id');

        $result = Loyalty_Hub_Promo_Handler::apply(
            $code,
            $customer_id,
            $hotel_id,
            $base_wet,
            $base_dry
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response($result, 200);
    }
}
