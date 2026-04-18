<?php
/**
 * Cart class — intercepts add-to-cart, applies the additive price to the cart item,
 * and saves it to the order item.
 */
defined( 'ABSPATH' ) || exit;

class LE_AP_Cart {

    public function __construct() {
        // Add custom price data to cart item
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 3 );
        
        // Output custom price data in minicart & cart table (optional but good for debugging/transparency)
        add_filter( 'woocommerce_get_item_data', [ $this, 'get_item_data' ], 10, 2 );

        // Override the actual price during cart calculation
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'override_price' ], 20, 1 );

        // Save the custom price to the order line item so it persists
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_order_item_meta' ], 10, 4 );
    }

    /**
     * Grab the hidden input value (le_ap_price) when adding to cart.
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        if ( isset( $_POST['le_ap_price'] ) && $_POST['le_ap_price'] !== '' ) {
            $cart_item_data['le_ap_price'] = (float) sanitize_text_field( wp_unslash( $_POST['le_ap_price'] ) );
        }
        return $cart_item_data;
    }

    /**
     * Optional: Show a small note in the cart that an additive price is active.
     */
    public function get_item_data( $item_data, $cart_item_data ) {
        if ( isset( $cart_item_data['le_ap_price'] ) ) {
            // Uncomment if you want to display a label in the cart
            /*
            $item_data[] = [
                'name'  => 'Pricing',
                'value' => 'Additive',
            ];
            */
        }
        return $item_data;
    }

    /**
     * The core logic: force the cart item to use our custom price.
     */
    public function override_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        // Avoid infinite loop during mini-cart rendering in some setups
        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
            // return;
        }

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['le_ap_price'] ) ) {
                $cart_item['data']->set_price( $cart_item['le_ap_price'] );
            }
        }
    }

    /**
     * When order is placed, store the custom price so it isn't lost if the product price changes later.
     */
    public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['le_ap_price'] ) ) {
            // Store it hidden so it doesn't show in the frontend order view, 
            // but keeps the internal price calculation correct.
            $item->add_meta_data( '_le_ap_price', $values['le_ap_price'] );
        }
    }
}
