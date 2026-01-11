<?php
/**
 * Admin Customers View
 *
 * Manage customer accounts and their RFID fobs.
 * QR codes are auto-generated and stored on the customer table.
 * RFID fobs are stored in the identifiers table (multiple per customer allowed).
 *
 * Available variables:
 * @var array  $customers            Paginated customer list (with rfid_fobs)
 * @var array  $hotels               Hotels for dropdown
 * @var int    $total                Total customer count
 * @var int    $total_pages          Total pages
 * @var int    $page                 Current page
 * @var string $search               Search term
 * @var object $editing              Customer being edited (if any)
 * @var array  $editing_rfid_fobs    RFID fobs for customer being edited
 *
 * @package Loyalty_Hub
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap loyalty-hub-admin">
    <h1>
        Customers
        <?php if (!$editing) : ?>
            <a href="#add-customer-form" class="page-title-action">Add New</a>
        <?php endif; ?>
    </h1>

    <?php if (isset($_GET['added'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Customer added successfully.</p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Customer updated successfully.</p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['rfid_added'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>RFID fob added successfully.</p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['rfid_removed'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>RFID fob removed successfully.</p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Customer deleted successfully.</p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])) : ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php
                switch ($_GET['error']) {
                    case 'duplicate':
                        echo 'This RFID code is already registered to another customer.';
                        break;
                    case 'duplicate_email':
                        echo 'This email address is already registered to another customer.';
                        break;
                    case 'duplicate_rfid':
                        echo 'This RFID code is already registered to another customer.';
                        break;
                    case 'insert_failed':
                        echo 'Failed to create customer. Please try again.';
                        break;
                    default:
                        echo 'An error occurred. Please try again.';
                }
                ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Search Form -->
    <form method="get" class="search-form">
        <input type="hidden" name="page" value="loyalty-hub-customers">
        <p class="search-box">
            <label class="screen-reader-text" for="customer-search">Search Customers:</label>
            <input type="search" id="customer-search" name="s"
                   value="<?php echo esc_attr($search); ?>"
                   placeholder="Search by name, email, or RFID...">
            <input type="submit" class="button" value="Search">
            <?php if ($search) : ?>
                <a href="<?php echo admin_url('admin.php?page=loyalty-hub-customers'); ?>" class="button">
                    Clear
                </a>
            <?php endif; ?>
        </p>
    </form>

    <!-- Customers Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Home Hotel</th>
                <th>Identifiers</th>
                <th>Staff</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($customers)) : ?>
                <tr>
                    <td colspan="7">
                        <?php echo $search ? 'No customers found matching your search.' : 'No customers yet.'; ?>
                    </td>
                </tr>
            <?php else : ?>
                <?php foreach ($customers as $customer) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($customer->name); ?></strong>
                            <br>
                            <small>ID: <?php echo esc_html($customer->id); ?></small>
                        </td>
                        <td><?php echo esc_html($customer->email ?: '-'); ?></td>
                        <td><?php echo esc_html($customer->home_hotel_name ?: '-'); ?></td>
                        <td>
                            <?php
                            $rfid_count = count($customer->rfid_fobs ?? []);
                            $has_qr = !empty($customer->qr_code);
                            ?>
                            <?php if ($rfid_count > 0) : ?>
                                <span class="identifier-badge rfid"><?php echo $rfid_count; ?> RFID</span>
                            <?php endif; ?>
                            <?php if ($has_qr) : ?>
                                <span class="identifier-badge qr">QR</span>
                            <?php endif; ?>
                            <?php if ($rfid_count === 0 && !$has_qr) : ?>
                                <span style="color: #999;">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($customer->is_staff) : ?>
                                <span class="status-badge status-staff">Staff</span>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($customer->is_active) : ?>
                                <span class="status-badge status-active">Active</span>
                            <?php else : ?>
                                <span class="status-badge status-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=loyalty-hub-customers&edit=' . $customer->id); ?>">
                                Edit
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo number_format($total); ?> items</span>
                <span class="pagination-links">
                    <?php
                    $base_url = admin_url('admin.php?page=loyalty-hub-customers');
                    if ($search) {
                        $base_url .= '&s=' . urlencode($search);
                    }

                    if ($page > 1) : ?>
                        <a class="prev-page button" href="<?php echo $base_url . '&paged=' . ($page - 1); ?>">
                            &lsaquo;
                        </a>
                    <?php endif; ?>

                    <span class="paging-input">
                        <span class="tablenav-paging-text">
                            <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </span>
                    </span>

                    <?php if ($page < $total_pages) : ?>
                        <a class="next-page button" href="<?php echo $base_url . '&paged=' . ($page + 1); ?>">
                            &rsaquo;
                        </a>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Add/Edit Customer Form -->
    <div id="add-customer-form" class="loyalty-hub-form-section">
        <h2><?php echo $editing ? 'Edit Customer' : 'Add New Customer'; ?></h2>

        <form method="post" action="">
            <?php wp_nonce_field('loyalty_hub_admin', 'loyalty_hub_nonce'); ?>
            <input type="hidden" name="loyalty_hub_action"
                   value="<?php echo $editing ? 'edit_customer' : 'add_customer'; ?>">

            <?php if ($editing) : ?>
                <input type="hidden" name="customer_id" value="<?php echo esc_attr($editing->id); ?>">
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="customer_name">Name *</label>
                    </th>
                    <td>
                        <input type="text" id="customer_name" name="customer_name" class="regular-text"
                               value="<?php echo esc_attr($editing->name ?? ''); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="customer_email">Email</label>
                    </th>
                    <td>
                        <input type="email" id="customer_email" name="customer_email" class="regular-text"
                               value="<?php echo esc_attr($editing->email ?? ''); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="customer_phone">Phone</label>
                    </th>
                    <td>
                        <input type="text" id="customer_phone" name="customer_phone" class="regular-text"
                               value="<?php echo esc_attr($editing->phone ?? ''); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="customer_dob">Date of Birth</label>
                    </th>
                    <td>
                        <input type="date" id="customer_dob" name="customer_dob"
                               value="<?php echo esc_attr($editing->dob ?? ''); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="home_hotel_id">Home Hotel *</label>
                    </th>
                    <td>
                        <select id="home_hotel_id" name="home_hotel_id" required>
                            <option value="">Select hotel...</option>
                            <?php foreach ($hotels as $hotel) : ?>
                                <option value="<?php echo esc_attr($hotel->id); ?>"
                                    <?php selected($editing->home_hotel_id ?? '', $hotel->id); ?>>
                                    <?php echo esc_html($hotel->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Where the customer registered. Affects tier calculation.</p>
                    </td>
                </tr>

                <?php if ($editing && !empty($editing->qr_code)) : ?>
                    <!-- Show QR code (read-only) when editing -->
                    <tr>
                        <th scope="row">QR Code</th>
                        <td>
                            <code style="font-size: 14px; padding: 5px 10px; background: #f0f0f1;"><?php echo esc_html($editing->qr_code); ?></code>
                            <p class="description">Auto-generated. Used for app identification.</p>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php if (!$editing) : ?>
                    <!-- RFID only shown when adding new customer -->
                    <tr>
                        <th scope="row">
                            <label for="rfid_code">Initial RFID Code</label>
                        </th>
                        <td>
                            <input type="text" id="rfid_code" name="rfid_code" class="regular-text">
                            <p class="description">Physical fob identifier. You can add more after creating the customer.</p>
                        </td>
                    </tr>
                <?php endif; ?>

                <tr>
                    <th scope="row">Staff Discount</th>
                    <td>
                        <label>
                            <input type="checkbox" name="is_staff" value="1"
                                <?php checked($editing->is_staff ?? 0, 1); ?>>
                            This customer is a staff member
                        </label>
                        <p class="description">
                            Staff members get separate discount rates and account codes (#2306/#3306).
                        </p>
                    </td>
                </tr>

                <?php if ($editing) : ?>
                    <tr>
                        <th scope="row">Status</th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" value="1"
                                    <?php checked($editing->is_active, 1); ?>>
                                Active
                            </label>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>

            <p class="submit">
                <input type="submit" class="button button-primary"
                       value="<?php echo $editing ? 'Update Customer' : 'Add Customer'; ?>">
                <?php if ($editing) : ?>
                    <a href="<?php echo admin_url('admin.php?page=loyalty-hub-customers'); ?>" class="button">
                        Cancel
                    </a>
                <?php endif; ?>
            </p>
        </form>

        <?php if ($editing) : ?>
            <!-- Delete Customer Form -->
            <form method="post" action="" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ccc;"
                  onsubmit="return confirm('Are you sure you want to delete this customer? This action cannot be undone.');">
                <?php wp_nonce_field('loyalty_hub_admin', 'loyalty_hub_nonce'); ?>
                <input type="hidden" name="loyalty_hub_action" value="delete_customer">
                <input type="hidden" name="customer_id" value="<?php echo esc_attr($editing->id); ?>">
                <button type="submit" class="button button-link-delete" style="color: #b32d2e;">
                    Delete Customer
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($editing) : ?>
        <!-- RFID Fobs Management Section -->
        <div class="loyalty-hub-form-section">
            <h2>RFID Fobs</h2>
            <p class="description">
                Manage RFID fobs for this customer. Multiple fobs can be added for couples or families sharing an account.
            </p>

            <!-- Current RFID Fobs -->
            <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th>RFID Code</th>
                        <th>Label</th>
                        <th>Status</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($editing_rfid_fobs)) : ?>
                        <tr>
                            <td colspan="5">No RFID fobs. Add one below.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($editing_rfid_fobs as $fob) : ?>
                            <tr class="<?php echo $fob->is_active ? '' : 'inactive-row'; ?>">
                                <td><code><?php echo esc_html($fob->identifier_value); ?></code></td>
                                <td><?php echo esc_html($fob->label ?: '-'); ?></td>
                                <td>
                                    <?php if ($fob->is_active) : ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php else : ?>
                                        <span class="status-badge status-inactive">Removed</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(date('Y-m-d', strtotime($fob->created_at))); ?></td>
                                <td>
                                    <?php if ($fob->is_active) : ?>
                                        <form method="post" action="" style="display: inline;"
                                              onsubmit="return confirm('Remove this RFID fob?');">
                                            <?php wp_nonce_field('loyalty_hub_admin', 'loyalty_hub_nonce'); ?>
                                            <input type="hidden" name="loyalty_hub_action" value="delete_identifier">
                                            <input type="hidden" name="identifier_id" value="<?php echo esc_attr($fob->id); ?>">
                                            <input type="hidden" name="customer_id" value="<?php echo esc_attr($editing->id); ?>">
                                            <button type="submit" class="button button-link-delete">Remove</button>
                                        </form>
                                    <?php else : ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Add New RFID Fob Form -->
            <h3 style="margin-top: 20px;">Add New RFID Fob</h3>
            <form method="post" action="">
                <?php wp_nonce_field('loyalty_hub_admin', 'loyalty_hub_nonce'); ?>
                <input type="hidden" name="loyalty_hub_action" value="add_identifier">
                <input type="hidden" name="customer_id" value="<?php echo esc_attr($editing->id); ?>">

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="identifier_value">RFID Code *</label>
                        </th>
                        <td>
                            <input type="text" id="identifier_value" name="identifier_value" class="regular-text" required>
                            <p class="description">The code from the physical fob.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="identifier_label">Label</label>
                        </th>
                        <td>
                            <input type="text" id="identifier_label" name="identifier_label" class="regular-text"
                                   placeholder="e.g., Wife's fob, Spare key">
                            <p class="description">Optional friendly name to identify this fob.</p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" class="button button-secondary" value="Add RFID Fob">
                </p>
            </form>
        </div>
    <?php endif; ?>
</div>

<style>
.identifier-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    margin-right: 4px;
}
.identifier-badge.rfid {
    background: #e7f3ff;
    color: #0073aa;
}
.identifier-badge.qr {
    background: #f0f0f1;
    color: #50575e;
}
.inactive-row {
    opacity: 0.5;
}
.button-link-delete {
    color: #b32d2e !important;
    border: none !important;
    background: none !important;
    cursor: pointer;
}
.button-link-delete:hover {
    color: #dc3232 !important;
}
</style>
