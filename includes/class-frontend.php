<?php
/**
 * Frontend class — enqueues scripts, injects price data, and overrides price HTML.
 */
defined( 'ABSPATH' ) || exit;

class LE_AP_Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts',                  [ $this, 'enqueue_scripts' ] );
        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'output_hidden_input' ] );
        // Price range on shop listing & product page
        add_filter( 'woocommerce_variable_price_html',     [ $this, 'override_price_html' ], 20, 2 );

        // Trick WooCommerce into making unpriced variations purchasable
        add_filter( 'woocommerce_available_variation',     [ $this, 'force_variation_available' ], 20, 3 );
        add_filter( 'woocommerce_variation_is_purchasable', [ $this, 'force_variation_purchasable' ], 20, 2 );
    }

    /* ------------------------------------------------------------------
     * Make unpriced variations purchasable
     * ------------------------------------------------------------------ */

    public function force_variation_purchasable( $purchasable, $variation ) {
        if ( self::is_enabled( (int) $variation->get_parent_id() ) ) {
            return true;
        }
        return $purchasable;
    }

    public function force_variation_available( $data, $product, $variation ) {
        if ( self::is_enabled( (int) $product->get_id() ) ) {
            $data['is_purchasable'] = true;
            // Let the JS handle the exact price string insertion
            $data['price_html'] = ''; 
        }
        return $data;
    }

    /* ------------------------------------------------------------------
     * Helpers (static so cart class can reuse)
     * ------------------------------------------------------------------ */

    public static function is_enabled( int $product_id ): bool {
        $data = get_post_meta( $product_id, LE_AP_META_KEY, true );
        return is_array( $data ) && ! empty( $data['enabled'] );
    }

    public static function get_config( int $product_id ): array {
        $data = get_post_meta( $product_id, LE_AP_META_KEY, true );
        return is_array( $data ) ? $data : [];
    }

    /* ------------------------------------------------------------------
     * Enqueue & localise
     * ------------------------------------------------------------------ */

    public function enqueue_scripts() {
        if ( ! is_product() ) return;

        global $post;
        if ( ! self::is_enabled( (int) $post->ID ) ) return;

        $config = self::get_config( (int) $post->ID );

        // Build a JS-friendly price map
        // { "pa_color": { "red": { "regular": 10, "sale": 8 }, ... }, ... }
        $js_prices = [];
        foreach ( $config['prices'] ?? [] as $taxonomy => $values ) {
            foreach ( (array) $values as $slug => $prices ) {
                $reg  = ( $prices['regular'] !== '' && $prices['regular'] !== null )
                        ? (float) $prices['regular'] : null;
                $sale = ( $prices['sale'] !== '' && $prices['sale'] !== null )
                        ? (float) $prices['sale'] : null;
                $js_prices[ $taxonomy ][ $slug ] = [
                    'regular' => $reg,
                    'sale'    => $sale,
                ];
            }
        }

        wp_enqueue_script(
            'le-ap-frontend',
            LE_AP_URL . 'assets/frontend.js',
            [ 'jquery', 'wc-add-to-cart-variation' ],
            LE_AP_VERSION,
            true
        );

        wp_localize_script( 'le-ap-frontend', 'leAP', [
            'prices'       => $js_prices,
            'baseRegular'  => ( $config['base_price'] !== '' ) ? (float) $config['base_price'] : null,
            'baseSale'     => ( $config['base_sale'] !== '' )  ? (float) $config['base_sale']  : null,
            'currencySymbol' => get_woocommerce_currency_symbol(),
            'decimals'     => wc_get_price_decimals(),
            'decimalSep'   => wc_get_price_decimal_separator(),
            'thousandSep'  => wc_get_price_thousand_separator(),
            'priceFormat'  => get_woocommerce_price_format(), // e.g. '%1$s%2$s'
        ] );
    }

    /* ------------------------------------------------------------------
     * Hidden input (carries computed price into add-to-cart POST)
     * ------------------------------------------------------------------ */

    public function output_hidden_input() {
        global $post;
        if ( ! self::is_enabled( (int) $post->ID ) ) return;
        echo '<input type="hidden" id="le_ap_price" name="le_ap_price" value="">';
    }

    /* ------------------------------------------------------------------
     * Price range override for shop listing & single product page
     *
     * Logic:
     *   min_effective = base_sale (if set, else base_regular)
     *                   + sum of min( sale ?? regular ) per attribute
     *   min_regular   = base_regular + sum of min( regular ) per attribute
     *   max_regular   = base_regular + sum of max( regular ) per attribute
     *
     * Display:
     *   If no base price:  min_effective – max_regular
     *   If base price set: (base + min_attr_sum) – (base + max_attr_sum)
     *   If sale applies:   <del>min_regular</del> <ins>min_effective</ins> – max_regular
     * ------------------------------------------------------------------ */

    public function override_price_html( string $price_html, \WC_Product $product ): string {
        if ( ! self::is_enabled( (int) $product->get_id() ) ) return $price_html;

        $config = self::get_config( (int) $product->get_id() );
        $prices = $config['prices'] ?? [];

        $base_regular = ( $config['base_price'] !== '' && $config['base_price'] !== null )
                        ? (float) $config['base_price'] : 0.0;
        $base_sale    = ( $config['base_sale'] !== '' && $config['base_sale'] !== null )
                        ? (float) $config['base_sale'] : null;

        if ( empty( $prices ) ) {
            // No attribute prices yet — show base only or dash
            if ( $base_regular > 0 ) {
                return $base_sale !== null && $base_sale < $base_regular
                    ? '<del>' . wc_price( $base_regular ) . '</del> <ins>' . wc_price( $base_sale ) . '</ins>'
                    : wc_price( $base_regular );
            }
            return $price_html;
        }

        // Collect per-attribute min/max values
        $attr_min_regular   = 0.0;
        $attr_min_effective = 0.0; // uses sale where available
        $attr_max_regular   = 0.0;

        foreach ( $prices as $values ) {
            $regulars   = [];
            $effectives = [];
            foreach ( (array) $values as $prices_per_val ) {
                $reg  = ( $prices_per_val['regular'] !== '' ) ? (float) $prices_per_val['regular'] : 0.0;
                $sale = ( $prices_per_val['sale'] !== '' && $prices_per_val['sale'] !== null )
                        ? (float) $prices_per_val['sale'] : null;
                $regulars[]   = $reg;
                $effectives[] = ( $sale !== null ) ? $sale : $reg;
            }
            if ( empty( $regulars ) ) continue;
            $attr_min_regular   += min( $regulars );
            $attr_min_effective += min( $effectives );
            $attr_max_regular   += max( $regulars );
        }

        // Total prices
        $total_min_regular   = $base_regular + $attr_min_regular;
        $total_min_effective = ( $base_sale ?? $base_regular ) + $attr_min_effective;
        $total_max           = $base_regular + $attr_max_regular;

        $has_sale = $total_min_effective < $total_min_regular;

        // Build HTML
        if ( $total_min_regular === $total_max ) {
            // Single price (only one combination possible or all same price)
            if ( $has_sale ) {
                return '<del>' . wc_price( $total_min_regular ) . '</del> <ins>' . wc_price( $total_min_effective ) . '</ins>';
            }
            return wc_price( $total_min_regular );
        }

        // Range
        if ( $has_sale ) {
            return '<del>' . wc_price( $total_min_regular ) . '</del>'
                 . ' <ins>' . wc_price( $total_min_effective ) . '</ins>'
                 . ' &ndash; ' . wc_price( $total_max );
        }
        return wc_price( $total_min_regular ) . ' &ndash; ' . wc_price( $total_max );
    }
}
