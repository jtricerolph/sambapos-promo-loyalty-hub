<?php
/**
 * Admin Promos View
 *
 * Create and manage promotional codes.
 *
 * Available variables:
 * @var array  $promos   All promos with usage counts
 * @var array  $hotels   Hotels for dropdown
 * @var object $editing  Promo being edited (if any)
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
        Promos
        <?php if (!$editing) : ?>
            <a href="#add-promo-form" class="page-title-action">Add New</a>
        <?php endif; ?>
    </h1>

    <?php if (isset($_GET['added'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Promo created successfully.</p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Promo updated successfully.</p>
        </div>
    <?php endif; ?>

    <!-- Promo Type Explanation -->
    <div class="promo-explanation">
        <h3>Promo Types</h3>
        <div class="promo-types-grid">
            <div class="promo-type-card">
                <h4>Loyalty Bonus</h4>
                <p>Multiplies the customer's existing tier discount.</p>
                <ul>
                    <li>Requires membership</li>
                    <li>Uses <code>bonus_multiplier</code> (e.g., 2.0 = double)</li>
                    <li>Same account codes as tier discount (#2303/#3303)</li>
                </ul>
                <p><strong>Example:</strong> "LUNCH2X" - Double loyalty discount at lunch</p>
            </div>
            <div class="promo-type-card">
                <h4>Promo Code</h4>
                <p>One-off fixed percentage discount.</p>
                <ul>
                    <li>Works for members AND guests</li>
                    <li>Uses fixed wet/dry percentages</li>
                    <li>Separate account codes (#2305/#3305)</li>
                    <li>Replaces tier discount if member uses it</li>
                </ul>
                <p><strong>Example:</strong> "SUMMER24" - 15% off for everyone</p>
            </div>
        </div>
    </div>

    <!-- Promos Table -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Type</th>
                <th>Discount</th>
                <th>Hotel</th>
                <th>Valid</th>
                <th>Uses</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($promos)) : ?>
                <tr>
                    <td colspan="9">No promos yet.</td>
                </tr>
            <?php else : ?>
                <?php foreach ($promos as $promo) : ?>
                    <tr>
                        <td><code><strong><?php echo esc_html($promo->code); ?></strong></code></td>
                        <td><?php echo esc_html($promo->name); ?></td>
                        <td>
                            <?php if ($promo->type === 'loyalty_bonus') : ?>
                                <span class="type-badge type-loyalty">Loyalty Bonus</span>
                            <?php else : ?>
                                <span class="type-badge type-promo">Promo Code</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($promo->type === 'loyalty_bonus') : ?>
                                <?php echo esc_html($promo->bonus_multiplier); ?>x multiplier
                            <?php else : ?>
                                <?php echo esc_html($promo->wet_discount); ?>% / <?php echo esc_html($promo->dry_discount); ?>%
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($promo->hotel_name ?: 'All'); ?></td>
                        <td>
                            <?php
                            if ($promo->valid_from || $promo->valid_until) {
                                $from = $promo->valid_from ? date('d M', strtotime($promo->valid_from)) : 'Any';
                                $until = $promo->valid_until ? date('d M', strtotime($promo->valid_until)) : 'Any';
                                echo esc_html("$from - $until");
                            } else {
                                echo 'Always';
                            }
                            ?>
                        </td>
                        <td>
                            <?php echo number_format($promo->use_count); ?>
                            <?php if ($promo->max_uses) : ?>
                                / <?php echo number_format($promo->max_uses); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($promo->is_active) : ?>
                                <span class="status-badge status-active">Active</span>
                            <?php else : ?>
                                <span class="status-badge status-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=loyalty-hub-promos&edit=' . $promo->id); ?>">
                                Edit
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Add/Edit Promo Form -->
    <div id="add-promo-form" class="loyalty-hub-form-section">
        <h2><?php echo $editing ? 'Edit Promo' : 'Add New Promo'; ?></h2>

        <form method="post" action="">
            <?php wp_nonce_field('loyalty_hub_admin', 'loyalty_hub_nonce'); ?>
            <input type="hidden" name="loyalty_hub_action"
                   value="<?php echo $editing ? 'edit_promo' : 'add_promo'; ?>">

            <?php if ($editing) : ?>
                <input type="hidden" name="promo_id" value="<?php echo esc_attr($editing->id); ?>">
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="promo_code">Code *</label>
                    </th>
                    <td>
                        <input type="text" id="promo_code" name="promo_code" class="regular-text"
                               value="<?php echo esc_attr($editing->code ?? ''); ?>"
                               style="text-transform: uppercase;" required>
                        <p class="description">The code customers enter. Will be converted to uppercase.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="promo_name">Name *</label>
                    </th>
                    <td>
                        <input type="text" id="promo_name" name="promo_name" class="regular-text"
                               value="<?php echo esc_attr($editing->name ?? ''); ?>" required>
                        <p class="description">Display name shown to staff.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="promo_description">Description</label>
                    </th>
                    <td>
                        <textarea id="promo_description" name="promo_description" class="large-text" rows="2"><?php
                            echo esc_textarea($editing->description ?? '');
                        ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="promo_type">Type *</label>
                    </th>
                    <td>
                        <select id="promo_type" name="promo_type" required onchange="togglePromoTypeFields()">
                            <option value="promo_code" <?php selected($editing->type ?? '', 'promo_code'); ?>>
                                Promo Code (fixed discount)
                            </option>
                            <option value="loyalty_bonus" <?php selected($editing->type ?? '', 'loyalty_bonus'); ?>>
                                Loyalty Bonus (multiplier)
                            </option>
                        </select>
                    </td>
                </tr>
                <tr class="promo-code-fields">
                    <th scope="row">Discount Percentages</th>
                    <td>
                        <label>
                            Wet (drinks):
                            <input type="number" name="wet_discount"
                                   value="<?php echo esc_attr($editing->wet_discount ?? 0); ?>"
                                   min="0" max="100" step="0.5" class="small-text">%
                        </label>
                        &nbsp;&nbsp;
                        <label>
                            Dry (food):
                            <input type="number" name="dry_discount"
                                   value="<?php echo esc_attr($editing->dry_discount ?? 0); ?>"
                                   min="0" max="100" step="0.5" class="small-text">%
                        </label>
                    </td>
                </tr>
                <tr class="loyalty-bonus-fields" style="display: none;">
                    <th scope="row">
                        <label for="bonus_multiplier">Bonus Multiplier</label>
                    </th>
                    <td>
                        <input type="number" id="bonus_multiplier" name="bonus_multiplier"
                               value="<?php echo esc_attr($editing->bonus_multiplier ?? 2); ?>"
                               min="1" max="10" step="0.1" class="small-text">
                        <span>x</span>
                        <p class="description">
                            Multiplies the customer's tier discount.
                            E.g., 2 = double, 1.5 = 50% extra.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="hotel_id">Hotel</label>
                    </th>
                    <td>
                        <select id="hotel_id" name="hotel_id">
                            <option value="">All Hotels</option>
                            <?php foreach ($hotels as $hotel) : ?>
                                <option value="<?php echo esc_attr($hotel->id); ?>"
                                    <?php selected($editing->hotel_id ?? '', $hotel->id); ?>>
                                    <?php echo esc_html($hotel->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Leave empty to allow at all hotels.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Date Range</th>
                    <td>
                        <label>
                            From:
                            <input type="datetime-local" name="valid_from"
                                   value="<?php echo esc_attr(!empty($editing->valid_from) ? date('Y-m-d\TH:i', strtotime($editing->valid_from)) : ''); ?>">
                        </label>
                        &nbsp;&nbsp;
                        <label>
                            Until:
                            <input type="datetime-local" name="valid_until"
                                   value="<?php echo esc_attr(!empty($editing->valid_until) ? date('Y-m-d\TH:i', strtotime($editing->valid_until)) : ''); ?>">
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Time of Day</th>
                    <td>
                        <label>
                            From:
                            <input type="time" name="time_start"
                                   value="<?php echo esc_attr($editing->time_start ?? ''); ?>">
                        </label>
                        &nbsp;&nbsp;
                        <label>
                            Until:
                            <input type="time" name="time_end"
                                   value="<?php echo esc_attr($editing->time_end ?? ''); ?>">
                        </label>
                        <p class="description">Leave blank for all day. E.g., 11:30 - 14:30 for lunch.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Valid Days</th>
                    <td>
                        <?php
                        $valid_days = !empty($editing->valid_days) ? explode(',', $editing->valid_days) : array();
                        $days = array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun');
                        foreach ($days as $day) : ?>
                            <label style="margin-right: 10px;">
                                <input type="checkbox" name="valid_days[]" value="<?php echo $day; ?>"
                                    <?php checked(in_array($day, $valid_days)); ?>>
                                <?php echo $day; ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description">Leave all unchecked for every day.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="min_spend">Minimum Spend</label>
                    </th>
                    <td>
                        &pound;
                        <input type="number" id="min_spend" name="min_spend"
                               value="<?php echo esc_attr($editing->min_spend ?? ''); ?>"
                               min="0" step="0.01" class="small-text">
                        <p class="description">Leave blank for no minimum.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Usage Limits</th>
                    <td>
                        <label>
                            Total uses:
                            <input type="number" name="max_uses"
                                   value="<?php echo esc_attr($editing->max_uses ?? ''); ?>"
                                   min="0" class="small-text">
                        </label>
                        &nbsp;&nbsp;
                        <label>
                            Per customer:
                            <input type="number" name="max_uses_per_customer"
                                   value="<?php echo esc_attr($editing->max_uses_per_customer ?? ''); ?>"
                                   min="0" class="small-text">
                        </label>
                        <p class="description">Leave blank for unlimited.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Membership</th>
                    <td>
                        <label>
                            <input type="checkbox" name="requires_membership" value="1"
                                <?php checked($editing->requires_membership ?? 0, 1); ?>>
                            Requires membership (customer must scan card first)
                        </label>
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
                       value="<?php echo $editing ? 'Update Promo' : 'Create Promo'; ?>">
                <?php if ($editing) : ?>
                    <a href="<?php echo admin_url('admin.php?page=loyalty-hub-promos'); ?>" class="button">
                        Cancel
                    </a>
                <?php endif; ?>
            </p>
        </form>
    </div>
</div>

<script>
function togglePromoTypeFields() {
    var type = document.getElementById('promo_type').value;
    var promoFields = document.querySelectorAll('.promo-code-fields');
    var bonusFields = document.querySelectorAll('.loyalty-bonus-fields');

    if (type === 'loyalty_bonus') {
        promoFields.forEach(function(el) { el.style.display = 'none'; });
        bonusFields.forEach(function(el) { el.style.display = ''; });
    } else {
        promoFields.forEach(function(el) { el.style.display = ''; });
        bonusFields.forEach(function(el) { el.style.display = 'none'; });
    }
}

// Run on page load
document.addEventListener('DOMContentLoaded', togglePromoTypeFields);
</script>

<style>
.promo-explanation {
    margin: 20px 0;
}
.promo-types-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    max-width: 800px;
}
.promo-type-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 15px 20px;
    border-radius: 4px;
}
.promo-type-card h4 {
    margin-top: 0;
    color: #1d2327;
}
.promo-type-card ul {
    margin-bottom: 10px;
}
.type-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
}
.type-badge.type-loyalty {
    background: #e7f3ff;
    color: #0066cc;
}
.type-badge.type-promo {
    background: #f0f6e6;
    color: #46760a;
}
</style>
