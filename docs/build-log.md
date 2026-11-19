# Quick Craft: build log

A running record of **what was done, how, and why**, so new pages, features and updates can follow the same steps.
Add a dated entry for every change. The newest entry goes at the bottom of the log.
For the list of what's still to build, see [build-plan.md](build-plan.md). For the rules, see [../CLAUDE.md](../CLAUDE.md).

---

## How to add a new page section (block)

1. **Read the Figma frame.** File `WdjEJ9NeK2FTVsJcGF6lby`, node from [build-plan.md](build-plan.md). Use the Figma MCP: `get_design_context` for layout, plus a screenshot.
2. **Tokens.** Every colour, size, radius, shadow or aspect ratio must exist in `@theme` in `src/css/app.css`. Add a token before using a new value. Never use `[...]` arbitrary classes.
3. **Content model (if fields change).**
   - Back up first: `ddev snapshot --name=before-<thing>`
   - `ddev craft migrate/create <name>`, then edit `migrations/<file>.php`. Look items up by handle first so the migration is idempotent. Read the Craft and plugin source in `vendor/` for exact APIs.
   - `ddev craft up`
   - If it fails, restore with `ddev snapshot restore before-<thing>`, fix it, and run it again.
4. **Template:** `templates/_blocks/<entryTypeHandle>.twig`. It receives `block` and `headingLevel`. Reuse the components in `templates/_components/` (button, image, card, section-heading, stars, store-links).
5. **Test content:** a seed migration (`m…_seed_…`). Use Figma copy and real links. For images, see "Placeholder images" below.
6. **Check:**
   - `ddev npm run build`
   - Load the page and check: one `<h1>`, no skipped heading levels, no empty `href`/`src`/headings, no arbitrary Tailwind values.
   - Playwright at 375, 768 and 1440 px.
7. **Commit** one block per commit, put the Figma node in the message, `git push`, and add an entry to this log.

## Placeholder images (from Figma)

1. Find the image nodes. Composite visuals (collages, phone screens, tiles) → export the media frame. Image sets (gallery, thumbnails) → take the original images.
2. Use Figma MCP `download_assets(nodeId)`. `export.url` is the whole node rendered; `rawImages[]` are the original images (max 20 per node). The URLs expire quickly, so download straight away.
3. Download into `web/uploads/images/placeholders/` (gitignored). Scratch script pattern: a list file of `<filename> <url>` lines, then `curl -sf -L -o`.
4. Index them: `ddev craft index-assets/one images/placeholders`
5. Attach them with a migration that looks assets up by filename and skips if they're missing. Example: `m261118_180000_attach_home_placeholders`.
6. These are placeholders only. They contain the reference site's branding, so replace them with Quick Craft's own before going live.

## Useful commands

| Task | Command |
|---|---|
| Dev server (HMR) | `ddev npm run dev` |
| Build CSS/JS | `ddev npm run build` |
| Apply migrations + project config | `ddev craft up` |
| DB snapshot / restore | `ddev snapshot --name=x` / `ddev snapshot restore x` |
| Index uploaded files | `ddev craft index-assets/one images/<folder>` |
| Project config drift | `ddev craft project-config/diff` |

---

## Log

### 2026-11-18: Project setup
- DDEV (PHP 8.3, MySQL 8.0), Craft 5.12.1, plugins: Vite, CKEditor, SEO, Wheel Form, Embedded Assets, Expanded Singles, Simple RP Menu (VCS repo, 1.1.0).
- Vite 8 + Tailwind v4 + Alpine. `config/vite.php` uses the dev server on :5173 and falls back to `web/dist`.
- Commit `1889b68`.

### 2026-11-18: Design tokens + content model
- Tokens extracted from Figma (most-used values on all page frames). Tailwind's default palette is reset.
- Migration `m261118_120000_content_model`: volume, categories, 39 fields, 15 pageBuilder blocks, sections, globals.
- Gotcha: `icon` is a reserved field handle, so the field is called `emoji`.
- Commit `0e5eaa4`.

### 2026-11-18: Menus, landing pages, site settings
- Simple RP Menu menus seeded through the plugin's own services: `saveMenu` and `saveMenuItem`; top-level items use `parent_id` 0. Clear its cache tag `simple-rp-menu` afterwards.
- Link fields need `allowRootRelativeUrls` before they accept URLs like `/login` (migration `m261118_135000`).
- Commit `46f08c3`.

