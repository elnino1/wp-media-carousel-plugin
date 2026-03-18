<?php
/**
 * Integration Test for Inkiz Media Carousel
 */

class Test_Inkiz_Media_Carousel extends WP_UnitTestCase {

    public function test_plugin_is_loaded() {
        $this->assertTrue( function_exists( 'inkiz_mc_activate' ) );
        $this->assertTrue( class_exists( 'Inkiz_MC\Plugin' ) );
    }

    public function test_shortcode_is_registered() {
        global $shortcode_tags;
        $this->assertArrayHasKey( 'inkiz_media_carousel', $shortcode_tags );
    }

    public function test_likes_ajax_hooks_are_registered() {
        $this->assertTrue( has_action( 'wp_ajax_inkiz_mc_like' ) !== false );
        $this->assertTrue( has_action( 'wp_ajax_nopriv_inkiz_mc_like' ) !== false );
    }

    public function test_shortcode_requires_ids() {
        $output = do_shortcode( '[inkiz_media_carousel]' );
        $this->assertStringContainsString( 'Veuillez spécifier un tag ou des IDs de médias', $output );
    }

    public function test_shortcode_requires_valid_ids() {
        $output = do_shortcode( '[inkiz_media_carousel ids="999999"]' );
        $this->assertStringContainsString( 'Aucun média trouvé', $output );
    }
}
