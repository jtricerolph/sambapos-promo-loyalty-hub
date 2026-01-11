<?php
/**
 * Admin Promos View
 *
 * Manage Customer Promos (for loyalty members) and Promo Codes (for anyone).
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

// Separate promos by type
$customer_promos = array_filter($promos, function ($p) {
    return $p->type === 'loyalty_bonus';
});
$promo_codes = array_filter($promos, function ($p) {
    return $p->type === 'promo_code';
});

// Determine which type we're adding/editing
$editing_type = null;
if ($editing) {
    $editing_type = $editing->type;
} elseif (isset($_GET['add'])) {
    $editing_type = $_GET['add'] === 'customer' ? 'loyalty_bonus' : 'promo_code';
}
?>

<div class="wrap loyalty-hub-admin">
    <h1>Promos</h1>

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

    <!-- ================================================================
         CUSTOMER PROMOS SECTION
         Applied automatically to registered members when criteria match
         ================================================================ -->
    <div class="promo-section">
        <h2>
            Customer Promos
            <?php if (!$editing) : ?>
                <a href="<?php echo admin_url('admin.php?page=loyalty-hub-promos&add=customer'); ?>#add-promo-form"
                   class="page-title-action">Add New</a>
            <?php endif; ?>
        </h2>
        <p class="description">
            Automatically applied to registered loyalty members when criteria match. No code entry required.
            These bonuses increase the customer's tier discount.
        </p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Bonus</th>
                    <th>Hotel</th>
                    <th>Time Restrictions</th>
                    <th>Valid Period</th>
                    <th>Uses</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customer_promos)) : ?>
                    <tr>
                        <td colspan="8">No customer promos yet. These are automatic bonuses for loyalty members.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($customer_promos as $promo) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($promo->name); ?></strong>
                                <?php if ($promo->description) : ?>
                                    <br><span class="description"><?php echo esc_html($promo->description); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($promo->bonus_add_wet) || !empty($promo->bonus_add_dry)) : ?>
                                    <span class="bonus-badge">+<?php echo esc_html($promo->bonus_add_wet ?? 0); ?>%</span> /
                                    <span class="bonus-badge">+<?php echo esc_html($promo->bonus_add_dry ?? 0); ?>%</span>
                                    <br><span class="description">wet / dry</span>
                                <?php else : ?>
                                    <span class="bonus-badge"><?php echo esc_html($promo->bonus_multiplier); ?>x</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($promo->hotel_name ?: 'All'); ?></td>
                            <td>
                                <?php
                                $restrictions = array();
                                if ($promo->time_start && $promo->time_end) {
                                    $restrictions[] = date('H:i', strtotime($promo->time_start)) . '-' . date('H:i', strtotime($promo->time_end));
                                }
                                if ($promo->valid_days) {
                                    $restrictions[] = $promo->valid_days;
                                }
                                echo $restrictions ? esc_html(implode(', ', $restrictions)) : 'Any time';
                                ?>
                            </td>
                            <td>
                                <?php
                                if ($promo->valid_from || $promo->valid_until) {
                                    $from = $promo->valid_from ? date('d M Y', strtotime($promo->valid_from)) : 'Start';
                                    $until = $promo->valid_until ? date('d M Y', strtotime($promo->valid_until)) : 'No end';
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
                                <a href="<?php echo admin_url('admin.php?page=loyalty-hub-promos&edit=' . $promo->id); ?>#add-promo-form">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ================================================================
         PROMO CODES SECTION
         One-off discount codes for anyone (members or guests)
         ================================================================ -->
    <div class="promo-section" style="margin-top: 40px;">
        <h2>
            Promo Codes
            <?php if (!$editing) : ?>
                <a href="<?php echo admin_url('admin.php?page=loyalty-hub-promos&add=code'); ?>#add-promo-form"
                   class="page-title-action">Add New</a>
            <?php endif; ?>
        </h2>
        <p class="description">
            Discount codes that customers or staff can enter. Work for both members and non-members.
            Uses separate account codes (#2305/#3305) for tracking.
        </p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Discount</th>
                    <th>Hotel</th>
                    <th>Valid Period</th>
                    <th>Uses</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($promo_codes)) : ?>
                    <tr>
                        <td colspan="8">No promo codes yet. Create codes that anyone can use for discounts.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($promo_codes as $promo) : ?>
                        <tr>
                            <td><code><strong><?php echo esc_html($promo->code); ?></strong></code></td>
                            <td>
                                <?php echo esc_html($promo->name); ?>
                                <?php if ($promo->description) : ?>
                                    <br><span class="description"><?php echo esc_html($promo->description); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="discount-display">
                                    <?php echo esc_html($promo->wet_discount); ?>% / <?php echo esc_html($promo->dry_discount); ?>%
                                </span>
                                <br><span class="description">wet / dry</span>
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
                                <a href="<?php echo admin_url('admin.php?page=loyalty-hub-promos&edit=' . $promo->id); ?>#add-promo-form">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ================================================================
         ADD/EDIT FORM
         Shows different fields based on promo type
         ================================================================ -->
    <?php if ($editing_type) : ?>
    <div id="add-promo-form" class="loyalty-hub-form-section" style="margin-top: 40px;">
        <?php if ($editing_type === 'loyalty_bonus') : ?>
            <!-- CUSTOMER PROMO FORM -->
            <h2><?php echo $editing ? 'Edit Customer Promo' : 'Add Customer Promo'; ?></h2>
            <p class="description">
                Customer promos are automatically applied when a loyalty member matches the criteria.
                They increase the customer's tier discount by a multiplier or fixed percentage.
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('loyalty_hub_admin', 'loyalty_hub_nonce'); ?>
                <input type="hidden" name="loyalty_hub_action"
                       value="<?php echo $editing ? 'edit_promo' : 'add_promo'; ?>">
                <input type="hidden" name="promo_type" value="loyalty_bonus">
                <input type="hidden" name="requires_membership" value="1">

                <?php if ($editing) : ?>
                    <input type="hidden" name="promo_id" value="<?php echo esc_attr($editing->id); ?>">
                    <input type="hidden" name="promo_code" value="<?php echo esc_attr($editing->code); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="promo_name">Name *</label>
                        </th>
                        <td>
                            <input type="text" id="promo_name" name="promo_name" class="regular-text"
                                   value="<?php echo esc_attr($editing->name ?? ''); ?>" required>
                            <p class="description">E.g., "Double Lunch Discount", "Weekend Bonus"</p>
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
                            <p class="description">Shown to staff when customer scans.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Bonus Type *</th>
                        <td>
                            <?php
                            $bonus_type = 'multiplier';
                            if (!empty($editing->bonus_add_wet) || !empty($editing->bonus_add_dry)) {
                                $bonus_type = 'add';
                            }
                            ?>
                            <label style="margin-right: 20px;">
                                <input type="radio" name="bonus_type" value="multiplier"
                                       <?php checked($bonus_type, 'multiplier'); ?>
                                       onchange="toggleBonusTypeFields()">
                                <strong>Multiplier</strong> - Multiply tier discount (e.g., 2x = double)
                            </label>
                            <br><br>
                            <label>
                                <input type="radio" name="bonus_type" value="add"
                                       <?php checked($bonus_type, 'add'); ?>
                                       onchange="toggleBonusTypeFields()">
                                <strong>Fixed Add</strong> - Add fixed % to tier discount
                            </label>
                        </td>
                    </tr>
                    <tr class="bonus-multiplier-field">
                        <th scope="row">
                            <label for="bonus_multiplier">Multiplier</label>
                        </th>
                        <td>
                            <input type="number" id="bonus_multiplier" name="bonus_multiplier"
                                   value="<?php echo esc_attr($editing->bonus_multiplier ?? 2); ?>"
                                   min="1" max="10" step="0.1" class="small-text">
                            <span>x</span>
                            <p class="description">
                                E.g., 2 = double discount (10% becomes 20%), 1.5 = 50% extra (10% becomes 15%)
                            </p>
                        </td>
                    </tr>
                    <tr class="bonus-add-field" style="display: none;">
                        <th scope="row">Add Percentages</th>
                        <td>
                            <label>
                                Wet (drinks): +
                                <input type="number" id="bonus_add_wet" name="bonus_add_wet"
                                       value="<?php echo esc_attr($editing->bonus_add_wet ?? 5); ?>"
                                       min="0" max="50" step="0.5" class="small-text">%
                            </label>
                            &nbsp;&nbsp;
                            <label>
                                Dry (food): +
                                <input type="number" id="bonus_add_dry" name="bonus_add_dry"
                                       value="<?php echo esc_attr($editing->bonus_add_dry ?? 10); ?>"
                                       min="0" max="50" step="0.5" class="small-text">%
                            </label>
                            <p class="description">
                                Added to tier discount. E.g., +5% wet / +10% dry means 10% tier becomes 15% / 20%.
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
                            <p class="description">E.g., 11:30 - 14:30 for lunch bonus. Leave blank for all day.</p>
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
                            <p class="description">Leave blank for no date restrictions.</p>
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
                           value="<?php echo $editing ? 'Update Customer Promo' : 'Create Customer Promo'; ?>">
                    <a href="<?php echo admin_url('admin.php?page=loyalty-hub-promos'); ?>" class="button">
                        Cancel
                    </a>
                </p>
            </form>

        <?php else : ?>
            <!-- PROMO CODE FORM -->
            <h2><?php echo $editing ? 'Edit Promo Code' : 'Add Promo Code'; ?></h2>
            <p class="description">
                Promo codes are discount codes that staff enter for customers. They work for anyone (members or guests).
                Uses separate account codes (#2305/#3305) for reporting.
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('loyalty_hub_admin', 'loyalty_hub_nonce'); ?>
                <input type="hidden" name="loyalty_hub_action"
                       value="<?php echo $editing ? 'edit_promo' : 'add_promo'; ?>">
                <input type="hidden" name="promo_type" value="promo_code">

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
                            <p class="description">The code staff will enter. Automatically converted to uppercase.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="promo_name">Name *</label>
                        </th>
                        <td>
                            <input type="text" id="promo_name" name="promo_name" class="regular-text"
                                   value="<?php echo esc_attr($editing->name ?? ''); ?>" required>
                            <p class="description">Display name for reports.</p>
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
                        <th scope="row">Discount Percentages *</th>
                        <td>
                            <label>
                                Wet (drinks):
                                <input type="number" name="wet_discount"
                                       value="<?php echo esc_attr($editing->wet_discount ?? 10); ?>"
                                       min="0" max="100" step="0.5" class="small-text">%
                            </label>
                            &nbsp;&nbsp;
                            <label>
                                Dry (food):
                                <input type="number" name="dry_discount"
                                       value="<?php echo esc_attr($editing->dry_discount ?? 10); ?>"
                                       min="0" max="100" step="0.5" class="small-text">%
                            </label>
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
                            <p class="description">Leave blank for no date restrictions.</p>
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
                            <p class="description">Leave blank for all day.</p>
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
                            <p class="description">Usually left unchecked so guests can use the code too.</p>
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
                           value="<?php echo $editing ? 'Update Promo Code' : 'Create Promo Code'; ?>">
                    <a href="<?php echo admin_url('admin.php?page=loyalty-hub-promos'); ?>" class="button">
                        Cancel
                    </a>
                </p>
            </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleBonusTypeFields() {
    var bonusType = document.querySelector('input[name="bonus_type"]:checked');
    var multiplierField = document.querySelector('.bonus-multiplier-field');
    var addField = document.querySelector('.bonus-add-field');

    if (!bonusType || !multiplierField || !addField) return;

    if (bonusType.value === 'add') {
        multiplierField.style.display = 'none';
        addField.style.display = '';
    } else {
        multiplierField.style.display = '';
        addField.style.display = 'none';
    }
}

// Run on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleBonusTypeFields();
});
</script>

<style>
.promo-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin-top: 20px;
}
.promo-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}
.promo-section > .description {
    margin-bottom: 15px;
    color: #666;
}
.bonus-badge {
    display: inline-block;
    padding: 4px 10px;
    background: #e7f3ff;
    color: #0066cc;
    border-radius: 3px;
    font-weight: 600;
}
.discount-display {
    font-weight: 600;
}
.status-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
}
.status-badge.status-active {
    background: #e6f4ea;
    color: #137333;
}
.status-badge.status-inactive {
    background: #fce8e6;
    color: #c5221f;
}
.loyalty-hub-form-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
}
.loyalty-hub-form-section h2 {
    margin-top: 0;
}
</style>
