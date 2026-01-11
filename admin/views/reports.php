<?php
/**
 * Admin Reports View
 *
 * Display loyalty system statistics and reports.
 *
 * Available variables:
 * @var string $start_date      Report start date
 * @var string $end_date        Report end date
 * @var array  $tier_stats      Tier distribution stats
 * @var array  $hotel_stats     Per-hotel stats
 * @var array  $promo_stats     Promo effectiveness stats
 * @var array  $lapsed_customers Customers with no recent visits
 *
 * @package Loyalty_Hub
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap loyalty-hub-admin">
    <h1>Loyalty Reports</h1>

    <!-- Date Range Filter -->
    <form method="get" class="report-filters">
        <input type="hidden" name="page" value="loyalty-hub-reports">

        <label>
            From:
            <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
        </label>
        <label>
            To:
            <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
        </label>
        <button type="submit" class="button">Update</button>

        <!-- Quick date links -->
        <span class="quick-dates">
            <a href="<?php echo admin_url('admin.php?page=loyalty-hub-reports&start_date=' . date('Y-m-d', strtotime('-7 days')) . '&end_date=' . date('Y-m-d')); ?>">
                Last 7 days
            </a>
            |
            <a href="<?php echo admin_url('admin.php?page=loyalty-hub-reports&start_date=' . date('Y-m-d', strtotime('-30 days')) . '&end_date=' . date('Y-m-d')); ?>">
                Last 30 days
            </a>
            |
            <a href="<?php echo admin_url('admin.php?page=loyalty-hub-reports&start_date=' . date('Y-m-01') . '&end_date=' . date('Y-m-d')); ?>">
                This month
            </a>
        </span>
    </form>

    <div class="reports-grid">

        <!-- Tier Distribution -->
        <div class="report-card">
            <h2>Tier Distribution</h2>
            <p class="description">Transactions by customer tier for selected period.</p>

            <?php if (empty($tier_stats)) : ?>
                <p class="no-data">No transactions in this period.</p>
            <?php else : ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>Tier</th>
                            <th>Transactions</th>
                            <th>Total Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tier_stats as $stat) : ?>
                            <tr>
                                <td>
                                    <span class="tier-badge tier-<?php echo esc_attr(strtolower($stat->tier ?: 'guest')); ?>">
                                        <?php echo esc_html($stat->tier ?: 'Guest'); ?>
                                    </span>
                                </td>
                                <td><?php echo number_format($stat->count); ?></td>
                                <td>&pound;<?php echo number_format($stat->total_sales, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Hotel Performance -->
        <div class="report-card">
            <h2>Hotel Performance</h2>
            <p class="description">Sales and discounts by hotel.</p>

            <?php if (empty($hotel_stats)) : ?>
                <p class="no-data">No transactions in this period.</p>
            <?php else : ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>Hotel</th>
                            <th>Transactions</th>
                            <th>Sales</th>
                            <th>Discounts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hotel_stats as $stat) : ?>
                            <tr>
                                <td><strong><?php echo esc_html($stat->hotel); ?></strong></td>
                                <td><?php echo number_format($stat->transactions); ?></td>
                                <td>&pound;<?php echo number_format($stat->total_sales, 2); ?></td>
                                <td>&pound;<?php echo number_format($stat->total_discounts, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Promo Effectiveness -->
        <div class="report-card">
            <h2>Promo Effectiveness</h2>
            <p class="description">Which promos are being used most.</p>

            <?php if (empty($promo_stats)) : ?>
                <p class="no-data">No promo usage in this period.</p>
            <?php else : ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Uses</th>
                            <th>Discount Given</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promo_stats as $stat) : ?>
                            <tr>
                                <td><code><?php echo esc_html($stat->code); ?></code></td>
                                <td><?php echo esc_html($stat->name); ?></td>
                                <td><?php echo number_format($stat->uses); ?></td>
                                <td>&pound;<?php echo number_format($stat->total_discount, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Lapsed Customers -->
        <div class="report-card report-card-wide">
            <h2>Lapsed Customers</h2>
            <p class="description">
                Customers who haven't visited in 30+ days.
                Consider sending them a personalized "We miss you" message!
            </p>

            <?php if (empty($lapsed_customers)) : ?>
                <p class="no-data">No lapsed customers - great retention!</p>
            <?php else : ?>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Home Hotel</th>
                            <th>Last Visit</th>
                            <th>Days Since</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lapsed_customers as $customer) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=loyalty-hub-customers&edit=' . $customer->id); ?>">
                                        <?php echo esc_html($customer->name); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($customer->email ?: '-'); ?></td>
                                <td><?php echo esc_html($customer->home_hotel ?: '-'); ?></td>
                                <td>
                                    <?php
                                    if ($customer->last_visit) {
                                        echo esc_html(date('d M Y', strtotime($customer->last_visit)));
                                    } else {
                                        echo 'Never';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($customer->days_since_visit) {
                                        $class = $customer->days_since_visit > 60 ? 'days-critical' : 'days-warning';
                                        echo '<span class="' . $class . '">' . esc_html($customer->days_since_visit) . ' days</span>';
                                    } else {
                                        echo '<span class="days-critical">Never visited</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </div>
</div>

<style>
.report-filters {
    background: #fff;
    padding: 15px 20px;
    border: 1px solid #ccd0d4;
    margin-bottom: 20px;
}
.report-filters label {
    margin-right: 15px;
}
.report-filters input[type="date"] {
    margin-left: 5px;
}
.quick-dates {
    margin-left: 20px;
    color: #666;
}
.quick-dates a {
    text-decoration: none;
}

.reports-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.report-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
}
.report-card-wide {
    grid-column: 1 / -1;
}
.report-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.report-card .description {
    color: #666;
    margin-bottom: 15px;
}
.report-card .no-data {
    color: #999;
    font-style: italic;
}

.tier-badge {
    padding: 3px 10px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}
.tier-badge.tier-member {
    background: #f0f0f1;
    color: #50575e;
}
.tier-badge.tier-loyalty {
    background: #e7f3ff;
    color: #0066cc;
}
.tier-badge.tier-regular {
    background: #f0f6e6;
    color: #46760a;
}
.tier-badge.tier-staff {
    background: #fcf0e3;
    color: #996800;
}
.tier-badge.tier-guest {
    background: #f0f0f1;
    color: #999;
}

.days-warning {
    color: #996800;
    font-weight: 500;
}
.days-critical {
    color: #d63638;
    font-weight: 500;
}

@media (max-width: 1200px) {
    .reports-grid {
        grid-template-columns: 1fr;
    }
}
</style>
