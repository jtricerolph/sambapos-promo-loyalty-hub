/**
 * Loyalty Hub Admin JavaScript
 *
 * Handles admin interface interactions.
 *
 * @package Loyalty_Hub
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        initCopyApiKey();
        initConfirmations();
        initFormValidation();
    });

    /**
     * Copy API key to clipboard
     *
     * Handles the "Copy" button clicks for API keys.
     */
    function initCopyApiKey() {
        $('.copy-api-key').on('click', function(e) {
            e.preventDefault();

            var key = $(this).data('key');
            var button = $(this);

            // Use modern clipboard API if available
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(key).then(function() {
                    showCopySuccess(button);
                }).catch(function(err) {
                    fallbackCopy(key, button);
                });
            } else {
                fallbackCopy(key, button);
            }
        });
    }

    /**
     * Fallback copy method for older browsers
     *
     * @param {string} text   Text to copy
     * @param {jQuery} button Button element
     */
    function fallbackCopy(text, button) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            showCopySuccess(button);
        } catch (err) {
            alert('Failed to copy. Please copy manually: ' + text);
        }

        document.body.removeChild(textarea);
    }

    /**
     * Show copy success feedback
     *
     * @param {jQuery} button Button element
     */
    function showCopySuccess(button) {
        var originalText = button.text();
        button.text('Copied!');
        button.addClass('button-primary');

        setTimeout(function() {
            button.text(originalText);
            button.removeClass('button-primary');
        }, 2000);
    }

    /**
     * Initialize confirmation dialogs
     *
     * Adds confirmation prompts for destructive actions.
     */
    function initConfirmations() {
        // Confirm status changes
        $('a[data-confirm]').on('click', function(e) {
            var message = $(this).data('confirm');
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });

        // Confirm form submissions for dangerous actions
        $('form[data-confirm-submit]').on('submit', function(e) {
            var message = $(this).data('confirm-submit');
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Initialize form validation
     *
     * Adds client-side validation for admin forms.
     */
    function initFormValidation() {
        // Promo code uppercase transformation
        $('#promo_code').on('input', function() {
            this.value = this.value.toUpperCase();
        });

        // Percentage validation
        $('input[type="number"][max="100"]').on('change', function() {
            var val = parseFloat(this.value);
            if (val > 100) {
                this.value = 100;
            }
            if (val < 0) {
                this.value = 0;
            }
        });

        // Date range validation
        $('input[name="valid_from"], input[name="valid_until"]').on('change', function() {
            var from = $('input[name="valid_from"]').val();
            var until = $('input[name="valid_until"]').val();

            if (from && until && from > until) {
                alert('End date must be after start date');
                $(this).val('');
            }
        });

        // Time range validation
        $('input[name="time_start"], input[name="time_end"]').on('change', function() {
            var start = $('input[name="time_start"]').val();
            var end = $('input[name="time_end"]').val();

            if (start && end && start > end) {
                alert('End time must be after start time');
                $(this).val('');
            }
        });
    }

    /**
     * Toggle promo type fields
     *
     * Shows/hides fields based on promo type selection.
     * This is also defined inline in promos.php for immediate availability.
     */
    window.togglePromoTypeFields = function() {
        var type = document.getElementById('promo_type');
        if (!type) return;

        var promoFields = document.querySelectorAll('.promo-code-fields');
        var bonusFields = document.querySelectorAll('.loyalty-bonus-fields');

        if (type.value === 'loyalty_bonus') {
            promoFields.forEach(function(el) { el.style.display = 'none'; });
            bonusFields.forEach(function(el) { el.style.display = ''; });
        } else {
            promoFields.forEach(function(el) { el.style.display = ''; });
            bonusFields.forEach(function(el) { el.style.display = 'none'; });
        }
    };

})(jQuery);
