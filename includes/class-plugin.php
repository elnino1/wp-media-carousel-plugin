<?php

namespace WP_MC;

defined( 'ABSPATH' ) || exit;

/**
 * Central loader. Bootstraps all plugin components from a single entry point.
 */
final class Plugin {

    private static ?Plugin $instance = null;

    private function __construct() {}

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        $this->load_includes();
        $this->register_hooks();
    }

    private function load_includes(): void {
        require_once WP_MC_DIR . 'includes/class-attachment-support.php';
        require_once WP_MC_DIR . 'includes/class-shortcode.php';
        require_once WP_MC_DIR . 'includes/class-related-products.php';
        require_once WP_MC_DIR . 'includes/class-likes.php';
        if ( is_admin() ) {
            require_once WP_MC_DIR . 'includes/class-admin.php';
        }
    }

    private function register_hooks(): void {
        // Attachment tag support.
        $attachment_support = new Attachment_Support();
        $attachment_support->register();

        // Shortcode.
        $shortcode = new Shortcode();
        $shortcode->register();

        // Likes AJAX handler.
        $likes = new Likes();
        $likes->register();

        // Admin UI.
        if ( is_admin() ) {
            $admin = new Admin();
            $admin->register();
        }

        // Load text domain.
        add_action( 'init', [ $this, 'load_textdomain' ] );
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'wp-media-carousel',
            false,
            dirname( plugin_basename( WP_MC_FILE ) ) . '/languages'
        );
    }
}
