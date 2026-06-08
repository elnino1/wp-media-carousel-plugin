<?php
/**
 * Integration Test for WordPress Media Carousel
 */

class Test_Inkiz_Media_Carousel extends WP_UnitTestCase {

    public function test_plugin_is_loaded() {
        $this->assertTrue( function_exists( 'wp_mc_activate' ) );
        $this->assertTrue( class_exists( 'WP_MC\Plugin' ) );
    }

    public function test_shortcode_is_registered() {
        global $shortcode_tags;
        $this->assertArrayHasKey( 'wp_media_carousel', $shortcode_tags );
    }

    public function test_likes_ajax_hooks_are_registered() {
        $this->assertTrue( has_action( 'wp_ajax_wp_mc_like' ) !== false );
        $this->assertTrue( has_action( 'wp_ajax_nopriv_wp_mc_like' ) !== false );
    }

    public function test_shortcode_requires_ids() {
        $output = do_shortcode( '[wp_media_carousel]' );
        $this->assertStringContainsString( 'Veuillez spécifier un tag ou des IDs de médias', $output );
    }

    public function test_shortcode_requires_valid_ids() {
        $output = do_shortcode( '[wp_media_carousel ids="999999"]' );
        $this->assertStringContainsString( 'Aucun média trouvé', $output );
    }

    public function test_shortcode_renders_image_wrap_and_main_image() {
        $attachment_id = $this->factory->attachment->create_upload_object( DIR_TESTDATA . '/images/test-image.jpg' );
        $output = do_shortcode( '[wp_media_carousel ids="' . $attachment_id . '"]' );
        $this->assertStringContainsString( 'wp-mc-image-wrap', $output );
        $this->assertStringContainsString( 'wp-mc-main-image', $output );
        wp_delete_attachment( $attachment_id, true );
    }
}
