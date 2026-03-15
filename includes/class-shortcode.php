<?php

namespace Inkiz_MC;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the [inkiz_media_carousel] shortcode.
 *
 * Usage:
 *   [inkiz_media_carousel ids="12,34,56"]
 *   [inkiz_media_carousel ids="12,34,56" columns="4" autoplay="5"]
 *
 * Attributes:
 *   ids      – Comma-separated attachment IDs (from the Media Library).
 *   columns  – Number of thumbnail columns (default 4).
 *   autoplay – Seconds between automatic slide advances. 0 = disabled (default).
 */
class Shortcode {

    public function register(): void {
        add_shortcode( 'inkiz_media_carousel', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ] );
    }

    /** Enqueue assets only on pages that actually contain the shortcode. */
    public function maybe_enqueue_assets(): void {
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) ) {
            return;
        }
        if ( has_shortcode( $post->post_content, 'inkiz_media_carousel' ) ) {
            $this->enqueue_assets();
        }
    }

    private function enqueue_assets(): void {
        wp_enqueue_style(
            'inkiz-mc-style',
            INKIZ_MC_URL . 'assets/css/carousel.css',
            [],
            INKIZ_MC_VERSION
        );
        wp_enqueue_script(
            'inkiz-mc-script',
            INKIZ_MC_URL . 'assets/js/carousel.js',
            [],
            INKIZ_MC_VERSION,
            true
        );

        // Pass settings to JS.
        wp_localize_script(
            'inkiz-mc-script',
            'inkizMC',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'inkiz_mc_nonce' ),
            ]
        );
    }

    /** Main shortcode render callback. */
    public function render( array $atts ): string {
        $a = shortcode_atts(
            [
                'ids'      => '',
                'columns'  => 4,
                'autoplay' => 0,
            ],
            $atts,
            'inkiz_media_carousel'
        );

        $ids = array_filter(
            array_map( 'absint', explode( ',', $a['ids'] ) )
        );

        if ( empty( $ids ) ) {
            return '<p class="inkiz-mc-error">' . esc_html__( 'Veuillez spécifier des IDs de médias : [inkiz_media_carousel ids="1,2,3"]', 'inkiz-media-carousel' ) . '</p>';
        }

        // Fetch attachment posts.
        $attachments = get_posts( [
            'post_type'   => 'attachment',
            'post__in'    => $ids,
            'orderby'     => 'post__in',
            'numberposts' => count( $ids ),
            'post_status' => 'inherit',
        ] );

        if ( empty( $attachments ) ) {
            return '<p class="inkiz-mc-error">' . esc_html__( 'Aucun média trouvé pour les IDs fournis.', 'inkiz-media-carousel' ) . '</p>';
        }

        $columns  = absint( $a['columns'] );
        $autoplay = absint( $a['autoplay'] );

        ob_start();
        ?>
        <div class="inkiz-mc-wrapper"
             data-autoplay="<?php echo esc_attr( $autoplay ); ?>"
             data-current="0">

            <!-- Main slide area -->
            <div class="inkiz-mc-stage">
                <?php foreach ( $attachments as $index => $att ) :
                    $img_url  = wp_get_attachment_url( $att->ID );
                    $full     = wp_get_attachment_image_src( $att->ID, 'large' );
                    $src      = $full ? esc_url( $full[0] ) : esc_url( $img_url );
                    $caption  = wp_kses_post( $att->post_excerpt );
                    $alt      = esc_attr( get_post_meta( $att->ID, '_wp_attachment_image_alt', true ) ?: $att->post_title );
                    $active   = ( 0 === $index ) ? ' inkiz-mc-slide--active' : '';
                    $tags     = wp_get_post_tags( $att->ID, [ 'fields' => 'slugs' ] );
                    $tags_str = esc_attr( implode( ',', $tags ) );
                    $likes    = Likes::get_count( $att->ID );
                    ?>
                    <div class="inkiz-mc-slide<?php echo esc_attr( $active ); ?>"
                         data-id="<?php echo esc_attr( $att->ID ); ?>"
                         data-tags="<?php echo $tags_str; ?>"
                         data-index="<?php echo esc_attr( $index ); ?>">

                        <div class="inkiz-mc-hero">
                            <!-- Left: image -->
                            <div class="inkiz-mc-image-wrap">
                                <img src="<?php echo $src; ?>"
                                     alt="<?php echo $alt; ?>"
                                     class="inkiz-mc-main-image"
                                     loading="<?php echo ( 0 === $index ) ? 'eager' : 'lazy'; ?>">
                                <button class="inkiz-mc-arrow inkiz-mc-arrow--prev" aria-label="<?php esc_attr_e( 'Précédent', 'inkiz-media-carousel' ); ?>">&#8592;</button>
                                <button class="inkiz-mc-arrow inkiz-mc-arrow--next" aria-label="<?php esc_attr_e( 'Suivant', 'inkiz-media-carousel' ); ?>">&#8594;</button>
                            </div>

                            <!-- Right: info panel -->
                            <div class="inkiz-mc-info">
                                <h2 class="inkiz-mc-title"><?php echo esc_html( $att->post_title ); ?></h2>

                                <?php if ( $caption ) : ?>
                                <div class="inkiz-mc-description">
                                    <?php echo $caption; ?>
                                </div>
                                <?php endif; ?>

                                <div class="inkiz-mc-like-wrap">
                                    <button class="inkiz-mc-like-btn" data-id="<?php echo esc_attr( $att->ID ); ?>" aria-label="<?php esc_attr_e( 'Aimer', 'inkiz-media-carousel' ); ?>">
                                        <svg class="inkiz-mc-heart" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                                        </svg>
                                        <span class="inkiz-mc-like-count"><?php echo esc_html( $likes ); ?></span>
                                    </button>
                                </div>
                            </div>
                        </div><!-- .inkiz-mc-hero -->

                    </div><!-- .inkiz-mc-slide -->
                <?php endforeach; ?>
            </div><!-- .inkiz-mc-stage -->

            <!-- Related products (outside slides, one panel per attachment, swapped by JS) -->
            <?php if ( class_exists( 'WooCommerce' ) ) : ?>
            <div class="inkiz-mc-related-panels">
                <?php foreach ( $attachments as $index => $att ) :
                    $tags    = wp_get_post_tags( $att->ID, [ 'fields' => 'slugs' ] );
                    $related = ! empty( $tags ) ? Related_Products::get_html( $tags ) : '';
                    $active  = ( 0 === $index ) ? ' inkiz-mc-related-panel--active' : '';
                    ?>
                    <?php if ( $related ) : ?>
                    <div class="inkiz-mc-related-panel<?php echo esc_attr( $active ); ?>"
                         data-slide-index="<?php echo esc_attr( $index ); ?>">
                        <h3 class="inkiz-mc-related-title"><?php esc_html_e( 'Produits associés', 'inkiz-media-carousel' ); ?></h3>
                        <div class="inkiz-mc-related-grid">
                            <?php echo $related; ?>
                        </div>
                    </div>
                    <?php else : ?>
                    <div class="inkiz-mc-related-panel<?php echo esc_attr( $active ); ?>"
                         data-slide-index="<?php echo esc_attr( $index ); ?>"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Collapsible comments (outside slides, one section per attachment, swapped by JS) -->
            <div class="inkiz-mc-comments-panels">
                <?php foreach ( $attachments as $index => $att ) :
                    $comment_count = (int) get_comments_number( $att->ID );
                    $active        = ( 0 === $index ) ? ' inkiz-mc-comments-panel--active' : '';
                    ?>
                    <div class="inkiz-mc-comments-panel<?php echo esc_attr( $active ); ?>"
                         data-slide-index="<?php echo esc_attr( $index ); ?>">

                        <!-- Toggle button (always visible) -->
                        <button class="inkiz-mc-comments-toggle" aria-expanded="false">
                            <span class="inkiz-mc-comments-toggle-label">
                                <?php esc_html_e( 'Commentaires', 'inkiz-media-carousel' ); ?>
                            </span>
                            <span class="inkiz-mc-comments-count"><?php echo esc_html( $comment_count ); ?></span>
                            <svg class="inkiz-mc-chevron" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </button>

                        <!-- Collapsible body -->
                        <div class="inkiz-mc-comments-body" aria-hidden="true">
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
                                    echo '<ol class="inkiz-mc-comment-list">';
                                    wp_list_comments( [
                                        'style'      => 'ol',
                                        'short_ping' => true,
                                        'avatar_size' => 40,
                                    ], $comments_list );
                                    echo '</ol>';
                                }

                                // Comment form.
                                $require_login = (bool) get_option( 'inkiz_mc_require_login_comment', false );
                                if ( ! $require_login || is_user_logged_in() ) {
                                    comment_form( [
                                        'id_form'       => 'inkiz-mc-comment-form-' . esc_attr( $att->ID ),
                                        'title_reply'   => esc_html__( 'Laisser un commentaire', 'inkiz-media-carousel' ),
                                        'label_submit'  => esc_html__( 'Publier', 'inkiz-media-carousel' ),
                                    ], $att->ID );
                                } elseif ( $require_login && ! is_user_logged_in() ) {
                                    echo '<p class="inkiz-mc-login-notice">';
                                    printf(
                                        wp_kses(
                                            /* translators: %s: login URL */
                                            __( '<a href="%s">Connectez-vous</a> pour laisser un commentaire.', 'inkiz-media-carousel' ),
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
                        </div><!-- .inkiz-mc-comments-body -->

                    </div><!-- .inkiz-mc-comments-panel -->
                <?php endforeach; ?>
            </div><!-- .inkiz-mc-comments-panels -->

        </div><!-- .inkiz-mc-wrapper -->
        <?php
        return ob_get_clean();
    }
}
