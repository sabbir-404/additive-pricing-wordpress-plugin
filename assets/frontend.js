jQuery(function ($) {
    if (typeof leAP === 'undefined') return;

    var $form = $('form.cart');
    if (!$form.length) return;

    // Helper to format currency
    function formatPrice(number) {
        // e.g. number = 1000.5
        var dec = leAP.decimals; // e.g. 2
        var decSep = leAP.decimalSep; // e.g. '.'
        var thoSep = leAP.thousandSep; // e.g. ','

        // Standard JS number formatting logic
        var fixed = number.toFixed(dec); // "1000.50"
        var parts = fixed.split('.');
        var intPart = parts[0];
        var decPart = parts.length > 1 ? decSep + parts[1] : '';

        // Add thousand separators
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, thoSep);

        var priceString = intPart + decPart;

        // Apply WooCommerce format string (e.g. "%1$s%2$s" -> "৳1,000.50")
        var format = leAP.priceFormat;
        var html = format.replace('%1$s', leAP.currencySymbol).replace('%2$s', priceString);
        
        return '<span class="woocommerce-Price-amount amount"><bdi>' + html + '</bdi></span>';
    }

    // Find the standard price element to update
    var $priceTarget = $('.woocommerce-variation-price');
    if (!$priceTarget.length) {
        // Try placing it right before the add to cart button wrap instead of top of form
        var $addToCartWrap = $('.woocommerce-variation-add-to-cart');
        if ($addToCartWrap.length) {
            $addToCartWrap.before('<div class="woocommerce-variation-price" style="font-size: 22px; font-weight: 700; margin-bottom: 15px;"></div>');
        } else {
            $form.prepend('<div class="woocommerce-variation-price" style="font-size: 22px; font-weight: 700; margin-bottom: 15px;"></div>');
        }
        $priceTarget = $('.woocommerce-variation-price');
    }

    function calculateAdditivePrice() {
        var baseReg  = leAP.baseRegular || 0;
        var baseSale   = (leAP.baseSale !== null) ? leAP.baseSale : baseReg;
        
        var totalReg = baseReg;
        var totalEffective = baseSale;
        
        var allSelected = true;

        // Loop through each attribute select dropdown
        $form.find('select[name^="attribute_"]').each(function () {
            var attrName = $(this).attr('name').replace('attribute_', '');
            var selectedVal = $(this).val();

            if (!selectedVal) {
                allSelected = false;
                return;
            }

            var attrPrices = leAP.prices[attrName];
            if (attrPrices && attrPrices[selectedVal] !== undefined) {
                var pData = attrPrices[selectedVal];
                var reg = pData.regular || 0;
                var sale = (pData.sale !== null) ? pData.sale : reg;
                
                totalReg += reg;
                totalEffective += sale;
            }
        });

        if (allSelected) {
            // Update displayed price
            var html = '';
            if (totalEffective < totalReg) {
                html = '<del>' + formatPrice(totalReg) + '</del> <ins>' + formatPrice(totalEffective) + '</ins>';
            } else {
                html = formatPrice(totalReg);
            }
            
            $priceTarget.html(html).show();
            // Store the final computed effective price into the hidden input
            $('#le_ap_price').val(totalEffective);
        } else {
            // Not all attributes selected -> clear
            $priceTarget.html('').hide();
            $('#le_ap_price').val('');
        }
    }

    // Bind events
    $form.on('change', 'select[name^="attribute_"]', calculateAdditivePrice);
    
    // Some swatches plugins trigger their own events or standard change events.
    // Also WooCommerce itself resets the form sometimes:
    $(document).on('reset_data', calculateAdditivePrice);

    // Initial check (in case values are pre-selected in the URL/session)
    setTimeout(calculateAdditivePrice, 500);
});