### 2026-11-18: Header, footer, Home blocks
- Header/footer partials, then hero, cardGrid (grid + compact), imageGallery (fan/marquee/grid/row), cityList, featureRows (stickyPhone/alternating), testimonials (carousel/quotes/reviews), ctaBanner.
- Content model additions, each in its own migration: `card.images` (city thumbnails), `cardGrid.secondaryButton`, and a `compact` option on `cardLayout`.
- Commits `7fab354` … `409b5d7`.

### 2026-11-18: Placeholder images
- 66 images exported from Figma into `images/placeholders/`, indexed, and attached by `m261118_180000_attach_home_placeholders`.
- Gotcha: exporting a *masked* image layer returns a blank 149-byte PNG. Use that node's `rawImages` instead.
- Commit `f8ffe03`.

### 2026-11-18: Smooth scroll, header behaviour, nav hover, Swiper
- Reference site: partiful.com, inspected with Playwright.
  - **Lenis** smooth scroll with `lerp: 0.1` and `smoothWheel`. It's in `src/js/smooth-scroll.js` and is skipped when the user prefers reduced motion.
  - **Header** hides when scrolling down past 160px and reappears when scrolling up or when anything inside it gets focus. It's the Alpine component `siteHeader` in `src/main.js`.
  - **Nav hover**: an `ink/5` background pill with a 4px radius (`hover:bg-ink/5`).
- **Swiper** (v14) runs the testimonials carousel: `src/js/carousels.js`, using the `data-carousel`, `.swiper`, `data-carousel-prev` and `data-carousel-next` markup. It's loaded only on pages that have a carousel. Slide widths come from Tailwind classes with the `!` suffix, because Swiper's CSS sets `width: 100%`.
- Removed `scroll-smooth` from `<html>`, because it conflicts with Lenis.
- Not built yet: the reference site's **mega menu dropdowns** (opened on nav hover). Figma has no design or content for them.

### 2026-11-18: Animations matched to partiful.com, scroll slider, hero chat, press strip
How I inspected the reference (Playwright `browser_evaluate` + `browser_hover`; screenshots were blocked):
- `document.getAnimations()` and inline `style.transform`, sampled over time and at several scroll positions, to find motion.
- Hover: snapshot each element's computed styles, hover it, then diff.

| Effect | Reference measurement | Ours |
|---|---|---|
| Button hover | scale 1.01, −2px, shadow `0 6px 12px rgba(0,0,0,.16)` | `_components/button.twig` (`hover:scale-101 hover:-translate-y-0.5 hover:shadow-lift`) |
| City name hover | lifts about 7px | `cityList.twig` `group-hover:-translate-y-2` |
| Reveal on scroll | sections and cards fade in | `src/js/reveal.js` + `data-reveal` / `data-reveal-index` (stagger 90ms) |
| Feature rows | sticky 900px panel; parent ≈ 4.5 screens for 3 slides; text slides up and out, phones stack | `src/js/scroll-slider.js` (`data-scroll-slider`); stacked fallback on mobile, with reduced motion, or without JS |
| Gallery fan | cards drift down-left along the diagonal (~30px/s), faster while scrolling, wrap around | `src/js/gallery-wave.js` (`data-gallery-wave`) |
| Hero | chat messages appear every ~3s, stack slides up, loops | `src/js/hero-chat.js` + `_components/hero-chat.twig`; new field `chatMessages` |
| Press strip (missing section, Figma 6-1259) | logo buttons, active one shows its quote | testimonials layout `press`: Swiper centred + loop + autoplay 5s; logo buttons activate their slide |

- Migrations: `m261118_190000_hero_chat` and `m261118_200000_press_strip`. The latter inserts the block after the hero by shifting `elements_owners.sortOrder`.
- Placeholder images: hero chat (photo, invite art, 3 avatars) and 9 press logos, from Figma `download_assets` (`rawImages`). Avatars are matched by **asset title = sender name**.
- **Gotchas:**
  - A Twig `{# #}` comment inside an array expression is a syntax error.
  - Tailwind v4 translate/scale utilities set the `translate`/`scale` properties, not `transform`.
  - The radius scale is reset, so only `sm`, `md`, `lg`, `xl`, `2xl` and `full` exist.
  - Swiper stops click propagation, so listen in the capture phase.
  - Tailwind must `@source "../js"` for classes that scripts toggle.
