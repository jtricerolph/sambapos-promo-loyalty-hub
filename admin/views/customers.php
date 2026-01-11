<?php
/**
 * Admin Customers View
 *
 * Manage customer accounts.
 *
 * Available variables:
 * @var array  $customers     Paginated customer list
 * @var array  $hotels        Hotels for dropdown
 * @var int    $total         Total customer count
 * @var int    $total_pages   Total pages
 * @var int    $page          Current page
 * @var string $search        Search term
 * @var object $editing       Customer being edited (if any)
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
                <th>RFID Code</th>
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
                            <?php if ($customer->rfid_code) : ?>
                                <code><?php echo esc_html($customer->rfid_code); ?></code>
                            <?php else : ?>
                                -
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
                <tr>
                    <th scope="row">
                        <label for="rfid_code">RFID Code</label>
                    </th>
                    <td>
                        <input type="text" id="rfid_code" name="rfid_code" class="regular-text"
                               value="<?php echo esc_attr($editing->rfid_code ?? ''); ?>">
                        <p class="description">Physical fob identifier.</p>
                    </td>
                </tr>

                <?php if ($editing) : ?>
                    <tr>
                        <th scope="row">QR Code</th>
                        <td>
                            <code><?php echo esc_html($editing->qr_code); ?></code>
                            <p class="description">Auto-generated app identifier.</p>
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
    </div>
</div>
