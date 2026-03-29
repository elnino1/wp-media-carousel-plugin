<?php

namespace WP_MC;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the "like" feature for media attachments.
 *
 * Stores like counts in post meta (_wp_mc_likes).
 * Uses AJAX for logged-in and anonymous visitors.
 */
class Likes {

    private const META_KEY = '_wp_mc_likes';

    public function register(): void {
        add_action( 'wp_ajax_wp_mc_like',        [ $this, 'handle_like' ] );
        add_action( 'wp_ajax_nopriv_wp_mc_like', [ $this, 'handle_like' ] );
    }

    /**
     * AJAX handler: toggle like for an attachment.
     */
    public function handle_like(): void {
        check_ajax_referer( 'wp_mc_nonce', 'nonce' );

        $attachment_id = absint( $_POST['attachment_id'] ?? 0 );
        if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
            wp_send_json_error( [ 'message' => 'Invalid attachment.' ], 400 );
        }

        $count = absint( get_post_meta( $attachment_id, self::META_KEY, true ) );
        $count++;
        update_post_meta( $attachment_id, self::META_KEY, $count );

        wp_send_json_success( [ 'count' => $count ] );
    }

    /**
     * Get the current like count for an attachment.
     */
    public static function get_count( int $attachment_id ): int {
        return absint( get_post_meta( $attachment_id, self::META_KEY, true ) );
    }
}
