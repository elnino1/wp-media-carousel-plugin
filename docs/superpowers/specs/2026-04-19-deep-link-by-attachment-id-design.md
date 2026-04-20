# Deep-Link to Carousel Slide by Attachment ID — Design

## Goal

Allow any external link (e.g. a home page carousel item) to open a page containing the `[wp_media_carousel]` shortcode and have the carousel automatically navigate to a specific slide, identified by WordPress attachment ID.

## URL Format

```
https://yoursite.com/gallery/?wp_mc_id=42
```

The query parameter `wp_mc_id` carries the attachment ID of the target image.

## Mechanism

Pure JavaScript, no PHP changes. A single addition to `assets/js/carousel.js`:

**On page load** (after each carousel instance initialises):

1. Read `wp_mc_id` from `window.location.search` via `URLSearchParams`
2. If the param is absent, do nothing — carousel starts at slide 0 as normal
3. If the param is present, scan the carousel's slides for one whose `data-id` attribute matches
4. If a matching slide is found:
   - Call the existing `goTo(matchedIndex, false)` to navigate to it
   - Call `wrapper.scrollIntoView({ behavior: 'smooth' })` so the carousel is visible without the user having to scroll
5. If the param is present but no matching slide is found, do nothing — carousel starts at slide 0

## Multiple Carousels on One Page

The param applies to the **first carousel** whose slides contain a matching `data-id`. If no carousel matches, all start at slide 0.

## Files Changed

- **Modify:** `assets/js/carousel.js` — add deep-link init logic after existing carousel setup

## Out of Scope

- No PHP changes
- No changes to the shortcode or rendering
- No URL rewriting or hash-based navigation
- No handling of the home page carousel (that is the caller's responsibility — just link to `?wp_mc_id={id}`)
