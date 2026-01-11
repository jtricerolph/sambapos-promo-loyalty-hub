<?php
/**
 * Database Handler for Loyalty Hub
 *
 * This class manages all database operations for the loyalty system including:
 * - Table creation and schema management
 * - Default data insertion
 * - Database version tracking for migrations
 *
 * Database Schema Overview:
 * ========================
 *
 * loyalty_hotels          - Hotel/venue configurations with API keys
 * loyalty_tiers           - Global tier definitions (Member, Loyalty, Regular)
 * loyalty_hotel_tiers     - Per-hotel tier thresholds AND discount rates
 * loyalty_hotel_staff_rates - Per-hotel staff discount rates
 * loyalty_customers       - Customer profiles with home hotel assignment
 * loyalty_transactions    - Visit/spend history with wet/dry totals
 * loyalty_transaction_items - Line items for product preference tracking
 * loyalty_customer_preferences - Aggregated favorite products per hotel
 * loyalty_promos          - Promo/bonus definitions
 * loyalty_customer_promos - Targeted promo assignments to specific customers
 * loyalty_promo_usage     - Tracks each promo redemption for reporting
 *
 * Key Concepts:
 * ============
 *
 * 1. HOME HOTEL: Where customer registered. Used for tier threshold comparison.
 *
 * 2. TIER CALCULATION:
 *    - Visits count GLOBALLY (all hotels)
 *    - Tier = BEST of (home hotel threshold, visiting hotel threshold)
 *    - Rates = From VISITING hotel for that tier
 *
 * 3. DISCOUNT TYPES:
 *    - "discount" = Loyalty tier discounts (account codes #2303/#3303)
 *    - "promo" = One-off promo codes (account codes #2305/#3305)
 *    - "staff" = Staff discounts (account codes #2306/#3306)
 *
 * @package Loyalty_Hub
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Loyalty_Hub_Database {

    /**
     * Create all required database tables
     *
     * Uses WordPress dbDelta() for safe table creation/updates.
     * This method is called on plugin activation and can be called
     * again safely for schema updates.
     *
     * @since 1.0.0
     * @return void
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        /*
         * =================================================================
         * HOTELS TABLE
         * =================================================================
         * Stores hotel/venue configurations.
         * Each hotel has a unique API key for SambaPOS authentication.
         *
         * Fields:
         * - name: Display name (e.g., "High Street Hotel")
         * - slug: URL-safe identifier (e.g., "high-street")
         * - api_key: 64-char key for API authentication
         * - address: Optional address for reference
         * - is_active: Soft delete flag
         */
        $table_hotels = $wpdb->prefix . 'loyalty_hotels';
        $sql_hotels = "CREATE TABLE $table_hotels (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(50) NOT NULL,
            api_key varchar(64) NOT NULL,
            address text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY api_key (api_key),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        dbDelta($sql_hotels);

        /*
         * =================================================================
         * TIERS TABLE (Global Definitions)
         * =================================================================
         * Defines the tier names used across all hotels.
         * This ensures consistency in tier naming.
         *
         * Default tiers:
         * - Member (sort_order: 1) - Base tier, everyone starts here
         * - Loyalty (sort_order: 2) - Mid tier, earned by visits
         * - Regular (sort_order: 3) - Top tier, most visits
         *
         * sort_order is used to determine which tier is "better" when
         * comparing home vs visiting hotel qualification.
         */
        $table_tiers = $wpdb->prefix . 'loyalty_tiers';
        $sql_tiers = "CREATE TABLE $table_tiers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(50) NOT NULL,
            slug varchar(50) NOT NULL,
            sort_order int NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        dbDelta($sql_tiers);

        /*
         * =================================================================
         * HOTEL TIERS TABLE (Per-Hotel Configuration)
         * =================================================================
         * Each hotel can set their own:
         * - visits_required: How many visits needed for this tier
         * - period_days: Rolling window for counting visits (default 28)
         * - wet_discount: Discount % for drinks at this tier
         * - dry_discount: Discount % for food at this tier
         *
         * Example configurations:
         *
         * High Street Hotel (busy venue):
         * - Member: 0 visits, 5% wet, 10% dry
         * - Loyalty: 4 visits in 28 days, 8% wet, 15% dry
         * - Regular: 8 visits in 28 days, 12% wet, 20% dry
         *
         * Number Four (quieter venue, more generous):
         * - Member: 0 visits, 5% wet, 10% dry
         * - Loyalty: 2 visits in 28 days, 10% wet, 20% dry
         * - Regular: 4 visits in 28 days, 15% wet, 25% dry
         *
         * UNIQUE KEY on (hotel_id, tier_id) ensures each hotel can only
         * have one configuration per tier.
         */
        $table_hotel_tiers = $wpdb->prefix . 'loyalty_hotel_tiers';
        $sql_hotel_tiers = "CREATE TABLE $table_hotel_tiers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            hotel_id bigint(20) unsigned NOT NULL,
            tier_id bigint(20) unsigned NOT NULL,
            visits_required int NOT NULL DEFAULT 0,
            period_days int NOT NULL DEFAULT 28,
            wet_discount decimal(5,2) NOT NULL DEFAULT 0.00,
            dry_discount decimal(5,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            UNIQUE KEY hotel_tier (hotel_id, tier_id),
            KEY hotel_id (hotel_id),
            KEY tier_id (tier_id)
        ) $charset_collate;";
        dbDelta($sql_hotel_tiers);

        /*
         * =================================================================
         * HOTEL STAFF RATES TABLE
         * =================================================================
         * Separate discount rates for staff members.
         * Staff status is manually assigned (is_staff flag on customer).
         *
         * When is_staff = true:
         * - Normal tier calculation is bypassed
         * - Staff discount rates from VISITING hotel are used
         * - Uses separate Newbook account codes (#2306/#3306)
         *
         * Example:
         * - High Street: 25% wet, 30% dry
         * - Number Four: 30% wet, 35% dry
         */
        $table_staff_rates = $wpdb->prefix . 'loyalty_hotel_staff_rates';
        $sql_staff_rates = "CREATE TABLE $table_staff_rates (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            hotel_id bigint(20) unsigned NOT NULL,
            wet_discount decimal(5,2) NOT NULL DEFAULT 0.00,
            dry_discount decimal(5,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id),
            UNIQUE KEY hotel_id (hotel_id)
        ) $charset_collate;";
        dbDelta($sql_staff_rates);

        /*
         * =================================================================
         * CUSTOMERS TABLE
         * =================================================================
         * Central customer database across all hotels.
         *
         * Key fields:
         * - home_hotel_id: Where customer registered (affects tier calc)
         * - rfid_code: Physical fob identifier
         * - qr_code: App-generated QR code
         * - is_staff: Manual flag for staff discounts
         *
         * Identification can be via RFID fob OR QR code.
         * Both must be unique across the system.
         *
         * HOME HOTEL is important for tier calculation:
         * - Customer's tier is the BEST of their home hotel threshold
         *   vs the visiting hotel threshold
         * - This rewards registering at venues with lower requirements
         */
        $table_customers = $wpdb->prefix . 'loyalty_customers';
        $sql_customers = "CREATE TABLE $table_customers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            home_hotel_id bigint(20) unsigned NOT NULL,
            name varchar(100) NOT NULL,
            email varchar(255),
            phone varchar(20),
            dob date,
            rfid_code varchar(50),
            qr_code varchar(100),
            is_staff tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY rfid_code (rfid_code),
            UNIQUE KEY qr_code (qr_code),
            KEY home_hotel_id (home_hotel_id),
            KEY email (email)
        ) $charset_collate;";
        dbDelta($sql_customers);

        /*
         * =================================================================
         * TRANSACTIONS TABLE
         * =================================================================
         * Records every visit/purchase for tier calculation and reporting.
         *
         * Key fields:
         * - customer_id: Who made the purchase
         * - hotel_id: WHERE the purchase was made (not home hotel)
         * - wet_total/dry_total: Breakdown for wet vs dry reporting
         * - discount_type: "discount", "promo", or "staff"
         * - tier_at_visit: What tier customer had at time of visit
         * - promo_code: If a promo was used
         *
         * IMPORTANT: Visits are counted GLOBALLY across all hotels
         * for tier calculation, but this table tracks WHERE each
         * transaction occurred.
         */
        $table_transactions = $wpdb->prefix . 'loyalty_transactions';
        $sql_transactions = "CREATE TABLE $table_transactions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            hotel_id bigint(20) unsigned NOT NULL,
            ticket_id varchar(50),
            total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            wet_total decimal(10,2) NOT NULL DEFAULT 0.00,
            dry_total decimal(10,2) NOT NULL DEFAULT 0.00,
            discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            discount_type varchar(20),
            tier_at_visit varchar(50),
            promo_code varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY hotel_id (hotel_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql_transactions);

        /*
         * =================================================================
         * TRANSACTION ITEMS TABLE
         * =================================================================
         * Line item details for product preference tracking.
         *
         * Used to build customer preferences over time:
         * - Track favorite drinks/foods per customer per hotel
         * - Enable personalized notifications
         *   e.g., "We miss you! How about a pint of [favorite drink]?"
         *
         * is_wet flag distinguishes drinks from food for preference
         * grouping and notification personalization.
         */
        $table_items = $wpdb->prefix . 'loyalty_transaction_items';
        $sql_items = "CREATE TABLE $table_items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            transaction_id bigint(20) unsigned NOT NULL,
            product_name varchar(100) NOT NULL,
            product_group varchar(100),
            quantity decimal(10,2) NOT NULL DEFAULT 1,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            is_wet tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY transaction_id (transaction_id)
        ) $charset_collate;";
        dbDelta($sql_items);

        /*
         * =================================================================
         * CUSTOMER PREFERENCES TABLE
         * =================================================================
         * Aggregated favorite products per customer per hotel.
         *
         * Updated via background process or trigger when transactions
         * are logged. Tracks:
         * - purchase_count: How many times product was bought
         * - last_purchased: When they last bought it
         *
         * Enables queries like:
         * - "What's John's favorite drink at High Street?"
         * - "Who hasn't bought their usual in 2 weeks?"
         */
        $table_prefs = $wpdb->prefix . 'loyalty_customer_preferences';
        $sql_prefs = "CREATE TABLE $table_prefs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            hotel_id bigint(20) unsigned NOT NULL,
            product_name varchar(100) NOT NULL,
            product_group varchar(100),
            purchase_count int NOT NULL DEFAULT 0,
            last_purchased datetime,
            PRIMARY KEY (id),
            UNIQUE KEY customer_hotel_product (customer_id, hotel_id, product_name),
            KEY customer_id (customer_id)
        ) $charset_collate;";
        dbDelta($sql_prefs);

        /*
         * =================================================================
         * PROMOS TABLE
         * =================================================================
         * Promo and loyalty bonus definitions.
         *
         * Two types:
         * 1. loyalty_bonus: Increases tier discount % (uses #2303/#3303)
         *    - bonus_multiplier: e.g., 2.0 for "double discount"
         *    - Requires membership (requires_membership = 1)
         *
         * 2. promo_code: One-off discount (uses #2305/#3305)
         *    - wet_discount/dry_discount: Fixed percentages
         *    - Works for anyone (requires_membership = 0)
         *
         * Restrictions:
         * - hotel_id: NULL = all hotels, or specific hotel
         * - valid_from/valid_until: Date range
         * - time_start/time_end: Time of day (e.g., lunch specials)
         * - valid_days: Comma-separated days (e.g., "Mon,Tue,Wed")
         * - max_uses: Total redemptions allowed
         * - max_uses_per_customer: Per-customer limit
         * - min_spend: Minimum transaction amount
         */
        $table_promos = $wpdb->prefix . 'loyalty_promos';
        $sql_promos = "CREATE TABLE $table_promos (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            name varchar(100) NOT NULL,
            description text,
            type enum('loyalty_bonus', 'promo_code') NOT NULL DEFAULT 'promo_code',
            hotel_id bigint(20) unsigned DEFAULT NULL,
            wet_discount decimal(5,2) DEFAULT NULL,
            dry_discount decimal(5,2) DEFAULT NULL,
            bonus_multiplier decimal(3,2) DEFAULT NULL,
            min_spend decimal(10,2) DEFAULT NULL,
            valid_from datetime,
            valid_until datetime,
            time_start time,
            time_end time,
            valid_days varchar(20),
            max_uses int DEFAULT NULL,
            max_uses_per_customer int DEFAULT NULL,
            requires_membership tinyint(1) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY hotel_id (hotel_id),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql_promos);

        /*
         * =================================================================
         * CUSTOMER PROMOS TABLE
         * =================================================================
         * Targeted promo assignments to specific customers.
         *
         * Used for:
         * - Birthday promos assigned to individual customers
         * - "Win back" promos for lapsed customers
         * - Tier upgrade celebration promos
         *
         * These show up in the available_promos array returned
         * by the /identify endpoint.
         */
        $table_customer_promos = $wpdb->prefix . 'loyalty_customer_promos';
        $sql_customer_promos = "CREATE TABLE $table_customer_promos (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            promo_id bigint(20) unsigned NOT NULL,
            assigned_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime,
            PRIMARY KEY (id),
            UNIQUE KEY customer_promo (customer_id, promo_id),
            KEY customer_id (customer_id)
        ) $charset_collate;";
        dbDelta($sql_customer_promos);

        /*
         * =================================================================
         * PROMO USAGE TABLE
         * =================================================================
         * Tracks every promo redemption for reporting and limits.
         *
         * Used to:
         * - Enforce max_uses and max_uses_per_customer limits
         * - Report on promo effectiveness
         * - Track discount amounts given per promo
         */
        $table_promo_usage = $wpdb->prefix . 'loyalty_promo_usage';
        $sql_promo_usage = "CREATE TABLE $table_promo_usage (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            promo_id bigint(20) unsigned NOT NULL,
            customer_id bigint(20) unsigned,
            transaction_id bigint(20) unsigned,
            discount_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            used_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY promo_id (promo_id),
            KEY customer_id (customer_id)
        ) $charset_collate;";
        dbDelta($sql_promo_usage);

        // Store database version for future migrations
        update_option('loyalty_hub_db_version', LOYALTY_HUB_VERSION);
    }

    /**
     * Insert default tier data
     *
     * Creates the three standard tiers:
     * - Member (base tier)
     * - Loyalty (mid tier)
     * - Regular (top tier)
     *
     * Only runs if tiers table is empty (first activation).
     *
     * @since 1.0.0
     * @return void
     */
    public static function insert_default_data() {
        global $wpdb;
        $table_tiers = $wpdb->prefix . 'loyalty_tiers';

        // Check if tiers already exist - don't duplicate
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $table_tiers");
        if ($existing > 0) {
            return;
        }

        // Insert default tiers with ascending sort_order
        // Higher sort_order = better tier
        $wpdb->insert($table_tiers, array(
            'name' => 'Member',
            'slug' => 'member',
            'sort_order' => 1
        ));

        $wpdb->insert($table_tiers, array(
            'name' => 'Loyalty',
            'slug' => 'loyalty',
            'sort_order' => 2
        ));

        $wpdb->insert($table_tiers, array(
            'name' => 'Regular',
            'slug' => 'regular',
            'sort_order' => 3
        ));
    }

    /**
     * Drop all tables
     *
     * WARNING: This permanently deletes all loyalty data.
     * Use with extreme caution - typically only for development
     * or complete plugin removal.
     *
     * Tables are dropped in reverse order to respect foreign key
     * dependencies (child tables first).
     *
     * @since 1.0.0
     * @return void
     */
    public static function drop_tables() {
        global $wpdb;

        // Drop in reverse dependency order
        $tables = array(
            'loyalty_promo_usage',
            'loyalty_customer_promos',
            'loyalty_promos',
            'loyalty_customer_preferences',
            'loyalty_transaction_items',
            'loyalty_transactions',
            'loyalty_customers',
            'loyalty_hotel_staff_rates',
            'loyalty_hotel_tiers',
            'loyalty_tiers',
            'loyalty_hotels'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }

        delete_option('loyalty_hub_db_version');
    }
}
