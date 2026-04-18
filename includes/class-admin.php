<?php
/**
 * Admin class — meta box for per-product additive pricing configuration.
 */
defined( 'ABSPATH' ) || exit;

class LE_AP_Admin {

    public function __construct() {
        add_action( 'add_meta_boxes',                          [ $this, 'add_meta_box' ] );
        add_action( 'woocommerce_process_product_meta',        [ $this, 'save_meta' ] );
        add_action( 'admin_enqueue_scripts',                   [ $this, 'enqueue_assets' ] );
    }

    /* ------------------------------------------------------------------
     * Meta box registration
     * ------------------------------------------------------------------ */

    public function add_meta_box() {
        add_meta_box(
            'le-additive-pricing',
            '💰 LE Additive Variation Pricing',
            [ $this, 'render_meta_box' ],
            'product',
            'normal',
            'default'
        );
    }

    public function enqueue_assets( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== 'product' ) return;

        wp_enqueue_style(
            'le-ap-admin',
            LE_AP_URL . 'assets/admin.css',
            [],
            LE_AP_VERSION
        );
        wp_enqueue_script(
            'le-ap-admin',
            LE_AP_URL . 'assets/admin.js',
            [ 'jquery' ],
            LE_AP_VERSION,
            true
        );
    }

    /* ------------------------------------------------------------------
     * Render
     * ------------------------------------------------------------------ */

    public function render_meta_box( $post ) {
        wp_nonce_field( 'le_ap_save', 'le_ap_nonce' );

        $product = wc_get_product( $post->ID );

        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            echo '<p style="color:#888;padding:8px 0;">⚠️ This meta box only applies to <strong>Variable Products</strong>. Change the product type to Variable to use additive pricing.</p>';
            return;
        }

        $saved       = get_post_meta( $post->ID, LE_AP_META_KEY, true );
        $data        = is_array( $saved ) ? $saved : [];
        $enabled     = ! empty( $data['enabled'] );
        $base_reg    = isset( $data['base_price'] ) ? $data['base_price'] : '';
        $base_sale   = isset( $data['base_sale'] )  ? $data['base_sale']  : '';
        $attr_prices = isset( $data['prices'] )     ? $data['prices']     : [];

        // Variation attributes: [ 'pa_color' => [ 'red', 'white' ], 'pa_size' => [...] ]
        $attributes = $product->get_variation_attributes();

        if ( empty( $attributes ) ) {
            echo '<p style="color:#888;padding:8px 0;">⚠️ No variation attributes found. Add attributes to this product first.</p>';
            return;
        }
        ?>
        <div class="le-ap-wrap">

            <!-- Enable toggle -->
            <label class="le-ap-toggle">
                <input type="checkbox" name="le_ap_enabled" value="1" <?php checked( $enabled ); ?>>
                <strong>Enable Additive Pricing for this product</strong>
                <span class="le-ap-badge">✓ Active</span>
            </label>

            <p class="le-ap-desc">
                When enabled, the product price is calculated as:
                <code>Base Price + Σ(selected attribute values)</code>.
                Sale prices are optional per value.
                WooCommerce's built-in variation prices are <em>ignored</em> for this product.
            </p>

            <div class="le-ap-main" <?php echo $enabled ? '' : 'style="display:none;"'; ?>>

                <!-- ── Base Price ─────────────────────────────────────── -->
                <div class="le-ap-section">
                    <h4>Base Price <span>(optional — fixed amount added to every combination)</span></h4>
                    <div class="le-ap-row le-ap-header-row">
                        <span></span>
                        <span>Regular Price</span>
                        <span>Sale Price <em>(leave blank for no sale)</em></span>
                    </div>
                    <div class="le-ap-row">
                        <label>Fixed Base</label>
                        <div class="le-ap-price-cell">
                            <?php echo get_woocommerce_currency_symbol(); ?>
                            <input type="number" step="0.01" min="0"
                                   name="le_ap_base_regular"
                                   value="<?php echo esc_attr( $base_reg ); ?>"
                                   placeholder="0.00">
                        </div>
                        <div class="le-ap-price-cell">
                            <?php echo get_woocommerce_currency_symbol(); ?>
                            <input type="number" step="0.01" min="0"
                                   name="le_ap_base_sale"
                                   value="<?php echo esc_attr( $base_sale ); ?>"
                                   placeholder="no sale">
                        </div>
                    </div>
                </div>

                <!-- ── Per-Attribute Value Prices ─────────────────────── -->
                <?php foreach ( $attributes as $taxonomy => $slugs ) :
                    $attr_label   = wc_attribute_label( $taxonomy );
                    $saved_values = $attr_prices[ $taxonomy ] ?? [];
                ?>
                <div class="le-ap-section">
                    <h4>
                        Attribute: <span class="le-ap-attr-name"><?php echo esc_html( $attr_label ); ?></span>
                        <span>(<?php echo esc_html( $taxonomy ); ?>)</span>
                    </h4>
                    <div class="le-ap-row le-ap-header-row">
                        <span>Value</span>
                        <span>Regular Price</span>
                        <span>Sale Price <em>(optional)</em></span>
                    </div>
                    <?php foreach ( $slugs as $slug ) :
                        $term     = get_term_by( 'slug', $slug, $taxonomy );
                        $nice     = $term ? $term->name : ucwords( str_replace( '-', ' ', $slug ) );
                        $reg_val  = $saved_values[ $slug ]['regular'] ?? '';
                        $sale_val = $saved_values[ $slug ]['sale']    ?? '';
                        $field    = 'le_ap_prices[' . esc_attr( $taxonomy ) . '][' . esc_attr( $slug ) . ']';
                    ?>
                    <div class="le-ap-row">
                        <label title="<?php echo esc_attr( $slug ); ?>">
                            <?php echo esc_html( $nice ); ?>
                        </label>
                        <div class="le-ap-price-cell">
                            <?php echo get_woocommerce_currency_symbol(); ?>
                            <input type="number" step="0.01" min="0"
                                   name="<?php echo $field; ?>[regular]"
                                   value="<?php echo esc_attr( $reg_val ); ?>"
                                   placeholder="0.00">
                        </div>
                        <div class="le-ap-price-cell">
                            <?php echo get_woocommerce_currency_symbol(); ?>
                            <input type="number" step="0.01" min="0"
                                   name="<?php echo $field; ?>[sale]"
                                   value="<?php echo esc_attr( $sale_val ); ?>"
                                   placeholder="no sale">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>

            </div><!-- /.le-ap-main -->
        </div><!-- /.le-ap-wrap -->
        <?php
    }

    /* ------------------------------------------------------------------
     * Save
     * ------------------------------------------------------------------ */

    public function save_meta( $post_id ) {
        if ( ! isset( $_POST['le_ap_nonce'] ) || ! wp_verify_nonce( $_POST['le_ap_nonce'], 'le_ap_save' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $data = [
            'enabled'    => ! empty( $_POST['le_ap_enabled'] ),
            'base_price' => ( isset( $_POST['le_ap_base_regular'] ) && $_POST['le_ap_base_regular'] !== '' )
                            ? wc_format_decimal( sanitize_text_field( $_POST['le_ap_base_regular'] ) )
                            : '',
            'base_sale'  => ( isset( $_POST['le_ap_base_sale'] ) && $_POST['le_ap_base_sale'] !== '' )
                            ? wc_format_decimal( sanitize_text_field( $_POST['le_ap_base_sale'] ) )
                            : '',
            'prices'     => [],
        ];

        if ( ! empty( $_POST['le_ap_prices'] ) && is_array( $_POST['le_ap_prices'] ) ) {
            foreach ( $_POST['le_ap_prices'] as $taxonomy => $values ) {
                $taxonomy = sanitize_key( $taxonomy );
                if ( ! is_array( $values ) ) continue;
                foreach ( $values as $slug => $prices ) {
                    $slug = sanitize_key( $slug );
                    $reg  = ( isset( $prices['regular'] ) && $prices['regular'] !== '' )
                            ? wc_format_decimal( sanitize_text_field( $prices['regular'] ) )
                            : '';
                    $sale = ( isset( $prices['sale'] ) && $prices['sale'] !== '' )
                            ? wc_format_decimal( sanitize_text_field( $prices['sale'] ) )
                            : '';
                    $data['prices'][ $taxonomy ][ $slug ] = [
                        'regular' => $reg,
                        'sale'    => $sale,
                    ];
                }
            }
        }

        update_post_meta( $post_id, LE_AP_META_KEY, $data );
    }
}
