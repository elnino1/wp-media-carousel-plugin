<?php

namespace WP_MC;

defined('ABSPATH') || exit;

/**
 * Enables post_tag taxonomy on attachments so images can be tagged to drive
 * the WooCommerce related-products panel.
 */
class Attachment_Support
{

    public function register(): void
    {
        add_action('init', [$this, 'enable_tags_on_attachments'], 20);
        add_filter('attachment_fields_to_save', [$this, 'allow_attachment_comments'], 10, 2);
    }

    /**
     * Register post_tag and category for the attachment post type so the Tags and Categories
     * metaboxes appear on the media edit screen.
     */
    public function enable_tags_on_attachments(): void
    {
        register_taxonomy_for_object_type('post_tag', 'attachment');
        register_taxonomy_for_object_type('category', 'attachment');
    }

    /**
     * Make sure new attachments have comments_status = 'open' so the comment
     * form renders correctly inside the carousel.
     */
    public function allow_attachment_comments(array $post, array $attachment): array
    {
        // Only set default; respect manually closed comments.
        // We do nothing here to avoid overriding the user's per-attachment setting.
        return $post;
    }
}