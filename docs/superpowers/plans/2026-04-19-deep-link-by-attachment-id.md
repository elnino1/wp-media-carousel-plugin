# Deep-Link to Carousel Slide by Attachment ID — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a page URL contains `?wp_mc_id={attachmentId}`, the carousel automatically navigates to the matching slide and scrolls it into view.

**Architecture:** Two changes to `assets/js/carousel.js` only. `bootAll` reads `URLSearchParams` and passes the ID to each `initCarousel`. `initCarousel` accepts an optional `initialId`, navigates to the matching slide if found, and returns `true` so `bootAll` stops passing the ID to subsequent carousels (first-match-wins).

**Tech Stack:** Vanilla JavaScript, `URLSearchParams` (browser-native), no new dependencies.

---

### Task 1: Add deep-link navigation to `carousel.js`

**Files:**
- Modify: `assets/js/carousel.js`

There is no JS test framework in this project — verification is done manually in a browser (see Step 4).

- [ ] **Step 1: Update the `initCarousel` signature and add deep-link logic**

Open `assets/js/carousel.js`.

Change the function signature on **line 12** from:

```js
    function initCarousel(wrapper) {
```

to:

```js
    function initCarousel(wrapper, initialId) {
```

Then replace the autoplay start block at the **bottom of `initCarousel`** (lines 238–240):

```js
        // Start autoplay.
        if (autoplay) resetAutoplay();
    }
```

with:

```js
        // Start autoplay.
        if (autoplay) resetAutoplay();

        // Deep-link: if a wp_mc_id param was provided, navigate to the matching slide.
        if (initialId) {
            const target = slides.findIndex(s => s.dataset.id === String(initialId));
            if (target !== -1) {
                goTo(target, false);
                wrapper.scrollIntoView({ behavior: 'smooth' });
                return true;
            }
        }

        return false;
    }
```

- [ ] **Step 2: Update `bootAll` to read the query param and apply first-match-wins**

Replace the `bootAll` function (lines 243–245):

```js
    /** Boot all carousel instances on the page */
    function bootAll() {
        document.querySelectorAll('.wp-mc-wrapper').forEach(initCarousel);
    }
```

with:

```js
    /** Boot all carousel instances on the page */
    function bootAll() {
        const params = new URLSearchParams(window.location.search);
        const initialId = params.get('wp_mc_id') || null;
        let linked = false;
        document.querySelectorAll('.wp-mc-wrapper').forEach(function (wrapper) {
            if (initCarousel(wrapper, linked ? null : initialId)) {
                linked = true;
            }
        });
    }
```

- [ ] **Step 3: Verify the full updated file looks correct**

Run:
```bash
grep -n "initialId\|bootAll\|wp_mc_id\|scrollIntoView\|return" assets/js/carousel.js
```

Expected output includes lines like:
```
12:    function initCarousel(wrapper, initialId) {
243:    function bootAll() {
245:        const params = new URLSearchParams(window.location.search);
246:        const initialId = params.get('wp_mc_id') || null;
...
            wrapper.scrollIntoView({ behavior: 'smooth' });
                return true;
...
        return false;
```

- [ ] **Step 4: Manual browser verification**

Start a local WordPress site with this plugin active. Place `[wp_media_carousel ids="X,Y,Z"]` on a page (where X, Y, Z are real attachment IDs in your install).

Open the page at:
```
http://localhost/your-page/?wp_mc_id=Y
```

Expected behaviour:
- The carousel opens with the slide for attachment Y already active (not slide 0)
- The page scrolls smoothly to the carousel if it is below the fold
- If you open `http://localhost/your-page/?wp_mc_id=99999` (non-existent ID), the carousel starts at slide 0 with no error

- [ ] **Step 5: Commit**

```bash
git add assets/js/carousel.js
git commit -m "feat: deep-link carousel to slide by wp_mc_id query param"
```
