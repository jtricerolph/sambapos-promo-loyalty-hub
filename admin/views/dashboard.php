<?php
/**
 * Admin Dashboard View
 *
 * Displays overview statistics for the loyalty system.
 *
 * Available variables:
 * @var int    $total_customers      Total active customers
 * @var int    $total_hotels         Total active hotels
 * @var int    $transactions_today   Transactions logged today
 * @var int    $active_promos        Currently active promos
 * @var array  $recent_transactions  Last 10 transactions
 *
 * @package Loyalty_Hub
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap loyalty-hub-admin">
    <h1>Loyalty Hub Dashboard</h1>

    <!-- Stats Cards -->
    <div class="loyalty-hub-stats">
        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-groups"></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($total_customers); ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-building"></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($total_hotels); ?></div>
                <div class="stat-label">Hotels</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-cart"></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($transactions_today); ?></div>
                <div class="stat-label">Transactions Today</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon dashicons dashicons-tag"></div>
            <div class="stat-content">
                <div class="stat-value"><?php echo number_format($active_promos); ?></div>
                <div class="stat-label">Active Promos</div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="loyalty-hub-quick-links">
        <h2>Quick Actions</h2>
        <div class="quick-links-grid">
            <a href="<?php echo admin_url('admin.php?page=loyalty-hub-hotels'); ?>" class="quick-link">
                <span class="dashicons dashicons-plus-alt"></span>
                Add Hotel
            </a>
            <a href="<?php echo admin_url('admin.php?page=loyalty-hub-customers'); ?>" class="quick-link">
                <span class="dashicons dashicons-admin-users"></span>
                Manage Customers
            </a>
            <a href="<?php echo admin_url('admin.php?page=loyalty-hub-promos'); ?>" class="quick-link">
                <span class="dashicons dashicons-megaphone"></span>
                Create Promo
            </a>
            <a href="<?php echo admin_url('admin.php?page=loyalty-hub-reports'); ?>" class="quick-link">
                <span class="dashicons dashicons-chart-bar"></span>
                View Reports
            </a>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="loyalty-hub-recent">
        <h2>Recent Transactions</h2>

        <?php if (empty($recent_transactions)) : ?>
            <p class="no-data">No transactions yet.</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Customer</th>
                        <th>Hotel</th>
                        <th>Tier</th>
                        <th>Total</th>
                        <th>Discount</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_transactions as $tx) : ?>
                        <tr>
                            <td><?php echo esc_html(date('d M H:i', strtotime($tx->created_at))); ?></td>
                            <td><?php echo esc_html($tx->customer_name ?: 'Guest'); ?></td>
                            <td><?php echo esc_html($tx->hotel_name); ?></td>
                            <td>
                                <span class="tier-badge tier-<?php echo esc_attr(strtolower($tx->tier_at_visit ?: 'guest')); ?>">
                                    <?php echo esc_html($tx->tier_at_visit ?: 'Guest'); ?>
                                </span>
                            </td>
                            <td>&pound;<?php echo number_format($tx->total_amount, 2); ?></td>
                            <td>&pound;<?php echo number_format($tx->discount_amount, 2); ?></td>
                            <td>
                                <?php
                                $type_labels = array(
                                    'discount' => 'Loyalty',
                                    'promo'    => 'Promo',
                                    'staff'    => 'Staff',
                                );
                                echo esc_html($type_labels[$tx->discount_type] ?? '-');
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- System Info -->
    <div class="loyalty-hub-info">
        <h2>API Information</h2>
        <p>
            <strong>API Endpoint:</strong>
            <code><?php echo esc_url(rest_url('loyalty/v1/')); ?></code>
        </p>
        <p>
            <strong>Authentication:</strong>
            Include <code>X-API-Key</code> header with hotel API key
        </p>
        <p>
            <a href="<?php echo admin_url('admin.php?page=loyalty-hub-hotels'); ?>">
                View hotel API keys &rarr;
            </a>
        </p>
    </div>
</div>
