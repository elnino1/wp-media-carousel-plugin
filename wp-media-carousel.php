<?php
/**
 * Plugin Name:  WP Media Carousel
 * Plugin URI:   https://inkiz.fr
 * Description:  Displays a media carousel from the WordPress Media Library. The selected image shows its caption, a comment section, and a panel of tag-matched WooCommerce products.
 * Version:      1.0.0
 * Author:       Inkiz
 * Author URI:   https://inkiz.fr
 * License:      GPL-2.0-or-later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  wp
 * -media-carousel
 * Domain Path:  /languages
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

defined('ABSPATH') || exit;

define('WP_MC_VERSION', '1.0.0');
define('WP_MC_FILE', __FILE__);
define('WP_MC_DIR', plugin_dir_path(__FILE__));
define('WP_MC_URL', plugin_dir_url(__FILE__));

// Activation / deactivation / uninstall must be in the top-level scope of the main file.
register_activation_hook(__FILE__, 'wp_mc_activate');
register_deactivation_hook(__FILE__, 'wp_mc_deactivate');

function wp_mc_activate(): void
{
// Nothing needed on activation.
}

function wp_mc_deactivate(): void
{
// Nothing needed on deactivation.
}

// Bootstrap the plugin after all plugins are loaded (so WooCommerce is available).
add_action('plugins_loaded', function () {
    require_once WP_MC_DIR . 'includes/class-plugin.php';
    WP_MC\Plugin::get_instance()->init();
});