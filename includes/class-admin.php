<?php

namespace WP_MC;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the plugin's Settings page under Settings → Media Carousel.
 */
class Admin {

    private const OPTION_GROUP = 'wp_mc_options';
    private const PAGE_SLUG    = 'wp_media_carousel';

    public function register(): void {
        add_action( 'admin_menu',  [ $this, 'add_settings_page' ] );
        add_action( 'admin_init',  [ $this, 'register_settings' ] );
        add_filter( 'attachment_fields_to_edit', [ $this, 'add_tag_field_hint' ], 10, 2 );
    }

    public function add_settings_page(): void {
        add_options_page(
            __( 'Media Carousel', 'wp-media-carousel' ),
            __( 'Media Carousel', 'wp-media-carousel' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_settings_page' ]
        );
    }

    public function register_settings(): void {
        // Section.
        add_settings_section(
            'wp_mc_main',
            __( 'Paramètres du carousel', 'wp-media-carousel' ),
            null,
            self::PAGE_SLUG
        );

        // Field: thumbnail_count.
        register_setting(
            self::OPTION_GROUP,
            'wp_mc_thumbnail_count',
            [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 12,
            ]
        );
        add_settings_field(
            'wp_mc_thumbnail_count',
            __( 'Nombre max de médias', 'wp-media-carousel' ),
            [ $this, 'field_thumbnail_count' ],
            self::PAGE_SLUG,
            'wp_mc_main'
        );

        // Field: require_login_comment.
        register_setting(
            self::OPTION_GROUP,
            'wp_mc_require_login_comment',
            [
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => false,
            ]
        );
        add_settings_field(
            'wp_mc_require_login_comment',
            __( 'Connexion requise pour commenter', 'wp-media-carousel' ),
            [ $this, 'field_require_login' ],
            self::PAGE_SLUG,
            'wp_mc_main'
        );

        // Field: tag_prefix.
        register_setting(
            self::OPTION_GROUP,
            'wp_mc_tag_prefix',
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            ]
        );
        add_settings_field(
            'wp_mc_tag_prefix',
            __( 'Préfixe de tag (optionnel)', 'wp-media-carousel' ),
            [ $this, 'field_tag_prefix' ],
            self::PAGE_SLUG,
            'wp_mc_main'
        );
    }

    public function field_thumbnail_count(): void {
        $val = absint( get_option( 'wp_mc_thumbnail_count', 12 ) );
        printf(
            '<input type="number" name="wp_mc_thumbnail_count" id="wp_mc_thumbnail_count" value="%d" min="1" max="100" class="small-text">
             <p class="description">%s</p>',
            esc_attr( $val ),
            esc_html__( 'Nombre maximum de médias affichés quand aucun attribut ids n\'est fourni au shortcode.', 'wp-media-carousel' )
        );
    }

    public function field_require_login(): void {
        $val = (bool) get_option( 'wp_mc_require_login_comment', false );
        printf(
            '<label><input type="checkbox" name="wp_mc_require_login_comment" id="wp_mc_require_login_comment" value="1" %s> %s</label>
             <p class="description">%s</p>',
            checked( $val, true, false ),
            esc_html__( 'Exiger que l\'utilisateur soit connecté pour commenter', 'wp-media-carousel' ),
            esc_html__( 'Si activé, un lien de connexion s\'affiche à la place du formulaire de commentaire pour les visiteurs non connectés.', 'wp-media-carousel' )
        );
    }

    public function field_tag_prefix(): void {
        $val = esc_attr( (string) get_option( 'wp_mc_tag_prefix', '' ) );
        printf(
            '<input type="text" name="wp_mc_tag_prefix" id="wp_mc_tag_prefix" value="%s" class="regular-text">
             <p class="description">%s</p>',
            $val,
            esc_html__( 'Si renseigné, seuls les tags commençant par ce préfixe seront utilisés pour trouver les produits WooCommerce associés (ex : wp-).', 'wp-media-carousel' )
        );
    }

    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'wp-media-carousel' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'WordPress Media Carousel – Paramètres', 'wp-media-carousel' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( self::PAGE_SLUG );
                submit_button( __( 'Enregistrer', 'wp-media-carousel' ) );
                ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'Utilisation', 'wp-media-carousel' ); ?></h2>
            <p><?php esc_html_e( 'Insérez le shortcode suivant dans n\'importe quelle page ou article :', 'wp-media-carousel' ); ?></p>
            <code>[wp_media_carousel ids="1,2,3"]</code>
            <p><?php esc_html_e( 'Remplacez 1, 2, 3 par les IDs de vos médias (visibles dans l\'URL lors de l\'édition d\'un média dans la bibliothèque de médias).', 'wp-media-carousel' ); ?></p>
            <table class="widefat" style="max-width:600px;margin-top:1em">
                <thead><tr>
                    <th><?php esc_html_e( 'Attribut', 'wp-media-carousel' ); ?></th>
                    <th><?php esc_html_e( 'Défaut', 'wp-media-carousel' ); ?></th>
                    <th><?php esc_html_e( 'Description', 'wp-media-carousel' ); ?></th>
                </tr></thead>
                <tbody>
                    <tr><td><code>ids</code></td><td>—</td><td><?php esc_html_e( 'IDs des médias séparés par des virgules (requis)', 'wp-media-carousel' ); ?></td></tr>
                    <tr><td><code>columns</code></td><td>4</td><td><?php esc_html_e( 'Nombre de colonnes de miniatures', 'wp-media-carousel' ); ?></td></tr>
                    <tr><td><code>autoplay</code></td><td>0</td><td><?php esc_html_e( 'Secondes entre chaque avance automatique (0 = désactivé)', 'wp-media-carousel' ); ?></td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Add a small hint in the Media Library attachment edit screen
     * to remind editors to tag images for related-product matching.
     */
    public function add_tag_field_hint( array $form_fields, \WP_Post $post ): array {
        $tags = wp_get_post_tags( $post->ID );
        $hint = empty( $tags )
            ? '<p style="color:#b32d2e">' . esc_html__( 'Aucun tag — ajoutez des tags pour afficher des produits WooCommerce associés dans le carousel.', 'wp-media-carousel' ) . '</p>'
            : '<p style="color:#00a32a">' . sprintf(
                esc_html( _n( '%d tag associé.', '%d tags associés.', count( $tags ), 'wp-media-carousel' ) ),
                count( $tags )
            ) . '</p>';

        $form_fields['wp_mc_tag_hint'] = [
            'label' => __( 'Carousel', 'wp-media-carousel' ),
            'input' => 'html',
            'html'  => $hint,
        ];

        return $form_fields;
    }
}
