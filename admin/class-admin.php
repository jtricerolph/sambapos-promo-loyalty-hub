<?php
/**
 * Admin Handler for Loyalty Hub
 *
 * Creates the WordPress admin interface for managing:
 * - Hotels (venues)
 * - Customers
 * - Tier configurations
 * - Promos
 * - Reports
 *
 * ==========================================================================
 * ADMIN MENU STRUCTURE
 * ==========================================================================
 *
 * Loyalty Hub (Dashboard)
 * ├── Hotels         - Manage venues and API keys
 * ├── Customers      - View/edit customer accounts
 * ├── Tiers          - Configure per-hotel tier thresholds and rates
 * ├── Promos         - Create and manage promos
 * └── Reports        - View loyalty statistics
 *
 * @package Loyalty_Hub
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Loyalty_Hub_Admin {

    /**
     * Singleton instance
     *
     * @var Loyalty_Hub_Admin
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Loyalty_Hub_Admin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - set up admin hooks
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'handle_form_submissions'));
    }

    /**
     * Add admin menu pages
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            'Loyalty Hub',                  // Page title
            'Loyalty Hub',                  // Menu title
            'manage_options',               // Capability
            'loyalty-hub',                  // Menu slug
            array($this, 'render_dashboard'), // Callback
            'dashicons-tickets-alt',        // Icon
            30                              // Position
        );

        // Dashboard (same as main)
        add_submenu_page(
            'loyalty-hub',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'loyalty-hub',
            array($this, 'render_dashboard')
        );

        // Hotels
        add_submenu_page(
            'loyalty-hub',
            'Hotels',
            'Hotels',
            'manage_options',
            'loyalty-hub-hotels',
            array($this, 'render_hotels')
        );

        // Customers
        add_submenu_page(
            'loyalty-hub',
            'Customers',
            'Customers',
            'manage_options',
            'loyalty-hub-customers',
            array($this, 'render_customers')
        );

        // Tiers
        add_submenu_page(
            'loyalty-hub',
            'Tier Configuration',
            'Tiers',
            'manage_options',
            'loyalty-hub-tiers',
            array($this, 'render_tiers')
        );

        // Promos
        add_submenu_page(
            'loyalty-hub',
            'Promos',
            'Promos',
            'manage_options',
            'loyalty-hub-promos',
            array($this, 'render_promos')
        );

        // Reports
        add_submenu_page(
            'loyalty-hub',
            'Reports',
            'Reports',
            'manage_options',
            'loyalty-hub-reports',
            array($this, 'render_reports')
        );
    }

    /**
     * Enqueue admin CSS and JS
     *
     * @since 1.0.0
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our pages
        if (strpos($hook, 'loyalty-hub') === false) {
            return;
        }

        wp_enqueue_style(
            'loyalty-hub-admin',
            LOYALTY_HUB_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            LOYALTY_HUB_VERSION
        );

        wp_enqueue_script(
            'loyalty-hub-admin',
            LOYALTY_HUB_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            LOYALTY_HUB_VERSION,
            true
        );
    }

    /**
     * Handle form submissions
     *
     * @since 1.0.0
     */
    public function handle_form_submissions() {
        // Verify we're on an admin page and have a form action
        if (!isset($_POST['loyalty_hub_action'])) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['loyalty_hub_nonce'] ?? '', 'loyalty_hub_admin')) {
            wp_die('Security check failed');
        }

        $action = sanitize_text_field($_POST['loyalty_hub_action']);

        switch ($action) {
            case 'add_hotel':
                $this->handle_add_hotel();
                break;
            case 'edit_hotel':
                $this->handle_edit_hotel();
                break;
            case 'save_hotel_tiers':
                $this->handle_save_hotel_tiers();
                break;
            case 'add_customer':
                $this->handle_add_customer();
                break;
            case 'edit_customer':
                $this->handle_edit_customer();
                break;
            case 'add_promo':
                $this->handle_add_promo();
                break;
            case 'edit_promo':
                $this->handle_edit_promo();
                break;
            case 'add_identifier':
                $this->handle_add_identifier();
                break;
            case 'delete_identifier':
                $this->handle_delete_identifier();
                break;
            case 'delete_customer':
                $this->handle_delete_customer();
                break;
        }
    }

    /**
     * Render Dashboard page
     *
     * Shows overview statistics:
     * - Total customers
     * - Customers by tier
     * - Recent transactions
     * - Active promos
     *
     * @since 1.0.0
     */
    public function render_dashboard() {
        global $wpdb;

        // Get stats
        $total_customers = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}loyalty_customers WHERE is_active = 1"
        );

        $total_hotels = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}loyalty_hotels WHERE is_active = 1"
        );

        $transactions_today = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}loyalty_transactions
             WHERE DATE(created_at) = CURDATE()"
        );

        $active_promos = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}loyalty_promos
             WHERE is_active = 1
               AND (valid_until IS NULL OR valid_until >= NOW())"
        );

        // Recent transactions
        $recent_transactions = $wpdb->get_results(
            "SELECT t.*, c.name as customer_name, h.name as hotel_name
             FROM {$wpdb->prefix}loyalty_transactions t
             LEFT JOIN {$wpdb->prefix}loyalty_customers c ON t.customer_id = c.id
             LEFT JOIN {$wpdb->prefix}loyalty_hotels h ON t.hotel_id = h.id
             ORDER BY t.created_at DESC
             LIMIT 10"
        );

        include LOYALTY_HUB_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Render Hotels page
     *
     * @since 1.0.0
     */
    public function render_hotels() {
        global $wpdb;

        $hotels = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}loyalty_hotels ORDER BY name ASC"
        );

        // Check if editing
        $editing = null;
        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
            $editing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}loyalty_hotels WHERE id = %d",
                intval($_GET['edit'])
            ));
        }

        include LOYALTY_HUB_PLUGIN_DIR . 'admin/views/hotels.php';
    }

    /**
     * Render Customers page
     *
     * @since 1.0.0
     */
    public function render_customers() {
        global $wpdb;

        // Pagination
        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;

        // Search
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // Build query - search includes identifiers table
        $where = "WHERE c.is_active = 1";
        if ($search) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where .= $wpdb->prepare(
                " AND (c.name LIKE %s OR c.email LIKE %s OR c.id IN (
                    SELECT customer_id FROM {$wpdb->prefix}loyalty_customer_identifiers
                    WHERE identifier_value LIKE %s
                ))",
                $search_like,
                $search_like,
                $search_like
            );
        }

        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}loyalty_customers c $where"
        );

        $customers = $wpdb->get_results(
            "SELECT c.*, h.name as home_hotel_name
             FROM {$wpdb->prefix}loyalty_customers c
             LEFT JOIN {$wpdb->prefix}loyalty_hotels h ON c.home_hotel_id = h.id
             $where
             ORDER BY c.name ASC
             LIMIT $per_page OFFSET $offset"
        );

        // Get identifiers for each customer (for display in list)
        foreach ($customers as &$customer) {
            $customer->identifiers = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}loyalty_customer_identifiers
                 WHERE customer_id = %d AND is_active = 1
                 ORDER BY identifier_type, created_at",
                $customer->id
            ));
        }

        $total_pages = ceil($total / $per_page);

        // Hotels for dropdown
        $hotels = $wpdb->get_results(
            "SELECT id, name FROM {$wpdb->prefix}loyalty_hotels WHERE is_active = 1 ORDER BY name"
        );

        // Check if editing
        $editing = null;
        $editing_identifiers = array();
        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
            $editing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}loyalty_customers WHERE id = %d",
                intval($_GET['edit'])
            ));
            if ($editing) {
                $editing_identifiers = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}loyalty_customer_identifiers
                     WHERE customer_id = %d
                     ORDER BY identifier_type, created_at",
                    $editing->id
                ));
            }
        }

        include LOYALTY_HUB_PLUGIN_DIR . 'admin/views/customers.php';
    }

    /**
     * Render Tiers configuration page
     *
     * @since 1.0.0
     */
    public function render_tiers() {
        global $wpdb;

        // Get all tiers
        $tiers = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}loyalty_tiers ORDER BY sort_order ASC"
        );

        // Get all hotels
        $hotels = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}loyalty_hotels WHERE is_active = 1 ORDER BY name ASC"
        );

        // Get all hotel tier configurations
        $hotel_tiers = $wpdb->get_results(
            "SELECT ht.*, h.name as hotel_name, t.name as tier_name, t.sort_order
             FROM {$wpdb->prefix}loyalty_hotel_tiers ht
             JOIN {$wpdb->prefix}loyalty_hotels h ON ht.hotel_id = h.id
             JOIN {$wpdb->prefix}loyalty_tiers t ON ht.tier_id = t.id
             ORDER BY h.name, t.sort_order"
        );

        // Get staff rates
        $staff_rates = $wpdb->get_results(
            "SELECT sr.*, h.name as hotel_name
             FROM {$wpdb->prefix}loyalty_hotel_staff_rates sr
             JOIN {$wpdb->prefix}loyalty_hotels h ON sr.hotel_id = h.id
             ORDER BY h.name"
        );

        include LOYALTY_HUB_PLUGIN_DIR . 'admin/views/tiers.php';
    }

    /**
     * Render Promos page
     *
     * @since 1.0.0
     */
    public function render_promos() {
        global $wpdb;

        $promos = $wpdb->get_results(
            "SELECT p.*, h.name as hotel_name,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}loyalty_promo_usage WHERE promo_id = p.id) as use_count
             FROM {$wpdb->prefix}loyalty_promos p
             LEFT JOIN {$wpdb->prefix}loyalty_hotels h ON p.hotel_id = h.id
             ORDER BY p.created_at DESC"
        );

        // Hotels for dropdown
        $hotels = $wpdb->get_results(
            "SELECT id, name FROM {$wpdb->prefix}loyalty_hotels WHERE is_active = 1 ORDER BY name"
        );

        // Check if editing
        $editing = null;
        if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
            $editing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}loyalty_promos WHERE id = %d",
                intval($_GET['edit'])
            ));
        }

        include LOYALTY_HUB_PLUGIN_DIR . 'admin/views/promos.php';
    }

    /**
     * Render Reports page
     *
     * @since 1.0.0
     */
    public function render_reports() {
        global $wpdb;

        // Date range
        $start_date = isset($_GET['start_date'])
            ? sanitize_text_field($_GET['start_date'])
            : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end_date'])
            ? sanitize_text_field($_GET['end_date'])
            : date('Y-m-d');

        // Tier distribution
        $tier_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT tier_at_visit as tier, COUNT(*) as count, SUM(total_amount) as total_sales
             FROM {$wpdb->prefix}loyalty_transactions
             WHERE DATE(created_at) BETWEEN %s AND %s
             GROUP BY tier_at_visit",
            $start_date, $end_date
        ));

        // Hotel breakdown
        $hotel_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT h.name as hotel, COUNT(t.id) as transactions,
                    SUM(t.total_amount) as total_sales,
                    SUM(t.discount_amount) as total_discounts
             FROM {$wpdb->prefix}loyalty_transactions t
             JOIN {$wpdb->prefix}loyalty_hotels h ON t.hotel_id = h.id
             WHERE DATE(t.created_at) BETWEEN %s AND %s
             GROUP BY t.hotel_id",
            $start_date, $end_date
        ));

        // Promo effectiveness
        $promo_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT p.code, p.name, COUNT(pu.id) as uses, SUM(pu.discount_amount) as total_discount
             FROM {$wpdb->prefix}loyalty_promos p
             LEFT JOIN {$wpdb->prefix}loyalty_promo_usage pu ON p.id = pu.promo_id
                AND DATE(pu.used_at) BETWEEN %s AND %s
             GROUP BY p.id
             HAVING uses > 0
             ORDER BY uses DESC",
            $start_date, $end_date
        ));

        // Lapsed customers (no visit in 30+ days)
        $lapsed_customers = $wpdb->get_results(
            "SELECT c.id, c.name, c.email, h.name as home_hotel,
                    MAX(t.created_at) as last_visit,
                    DATEDIFF(NOW(), MAX(t.created_at)) as days_since_visit
             FROM {$wpdb->prefix}loyalty_customers c
             LEFT JOIN {$wpdb->prefix}loyalty_hotels h ON c.home_hotel_id = h.id
             LEFT JOIN {$wpdb->prefix}loyalty_transactions t ON c.id = t.customer_id
             WHERE c.is_active = 1
             GROUP BY c.id
             HAVING days_since_visit >= 30 OR last_visit IS NULL
             ORDER BY days_since_visit DESC
             LIMIT 50"
        );

        include LOYALTY_HUB_PLUGIN_DIR . 'admin/views/reports.php';
    }

    // ======================================================================
    // Form Handlers
    // ======================================================================

    /**
     * Handle adding a new hotel
     */
    private function handle_add_hotel() {
        global $wpdb;

        $name = sanitize_text_field($_POST['hotel_name']);
        $slug = sanitize_title($_POST['hotel_slug'] ?? $name);
        $address = sanitize_textarea_field($_POST['hotel_address'] ?? '');

        // Generate API key
        $api_key = wp_generate_password(64, false, false);

        $wpdb->insert(
            $wpdb->prefix . 'loyalty_hotels',
            array(
                'name'    => $name,
                'slug'    => $slug,
                'api_key' => $api_key,
                'address' => $address,
            ),
            array('%s', '%s', '%s', '%s')
        );

        $hotel_id = $wpdb->insert_id;

        // Create default tier configurations for this hotel
        $tiers = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}loyalty_tiers ORDER BY sort_order");
        foreach ($tiers as $tier) {
            $wpdb->insert(
                $wpdb->prefix . 'loyalty_hotel_tiers',
                array(
                    'hotel_id'        => $hotel_id,
                    'tier_id'         => $tier->id,
                    'visits_required' => 0,
                    'period_days'     => 28,
                    'wet_discount'    => 0,
                    'dry_discount'    => 0,
                ),
                array('%d', '%d', '%d', '%d', '%f', '%f')
            );
        }

        // Create default staff rates
        $wpdb->insert(
            $wpdb->prefix . 'loyalty_hotel_staff_rates',
            array(
                'hotel_id'     => $hotel_id,
                'wet_discount' => 0,
                'dry_discount' => 0,
            ),
            array('%d', '%f', '%f')
        );

        wp_redirect(admin_url('admin.php?page=loyalty-hub-hotels&added=1'));
        exit;
    }

    /**
     * Handle editing a hotel
     */
    private function handle_edit_hotel() {
        global $wpdb;

        $id = intval($_POST['hotel_id']);
        $name = sanitize_text_field($_POST['hotel_name']);
        $slug = sanitize_title($_POST['hotel_slug']);
        $address = sanitize_textarea_field($_POST['hotel_address'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $wpdb->update(
            $wpdb->prefix . 'loyalty_hotels',
            array(
                'name'      => $name,
                'slug'      => $slug,
                'address'   => $address,
                'is_active' => $is_active,
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%d'),
            array('%d')
        );

        wp_redirect(admin_url('admin.php?page=loyalty-hub-hotels&updated=1'));
        exit;
    }

    /**
     * Handle saving hotel tier configurations
     */
    private function handle_save_hotel_tiers() {
        global $wpdb;

        $hotel_id = intval($_POST['hotel_id']);

        // Save tier configurations
        if (isset($_POST['tiers']) && is_array($_POST['tiers'])) {
            foreach ($_POST['tiers'] as $tier_id => $config) {
                $wpdb->replace(
                    $wpdb->prefix . 'loyalty_hotel_tiers',
                    array(
                        'hotel_id'        => $hotel_id,
                        'tier_id'         => intval($tier_id),
                        'visits_required' => intval($config['visits_required']),
                        'period_days'     => intval($config['period_days'] ?? 28),
                        'wet_discount'    => floatval($config['wet_discount']),
                        'dry_discount'    => floatval($config['dry_discount']),
                    ),
                    array('%d', '%d', '%d', '%d', '%f', '%f')
                );
            }
        }

        // Save staff rates
        if (isset($_POST['staff'])) {
            $wpdb->replace(
                $wpdb->prefix . 'loyalty_hotel_staff_rates',
                array(
                    'hotel_id'     => $hotel_id,
                    'wet_discount' => floatval($_POST['staff']['wet_discount']),
                    'dry_discount' => floatval($_POST['staff']['dry_discount']),
                ),
                array('%d', '%f', '%f')
            );
        }

        wp_redirect(admin_url('admin.php?page=loyalty-hub-tiers&updated=1&hotel=' . $hotel_id));
        exit;
    }

    /**
     * Handle adding a new customer
     */
    private function handle_add_customer() {
        global $wpdb;

        $email = sanitize_email($_POST['customer_email']);
        $rfid_code = sanitize_text_field($_POST['rfid_code'] ?? '');

        // Check for duplicate email
        if (!empty($email)) {
            $existing_email = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}loyalty_customers WHERE email = %s",
                $email
            ));
            if ($existing_email) {
                wp_redirect(admin_url('admin.php?page=loyalty-hub-customers&error=duplicate_email'));
                exit;
            }
        }

        // Check for duplicate RFID in identifiers table
        if (!empty($rfid_code)) {
            $existing_rfid = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}loyalty_customer_identifiers WHERE identifier_value = %s",
                $rfid_code
            ));
            if ($existing_rfid) {
                wp_redirect(admin_url('admin.php?page=loyalty-hub-customers&error=duplicate_rfid'));
                exit;
            }
        }

        // Insert customer (without rfid_code/qr_code - those go in identifiers table)
        $wpdb->insert(
            $wpdb->prefix . 'loyalty_customers',
            array(
                'home_hotel_id' => intval($_POST['home_hotel_id']),
                'name'          => sanitize_text_field($_POST['customer_name']),
                'email'         => $email ?: null,
                'phone'         => sanitize_text_field($_POST['customer_phone'] ?? ''),
                'dob'           => sanitize_text_field($_POST['customer_dob'] ?? null) ?: null,
                'is_staff'      => isset($_POST['is_staff']) ? 1 : 0,
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d')
        );

        $customer_id = $wpdb->insert_id;

        if (!$customer_id) {
            wp_redirect(admin_url('admin.php?page=loyalty-hub-customers&error=insert_failed'));
            exit;
        }

        // Generate and insert QR code
        $qr_code = 'LH' . strtoupper(wp_generate_password(12, false, false));
        $wpdb->insert(
            $wpdb->prefix . 'loyalty_customer_identifiers',
            array(
                'customer_id'      => $customer_id,
                'identifier_type'  => 'qr',
                'identifier_value' => $qr_code,
                'label'            => 'Primary QR Code',
                'is_active'        => 1,
            ),
            array('%d', '%s', '%s', '%s', '%d')
        );

        // Insert RFID if provided
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

        wp_redirect(admin_url('admin.php?page=loyalty-hub-customers&added=1'));
        exit;
    }

    /**
     * Handle editing a customer
     */
    private function handle_edit_customer() {
        global $wpdb;

        $id = intval($_POST['customer_id']);
        $email = sanitize_email($_POST['customer_email']);

        // Check for duplicate email (excluding current customer)
        if (!empty($email)) {
            $existing_email = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}loyalty_customers WHERE email = %s AND id != %d",
                $email,
                $id
            ));
            if ($existing_email) {
                wp_redirect(admin_url('admin.php?page=loyalty-hub-customers&edit=' . $id . '&error=duplicate_email'));
                exit;
            }
        }

        // Update customer (identifiers are managed separately)
        $wpdb->update(
            $wpdb->prefix . 'loyalty_customers',
            array(
                'home_hotel_id' => intval($_POST['home_hotel_id']),
                'name'          => sanitize_text_field($_POST['customer_name']),
                'email'         => $email ?: null,
                'phone'         => sanitize_text_field($_POST['customer_phone'] ?? ''),
                'dob'           => sanitize_text_field($_POST['customer_dob'] ?? null) ?: null,
                'is_staff'      => isset($_POST['is_staff']) ? 1 : 0,
                'is_active'     => isset($_POST['is_active']) ? 1 : 0,
            ),
            array('id' => $id),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%d'),
            array('%d')
        );

        wp_redirect(admin_url('admin.php?page=loyalty-hub-customers&updated=1'));
        exit;
    }

    /**
     * Handle deleting a customer (soft delete)
     */
    private function handle_delete_customer() {
        global $wpdb;

        $id = intval($_POST['customer_id']);

        // Soft delete - set is_active = 0
        $wpdb->update(
            $wpdb->prefix . 'loyalty_customers',
            array('is_active' => 0),
            array('id' => $id),
            array('%d'),
            array('%d')
        );

        // Also deactivate all identifiers
        $wpdb->update(
            $wpdb->prefix . 'loyalty_customer_identifiers',
            array('is_active' => 0),
            array('customer_id' => $id),
            array('%d'),
            array('%d')
        );

        wp_redirect(admin_url('admin.php?page=loyalty-hub-customers&deleted=1'));
        exit;
    }

    /**
     * Handle adding an identifier to a customer
     */
    private function handle_add_identifier() {
        global $wpdb;

        $customer_id = intval($_POST['customer_id']);
        $identifier = sanitize_text_field($_POST['identifier_value']);
        $type = sanitize_text_field($_POST['identifier_type'] ?? 'rfid');
        $label = sanitize_text_field($_POST['identifier_label'] ?? '');

        // Check for duplicate
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}loyalty_customer_identifiers WHERE identifier_value = %s",
            $identifier
        ));

        if ($existing) {
            wp_redirect(admin_url('admin.php?page=loyalty-hub-customers&edit=' . $customer_id . '&error=duplicate'));
            exit;
        }

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

        // Update customer's updated_at for sync
        $wpdb->update(
            $wpdb->prefix . 'loyalty_customers',
            array('updated_at' => current_time('mysql')),
            array('id' => $customer_id),
            array('%s'),
            array('%d')
        );

        wp_redirect(admin_url('admin.php?page=loyalty-hub-customers&edit=' . $customer_id . '&identifier_added=1'));
        exit;
    }

    /**
     * Handle deleting an identifier
     */
    private function handle_delete_identifier() {
        global $wpdb;

        $identifier_id = intval($_POST['identifier_id']);
        $customer_id = intval($_POST['customer_id']);

        // Soft delete (set is_active = 0)
        $wpdb->update(
            $wpdb->prefix . 'loyalty_customer_identifiers',
            array('is_active' => 0),
            array('id' => $identifier_id),
            array('%d'),
            array('%d')
        );

        // Update customer's updated_at for sync
        $wpdb->update(
            $wpdb->prefix . 'loyalty_customers',
            array('updated_at' => current_time('mysql')),
            array('id' => $customer_id),
            array('%s'),
            array('%d')
        );

        wp_redirect(admin_url('admin.php?page=loyalty-hub-customers&edit=' . $customer_id . '&identifier_removed=1'));
        exit;
    }

    /**
     * Handle adding a new promo
     */
    private function handle_add_promo() {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'loyalty_promos',
            array(
                'code'                   => strtoupper(sanitize_text_field($_POST['promo_code'])),
                'name'                   => sanitize_text_field($_POST['promo_name']),
                'description'            => sanitize_textarea_field($_POST['promo_description'] ?? ''),
                'type'                   => sanitize_text_field($_POST['promo_type']),
                'hotel_id'               => !empty($_POST['hotel_id']) ? intval($_POST['hotel_id']) : null,
                'wet_discount'           => floatval($_POST['wet_discount'] ?? 0),
                'dry_discount'           => floatval($_POST['dry_discount'] ?? 0),
                'bonus_multiplier'       => floatval($_POST['bonus_multiplier'] ?? 1),
                'min_spend'              => !empty($_POST['min_spend']) ? floatval($_POST['min_spend']) : null,
                'valid_from'             => !empty($_POST['valid_from']) ? $_POST['valid_from'] : null,
                'valid_until'            => !empty($_POST['valid_until']) ? $_POST['valid_until'] : null,
                'time_start'             => !empty($_POST['time_start']) ? $_POST['time_start'] : null,
                'time_end'               => !empty($_POST['time_end']) ? $_POST['time_end'] : null,
                'valid_days'             => !empty($_POST['valid_days']) ? implode(',', $_POST['valid_days']) : null,
                'max_uses'               => !empty($_POST['max_uses']) ? intval($_POST['max_uses']) : null,
                'max_uses_per_customer'  => !empty($_POST['max_uses_per_customer']) ? intval($_POST['max_uses_per_customer']) : null,
                'requires_membership'    => isset($_POST['requires_membership']) ? 1 : 0,
                'is_active'              => 1,
            ),
            array('%s', '%s', '%s', '%s', '%d', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d')
        );

        wp_redirect(admin_url('admin.php?page=loyalty-hub-promos&added=1'));
        exit;
    }

    /**
     * Handle editing a promo
     */
    private function handle_edit_promo() {
        global $wpdb;

        $id = intval($_POST['promo_id']);

        $wpdb->update(
            $wpdb->prefix . 'loyalty_promos',
            array(
                'code'                   => strtoupper(sanitize_text_field($_POST['promo_code'])),
                'name'                   => sanitize_text_field($_POST['promo_name']),
                'description'            => sanitize_textarea_field($_POST['promo_description'] ?? ''),
                'type'                   => sanitize_text_field($_POST['promo_type']),
                'hotel_id'               => !empty($_POST['hotel_id']) ? intval($_POST['hotel_id']) : null,
                'wet_discount'           => floatval($_POST['wet_discount'] ?? 0),
                'dry_discount'           => floatval($_POST['dry_discount'] ?? 0),
                'bonus_multiplier'       => floatval($_POST['bonus_multiplier'] ?? 1),
                'min_spend'              => !empty($_POST['min_spend']) ? floatval($_POST['min_spend']) : null,
                'valid_from'             => !empty($_POST['valid_from']) ? $_POST['valid_from'] : null,
                'valid_until'            => !empty($_POST['valid_until']) ? $_POST['valid_until'] : null,
                'time_start'             => !empty($_POST['time_start']) ? $_POST['time_start'] : null,
                'time_end'               => !empty($_POST['time_end']) ? $_POST['time_end'] : null,
                'valid_days'             => !empty($_POST['valid_days']) ? implode(',', $_POST['valid_days']) : null,
                'max_uses'               => !empty($_POST['max_uses']) ? intval($_POST['max_uses']) : null,
                'max_uses_per_customer'  => !empty($_POST['max_uses_per_customer']) ? intval($_POST['max_uses_per_customer']) : null,
                'requires_membership'    => isset($_POST['requires_membership']) ? 1 : 0,
                'is_active'              => isset($_POST['is_active']) ? 1 : 0,
            ),
            array('id' => $id),
            array('%s', '%s', '%s', '%s', '%d', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d'),
            array('%d')
        );

        wp_redirect(admin_url('admin.php?page=loyalty-hub-promos&updated=1'));
        exit;
    }
}
