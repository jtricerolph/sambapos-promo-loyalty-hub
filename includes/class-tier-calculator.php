<?php
/**
 * Tier Calculator for Loyalty Hub
 *
 * This is the CORE business logic class that calculates customer tiers
 * based on visit frequency across all hotels.
 *
 * ==========================================================================
 * TIER CALCULATION ALGORITHM
 * ==========================================================================
 *
 * The tier calculation follows this logic:
 *
 * 1. CHECK STAFF STATUS
 *    - If customer.is_staff = true, skip tier calculation
 *    - Return staff rates from VISITING hotel
 *    - Use discount_type = "staff"
 *
 * 2. COUNT GLOBAL VISITS
 *    - Count visits across ALL hotels in the rolling period (default 28 days)
 *    - Visits are transactions in loyalty_transactions table
 *
 * 3. CALCULATE TIER AT HOME HOTEL
 *    - Compare visit count against home hotel's thresholds
 *    - Get the highest tier the customer qualifies for
 *
 * 4. CALCULATE TIER AT VISITING HOTEL
 *    - Compare visit count against visiting hotel's thresholds
 *    - Get the highest tier the customer qualifies for
 *
 * 5. PICK THE BEST TIER
 *    - Compare sort_order of both tiers
 *    - Higher sort_order = better tier
 *    - Use the BEST tier from either hotel
 *
 * 6. GET RATES FROM VISITING HOTEL
 *    - Look up the discount percentages for the chosen tier
 *    - AT the VISITING hotel (not home hotel)
 *
 * ==========================================================================
 * EXAMPLE SCENARIOS
 * ==========================================================================
 *
 * EXAMPLE 1: Customer benefits from home hotel's lower thresholds
 * ---------------------------------------------------------------
 * Customer: John
 * Home hotel: Number Four (Loyalty: 2 visits, Regular: 4 visits)
 * Visiting: High Street (Loyalty: 4 visits, Regular: 8 visits)
 * Total visits: 3 in last 28 days
 *
 * Step 1: Not staff, continue
 * Step 2: Global visits = 3
 * Step 3: At Number Four: 3 >= 2 = LOYALTY tier
 * Step 4: At High Street: 3 < 4 = MEMBER tier
 * Step 5: Loyalty > Member = LOYALTY
 * Step 6: High Street's Loyalty rates = 8% wet, 15% dry
 *
 * Result: John gets LOYALTY tier at 8%/15% at High Street
 *
 * EXAMPLE 2: Customer benefits from visiting hotel's lower thresholds
 * -------------------------------------------------------------------
 * Customer: Jane
 * Home hotel: High Street (Loyalty: 4 visits, Regular: 8 visits)
 * Visiting: Number Four (Loyalty: 2 visits, Regular: 4 visits)
 * Total visits: 5 in last 28 days
 *
 * Step 1: Not staff, continue
 * Step 2: Global visits = 5
 * Step 3: At High Street: 5 >= 4 = LOYALTY tier
 * Step 4: At Number Four: 5 >= 4 = REGULAR tier
 * Step 5: Regular > Loyalty = REGULAR
 * Step 6: Number Four's Regular rates = 15% wet, 25% dry
 *
 * Result: Jane gets REGULAR tier at 15%/25% at Number Four
 *
 * EXAMPLE 3: Staff member
 * -----------------------
 * Customer: Sarah (is_staff = true)
 * Visiting: High Street (Staff rates: 25% wet, 30% dry)
 *
 * Step 1: is_staff = true, use staff discount
 * Step 6: High Street's staff rates = 25% wet, 30% dry
 *
 * Result: Sarah gets STAFF tier at 25%/30%
 *
 * @package Loyalty_Hub
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Loyalty_Hub_Tier_Calculator {

    /**
     * Calculate the tier and discount rates for a customer at a specific hotel
     *
     * This is the main entry point for tier calculation.
     * Called by the /identify API endpoint.
     *
     * @since 1.0.0
     *
     * @param int $customer_id      The customer's database ID
     * @param int $visiting_hotel_id The hotel where the customer is currently visiting
     *
     * @return array {
     *     Tier calculation result
     *
     *     @type string $tier           Tier name (Member, Loyalty, Regular, or Staff)
     *     @type bool   $is_staff       Whether customer is flagged as staff
     *     @type float  $wet_discount   Drink discount percentage
     *     @type float  $dry_discount   Food discount percentage
     *     @type string $discount_type  "discount", "promo", or "staff"
     *     @type int    $total_visits   Number of visits in the rolling period
     *     @type string $home_hotel     Name of customer's home hotel
     *     @type string $visiting_hotel Name of the visiting hotel
     * }
     */
    public static function calculate($customer_id, $visiting_hotel_id) {
        global $wpdb;

        // ------------------------------------------------------------------
        // STEP 1: Load customer data
        // ------------------------------------------------------------------
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT c.*, h.name as home_hotel_name
             FROM {$wpdb->prefix}loyalty_customers c
             LEFT JOIN {$wpdb->prefix}loyalty_hotels h ON c.home_hotel_id = h.id
             WHERE c.id = %d AND c.is_active = 1",
            $customer_id
        ));

        if (!$customer) {
            return new WP_Error('customer_not_found', 'Customer not found', array('status' => 404));
        }

        // Get visiting hotel name for response
        $visiting_hotel = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}loyalty_hotels WHERE id = %d",
            $visiting_hotel_id
        ));

        // ------------------------------------------------------------------
        // STEP 2: Check if customer is STAFF (overrides all tier logic)
        // ------------------------------------------------------------------
        if ($customer->is_staff) {
            return self::get_staff_result($customer, $visiting_hotel_id, $visiting_hotel);
        }

        // ------------------------------------------------------------------
        // STEP 3: Count GLOBAL visits (across ALL hotels)
        // ------------------------------------------------------------------
        // Get the period from the visiting hotel's tier config (default 28 days)
        $period_days = self::get_period_days($visiting_hotel_id);
        $total_visits = self::count_global_visits($customer_id, $period_days);

        // ------------------------------------------------------------------
        // STEP 4: Calculate tier at HOME hotel
        // ------------------------------------------------------------------
        $home_tier = self::get_tier_for_visits($customer->home_hotel_id, $total_visits);

        // ------------------------------------------------------------------
        // STEP 5: Calculate tier at VISITING hotel
        // ------------------------------------------------------------------
        $visiting_tier = self::get_tier_for_visits($visiting_hotel_id, $total_visits);

        // ------------------------------------------------------------------
        // STEP 6: Pick the BEST tier (highest sort_order wins)
        // ------------------------------------------------------------------
        $best_tier = ($home_tier['sort_order'] >= $visiting_tier['sort_order'])
            ? $home_tier
            : $visiting_tier;

        // ------------------------------------------------------------------
        // STEP 7: Get RATES from VISITING hotel for the best tier
        // ------------------------------------------------------------------
        $rates = self::get_hotel_rates_for_tier($visiting_hotel_id, $best_tier['tier_id']);

        // ------------------------------------------------------------------
        // STEP 8: Build and return result
        // ------------------------------------------------------------------
        return array(
            'tier'           => $best_tier['tier_name'],
            'tier_id'        => $best_tier['tier_id'],
            'is_staff'       => false,
            'wet_discount'   => floatval($rates['wet_discount']),
            'dry_discount'   => floatval($rates['dry_discount']),
            'discount_type'  => 'discount',  // Normal loyalty discount
            'total_visits'   => $total_visits,
            'period_days'    => $period_days,
            'home_hotel'     => $customer->home_hotel_name,
            'home_hotel_id'  => $customer->home_hotel_id,
            'visiting_hotel' => $visiting_hotel ? $visiting_hotel->name : null,
            'home_tier'      => $home_tier['tier_name'],
            'visiting_tier'  => $visiting_tier['tier_name'],
        );
    }

    /**
     * Get staff discount result
     *
     * Called when customer.is_staff = true.
     * Bypasses normal tier calculation and returns staff rates.
     *
     * @since 1.0.0
     *
     * @param object $customer          Customer database row
     * @param int    $visiting_hotel_id The visiting hotel ID
     * @param object $visiting_hotel    The visiting hotel database row
     *
     * @return array Staff discount result
     */
    private static function get_staff_result($customer, $visiting_hotel_id, $visiting_hotel) {
        global $wpdb;

        // Get staff rates from the VISITING hotel
        $staff_rates = $wpdb->get_row($wpdb->prepare(
            "SELECT wet_discount, dry_discount
             FROM {$wpdb->prefix}loyalty_hotel_staff_rates
             WHERE hotel_id = %d",
            $visiting_hotel_id
        ));

        // Default to 0% if no staff rates configured
        $wet = $staff_rates ? floatval($staff_rates->wet_discount) : 0;
        $dry = $staff_rates ? floatval($staff_rates->dry_discount) : 0;

        return array(
            'tier'           => 'Staff',
            'tier_id'        => null,
            'is_staff'       => true,
            'wet_discount'   => $wet,
            'dry_discount'   => $dry,
            'discount_type'  => 'staff',  // Uses #2306/#3306 account codes
            'total_visits'   => 0,        // Not relevant for staff
            'period_days'    => 0,
            'home_hotel'     => $customer->home_hotel_name,
            'home_hotel_id'  => $customer->home_hotel_id,
            'visiting_hotel' => $visiting_hotel ? $visiting_hotel->name : null,
            'home_tier'      => 'Staff',
            'visiting_tier'  => 'Staff',
        );
    }

    /**
     * Count global visits across ALL hotels
     *
     * Visits are counted from the loyalty_transactions table.
     * Each transaction = 1 visit, regardless of which hotel it was at.
     *
     * @since 1.0.0
     *
     * @param int $customer_id The customer ID
     * @param int $period_days Rolling window in days (default 28)
     *
     * @return int Number of visits in the period
     */
    private static function count_global_visits($customer_id, $period_days = 28) {
        global $wpdb;

        // Count distinct days with transactions (not individual transactions)
        // This prevents multiple purchases on same day counting as multiple visits
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}loyalty_transactions
             WHERE customer_id = %d
               AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $customer_id,
            $period_days
        ));

        return intval($count);
    }

    /**
     * Get the period days configuration for a hotel
     *
     * Returns the rolling window used for visit counting.
     * Takes the minimum period_days from all tier configurations
     * for the hotel (typically all the same, default 28).
     *
     * @since 1.0.0
     *
     * @param int $hotel_id The hotel ID
     *
     * @return int Period days (default 28)
     */
    private static function get_period_days($hotel_id) {
        global $wpdb;

        $period = $wpdb->get_var($wpdb->prepare(
            "SELECT MIN(period_days)
             FROM {$wpdb->prefix}loyalty_hotel_tiers
             WHERE hotel_id = %d",
            $hotel_id
        ));

        return $period ? intval($period) : 28;
    }

    /**
     * Get the tier a customer qualifies for at a specific hotel
     *
     * Compares the visit count against the hotel's tier thresholds.
     * Returns the HIGHEST tier the customer qualifies for.
     *
     * The query orders by visits_required DESC and takes the first
     * row where visits_required <= the customer's visit count.
     *
     * @since 1.0.0
     *
     * @param int $hotel_id     The hotel ID
     * @param int $visit_count  Number of visits to check against
     *
     * @return array {
     *     @type int    $tier_id    The tier's database ID
     *     @type string $tier_name  The tier name (Member, Loyalty, Regular)
     *     @type int    $sort_order The tier's sort order (higher = better)
     * }
     */
    private static function get_tier_for_visits($hotel_id, $visit_count) {
        global $wpdb;

        // Get the highest tier the customer qualifies for
        // Orders by sort_order DESC to get best tier first, then filters by visits_required
        $tier = $wpdb->get_row($wpdb->prepare(
            "SELECT t.id as tier_id, t.name as tier_name, t.sort_order, ht.visits_required
             FROM {$wpdb->prefix}loyalty_hotel_tiers ht
             JOIN {$wpdb->prefix}loyalty_tiers t ON ht.tier_id = t.id
             WHERE ht.hotel_id = %d
               AND ht.visits_required <= %d
             ORDER BY t.sort_order DESC
             LIMIT 1",
            $hotel_id,
            $visit_count
        ));

        // If no tier found (shouldn't happen if data is set up correctly),
        // return a default "Member" tier with sort_order 1
        if (!$tier) {
            // Try to get the Member tier as fallback
            $member_tier = $wpdb->get_row(
                "SELECT id as tier_id, name as tier_name, sort_order
                 FROM {$wpdb->prefix}loyalty_tiers
                 WHERE slug = 'member'
                 LIMIT 1"
            );

            if ($member_tier) {
                return array(
                    'tier_id'    => $member_tier->tier_id,
                    'tier_name'  => $member_tier->tier_name,
                    'sort_order' => intval($member_tier->sort_order),
                );
            }

            // Absolute fallback
            return array(
                'tier_id'    => 0,
                'tier_name'  => 'Member',
                'sort_order' => 1,
            );
        }

        return array(
            'tier_id'    => intval($tier->tier_id),
            'tier_name'  => $tier->tier_name,
            'sort_order' => intval($tier->sort_order),
        );
    }

    /**
     * Get the discount rates for a tier at a specific hotel
     *
     * Each hotel has its own discount percentages for each tier.
     * This retrieves the wet (drinks) and dry (food) discount rates.
     *
     * @since 1.0.0
     *
     * @param int $hotel_id The hotel ID
     * @param int $tier_id  The tier ID
     *
     * @return array {
     *     @type float $wet_discount Drink discount percentage
     *     @type float $dry_discount Food discount percentage
     * }
     */
    private static function get_hotel_rates_for_tier($hotel_id, $tier_id) {
        global $wpdb;

        $rates = $wpdb->get_row($wpdb->prepare(
            "SELECT wet_discount, dry_discount
             FROM {$wpdb->prefix}loyalty_hotel_tiers
             WHERE hotel_id = %d AND tier_id = %d",
            $hotel_id,
            $tier_id
        ));

        // Default to 0% if rates not configured
        if (!$rates) {
            return array(
                'wet_discount' => 0,
                'dry_discount' => 0,
            );
        }

        return array(
            'wet_discount' => floatval($rates->wet_discount),
            'dry_discount' => floatval($rates->dry_discount),
        );
    }

    /**
     * Apply loyalty bonus to base discount rates
     *
     * Called when a loyalty_bonus promo is applied.
     * Modifies the discount percentages but keeps discount_type = "discount".
     *
     * @since 1.0.0
     *
     * @param array $tier_result     Result from calculate()
     * @param float $bonus_multiplier Multiplier for discount (e.g., 2.0 for double)
     *
     * @return array Modified tier result with boosted discounts
     */
    public static function apply_loyalty_bonus($tier_result, $bonus_multiplier) {
        // Apply multiplier to both wet and dry discounts
        $tier_result['wet_discount'] = $tier_result['wet_discount'] * $bonus_multiplier;
        $tier_result['dry_discount'] = $tier_result['dry_discount'] * $bonus_multiplier;

        // Cap at 100% maximum
        $tier_result['wet_discount'] = min($tier_result['wet_discount'], 100);
        $tier_result['dry_discount'] = min($tier_result['dry_discount'], 100);

        // discount_type stays as "discount" - uses same #2303/#3303 codes
        // The bonus just increases the percentage

        return $tier_result;
    }

    /**
     * Check if a customer is close to the next tier
     *
     * Useful for notifications like "1 more visit to reach Loyalty tier!"
     *
     * @since 1.0.0
     *
     * @param int $customer_id The customer ID
     * @param int $hotel_id    The hotel to check thresholds for
     *
     * @return array|null {
     *     @type string $next_tier    Name of the next tier
     *     @type int    $visits_to_go How many more visits needed
     * } or null if already at top tier
     */
    public static function get_next_tier_info($customer_id, $hotel_id) {
        global $wpdb;

        $period_days = self::get_period_days($hotel_id);
        $current_visits = self::count_global_visits($customer_id, $period_days);
        $current_tier = self::get_tier_for_visits($hotel_id, $current_visits);

        // Find the next tier (one sort_order higher)
        $next_tier = $wpdb->get_row($wpdb->prepare(
            "SELECT t.name as tier_name, ht.visits_required
             FROM {$wpdb->prefix}loyalty_hotel_tiers ht
             JOIN {$wpdb->prefix}loyalty_tiers t ON ht.tier_id = t.id
             WHERE ht.hotel_id = %d
               AND t.sort_order > %d
             ORDER BY t.sort_order ASC
             LIMIT 1",
            $hotel_id,
            $current_tier['sort_order']
        ));

        if (!$next_tier) {
            return null; // Already at top tier
        }

        return array(
            'next_tier'    => $next_tier->tier_name,
            'visits_to_go' => intval($next_tier->visits_required) - $current_visits,
        );
    }
}
