<?php
/**
 * Plugin Name: SambaPOS Loyalty Hub
 * Plugin URI: https://github.com/jtricerolph/sambapos-promo-loyalty-hub
 * Description: Centralized loyalty system for multi-hotel SambaPOS integration
 * Version: 1.0.0
 * Author: JTR
 * License: GPL v2 or later
 * Text Domain: loyalty-hub
 *
 * ==========================================================================
 * SAMBAPOS LOYALTY HUB
 * ==========================================================================
 *
 * A centralized loyalty platform for managing customer discounts across
 * multiple hotel venues using SambaPOS point-of-sale systems.
 *
 * KEY FEATURES:
 * - Centralized customer database across all hotels
 * - RFID fob and QR code identification
 * - Per-hotel tier thresholds AND discount rates
 * - Global visit counting with "best-tier" calculation
 * - Staff discounts with separate reporting
 * - Loyalty bonuses and promo codes
 * - Product preference tracking for personalization
 * - Offline caching support
 *
 * TIER CALCULATION LOGIC:
 * 1. Visits count GLOBALLY (all hotels combined)
 * 2. Customer's tier = BEST of (home hotel threshold, visiting hotel threshold)
 * 3. Discount rates = from the VISITING hotel for that tier
 *
 * DISCOUNT TYPES & ACCOUNT CODES:
 * - "discount" -> #2303/#3303 (loyalty tier discounts)
 * - "promo" -> #2305/#3305 (one-off promo codes)
 * - "staff" -> #2306/#3306 (staff discounts)
 *
 * API ENDPOINTS:
 * - POST /wp-json/loyalty/v1/identify - Look up customer
 * - POST /wp-json/loyalty/v1/transaction - Log sale
 * - POST /wp-json/loyalty/v1/register - Register customer
 * - GET /wp-json/loyalty/v1/sync - Bulk sync for offline cache
 * - POST /wp-json/loyalty/v1/promos/validate - Validate promo code
 * - POST /wp-json/loyalty/v1/promos/apply - Apply promo code
 *
 * FILE STRUCTURE:
 * - loyalty-hub.php (this file) - Main plugin bootstrap
 * - includes/class-database.php - Database schema and operations
 * - includes/class-api.php - REST API endpoints
 * - includes/class-tier-calculator.php - Tier calculation logic
 * - includes/class-promo-handler.php - Promo validation and application
 * - admin/class-admin.php - WordPress admin interface
 * - admin/views/ - Admin page templates
 * - admin/css/ - Admin styles
 * - admin/js/ - Admin JavaScript
 * - docs/ - Documentation and integration examples
 *
 * @package Loyalty_Hub
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// ==========================================================================
// PLUGIN CONSTANTS
// ==========================================================================

/**
 * Plugin version number
 * Used for database versioning and cache busting
 */
define('LOYALTY_HUB_VERSION', '1.0.0');

/**
 * Plugin directory path (with trailing slash)
 * Used for including PHP files
 */
define('LOYALTY_HUB_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Plugin URL path (with trailing slash)
 * Used for enqueuing assets
 */
define('LOYALTY_HUB_PLUGIN_URL', plugin_dir_url(__FILE__));

// ==========================================================================
// MAIN PLUGIN CLASS
// ==========================================================================

/**
 * Main Loyalty Hub Plugin Class
 *
 * Singleton pattern to ensure only one instance runs.
 * Handles plugin initialization, dependency loading, and hook registration.
 *
 * @since 1.0.0
 */
class Loyalty_Hub {

    /**
     * Singleton instance
     *
     * @var Loyalty_Hub|null
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * Ensures only one instance of the plugin runs.
     * Creates instance if it doesn't exist.
     *
     * @since 1.0.0
     * @return Loyalty_Hub The singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * Private to enforce singleton pattern.
     * Loads dependencies and initializes hooks.
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required class files
     *
     * Includes all plugin classes. Admin classes only loaded
     * when in WordPress admin context.
     *
     * @since 1.0.0
     */
    private function load_dependencies() {
        // Core classes - always loaded
        require_once LOYALTY_HUB_PLUGIN_DIR . 'includes/class-database.php';
        require_once LOYALTY_HUB_PLUGIN_DIR . 'includes/class-api.php';
        require_once LOYALTY_HUB_PLUGIN_DIR . 'includes/class-tier-calculator.php';
        require_once LOYALTY_HUB_PLUGIN_DIR . 'includes/class-promo-handler.php';

        // Admin classes - only in admin context
        if (is_admin()) {
            require_once LOYALTY_HUB_PLUGIN_DIR . 'admin/class-admin.php';
        }
    }

    /**
     * Initialize WordPress hooks
     *
     * Registers actions and filters for plugin functionality.
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Register REST API routes on rest_api_init
        add_action('rest_api_init', array($this, 'register_api_routes'));

        // Initialize admin interface
        if (is_admin()) {
            Loyalty_Hub_Admin::get_instance();
        }
    }

    /**
     * Register REST API routes
     *
     * Called on rest_api_init action.
     * Delegates to the API class for route registration.
     *
     * @since 1.0.0
     */
    public function register_api_routes() {
        Loyalty_Hub_API::register_routes();
    }

    /**
     * Plugin activation handler
     *
     * Called when plugin is activated.
     * Creates database tables and inserts default data.
     *
     * @since 1.0.0
     */
    public static function activate() {
        // Load database class (not loaded yet during activation)
        require_once LOYALTY_HUB_PLUGIN_DIR . 'includes/class-database.php';

        // Create database tables
        Loyalty_Hub_Database::create_tables();

        // Insert default tier data (Member, Loyalty, Regular)
        Loyalty_Hub_Database::insert_default_data();

        // Flush rewrite rules for REST API
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation handler
     *
     * Called when plugin is deactivated.
     * Does NOT delete data - only cleans up rewrite rules.
     *
     * @since 1.0.0
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin uninstall handler
     *
     * Called when plugin is deleted.
     * Optionally drops all tables - currently disabled.
     * Uncomment the line below to delete data on uninstall.
     *
     * @since 1.0.0
     */
    public static function uninstall() {
        // WARNING: Uncomment to delete all data when plugin is removed
        // Loyalty_Hub_Database::drop_tables();
    }
}

// ==========================================================================
// PLUGIN LIFECYCLE HOOKS
// ==========================================================================

// Activation hook - runs when plugin is activated
register_activation_hook(__FILE__, array('Loyalty_Hub', 'activate'));

// Deactivation hook - runs when plugin is deactivated
register_deactivation_hook(__FILE__, array('Loyalty_Hub', 'deactivate'));

// ==========================================================================
// PLUGIN INITIALIZATION
// ==========================================================================

// Initialize plugin after all plugins are loaded
// This ensures compatibility with other plugins
add_action('plugins_loaded', array('Loyalty_Hub', 'get_instance'));
