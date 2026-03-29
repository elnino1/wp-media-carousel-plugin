<?php
/**
 * Uninstall routine for WordPress Media Carousel.
 * Removes all plugin options from wp_options.
 * Does NOT remove attachment posts or their tags (data safety).
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$option_keys = [
    'wp_mc_thumbnail_count',
    'wp_mc_require_login_comment',
    'wp_mc_tag_prefix',
];

foreach ( $option_keys as $key ) {
    delete_option( $key );
}
