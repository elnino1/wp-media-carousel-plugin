<?php

namespace WP_MC;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the [wp_media_carousel] shortcode.
 *
 * Usage:
 *   [wp_media_carousel ids="12,34,56"]
 *   [wp_media_carousel ids="12,34,56" columns="4" autoplay="5"]
 *
 * Attributes:
 *   ids      – Comma-separated attachment IDs (from the Media Library).
 *   columns  – Number of thumbnail columns (default 4).
 *   autoplay – Seconds between automatic slide advances. 0 = disabled (default).
 */
class Shortcode {

    public function register(): void {
        add_shortcode( 'wp_media_carousel', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ] );
    }

    /** Enqueue assets only on pages that actually contain the shortcode. */
    public function maybe_enqueue_assets(): void {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) {
            return;
        }
        if ( has_shortcode( $post->post_content, 'wp_media_carousel' ) ) {
            $this->enqueue_assets();
        }
    }

    private function enqueue_assets(): void {
        wp_enqueue_style(
            'wp-mc-style',
            WP_MC_URL . 'assets/css/carousel.css',
            [],
            WP_MC_VERSION
        );
        wp_enqueue_script(
            'wp-mc-script',
            WP_MC_URL . 'assets/js/carousel.js',
            [],
            WP_MC_VERSION,
            true
        );

        // Pass settings to JS.
        wp_localize_script(
            'wp-mc-script',
            'wpMC',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'wp_mc_nonce' ),
            ]
        );
    }

    /** Main shortcode render callback. */
    public function render( array $atts ): string {
        $a = shortcode_atts(
            [
                'ids'        => '',
                'tag'        => '',
                'columns'    => 4,
                'autoplay'   => 0,
                'thumbnails' => 'none', // 'top', 'bottom', 'none'
                'filterable' => 'false',
            ],
            $atts,
            'wp_media_carousel'
        );

        $ids = array_filter(
            array_map( 'absint', explode( ',', $a['ids'] ) )
        );
        $tag = sanitize_text_field( $a['tag'] );

        if ( empty( $ids ) && empty( $tag ) ) {
            return '<p class="wp-mc-error">' . esc_html__( 'Veuillez spécifier un tag ou des IDs de médias.', 'wp-media-carousel' ) . '</p>';
        }

        // Fetch attachment posts.
        $query_args = [
            'post_type'   => 'attachment',
            'post_status' => 'inherit',
            'numberposts' => -1, // Load all matching, or let user limit? Usually carousels want all matching tags
        ];

        // If tag is present, prioritize it.
        if ( ! empty( $tag ) ) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'post_tag',
                    'field'    => 'slug',
                    'terms'    => $tag,
                ],
            ];
            // If they also passed IDs, we could constrain by them, but normally it's one or the other.
        } else {
            $query_args['post__in'] = $ids;
            $query_args['orderby']  = 'post__in';
            $query_args['numberposts'] = count( $ids );
        }

        $attachments = get_posts( $query_args );

        if ( empty( $attachments ) ) {
            return '<p class="wp-mc-error">' . esc_html__( 'Aucun média trouvé.', 'wp-media-carousel' ) . '</p>';
        }

        $columns    = absint( $a['columns'] );
        $autoplay   = absint( $a['autoplay'] );
        $thumbnails = in_array( $a['thumbnails'], [ 'top', 'bottom', 'none' ], true ) ? $a['thumbnails'] : 'none';
        $filterable = filter_var( $a['filterable'], FILTER_VALIDATE_BOOLEAN );

        // 1. Gather Categories (if filterable)
        $categories_map = [];
        if ( $filterable ) {
            foreach ( $attachments as $att ) {
                $cats = wp_get_post_terms( $att->ID, 'category' );
                if ( ! is_wp_error( $cats ) && ! empty( $cats ) ) {
                    foreach ( $cats as $c ) {
                        $categories_map[ $c->term_id ] = $c->name;
                    }
                }
            }
        }

        // 2. Generate Thumbnails HTML (if top or bottom)
        $thumbnails_html = '';
        if ( 'none' !== $thumbnails ) {
            ob_start();
            ?>
            <div class="wp-mc-thumbnails wp-mc-thumbnails--<?php echo esc_attr( $thumbnails ); ?>">
                <div class="wp-mc-thumbnails-track">
                    <?php foreach ( $attachments as $index => $att ) : 
                        $thumb = wp_get_attachment_image_src( $att->ID, 'thumbnail' );
                        $src   = $thumb ? esc_url( $thumb[0] ) : esc_url( wp_get_attachment_url( $att->ID ) );
                        $active = ( 0 === $index ) ? ' wp-mc-thumb--active' : '';

                        // Determine slide categories for filtering
                        $slide_cats = wp_get_post_terms( $att->ID, 'category', [ 'fields' => 'ids' ] );
                        $cats_str   = ( ! is_wp_error( $slide_cats ) && ! empty( $slide_cats ) ) ? implode( ',', $slide_cats ) : '';
                        ?>
                        <button class="wp-mc-thumb<?php echo esc_attr( $active ); ?>"
                                data-index="<?php echo esc_attr( $index ); ?>"
                                data-cats="<?php echo esc_attr( $cats_str ); ?>"
                                aria-label="<?php esc_attr_e( 'Aller à la diapositive', 'wp-media-carousel' ); ?> <?php echo ( $index + 1 ); ?>"
                                style="background-image: url('<?php echo $src; ?>');">
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
            $thumbnails_html = ob_get_clean();
        }

        // 3. Generate Filters HTML
        $filters_html = '';
        if ( $filterable && ! empty( $categories_map ) ) {
            ob_start();
            ?>
            <div class="wp-mc-filters">
                <button class="wp-mc-filter-btn wp-mc-filter--active" data-filter="all">
                    <?php esc_html_e( 'Tous', 'wp-media-carousel' ); ?>
                </button>
                <?php foreach ( $categories_map as $cat_id => $cat_name ) : ?>
                    <button class="wp-mc-filter-btn" data-filter="<?php echo esc_attr( $cat_id ); ?>">
                        <?php echo esc_html( $cat_name ); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php
            $filters_html = ob_get_clean();
        }

        ob_start();
        ?>
        <div class="wp-mc-wrapper"
             data-autoplay="<?php echo esc_attr( $autoplay ); ?>"
             data-current="0">

            <?php echo $filters_html; ?>

            <?php if ( 'top' === $thumbnails ) echo $thumbnails_html; ?>

            <!-- Main slide area -->
            <div class="wp-mc-stage">
                <?php foreach ( $attachments as $index => $att ) :
                    $img_url  = wp_get_attachment_url( $att->ID );
                    $full     = wp_get_attachment_image_src( $att->ID, 'large' );
                    $src      = $full ? esc_url( $full[0] ) : esc_url( $img_url );
                    $caption  = wp_kses_post( $att->post_excerpt );
                    $alt      = esc_attr( get_post_meta( $att->ID, '_wp_attachment_image_alt', true ) ?: $att->post_title );
                    $active   = ( 0 === $index ) ? ' wp-mc-slide--active' : '';
                    $tags     = wp_get_post_tags( $att->ID, [ 'fields' => 'slugs' ] );
                    $tags_str = esc_attr( implode( ',', $tags ) );
                    $likes    = Likes::get_count( $att->ID );
                    $cats_args  = [ 'fields' => 'ids' ];
                    $slide_cats = wp_get_post_terms( $att->ID, 'category', $cats_args );
                    $cats_str   = ( ! is_wp_error( $slide_cats ) && ! empty( $slide_cats ) ) ? implode( ',', $slide_cats ) : '';
                    ?>
                    <div class="wp-mc-slide<?php echo esc_attr( $active ); ?>"
                         data-id="<?php echo esc_attr( $att->ID ); ?>"
                         data-tags="<?php echo $tags_str; ?>"
                         data-cats="<?php echo esc_attr( $cats_str ); ?>"
                         data-index="<?php echo esc_attr( $index ); ?>">

                        <div class="wp-mc-hero">
                            <!-- Left: image -->
                            <div class="wp-mc-image-wrap">
                                <img src="<?php echo $src; ?>"
                                     alt="<?php echo $alt; ?>"
                                     class="wp-mc-main-image"
                                     loading="<?php echo ( 0 === $index ) ? 'eager' : 'lazy'; ?>">
                                <button class="wp-mc-arrow wp-mc-arrow--prev" aria-label="<?php esc_attr_e( 'Précédent', 'wp-media-carousel' ); ?>">&#8592;</button>
                                <button class="wp-mc-arrow wp-mc-arrow--next" aria-label="<?php esc_attr_e( 'Suivant', 'wp-media-carousel' ); ?>">&#8594;</button>
                            </div>

                            <!-- Right: info panel -->
                            <div class="wp-mc-info">
                                <h2 class="wp-mc-title"><?php echo esc_html( $att->post_title ); ?></h2>

                                <?php if ( $caption ) : ?>
                                <div class="wp-mc-description">
                                    <?php echo $caption; ?>
                                </div>
                                <?php endif; ?>

                                <div class="wp-mc-like-wrap">
                                    <button class="wp-mc-like-btn" data-id="<?php echo esc_attr( $att->ID ); ?>" aria-label="<?php esc_attr_e( 'Aimer', 'wp-media-carousel' ); ?>">
                                        <svg class="wp-mc-heart" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                        </svg>
                                        <span class="wp-mc-like-count"><?php echo esc_html( $likes ); ?></span>
                                    </button>
                                </div>
                            </div>
                        </div><!-- .wp-mc-hero -->

                    </div><!-- .wp-mc-slide -->
                <?php endforeach; ?>
            </div><!-- .wp-mc-stage -->

            <?php if ( 'bottom' === $thumbnails ) echo $thumbnails_html; ?>

            <!-- Related products (outside slides, one panel per attachment, swapped by JS) -->
            <?php if ( class_exists( 'WooCommerce' ) ) : ?>
            <div class="wp-mc-related-panels">
                <?php foreach ( $attachments as $index => $att ) :
                    $tags    = wp_get_post_tags( $att->ID, [ 'fields' => 'slugs' ] );
                    $related = ! empty( $tags ) ? Related_Products::get_html( $tags ) : '';
                    $active  = ( 0 === $index ) ? ' wp-mc-related-panel--active' : '';
                    ?>
                    <?php if ( $related ) : ?>
                    <div class="wp-mc-related-panel<?php echo esc_attr( $active ); ?>"
                         data-slide-index="<?php echo esc_attr( $index ); ?>">
                        <h3 class="wp-mc-related-title"><?php esc_html_e( 'Produits associés', 'wp-media-carousel' ); ?></h3>
                        <div class="wp-mc-related-grid">
                            <?php echo $related; ?>
                        </div>
                    </div>
                    <?php else : ?>
                    <div class="wp-mc-related-panel<?php echo esc_attr( $active ); ?>"
                         data-slide-index="<?php echo esc_attr( $index ); ?>"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Collapsible comments (outside slides, one section per attachment, swapped by JS) -->
            <div class="wp-mc-comments-panels">
                <?php foreach ( $attachments as $index => $att ) :
                    $comment_count = (int) get_comments_number( $att->ID );
                    $active        = ( 0 === $index ) ? ' wp-mc-comments-panel--active' : '';
                    ?>
                    <div class="wp-mc-comments-panel<?php echo esc_attr( $active ); ?>"
                         data-slide-index="<?php echo esc_attr( $index ); ?>">

                        <!-- Toggle button (always visible) -->
                        <button class="wp-mc-comments-toggle" aria-expanded="false">
                            <span class="wp-mc-comments-toggle-label">
                                <?php esc_html_e( 'Commentaires', 'wp-media-carousel' ); ?>
                            </span>
                            <span class="wp-mc-comments-count"><?php echo esc_html( $comment_count ); ?></span>
                            <svg class="wp-mc-chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </button>

                        <!-- Collapsible body -->
                        <div class="wp-mc-comments-body" aria-hidden="true">
                            <?php
                            // Temporarily switch the global $post so comment functions target this attachment.
                            $original_post = $GLOBALS['post'] ?? null;
                            $GLOBALS['post'] = $att;
                            setup_postdata( $att );

                            if ( get_comments_number( $att->ID ) > 0 || comments_open( $att->ID ) ) :

                                // List existing comments.
                                $comments_list = get_comments( [
                                    'post_id' => $att->ID,
                                    'status'  => 'approve',
                                ] );
                                if ( $comments_list ) {
                                    echo '<ol class="wp-mc-comment-list">';
                                    wp_list_comments( [
                                        'style'      => 'ol',
                                        'short_ping' => true,
                                        'avatar_size' => 40,
                                    ], $comments_list );
                                    echo '</ol>';
                                }

                                // Comment form.
                                $require_login = (bool) get_option( 'wp_mc_require_login_comment', false );
                                if ( ! $require_login || is_user_logged_in() ) {
                                    comment_form( [
                                        'id_form'       => 'wp-mc-comment-form-' . esc_attr( $att->ID ),
                                        'title_reply'   => esc_html__( 'Laisser un commentaire', 'wp-media-carousel' ),
                                        'label_submit'  => esc_html__( 'Publier', 'wp-media-carousel' ),
                                    ], $att->ID );
                                } elseif ( $require_login && ! is_user_logged_in() ) {
                                    echo '<p class="wp-mc-login-notice">';
                                    printf(
                                        wp_kses(
                                            /* translators: %s: login URL */
                                            __( '<a href="%s">Connectez-vous</a> pour laisser un commentaire.', 'wp-media-carousel' ),
                                            [ 'a' => [ 'href' => [] ] ]
                                        ),
                                        esc_url( wp_login_url( get_permalink() ) )
                                    );
                                    echo '</p>';
                                }
                            endif;

                            wp_reset_postdata();
                            $GLOBALS['post'] = $original_post;
                            ?>
                        </div><!-- .wp-mc-comments-body -->

                    </div><!-- .wp-mc-comments-panel -->
                <?php endforeach; ?>
            </div><!-- .wp-mc-comments-panels -->

        </div><!-- .wp-mc-wrapper -->
        <?php
        return ob_get_clean();
    }
}
