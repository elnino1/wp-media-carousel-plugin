<?php

namespace WP_MC;

defined( 'ABSPATH' ) || exit;

/**
 * Queries WooCommerce products by shared post_tag slugs and renders
 * an HTML grid of thumbnails linking to the product pages.
 */
class Related_Products {

    /**
     * Return the HTML for the related products grid or empty string if none found.
     *
     * @param string[] $tag_slugs  Array of tag slugs from the current attachment.
     * @param int      $max        Maximum number of products to display.
     */
    public static function get_html( array $tag_slugs, int $max = 8 ): string {
        if ( empty( $tag_slugs ) ) {
            return '';
        }

        // Optional prefix filter set in plugin settings.
        $prefix = sanitize_text_field( (string) get_option( 'wp_mc_tag_prefix', '' ) );
        if ( $prefix ) {
            $tag_slugs = array_filter( $tag_slugs, function ( string $slug ) use ( $prefix ) {
                return str_starts_with( $slug, $prefix );
            } );
        }

        if ( empty( $tag_slugs ) ) {
            return '';
        }

        $query_args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $max,
            'tax_query'      => [
                [
                    // WooCommerce uses 'product_tag', not 'post_tag'
                    'taxonomy' => 'product_tag',
                    'field'    => 'slug',
                    'terms'    => array_values( $tag_slugs ),
                    'operator' => 'IN',
                ],
            ],
        ];

        $query = new \WP_Query( $query_args );

        if ( ! $query->have_posts() ) {
            return '';
        }

        ob_start();
        while ( $query->have_posts() ) :
            $query->the_post();
            $product_id  = get_the_ID();
            $product_url = get_permalink();
            $thumb       = get_the_post_thumbnail( $product_id, 'woocommerce_thumbnail' );
            $title       = get_the_title();

            // Fallback if no WooCommerce thumbnail size.
            if ( ! $thumb ) {
                $thumb = get_the_post_thumbnail( $product_id, 'thumbnail' );
            }
            ?>
            <a href="<?php echo esc_url( $product_url ); ?>"
               class="wp-mc-product-card"
               title="<?php echo esc_attr( $title ); ?>">
                <div class="wp-mc-product-thumb">
                    <?php if ( $thumb ) : ?>
                        <?php echo $thumb; ?>
                    <?php else : ?>
                        <div class="wp-mc-product-no-thumb"></div>
                    <?php endif; ?>
                </div>
                <span class="wp-mc-product-title"><?php echo esc_html( $title ); ?></span>
            </a>
            <?php
        endwhile;
        wp_reset_postdata();

        return ob_get_clean();
    }
}
