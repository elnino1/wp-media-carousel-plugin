# WordPress Media Carousel

A WordPress plugin to display a media carousel linked to WooCommerce products. This plugin allows you to showcase media attachments in a responsive carousel, complete with likes, collapsible comments, associated WooCommerce products, thumbnail navigation, and category filtering.

## Features

- **Media Loading**: Load items dynamically using WordPress tags or specific attachment IDs.
- **Related Products**: Automatically fetches and displays WooCommerce products that share the same tags as the media item.
- **Engagement**: Built-in "Like" button and collapsible native WordPress comments for each media slide.
- **Thumbnails**: Optional thumbnail strip (top or bottom) for quick navigation between slides.
- **Category Filters**: Give your users the ability to filter slides instantly by their assigned WordPress Categories.

## Usage

You can display the carousel anywhere on your site using the `[wp_media_carousel]` shortcode.

### Shortcode Options

The shortcode accepts several attributes to customize what is displayed and how it behaves:

| Attribute | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `tag` | String | (empty) | The slug of the standard WordPress tag you want to load. Example: `tag="artwork"` will load all attachments tagged with "artwork". |
| `ids` | String | (empty) | A comma-separated list of specific media attachment IDs to display. Useful if you want manual control instead of `tag`. Example: `ids="12,34,56"`. |
| `thumbnails` | String | `none` | Position for the media thumbnails track. Options: `top`, `bottom`, or `none`. Example: `thumbnails="bottom"`. |
| `filterable` | Boolean | `false` | Set to `true` to enable category filtering. It will collect all categories attached to the displayed media and render them as filter buttons. Example: `filterable="true"`. |
| `columns` | Integer | `4` | Number of columns (slides) to show simultaneously on desktop screens. Example: `columns="3"`. |
| `autoplay` | Integer | `0` | Autoplay speed in milliseconds. Set to `0` to disable autoplay. Example: `autoplay="3000"` (3 seconds). |

### Examples

**1. Standard use with Tag and bottom Thumbnails:**
```shortcode
[wp_media_carousel tag="artwork" thumbnails="bottom" columns="1"]
```

**2. Filterable gallery of artwork:**
```shortcode
[wp_media_carousel tag="artwork" filterable="true" thumbnails="bottom" columns="3"]
```

**3. Specific media IDs with Autoplay:**
```shortcode
[wp_media_carousel ids="120,432,65" autoplay="5000" thumbnails="none"]
```

## Setup & Administration

1. **Tagging Media**: In your WordPress Media Library, click on any image/attachment. You will find a "Tags" and "Categories" box. Use these to organize your media files.
2. **Linking Products**: To link a WooCommerce product to a media slide, ensure the WooCommerce product and the media attachment share the exact same tag (e.g., tag both with `art-123`). The plugin will automatically query related products by matching the tag.