- **PLACEHOLDER content:** the press quotes and logos are real publications' (from the reference design). They must be replaced with Quick Craft's real press before launch.

### 2026-11-18: Header redesign + mega-menu dropdowns (reference: partiful.com)
- **Reference measurements:**
  - The dropdown is not a separate panel: the header pill grows from 976×83 (radius ≈ 44px) to 1287×467 with a 16px radius, keeping the paper/80 background, 20px blur and nav shadow.
  - Columns have small 12px grey headings and 16px medium links with icons (row 24px, gap 24px). A featured card sits on the right: label, 28px title, grey text, optional image.
- **Content: Simple RP Menu `headerMenu` as a tree.** Seeded by `m261118_210000_header_dropdowns`.
  - Level 2 with no link is a column heading; level 3 items are its links.
  - `data_json` lines: `icon: 🎂` on a link; `label: …` and `image: /path` on the featured card.
  - The featured card is a level-2 item with class `menu-featured`. Its `customShortContent` is the description.
- **Template: `_partials/header.twig`.**
  - The tree comes from `craft.app.plugins.getPlugin('simple-rp-menu').simplerpmenuItems.getResolvedMenuItems(menu.id)`, which returns items with `name`, `custom_url`, `target`, `noLink`, `class`, `customShortContent`, `data_json` and `children`. This is the plugin's own service, read in vendor.
  - Nav items that have children render as disclosure buttons (`aria-expanded`, `aria-controls`). Mobile shows them as accordions.
- **CSS:** `.site-nav-pill` / `.is-open` in app.css: max-width and radius transitions, plus a panel height transition using `grid-template-rows` from 0fr to 1fr. Token `--container-nav-open: 80.5rem`.
  - Gotcha: Tailwind utilities beat the components layer, so a pill that also had `max-w-nav` / `rounded-xl` never changed shape. Those values now live in the component CSS.
