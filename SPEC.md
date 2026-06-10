# Spec: Main Image Fit + Lightbox with Zoom

## Objective

The carousel's main image is currently cropped for tall/portrait images because it uses
`object-fit: cover` inside a fixed `4/3` aspect-ratio container. The full image is never
visible unless it happens to be landscape.

**Target users:** Site visitors browsing the carousel.

**What we are building:**
1. The main hero image is always fully visible — no cropping.
2. Clicking the image opens a full-screen lightbox showing the image at full/natural size.
3. The lightbox supports zoom in, zoom out, scroll-wheel zoom, pinch-to-zoom (touch),
   and dismissal via the Escape key or clicking outside the image.

**Success criteria:**
- A tall portrait image (taller than the container) is displayed in full, letterboxed
  against the container background — no pixels are cut off.
- A cursor `zoom-in` pointer is visible on the main image to signal clickability.
- Clicking the main image opens a full-screen dark overlay with the full-size image centered.
- Zoom in/out buttons (+/−) are visible in the lightbox and change the image scale.
- Scroll wheel on desktop changes zoom level.
- Pinch gesture on touch devices changes zoom level.
- Pressing Escape or clicking the dark backdrop closes the lightbox.
- No external JS libraries are added. Implementation is vanilla JS/CSS only.
- Existing behaviour (slide navigation, likes, deep-link, autoplay, comments) is unaffected.

## Tech Stack

- PHP 7.4+ (WordPress plugin, no composer changes needed)
- Vanilla JS (ES5-compatible, IIFE pattern matching existing `carousel.js`)
- Plain CSS (BEM-ish class names prefixed `wp-mc-`)
- No build step, no npm, no Webpack

## Commands

```
Test (PHPUnit):  ./vendor/bin/phpunit
Lint (PHP):      ./vendor/bin/phpcs --standard=WordPress includes/
Dev:             Open the installed WordPress site in a browser
Package:         bash package.sh
```

## Project Structure

```
assets/
  css/carousel.css   ← all frontend styles (add lightbox rules here)
  js/carousel.js     ← all frontend JS (add lightbox logic here)
includes/
  class-shortcode.php ← PHP renders the HTML (no changes needed for this feature)
tests/
  test-plugin.php    ← PHPUnit tests (add smoke test for image-wrap markup)
```

## Code Style

Follow the existing patterns exactly:

```css
/* BEM-ish, wp-mc- prefix, custom properties for shared values */
.wp-mc-lightbox {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0, 0, 0, 0.88);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease;
}
.wp-mc-lightbox--open {
    opacity: 1;
    pointer-events: all;
}
```

```js
// Vanilla IIFE, camelCase functions, no arrow functions at top level
function openLightbox(src, alt) { ... }
wrapper.querySelector('.wp-mc-image-wrap')
    .addEventListener('click', function () { openLightbox(img.src, img.alt); });
```

## Testing Strategy

- **PHPUnit** — add one test asserting `.wp-mc-image-wrap` and `.wp-mc-main-image` are
  present in the rendered shortcode HTML.
- **Manual browser test** — golden path: open carousel page, verify portrait image is
  fully visible; click image, verify lightbox opens; use +/− buttons and scroll wheel;
  press Escape, verify lightbox closes.
- No JS unit test framework is in scope (no Jest/Mocha in this project).

## Boundaries

- **Always:** Keep all code inside the existing `carousel.css` / `carousel.js` files.
  Prefix every new CSS class with `wp-mc-`. Keep the `z-index` of the lightbox ≥ 9999
  to sit above WordPress admin bar and theme overlays.
- **Ask first:** Adding a new PHP file or new enqueue hook; changing shortcode attributes
  or default behaviour; adding a dependency (even a tiny one).
- **Never:** Use `!important` to override theme styles unless there is no alternative.
  Introduce a JS framework or bundler. Break existing keyboard/touch navigation.

## Implementation Plan

### Step 1 — Fix image fit in the carousel (CSS only)

**Files:** `assets/css/carousel.css`

Change `.wp-mc-image-wrap`:
- Remove the hard-coded `aspect-ratio: 4/3`.
- Add `min-height: 300px` as a floor so the container never collapses.
- Keep `overflow: hidden` and `background: #f0f0f0`.

