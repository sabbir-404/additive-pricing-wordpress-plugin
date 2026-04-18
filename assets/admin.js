jQuery(document).ready(function($) {
    var $toggle = $('input[name="le_ap_enabled"]');
    var $main = $('.le-ap-main');

    $toggle.on('change', function() {
        if ($(this).is(':checked')) {
            // User checked the box — auto-save the product to reload the page
            // and fetch the most up-to-date attributes and variations.
            $(this).parent().append(' <span style="color:#2271b1;font-size:12px;margin-left:10px;">↻ Saving & Reloading to fetch latest attributes...</span>');
            $('#publish').trigger('click');
        } else {
            $main.slideUp(200);
        }
    });
});
