<?php
/**
 * Admin Tiers Configuration View
 *
 * Configure per-hotel tier thresholds and discount rates.
 *
 * Available variables:
 * @var array  $tiers        Global tier definitions
 * @var array  $hotels       All active hotels
 * @var array  $hotel_tiers  Per-hotel tier configurations
 * @var array  $staff_rates  Per-hotel staff discount rates
 *
 * @package Loyalty_Hub
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Organize hotel_tiers by hotel_id
$tiers_by_hotel = array();
foreach ($hotel_tiers as $ht) {
    if (!isset($tiers_by_hotel[$ht->hotel_id])) {
        $tiers_by_hotel[$ht->hotel_id] = array();
    }
    $tiers_by_hotel[$ht->hotel_id][$ht->tier_id] = $ht;
}

// Organize staff rates by hotel_id
$staff_by_hotel = array();
foreach ($staff_rates as $sr) {
    $staff_by_hotel[$sr->hotel_id] = $sr;
}

// Get selected hotel from URL
$selected_hotel_id = isset($_GET['hotel']) ? intval($_GET['hotel']) : (count($hotels) > 0 ? $hotels[0]->id : 0);
?>

<div class="wrap loyalty-hub-admin">
    <h1>Tier Configuration</h1>

    <?php if (isset($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Tier configuration saved successfully.</p>
        </div>
    <?php endif; ?>

    <?php if (empty($hotels)) : ?>
        <div class="notice notice-warning">
            <p>No hotels configured yet. <a href="<?php echo admin_url('admin.php?page=loyalty-hub-hotels'); ?>">Add a hotel first</a>.</p>
        </div>
    <?php else : ?>

        <!-- Hotel Selector -->
        <div class="hotel-selector">
            <label for="hotel-select"><strong>Select Hotel:</strong></label>
            <select id="hotel-select" onchange="window.location.href='<?php echo admin_url('admin.php?page=loyalty-hub-tiers&hotel='); ?>' + this.value;">
                <?php foreach ($hotels as $hotel) : ?>
                    <option value="<?php echo esc_attr($hotel->id); ?>"
                        <?php selected($selected_hotel_id, $hotel->id); ?>>
                        <?php echo esc_html($hotel->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Tier Configuration Explanation -->
        <div class="tier-explanation">
            <h3>How Tier Calculation Works</h3>
            <ol>
                <li><strong>Visits count GLOBALLY</strong> - All visits to ANY hotel count toward the customer's total.</li>
                <li><strong>Best tier wins</strong> - Customer's tier is the BEST they qualify for between their HOME hotel and VISITING hotel thresholds.</li>
                <li><strong>Rates from visiting hotel</strong> - Discount percentages are always from the VISITING hotel for the calculated tier.</li>
            </ol>
            <p>
                <strong>Example:</strong> If a customer has 3 visits and their home hotel requires 2 visits for Loyalty
                but this hotel requires 4, they still get Loyalty tier (from home hotel threshold) but at THIS hotel's Loyalty rates.
            </p>
        </div>

        <!-- Tier Configuration Form -->
        <?php
        $selected_hotel = null;
        foreach ($hotels as $h) {
            if ($h->id == $selected_hotel_id) {
                $selected_hotel = $h;
                break;
            }
        }

        if ($selected_hotel) :
            $hotel_tier_config = $tiers_by_hotel[$selected_hotel_id] ?? array();
            $hotel_staff = $staff_by_hotel[$selected_hotel_id] ?? null;
        ?>

        <form method="post" action="">
            <?php wp_nonce_field('loyalty_hub_admin', 'loyalty_hub_nonce'); ?>
            <input type="hidden" name="loyalty_hub_action" value="save_hotel_tiers">
            <input type="hidden" name="hotel_id" value="<?php echo esc_attr($selected_hotel_id); ?>">

            <h2><?php echo esc_html($selected_hotel->name); ?> - Tier Configuration</h2>

            <table class="wp-list-table widefat fixed striped tier-config-table">
                <thead>
                    <tr>
                        <th>Tier</th>
                        <th>Visits Required</th>
                        <th>Period (Days)</th>
                        <th>Wet Discount %</th>
                        <th>Dry Discount %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tiers as $tier) :
                        $config = $hotel_tier_config[$tier->id] ?? null;
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($tier->name); ?></strong>
                                <br>
                                <small class="description">Sort order: <?php echo esc_html($tier->sort_order); ?></small>
                            </td>
                            <td>
                                <input type="number" name="tiers[<?php echo esc_attr($tier->id); ?>][visits_required]"
                                       value="<?php echo esc_attr($config->visits_required ?? 0); ?>"
                                       min="0" class="small-text">
                                <p class="description">
                                    <?php if ($tier->slug === 'member') : ?>
                                        Base tier - usually 0
                                    <?php else : ?>
                                        Visits in period to qualify
                                    <?php endif; ?>
                                </p>
                            </td>
                            <td>
                                <input type="number" name="tiers[<?php echo esc_attr($tier->id); ?>][period_days]"
                                       value="<?php echo esc_attr($config->period_days ?? 28); ?>"
                                       min="1" class="small-text">
                                <p class="description">Rolling window</p>
                            </td>
                            <td>
                                <input type="number" name="tiers[<?php echo esc_attr($tier->id); ?>][wet_discount]"
                                       value="<?php echo esc_attr($config->wet_discount ?? 0); ?>"
                                       min="0" max="100" step="0.5" class="small-text">
                                <span class="description">%</span>
                            </td>
                            <td>
                                <input type="number" name="tiers[<?php echo esc_attr($tier->id); ?>][dry_discount]"
                                       value="<?php echo esc_attr($config->dry_discount ?? 0); ?>"
                                       min="0" max="100" step="0.5" class="small-text">
                                <span class="description">%</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Staff Rates -->
            <h3>Staff Discount Rates</h3>
            <p class="description">
                Staff discounts are applied when a customer has <code>is_staff = true</code>.
                This overrides normal tier calculation and uses separate Newbook account codes (#2306/#3306).
            </p>

            <table class="wp-list-table widefat fixed striped" style="max-width: 500px;">
                <tr>
                    <th>Staff Wet Discount %</th>
                    <td>
                        <input type="number" name="staff[wet_discount]"
                               value="<?php echo esc_attr($hotel_staff->wet_discount ?? 0); ?>"
                               min="0" max="100" step="0.5" class="small-text">
                        <span class="description">%</span>
                    </td>
                </tr>
                <tr>
                    <th>Staff Dry Discount %</th>
                    <td>
                        <input type="number" name="staff[dry_discount]"
                               value="<?php echo esc_attr($hotel_staff->dry_discount ?? 0); ?>"
                               min="0" max="100" step="0.5" class="small-text">
                        <span class="description">%</span>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button button-primary" value="Save Configuration">
            </p>
        </form>

        <?php endif; ?>
    <?php endif; ?>

    <!-- Account Codes Reference -->
    <div class="account-codes-reference">
        <h3>Newbook Account Codes Reference</h3>
        <table class="wp-list-table widefat fixed" style="max-width: 600px;">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Purpose</th>
                    <th>Discount Type</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>#2303</code></td>
                    <td>Loyalty drink discounts</td>
                    <td>discount</td>
                </tr>
                <tr>
                    <td><code>#3303</code></td>
                    <td>Loyalty food discounts</td>
                    <td>discount</td>
                </tr>
                <tr>
                    <td><code>#2305</code></td>
                    <td>Promo drink discounts</td>
                    <td>promo</td>
                </tr>
                <tr>
                    <td><code>#3305</code></td>
                    <td>Promo food discounts</td>
                    <td>promo</td>
                </tr>
                <tr>
                    <td><code>#2306</code></td>
                    <td>Staff drink discounts</td>
                    <td>staff</td>
                </tr>
                <tr>
                    <td><code>#3306</code></td>
                    <td>Staff food discounts</td>
                    <td>staff</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<style>
.hotel-selector {
    margin: 20px 0;
    padding: 15px;
    background: #fff;
    border: 1px solid #ccd0d4;
}
.hotel-selector select {
    margin-left: 10px;
    min-width: 200px;
}
.tier-explanation {
    background: #f0f6fc;
    border-left: 4px solid #2271b1;
    padding: 15px 20px;
    margin: 20px 0;
}
.tier-explanation h3 {
    margin-top: 0;
}
.tier-config-table input.small-text {
    width: 80px;
}
.account-codes-reference {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #ccd0d4;
}
</style>
