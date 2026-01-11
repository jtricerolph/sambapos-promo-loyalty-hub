<?php
/**
 * Admin Hotels View
 *
 * Manage hotel/venue configurations and API keys.
 *
 * Available variables:
 * @var array  $hotels   List of all hotels
 * @var object $editing  Hotel being edited (if any)
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
        Hotels
        <?php if (!$editing) : ?>
            <a href="#add-hotel-form" class="page-title-action">Add New</a>
        <?php endif; ?>
    </h1>

    <?php if (isset($_GET['added'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Hotel added successfully. Don't forget to configure tier rates!</p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p>Hotel updated successfully.</p>
        </div>
    <?php endif; ?>

    <!-- Hotels List -->
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Slug</th>
                <th>API Key</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($hotels)) : ?>
                <tr>
                    <td colspan="5">No hotels configured yet.</td>
                </tr>
            <?php else : ?>
                <?php foreach ($hotels as $hotel) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($hotel->name); ?></strong></td>
                        <td><code><?php echo esc_html($hotel->slug); ?></code></td>
                        <td>
                            <code class="api-key" data-key="<?php echo esc_attr($hotel->api_key); ?>">
                                <?php echo esc_html(substr($hotel->api_key, 0, 8)); ?>...
                            </code>
                            <button type="button" class="button button-small copy-api-key"
                                    data-key="<?php echo esc_attr($hotel->api_key); ?>">
                                Copy
                            </button>
                        </td>
                        <td>
                            <?php if ($hotel->is_active) : ?>
                                <span class="status-badge status-active">Active</span>
                            <?php else : ?>
                                <span class="status-badge status-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=loyalty-hub-hotels&edit=' . $hotel->id); ?>">
                                Edit
                            </a>
                            |
                            <a href="<?php echo admin_url('admin.php?page=loyalty-hub-tiers&hotel=' . $hotel->id); ?>">
                                Configure Tiers
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Add/Edit Form -->
    <div id="add-hotel-form" class="loyalty-hub-form-section">
        <h2><?php echo $editing ? 'Edit Hotel' : 'Add New Hotel'; ?></h2>

        <form method="post" action="">
            <?php wp_nonce_field('loyalty_hub_admin', 'loyalty_hub_nonce'); ?>
            <input type="hidden" name="loyalty_hub_action" value="<?php echo $editing ? 'edit_hotel' : 'add_hotel'; ?>">

            <?php if ($editing) : ?>
                <input type="hidden" name="hotel_id" value="<?php echo esc_attr($editing->id); ?>">
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="hotel_name">Hotel Name *</label>
                    </th>
                    <td>
                        <input type="text" id="hotel_name" name="hotel_name" class="regular-text"
                               value="<?php echo esc_attr($editing->name ?? ''); ?>" required>
                        <p class="description">Display name for this hotel/venue.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="hotel_slug">Slug</label>
                    </th>
                    <td>
                        <input type="text" id="hotel_slug" name="hotel_slug" class="regular-text"
                               value="<?php echo esc_attr($editing->slug ?? ''); ?>">
                        <p class="description">URL-friendly identifier. Auto-generated if left blank.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="hotel_address">Address</label>
                    </th>
                    <td>
                        <textarea id="hotel_address" name="hotel_address" class="large-text" rows="3"><?php
                            echo esc_textarea($editing->address ?? '');
                        ?></textarea>
                    </td>
                </tr>

                <?php if ($editing) : ?>
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <code><?php echo esc_html($editing->api_key); ?></code>
                            <button type="button" class="button button-small copy-api-key"
                                    data-key="<?php echo esc_attr($editing->api_key); ?>">
                                Copy
                            </button>
                            <p class="description">
                                Use this key in the X-API-Key header for API requests from this hotel's SambaPOS.
                            </p>
                        </td>
                    </tr>
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
                       value="<?php echo $editing ? 'Update Hotel' : 'Add Hotel'; ?>">
                <?php if ($editing) : ?>
                    <a href="<?php echo admin_url('admin.php?page=loyalty-hub-hotels'); ?>" class="button">
                        Cancel
                    </a>
                <?php endif; ?>
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Copy API key to clipboard
    $('.copy-api-key').on('click', function() {
        var key = $(this).data('key');
        navigator.clipboard.writeText(key).then(function() {
            alert('API key copied to clipboard!');
        });
    });
});
</script>