Change `.wp-mc-main-image`:
- `object-fit: contain` (was `cover`) so the full image is visible.
- `max-height: 60vh` to prevent an extremely tall image from pushing page content off-screen.
- Add `cursor: zoom-in` to signal lightbox affordance.
- Remove the hover `scale(1.02)` transform (it looks odd with `contain` and a small image).

### Step 2 — Lightbox markup (JS — injected once into `<body>`)

**Files:** `assets/js/carousel.js`

Inject a single `<div class="wp-mc-lightbox">` element into `document.body` on first use
(lazy creation). Structure:

```html
<div class="wp-mc-lightbox" role="dialog" aria-modal="true" aria-label="Image agrandie">
  <button class="wp-mc-lightbox-close" aria-label="Fermer">✕</button>
  <div class="wp-mc-lightbox-controls">
    <button class="wp-mc-lightbox-zoom-out" aria-label="Zoom arrière">−</button>
    <button class="wp-mc-lightbox-zoom-in"  aria-label="Zoom avant">+</button>
  </div>
  <div class="wp-mc-lightbox-canvas">
    <img class="wp-mc-lightbox-img" src="" alt="" draggable="false">
  </div>
</div>
```

### Step 3 — Lightbox behaviour (JS)

**Files:** `assets/js/carousel.js`

- `openLightbox(src, alt)` — set image src/alt, add `wp-mc-lightbox--open`, reset zoom to 1.
- `closeLightbox()` — remove `wp-mc-lightbox--open`, clear src after transition ends.
- Zoom state: a `scale` variable (float, clamped 0.5–5.0, step 0.25).
  Apply via `img.style.transform = 'scale(' + scale + ')'`.
- Scroll wheel: `wheel` event on the canvas, `deltaY < 0` → zoom in, else zoom out.
- Touch pinch: track two-finger `touchstart` / `touchmove` distance, adjust scale proportionally.
- Keyboard: `keydown` on `document` for Escape → close.
- Backdrop click: click on `.wp-mc-lightbox-canvas` outside the image → close.
  (Use `event.target === canvas` check.)
- Every main image click inside any `.wp-mc-image-wrap` triggers `openLightbox`.

### Step 4 — Lightbox styles (CSS)

**Files:** `assets/css/carousel.css`

New classes: `.wp-mc-lightbox`, `.wp-mc-lightbox--open`, `.wp-mc-lightbox-close`,
`.wp-mc-lightbox-controls`, `.wp-mc-lightbox-zoom-in`, `.wp-mc-lightbox-zoom-out`,
`.wp-mc-lightbox-canvas`, `.wp-mc-lightbox-img`.

Key rules:
- Lightbox: `position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.88)`.
- Image: `max-width: 90vw; max-height: 85vh; object-fit: contain; transition: transform 0.15s ease`.
- Close button: top-right corner, white, large tap target.
- Controls: bottom-center, two pill buttons.

### Step 5 — PHPUnit smoke test

**Files:** `tests/test-plugin.php`

Add a test that renders the shortcode with a known attachment ID and asserts:
- `wp-mc-image-wrap` is present in the output.
- `wp-mc-main-image` is present in the output.

## Decisions

1. **Drag-to-pan in lightbox:** Yes — when zoom scale > 1, the user can click-and-drag
   the image to pan. Pan is clamped so the image cannot be dragged fully out of view.
2. **Arrow keys / navigation inside lightbox:** No arrows shown in the lightbox.
   Pressing ArrowLeft/Right or clicking the carousel arrows while the lightbox is open
   first closes the lightbox, then advances the slide as normal.
3. **Thumbnail clicks:** No change — thumbnails select the slide as before; they do not
   open the lightbox.

### Step 3 additions (drag-to-pan)

Track `mousedown` / `mousemove` / `mouseup` on `.wp-mc-lightbox-img`:
- On `mousedown`: record `startX`, `startY`, current `translateX`, `translateY`.
- On `mousemove` (while dragging): update `translateX`/`translateY`; apply both translate
  and scale together: `transform: translate(Xpx, Ypx) scale(N)`.
- Clamp translate so the image edges never go beyond the viewport edges at the current scale.
- On `mouseup` / `mouseleave`: stop drag.
- Reset translate to `0, 0` whenever zoom resets to 1.
