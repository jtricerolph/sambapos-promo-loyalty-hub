<?php
/**
 * Promo Handler for Loyalty Hub
 *
 * Manages promo validation, application, and usage tracking.
 *
 * ==========================================================================
 * PROMO TYPES
 * ==========================================================================
 *
 * 1. LOYALTY BONUS (type = 'loyalty_bonus')
 *    - Increases the member's tier discount
 *    - Uses bonus_multiplier (e.g., 2.0 = double discount)
 *    - Requires membership (customer must be identified first)
 *    - Uses same account codes as tier discount (#2303/#3303)
 *    - discount_type remains "discount"
 *
 *    Example: "LUNCH2X" - Double your loyalty discount at lunch
 *    - Customer has 10% tier discount
 *    - With LUNCH2X, they get 20% (10% * 2.0)
 *    - Still uses #2303/#3303 codes
 *
 * 2. PROMO CODE (type = 'promo_code')
 *    - One-off discount with fixed percentages
 *    - Works for members AND non-members (guests)
 *    - Uses wet_discount/dry_discount from promo
 *    - If member uses this, it REPLACES their tier discount
 *    - Uses #2305/#3305 account codes
 *    - discount_type = "promo"
 *
 *    Example: "SUMMER24" - 15% off everything
 *    - Anyone can use it (member or guest)
 *    - Fixed 15% wet and dry discount
 *    - Uses #2305/#3305 codes
 *
 * ==========================================================================
 * PROMO RESTRICTIONS
 * ==========================================================================
 *
 * Promos can have various restrictions:
 *
 * - hotel_id: NULL = all hotels, or specific hotel only
 * - valid_from/valid_until: Date range
 * - time_start/time_end: Time of day (e.g., 11:30-14:30 for lunch)
 * - valid_days: Comma-separated days (e.g., "Mon,Tue,Wed")
 * - max_uses: Total redemptions allowed across all customers
 * - max_uses_per_customer: Per-customer redemption limit
 * - min_spend: Minimum transaction amount to qualify
 * - requires_membership: Must be an identified member to use
 *
 * ==========================================================================
 * AVAILABLE PROMOS FLOW
 * ==========================================================================
 *
 * When a customer scans their fob, the /identify endpoint returns
 * available_promos[] - an array of promos they can use.
 *
 * This includes:
 * 1. General promos valid at this hotel
 * 2. Targeted promos assigned specifically to this customer
 *
 * The SambaPOS JScript can display these in an AskQuestion dialog:
 *
 *   ┌─────────────────────────────────────────┐
 *   │  Welcome back, John! (Loyalty Tier)     │
 *   │                                         │
 *   │  You have 2 available promos:           │
 *   │                                         │
 *   │  [LUNCH2X] Double discount at lunch     │
 *   │  [BIRTHDAY] 20% extra - expires today!  │
 *   │                                         │
 *   │            [ OK ]  [ Apply Code ]       │
 *   └─────────────────────────────────────────┘
 *
 * @package Loyalty_Hub
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Loyalty_Hub_Promo_Handler {

    /**
     * Get available promos for a customer at a specific hotel
     *
     * Returns PROMO CODE type promos that the customer can currently use.
     * Customer promos (loyalty_bonus) are auto-applied and not returned here.
     * Checks all restrictions (date, time, day, usage limits, etc.)
     *
     * @since 1.0.0
     *
     * @param int $customer_id The customer ID (can be null for guest)
     * @param int $hotel_id    The hotel where promo would be used
     *
     * @return array Array of available promo objects (promo_code type only)
     */
    public static function get_available_promos($customer_id, $hotel_id) {
        global $wpdb;

        $promos = array();
        $now = current_time('mysql');
        $current_time = current_time('H:i:s');
        $current_day = current_time('D'); // Mon, Tue, etc.

        // ----------------------------------------------------------------
        // Get general promo_code promos valid at this hotel
        // (loyalty_bonus promos are auto-applied, not shown here)
        // ----------------------------------------------------------------
        $general_promos = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*
             FROM {$wpdb->prefix}loyalty_promos p
             WHERE p.is_active = 1
               AND p.type = 'promo_code'
               AND (p.hotel_id IS NULL OR p.hotel_id = %d)
               AND (p.valid_from IS NULL OR p.valid_from <= %s)
               AND (p.valid_until IS NULL OR p.valid_until >= %s)
               AND (p.requires_membership = 0 OR %d IS NOT NULL)",
            $hotel_id,
            $now,
            $now,
            $customer_id
        ));

        foreach ($general_promos as $promo) {
            // Check time restriction
            if (!self::check_time_restriction($promo, $current_time)) {
                continue;
            }

            // Check day restriction
            if (!self::check_day_restriction($promo, $current_day)) {
                continue;
            }

            // Check usage limits
            if (!self::check_usage_limits($promo, $customer_id)) {
                continue;
            }

            $promos[] = self::format_promo($promo);
        }

        // ----------------------------------------------------------------
        // Get targeted promo_code promos assigned to this customer
        // (loyalty_bonus promos are auto-applied, not shown here)
        // ----------------------------------------------------------------
        if ($customer_id) {
            $targeted_promos = $wpdb->get_results($wpdb->prepare(
                "SELECT p.*, cp.expires_at as assigned_expires_at
                 FROM {$wpdb->prefix}loyalty_customer_promos cp
                 JOIN {$wpdb->prefix}loyalty_promos p ON cp.promo_id = p.id
                 WHERE cp.customer_id = %d
                   AND p.is_active = 1
                   AND p.type = 'promo_code'
                   AND (p.hotel_id IS NULL OR p.hotel_id = %d)
                   AND (cp.expires_at IS NULL OR cp.expires_at >= %s)",
                $customer_id,
                $hotel_id,
                $now
            ));

            foreach ($targeted_promos as $promo) {
                // Check time restriction
                if (!self::check_time_restriction($promo, $current_time)) {
                    continue;
                }

                // Check day restriction
                if (!self::check_day_restriction($promo, $current_day)) {
                    continue;
                }

                // Check usage limits
                if (!self::check_usage_limits($promo, $customer_id)) {
                    continue;
                }

                // Mark as targeted
                $formatted = self::format_promo($promo);
                $formatted['targeted'] = true;
                if (!empty($promo->assigned_expires_at)) {
                    $formatted['expires_at'] = $promo->assigned_expires_at;
                }
                $promos[] = $formatted;
            }
        }

        return $promos;
    }

    /**
     * Validate a promo code
     *
     * Checks if a promo code is valid and can be used.
     *
     * @since 1.0.0
     *
     * @param string    $code         The promo code
     * @param int|null  $customer_id  Customer ID (null for guest)
     * @param int       $hotel_id     Hotel where promo is being used
     * @param float|null $total_amount Transaction total (for min_spend check)
     *
     * @return array {
     *     @type bool   $valid  Whether promo is valid
     *     @type object $promo  Promo details (if valid)
     *     @type string $error  Error message (if invalid)
     * }
     */
    public static function validate($code, $customer_id, $hotel_id, $total_amount = null) {
        global $wpdb;

        // Look up promo by code
        $promo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}loyalty_promos WHERE code = %s",
            strtoupper($code)
        ));

        if (!$promo) {
            return array(
                'valid' => false,
                'error' => 'Promo code not found',
            );
        }

        // Check if active
        if (!$promo->is_active) {
            return array(
                'valid' => false,
                'error' => 'This promo code is no longer active',
            );
        }

        // Check hotel restriction
        if ($promo->hotel_id && $promo->hotel_id != $hotel_id) {
            return array(
                'valid' => false,
                'error' => 'This promo is not valid at this location',
            );
        }

        // Check date range
        $now = current_time('mysql');
        if ($promo->valid_from && $promo->valid_from > $now) {
            return array(
                'valid' => false,
                'error' => 'This promo is not yet active',
            );
        }
        if ($promo->valid_until && $promo->valid_until < $now) {
            return array(
                'valid' => false,
                'error' => 'This promo has expired',
            );
        }

        // Check time restriction
        $current_time = current_time('H:i:s');
        if (!self::check_time_restriction($promo, $current_time)) {
            return array(
                'valid' => false,
                'error' => 'This promo is not valid at this time of day',
            );
        }

        // Check day restriction
        $current_day = current_time('D');
        if (!self::check_day_restriction($promo, $current_day)) {
            return array(
                'valid' => false,
                'error' => 'This promo is not valid today',
            );
        }

        // Check membership requirement
        if ($promo->requires_membership && !$customer_id) {
            return array(
                'valid' => false,
                'error' => 'This promo requires membership. Please scan your card first.',
            );
        }

        // Check min spend
        if ($promo->min_spend && $total_amount !== null && $total_amount < $promo->min_spend) {
            return array(
                'valid' => false,
                'error' => sprintf('Minimum spend of £%.2f required', $promo->min_spend),
            );
        }

        // Check usage limits
        if (!self::check_usage_limits($promo, $customer_id)) {
            return array(
                'valid' => false,
                'error' => 'This promo has reached its usage limit',
            );
        }

        // All checks passed
        return array(
            'valid' => true,
            'promo' => self::format_promo($promo),
        );
    }

    /**
     * Apply a promo code and calculate discount rates
     *
     * For loyalty_bonus: Multiplies the base tier discount
     * For promo_code: Returns the promo's fixed discount
     *
     * @since 1.0.0
     *
     * @param string    $code       The promo code
     * @param int|null  $customer_id Customer ID (required for loyalty_bonus)
     * @param int       $hotel_id    Hotel where promo is being used
     * @param float     $base_wet    Base wet discount (for loyalty_bonus)
     * @param float     $base_dry    Base dry discount (for loyalty_bonus)
     *
     * @return array|WP_Error {
     *     @type bool   $success       Whether promo was applied
     *     @type string $discount_type "discount" or "promo"
     *     @type float  $wet_discount  Final wet discount %
     *     @type float  $dry_discount  Final dry discount %
     *     @type string $promo_code    The applied promo code
     * }
     */
    public static function apply($code, $customer_id, $hotel_id, $base_wet = 0, $base_dry = 0) {
        global $wpdb;

        // Validate first
        $validation = self::validate($code, $customer_id, $hotel_id);
        if (!$validation['valid']) {
            return new WP_Error(
                'promo_invalid',
                $validation['error'],
                array('status' => 400)
            );
        }

        $promo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}loyalty_promos WHERE code = %s",
            strtoupper($code)
        ));

        // ----------------------------------------------------------------
        // Handle LOYALTY BONUS type
        // ----------------------------------------------------------------
        if ($promo->type === 'loyalty_bonus') {
            // Must have base discount to boost
            if ($base_wet == 0 && $base_dry == 0) {
                return new WP_Error(
                    'no_base_discount',
                    'Loyalty bonus requires an existing tier discount',
                    array('status' => 400)
                );
            }

            // Calculate boosted discount rates
            $calculated = self::calculate_loyalty_bonus($promo, $base_wet, $base_dry);

            return array(
                'success'       => true,
                'discount_type' => 'discount',  // Still uses #2303/#3303
                'wet_discount'  => $calculated['wet_discount'],
                'dry_discount'  => $calculated['dry_discount'],
                'promo_code'    => $promo->code,
                'promo_name'    => $promo->name,
                'type'          => 'loyalty_bonus',
            );
        }

        // ----------------------------------------------------------------
        // Handle PROMO CODE type
        // ----------------------------------------------------------------
        // Uses the promo's fixed discount rates
        // REPLACES any existing tier discount
        return array(
            'success'       => true,
            'discount_type' => 'promo',  // Uses #2305/#3305
            'wet_discount'  => floatval($promo->wet_discount) ?: 0,
            'dry_discount'  => floatval($promo->dry_discount) ?: 0,
            'promo_code'    => $promo->code,
            'promo_name'    => $promo->name,
            'type'          => 'promo_code',
        );
    }

    /**
     * Record promo usage
     *
     * Called after a transaction is logged to track promo redemptions.
     *
     * @since 1.0.0
     *
     * @param string   $code            The promo code
     * @param int|null $customer_id     Customer ID (null for guest)
     * @param int      $transaction_id  Transaction ID
     * @param float    $discount_amount Discount amount given
     *
     * @return bool Whether usage was recorded
     */
    public static function record_usage($code, $customer_id, $transaction_id, $discount_amount) {
        global $wpdb;

        $promo = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}loyalty_promos WHERE code = %s",
            strtoupper($code)
        ));

        if (!$promo) {
            return false;
        }

        $wpdb->insert(
            $wpdb->prefix . 'loyalty_promo_usage',
            array(
                'promo_id'        => $promo->id,
                'customer_id'     => $customer_id,
                'transaction_id'  => $transaction_id,
                'discount_amount' => $discount_amount,
            ),
            array('%d', '%d', '%d', '%f')
        );

        return $wpdb->insert_id ? true : false;
    }

    /**
     * Check time restriction for a promo
     *
     * @param object $promo        Promo database row
     * @param string $current_time Current time (H:i:s format)
     *
     * @return bool Whether promo passes time restriction
     */
    private static function check_time_restriction($promo, $current_time) {
        // No time restriction
        if (empty($promo->time_start) && empty($promo->time_end)) {
            return true;
        }

        // Only start time set
        if (!empty($promo->time_start) && empty($promo->time_end)) {
            return $current_time >= $promo->time_start;
        }

        // Only end time set
        if (empty($promo->time_start) && !empty($promo->time_end)) {
            return $current_time <= $promo->time_end;
        }

        // Both set - check range
        return $current_time >= $promo->time_start && $current_time <= $promo->time_end;
    }

    /**
     * Check day restriction for a promo
     *
     * @param object $promo       Promo database row
     * @param string $current_day Current day (Mon, Tue, etc.)
     *
     * @return bool Whether promo passes day restriction
     */
    private static function check_day_restriction($promo, $current_day) {
        // No day restriction
        if (empty($promo->valid_days)) {
            return true;
        }

        // Check if current day is in the list
        $valid_days = array_map('trim', explode(',', $promo->valid_days));
        return in_array($current_day, $valid_days);
    }

    /**
     * Check usage limits for a promo
     *
     * @param object   $promo       Promo database row
     * @param int|null $customer_id Customer ID
     *
     * @return bool Whether promo passes usage limits
     */
    private static function check_usage_limits($promo, $customer_id) {
        global $wpdb;

        // Check total usage limit
        if ($promo->max_uses) {
            $total_uses = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}loyalty_promo_usage WHERE promo_id = %d",
                $promo->id
            ));
            if ($total_uses >= $promo->max_uses) {
                return false;
            }
        }

        // Check per-customer limit
        if ($promo->max_uses_per_customer && $customer_id) {
            $customer_uses = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$wpdb->prefix}loyalty_promo_usage
                 WHERE promo_id = %d AND customer_id = %d",
                $promo->id,
                $customer_id
            ));
            if ($customer_uses >= $promo->max_uses_per_customer) {
                return false;
            }
        }

        return true;
    }

    /**
     * Format promo for API response
     *
     * @param object $promo Promo database row
     *
     * @return array Formatted promo data
     */
    private static function format_promo($promo) {
        return array(
            'id'               => $promo->id,
            'code'             => $promo->code,
            'name'             => $promo->name,
            'description'      => $promo->description,
            'type'             => $promo->type,
            'wet_discount'     => floatval($promo->wet_discount),
            'dry_discount'     => floatval($promo->dry_discount),
            'bonus_multiplier' => floatval($promo->bonus_multiplier),
            'bonus_add_wet'    => floatval($promo->bonus_add_wet ?? 0),
            'bonus_add_dry'    => floatval($promo->bonus_add_dry ?? 0),
            'min_spend'        => floatval($promo->min_spend),
            'valid_until'      => $promo->valid_until,
        );
    }

    /**
     * Get the best applicable customer promo (loyalty_bonus) for a customer
     *
     * Finds all valid loyalty_bonus promos for the customer and determines
     * which one provides the best discount when applied to their tier rates.
     *
     * @since 1.0.0
     *
     * @param int   $customer_id Customer ID
     * @param int   $hotel_id    Hotel ID
     * @param float $base_wet    Base wet discount (tier rate)
     * @param float $base_dry    Base dry discount (tier rate)
     *
     * @return array|null Best promo with calculated discounts, or null if none
     */
    public static function get_best_customer_promo($customer_id, $hotel_id, $base_wet, $base_dry) {
        global $wpdb;

        if (!$customer_id || ($base_wet == 0 && $base_dry == 0)) {
            return null;
        }

        $now = current_time('mysql');
        $current_time = current_time('H:i:s');
        $current_day = current_time('D');

        // Get all active loyalty_bonus promos valid at this hotel
        $promos = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*
             FROM {$wpdb->prefix}loyalty_promos p
             WHERE p.is_active = 1
               AND p.type = 'loyalty_bonus'
               AND (p.hotel_id IS NULL OR p.hotel_id = %d)
               AND (p.valid_from IS NULL OR p.valid_from <= %s)
               AND (p.valid_until IS NULL OR p.valid_until >= %s)
               AND p.requires_membership = 1",
            $hotel_id,
            $now,
            $now
        ));

        // Also get targeted loyalty_bonus promos for this customer
        $targeted_promos = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, cp.expires_at as assigned_expires_at
             FROM {$wpdb->prefix}loyalty_customer_promos cp
             JOIN {$wpdb->prefix}loyalty_promos p ON cp.promo_id = p.id
             WHERE cp.customer_id = %d
               AND p.is_active = 1
               AND p.type = 'loyalty_bonus'
               AND (p.hotel_id IS NULL OR p.hotel_id = %d)
               AND (cp.expires_at IS NULL OR cp.expires_at >= %s)",
            $customer_id,
            $hotel_id,
            $now
        ));

        // Merge and dedupe (targeted promos may overlap with general promos)
        $all_promos = array();
        $seen_ids = array();
        foreach (array_merge($promos, $targeted_promos) as $promo) {
            if (!in_array($promo->id, $seen_ids)) {
                $all_promos[] = $promo;
                $seen_ids[] = $promo->id;
            }
        }

        $best_promo = null;
        $best_total = 0;

        foreach ($all_promos as $promo) {
            // Check time restriction
            if (!self::check_time_restriction($promo, $current_time)) {
                continue;
            }

            // Check day restriction
            if (!self::check_day_restriction($promo, $current_day)) {
                continue;
            }

            // Check usage limits
            if (!self::check_usage_limits($promo, $customer_id)) {
                continue;
            }

            // Calculate resulting discounts
            $calculated = self::calculate_loyalty_bonus($promo, $base_wet, $base_dry);
            $total_discount = $calculated['wet_discount'] + $calculated['dry_discount'];

            // Keep track of best (highest combined discount)
            if ($total_discount > $best_total) {
                $best_total = $total_discount;
                $best_promo = array(
                    'promo'        => self::format_promo($promo),
                    'wet_discount' => $calculated['wet_discount'],
                    'dry_discount' => $calculated['dry_discount'],
                );
            }
        }

        return $best_promo;
    }

    /**
     * Calculate loyalty bonus discount rates
     *
     * Applies either multiplier or add percentages to base tier rates.
     *
     * @param object $promo    Promo database row
     * @param float  $base_wet Base wet discount
     * @param float  $base_dry Base dry discount
     *
     * @return array Calculated wet_discount and dry_discount
     */
    private static function calculate_loyalty_bonus($promo, $base_wet, $base_dry) {
        $wet_discount = $base_wet;
        $dry_discount = $base_dry;

        // Check if using add percentages (takes priority if set)
        $add_wet = floatval($promo->bonus_add_wet ?? 0);
        $add_dry = floatval($promo->bonus_add_dry ?? 0);

        if ($add_wet > 0 || $add_dry > 0) {
            // Add fixed percentage
            $wet_discount = min($base_wet + $add_wet, 100);
            $dry_discount = min($base_dry + $add_dry, 100);
        } else {
            // Use multiplier
            $multiplier = floatval($promo->bonus_multiplier) ?: 1;
            $wet_discount = min($base_wet * $multiplier, 100);
            $dry_discount = min($base_dry * $multiplier, 100);
        }

        return array(
            'wet_discount' => $wet_discount,
            'dry_discount' => $dry_discount,
        );
    }
}