- **JS:** Alpine `siteHeader` gains `menu`, `openMenu`, `toggleMenu` and `closeMenu` (150ms close delay so moving into the panel doesn't flicker). Escape or focus leaving the header closes it, and the header won't auto-hide while a menu is open.
- **Checked:**
  - Invites opens the pill to 1288×448 with a 16px radius, close to the reference's 1287×467.
  - Company: 4 columns and the card fit with no overflow.
  - Escape closes the menu.
  - At 375px the accordion works with no horizontal overflow.
- **Placeholder:** the Invites card image (`placeholders/menu-invites.png`) comes from the reference site. Replace it.

### 2026-11-18: Gallery cards draggable ("A million reasons to hang")
- As on the reference site, the drifting cards can be grabbed and dragged with the mouse, or swiped sideways on touch.
- `src/js/gallery-wave.js`:
  - Pointer movement is projected onto the diagonal: `(dx + SLOPE·dy) / (1 + SLOPE²)`.
  - Release keeps the last movement as momentum (decaying ×0.94 per frame), then the idle drift resumes.
  - A 4px threshold separates a click from a drag. A finished drag swallows the click, and `dragstart` is blocked.
  - The drag ends on `pointerup`, `pointercancel` or window `blur`, so it can't get stuck.
- CSS: `cursor: grab` / `grabbing` (`.is-dragging`), `user-select: none`, `touch-action: pan-y` (vertical page scroll still works on touch), and images are not draggable.
- **Testing notes:**
  - Playwright's `browser_drag` can't grab moving elements ("element is not stable"), so the test dispatches PointerEvents instead. Result: a 300px drag left moves the cards about 303px, with about 50px more from momentum.
  - The Playwright window throttles `requestAnimationFrame` to about 2 fps when it isn't in front, which makes the drift look stuck in tests.
  - `setPointerCapture` throws for synthetic pointers, so it's wrapped in try/catch.

### 2026-11-18: Header right-side buttons overflowed the pill
- Problem: at 1440px the "Create an invite" / "Log in" buttons ended about 10px past the pill's right edge. The bar's content (logo + nav 506px + buttons 279px + 2×64px gaps) was wider than the 976px pill.
- Fix in `_partials/header.twig`: bar gap `lg:gap-6 xl:gap-8` (was `lg:gap-8 xl:gap-16`), nav items `p-0.5 xl:p-1` (was `p-1 xl:p-2`), nav `min-w-0`, button group `shrink-0` so the buttons never get squeezed.
- Checked: the buttons end at x=1184 at 1440px (reference partiful.com: 1184), with a 17px gap to the pill edge and no overflow at 1024, 1280 or 1440.
- Tip: after changing the header, run `bar.scrollWidth > bar.clientWidth` in the browser at lg/xl widths to catch overflow.

### 2026-11-18: Mobile header buttons
- Problem: below lg, "Create an invite" was 52px tall next to the 44px menu button. The header passed `class: 'px-4 py-3 text-sm'`, but the component's own `px-6 py-4` won: with Tailwind, two classes for the same property are decided by stylesheet order, not by their order in `class=""`.
- Fix: `_components/button.twig` takes a `size` prop: `md` (default, `px-6 py-4 text-base`) or `sm` (`h-11 px-4 text-sm`, 44px, matching the menu button). The header uses `size: 'sm'`. The menu button gets `shrink-0` and a hover background.
- **Rule:** use the `class` prop of components for layout and margins only. Never use it to override padding or font size; add a prop for that instead.
- Checked: at 375px and 768px both buttons are 44px with centres aligned and no overflow. Desktop is unchanged (170×54 and 96×54, ending at x=1184).

### 2026-11-18: Swiper version of the gallery (demo, to compare with the fan)
- Request: keep the custom diagonal fan on Home, but build a Swiper version to see how it looks.
- Backup: `ddev snapshot --name=before-gallery-slider`.
- Migration `m261118_220000_gallery_slider_demo`:
  - Adds the `slider` option to `galleryLayout`.
  - Creates the landing page **/gallery-slider-demo** with a copy of Home's fan gallery (same heading, text and images), set to the `slider` layout.
  - **DEMO ONLY:** delete the page, and the option if it ends up unused, once a layout is chosen. To use Swiper on Home instead, set the Home gallery's layout to "Slider" in the CP.
- `_blocks/imageGallery.twig`, layout `slider`: `data-carousel="gallery"` with `.swiper` and `swiper-slide w-48! md:w-64!`.
- `src/js/carousels.js`, variant `gallery`:
  - Modules: EffectCoverflow, FreeMode, Autoplay, Keyboard, A11y.
  - `loop`, `centeredSlides`, `slidesPerView: 'auto'`, `spaceBetween: -64` (cards overlap).
  - Continuous glide: `autoplay.delay: 0` with `speed: 3500`. The wrapper gets the class `is-gliding` (linear easing) on the `autoplay` event, and loses it on `touchStart` so the drag release keeps Swiper's easing.
  - Pauses when off screen (IntersectionObserver). No autoplay with reduced motion.
- **Gotchas (read in `node_modules/swiper/modules/effect-coverflow.mjs`):**
  - Coverflow multiplies `rotate`, `depth` and `stretch` by each card's distance from the centre with no limit, so on a wide screen the far cards rotated up to 245° and flipped around. Fix: `modifier` can be a function, so `(o) => clamp(o, -1, 1)` caps it.
  - Once clamped, coverflow's own z-index is flat. A `setTranslate` handler registered after construction sets `zIndex` from `slide.progress`, so cards nearer the centre sit on top.
  - Positive `stretch` pulls side cards toward the centre. A negative `spaceBetween` gives a more even overlap.
- Checked:
  - At 1440px: 10 cards visible, maximum angle 38°, correct stacking.
  - A synthetic 300px drag moves the cards 280px, and the glide resumes afterwards.
  - At 375px there is no horizontal overflow.
  - The page has one `<h1>` and no console errors.

### 2026-11-18: Swiper gallery reworked to the fan's style and effect
- Request: the Swiper version should look and move exactly like the fan (and partiful.com), not like a coverflow.
- **How (Swiper only moves a straight row):**
  - CSS (md+) tilts the whole `.swiper` by −16.7° (= atan 0.3, the fan's slope) and turns each card back upright: `.gallery-slider__card { transform: rotate(16.7deg) rotateY(-38deg) }`, with perspective 1200px on the slide. Hover is the same as the fan (rotateY −20°, lift 16px).
  - `spaceBetween: -156` at 768px and up: a 100px step along the tilted row, which is 96px across the screen, the fan's `STEP_X`.
  - The strip is wider than the screen (`calc(105vw + 640px)`) and starts off-screen left, so the loop reshuffles cards out of sight. Its `bottom` (−104 × spacing) was measured so the card line sits exactly where the fan's does.
  - `.gallery-slider` uses `box-sizing: content-box`, so `pt-24 lg:pt-40` is added to the 520px height as on the fan. With border-box the area was 160px shorter.
  - Cards further left sit on top: `zIndex` is set from DOM order on `loopFix`.
- **Motion (`initGallerySwiper` in `src/js/carousels.js`):**
  - Not Swiper autoplay, because it can't speed up in the middle of a transition. Instead a requestAnimationFrame drive moves the row by `DRIFT` (28px/s on screen) plus the scrolled px × 1.2, as in `gallery-wave.js`.
  - Each frame: `setTranslate`, `updateActiveIndex`, `updateSlidesClasses`. Near the end of the row it calls `swiper.loopFix({ direction: 'next', setTranslate: true, activeSlideIndex: slides.length - slidesPerViewDynamic() })`, the same call Swiper makes in its own drag handler (`onTouchMove` in `swiper-core.mjs`).
  - The drift pauses while touched (`touchEventsData.isTouched`) or gliding after release (`swiper.animating`). Swiper freeMode provides the drag momentum.
  - It only runs while on screen. Reduced motion: no drift.
- **Gotcha:** the cards are rendered twice (copies `aria-hidden`, decorative `alt`). With one set of 20 the row was shorter than the tilted strip, so `maxTranslate()` was 0, the loop couldn't work, and gaps appeared.
- Migration `m261118_230000_gallery_slider_label` renames the option to "Fan slider (Swiper)".
- **Checked (Home fan vs demo):**
  - At 1440px: same step (95.7 / −28.8px), same card size (203px wide), same line position (400px below the top of the area), and 17 cards visible on both.
  - Loop: 150 frames with fast scrolling and 75 reshuffles, the worst per-card deviation was 0px (no jumps).
  - A drag of 300px moves the cards 280px, then 224px of momentum, then the drift resumes.
  - 768px: diagonal, no overlap with the heading. 375px: straight swipeable row. No horizontal overflow and no console warnings.
- **Still demo-only** (`/gallery-slider-demo`). To use it on Home, set the Home gallery's layout to "Fan slider (Swiper)".

### 2026-11-18: Decision: keep the custom fan, remove the Swiper gallery
- After comparing the two, the custom diagonal fan (`src/js/gallery-wave.js`) stays on Home and the Swiper version is removed.
- Backup: `ddev snapshot --name=before-remove-gallery-slider`.
- Migration `m261118_240000_remove_gallery_slider_demo`:
  - Hard-deletes the `/gallery-slider-demo` page, including its nested block.
  - Removes the `slider` option from `galleryLayout`, but only if no block still uses it.
- Code: `src/js/carousels.js`, `src/css/app.css` and `templates/_blocks/imageGallery.twig` were restored to commit `25c2a2b`. Only the two Swiper gallery commits had touched them since.
- **For future reference:** the Swiper attempt (commits `46eac93` and `978a6b6`, and the log entries above) shows how to tilt a Swiper row and drive a seamless loop with `loopFix`. It's useful if a Swiper-based diagonal slider is ever needed.
- Checked: on Home the fan runs (`is-wave`, 20 cards), the press Swiper still starts, and `/gallery-slider-demo` returns 404.

### 2026-11-18: "Social life runs on quick craft" carousel loops
- Reference (partiful.com, measured): an endless loop, the 6 cards rendered 3× (18 slides), auto-advances one card (340px + gap) about every 4s, and Previous/Next never disable.
- `_blocks/testimonials.twig` (carousel layout):
  - Markup is now `data-carousel="loop"`.
  - Items are rendered 3× when there's more than one. Copies are `aria-hidden="true"` with decorative images. With only 6 slides, Swiper's loop can't fill a wide screen.
- `src/js/carousels.js`, variant `loop`: `loop: true`, `autoplay { delay: 3500, pauseOnMouseEnter, disableOnInteraction: false }`, and prev/next navigation. No autoplay with reduced motion.
- The plain `data-carousel` (no loop) variant is kept for other rows.
- Checked at 1440px: 18 slides, auto-advance after about 4s, Previous wraps, 20× Next always shows 5 cards, arrows never disabled, no console warnings.

### 2026-11-18: Testimonials carousel: even sides + smooth mouse
- Problem: the row started at the gutter, so at 1440px only 48px of a card showed on the left against 308px on the right.
- Fix: `centeredSlides: true`, and the `px-4! md:px-gutter!` padding was removed from `.swiper`, because padding shifts the centre. Now 171px shows on each side at 1440px and 20px at 375px.
  - Measurement gotcha: use `document.documentElement.clientWidth`, not `innerWidth`, which includes the vertical scrollbar (15px).
- Smooth mouse (`carousels.js`, variant `loop`, modules `FreeMode` and `Mousewheel`, option names read in `node_modules/swiper/modules/*.mjs`):
  - `freeMode { sticky: true, momentumRatio: 0.5, momentumVelocityRatio: 0.8 }`: a drag glides with momentum, then settles on a card.
  - `mousewheel { forceToAxis: true, sensitivity: 0.6 }`: a sideways trackpad swipe or shift+wheel moves the row. The vertical wheel still scrolls the page through Lenis.
  - `speed: 700` for arrows and autoplay.
- Checked: a sideways wheel moves it 356px, a vertical wheel 0px; a drag of 250px is followed by 487px of glide and lands exactly on a card; no console warnings.

### 2026-11-18: Testimonials loop showed empty space after mouse scrolling
- Symptom (user screenshot, about 1590px wide): after scrolling with the mouse, "200K+" was the last card and the right side of the row was empty.
- Cause: Swiper `freeMode` combined with `loop`. The momentum glide after a drag or wheel can run to the last slide, and Swiper only calls `loopFix` when a touch starts or moves, not during the glide. Stopping there leaves nothing to the right of the centred slide. My synthetic tests hadn't caught it.
- Fix (`carousels.js`, variant `loop`):
  - **No freeMode.** Moving slide by slide (drag that settles on a card, one card per wheel gesture with `thresholdDelta: 10`, arrows, autoplay) always runs `loopFix`.
  - **Rule:** don't combine Swiper `loop` with `freeMode`.
  - Smoothness comes from a soft ease-out (`--ease-out-soft`) at `speed: 700`.
- **Gotcha:** `[data-carousel="loop"] .swiper-wrapper { transition-timing-function }` inside `@layer components` had no effect, because `swiper/css` is unlayered and unlayered CSS beats layered CSS. Use Swiper's variable instead: `--swiper-wrapper-transition-timing-function` on the carousel root.
- Also added `preloadImages(root)`: within one screen of the carousel, its lazy images switch to eager and are decoded, so a fast scroll never reaches an undrawn photo. The loop copies reuse the same 5 files.
- Checked at 1585px: 925 frames covering 24 fast drags each way and 160 wheel steps, with 0 frames of empty space. Easing is `cubic-bezier(0.22, 1, 0.36, 1)`. No warnings.
- Noticed: `/favicon.ico` returns 404, because no favicon is set up yet. Unrelated.

### 2026-11-19: Testimonials loop: empty space on long mouse drags (real cause)
- The user still saw empty space after the freeMode fix. I reproduced it in the user's **real Chrome** (Claude in Chrome), with real mouse drags and wheel scrolling, plus an in-page `requestAnimationFrame` monitor that measures the uncovered width every frame.
  - Result: a long, fast drag (about 1200px) produced 27 frames with up to 267px empty (active slide 16 of 18).
  - Synthetic PointerEvents in Playwright had passed, because their drags were too short.
- **Cause:** Swiper reshuffles loop slides when a drag starts. With 18 slides (6 × 3) at about 1590px wide, the spare slides on the leading side ran out before a long drag ended.
- **Fix:**
  - Render the set 4× (24 slides).
  - `loopAdditionalSlides: 6`, so more spare slides are kept on each side.
  - `select-none` on `.swiper`: a drag was also selecting the card text and image (blue highlight).
- Checked in real Chrome: 6 long drags (1350px each way) plus wheel left and right gave 1388 frames with 0 empty, and nothing was selected.
- **Lesson:** for drag and loop bugs, test with real input in a real browser (Claude in Chrome `left_click_drag` / `scroll`) together with a per-frame monitor. Synthetic events can miss them.
