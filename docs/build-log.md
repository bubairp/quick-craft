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

### 2026-11-19: Press strip under the hero matched to Figma 6-1259 + smooth mouse
- **Read from Figma** (`get_design_context` 6:1259, after loading the figma-design-to-code guide):
  - Section background `#f7f7f7`, read from the design render's pixels; the section sits straight on the page body. That is the existing `surface` token. Padding 64/32.
  - Item 390×219. The inactive logo is 240×40 at top 104.
  - The active item has a white 48% card, 390×180, at top 34 with a 4px radius. The logo moves up to top 14.
  - Quote: 20px / 26px, medium, −0.2px, `#30302f`, 310px wide, content 12px lower in the card.
- **Built** (`_blocks/testimonials.twig`, press layout):
  - `bg-surface` on the section, `md:w-97.5!` items.
  - The logo is absolute at `top-26`; the active one moves by `-22.5 × spacing` (app.css).
  - Quote: `top-8.5 h-45 bg-white/48 pt-6 leading-6.5 tracking-snug`. New token `--tracking-snug: -0.01em`.
  - Measured in Chrome at 1585px, every value matches the design, including the text centre 102px into the card.
- **Quotes on all items:** migration `m261119_100000_press_quotes` adds the reference site's press-strip quotes, word for word, to TIME, Business Insider, Inc., Google Play and App Store. It never overwrites an existing quote.
  - **Skipped on purpose: The New York Times and USA Today.** Their reference quotes name the reference brand, and rewriting them to name Quick Craft would fabricate a quote from a real publication. Add real quotes in the CP: Home → Press strip block → the item's Text field.
  - **PLACEHOLDER:** all press logos and quotes are real publications'. Replace them before launch.
- **Smooth mouse**, same as the testimonials row:
  - `Mousewheel { forceToAxis, thresholdDelta: 10 }`, `speed: 700`, ease-out via `--swiper-wrapper-transition-timing-function`, and `select-none`.
  - The click-to-activate handler ignores the click that follows a drag (`if (!swiper.allowClick) return`). Read in swiper-core: `allowClick` stays false until a `nextTick` after `touchEnd`.
  - `preloadImages(root)` is used here too.
- **Loop gap:** with 9 logos × 390px, a long drag showed up to 208px of empty space (154 of 2013 frames, measured in real Chrome). Fix: the set is rendered 3× (27 slides), with copies `aria-hidden` and buttons `tabindex="-1"`, plus `loopAdditionalSlides: 4`. Checked:
  - Real drags and wheel in Chrome: 0 gaps.
  - 10 synthetic 1352px drags with 340 checks after each move step: 0px.
- **Bug fix in `_components/image.twig`:** SVGs are now served as-is. Transforming them rasterised them to WebP with a white background, which showed as a white box behind the NYT logo on the grey section. This affects every SVG on the site.
- **Not checked:** mobile widths. The Chrome window wouldn't resize (it's maximised), and Playwright can't load the local HTTPS site at the moment (certificate).

### 2026-11-19: Home page background = #f7f7f7 (all light sections, as in Figma)
- Checked with a screenshot of Figma Body 6-1255, sampling pixels down the left edge and across five columns. Every light section on Home is `#f7f7f7` (`surface`): press strip, "One link. Everyone's in.", "A million reasons to hang", cities, "More than an invite", testimonials, Get the app, and the area around the footer. Ours was `paper` `#fdfdfb`.
- Other page designs differ: Careers 6-6167 is white. So this is **Home's page background**, not a global token change.
- How:
  - `_layouts/base.twig` has a new block `bodyBg` (default `bg-paper`).
  - `_entries/page.twig` sets `bg-surface` when `entry.section.handle == 'home'`.
  - **Light sections no longer paint their own background**, so they show the page background: hero light, ctaBanner light, cityList, testimonials carousel (its `from-paper to-surface` gradient wasn't in the design) and the press strip.
  - **Rule:** light blocks stay transparent. Only dark variants set a background.
- The light CTA's text box (`bg-surface`) matches Figma 6-2052 (`#f7f7f7`), so it blends with the page as designed.
- Checked in Chrome: on Home, `body` and every light section resolve to `rgb(247,247,247)`; the hero (black) and featureRows (ink) are unchanged. `/organizers` stays `rgb(253,253,251)`.

### 2026-11-19: "The best events in your city" section is white
- User request. Checked with a screenshot of Figma frame 6-4774: its background is `#ffffff`. The earlier Body (6-1255) sampling showed grey there, because that section is a separate frame in the file.
- `_blocks/cityList.twig`: `bg-white` on the section. This is an exception to "light blocks are transparent": the design gives this block its own white background.
- **Lesson:** sample each section's own frame, not only the page body.

### 2026-11-19: City list: event tiles, hover card, animations (reference: partiful.com)
- **Measured on the reference in real Chrome** (real hovers plus an in-page recorder):
  - The active city (the first by default, then the last hovered) lifts its name 16px, and "Explore … ↗" fades in below. The rows don't move.
  - **Tiles:** 8 per city, 130×130 with a 2px radius and a `rgba(0,0,0,.04)` background, 146px apart, starting about 253px from the centre (4 per side), vertically centred on the row. They don't move.
  - **Entrance:** each tile grows from about 0.87 and fades in over about 250ms. The previous city's tiles fade out.
  - **Tile hover:**
    - A white card (radius 8, `0 8px 24px rgba(149,157,165,.2)`) grows from 130 to 177px tall.
    - The image scales to 0.877 (114px with an 8px inset).
    - Title (14/21, `#111`) and subtitle (12px, black/55) fade up 6px at top 132.
  - The reference shows the same 8 event titles ("Gals run NYC" / "34 going", …) for every city; only the images differ.
- **Content:**
  - Migration `m261119_110000_city_event_tiles`:
    - Adds the plain-text `text` field (label "Subtitle") to the **images volume** layout. Tile text = asset title + Subtitle.
    - LA, SF, Chicago and Everywhere get the reference's 8 images each (`placeholders/city-{la,sf,chi,everywhere}-1…8`), with titles and subtitles.
  - Migration `m261119_111000_city_nyc_tiles` replaces NYC's Figma set, which repeated Stage Break and Point Break, with the reference's 8 (`city-nyc-1…8`). It only acts while the Figma set is still there.
  - Images were downloaded from `framerusercontent.com/images/<file>` with no query string. **Gotcha:** some `.webp` URLs return PNG data, so check with `file` and fix the extension before `index-assets`, because Craft goes by the extension.
  - **PLACEHOLDER:** images and event names are the reference's. Replace them before launch.
- **Template** `_blocks/cityList.twig`:
  - The row `li.city-row` gets `.is-active` from Alpine (`active`).
  - Tiles are `li.city-tile` (`size-32.5`) containing `.city-tile__card` (white, `shadow-tile`), `.city-tile__media` (`rounded-xs bg-black/4`) and `.city-tile__text`.
  - Sides use `right-1/2 mr-63` and `left-1/2 ml-63`, from lg up.
  - The label is absolutely positioned under the name, so rows keep their height. Row gap `lg:gap-18` (about 142px between names, reference 141).
- **CSS** (app.css components): `.city-tile` opacity/scale entrance, plus the hover card, media and text transitions. Reduced motion turns transitions off. New tokens: `--radius-xs` (2px) and `--shadow-tile`.
- **Gotcha:** Tailwind spacing utilities only exist in steps of 0.25, so `leading-3.6` (14.4px) was never generated. Use `leading-3.5`.
- **Testing gotcha:** `window.scrollTo`/`scrollBy` gets undone by Lenis on the next input, so the page jumps back under the pointer. Scroll with real wheel input (Claude in Chrome `scroll`) before hovering.
- **Checked in Chrome:**
  - Each city shows its 8 images on hover.
  - Hovered tile: card 130×177, image 8,8,114×114, text at 132, title 14/21 `#111`, step 146px, 252px from the centre. All equal to the reference.
  - Entrance: opacity 0→1 and scale 0.87→1 in about 280ms while the old tiles fade out.

### 2026-11-19: Swiper version of the scroll slider (demo, option 2 "hybrid")
- Question: the "Text blast / Collect info / Build hype" scroll slider is custom (`src/js/scroll-slider.js`); could it be Swiper? The user chose **option 2**: keep the scroll maths, let Swiper render. Home keeps the custom one; the demo is at **/feature-slider-demo**.
- Backup: `ddev snapshot --name=before-feature-swiper`.
- Migration `m261119_120000_feature_swiper_demo`:
  - Adds the `stickySwiper` option to `featureLayout` ("Sticky phone — Swiper (demo)").
  - Creates the demo landing page with a copy of Home's stickyPhone block. The cards are copied field by field with `$field->serializeValue()`, which round-trips text, assets and links.
  - The demo block gets a heading ("Swiper version (demo)"). Home's has none, so on a page where it's the first block its rows would become three `<h1>`s.
- `src/js/scroll-swiper.js` (imported in main.js):
  - **Desktop (≥1024px, no reduced motion):** the same scroll maths as scroll-slider.js (1.35 screens per slide, move during 25–75% of each step, eased).
    - The position is handed to two Swipers with `setProgress(p, 0)`, read in swiper-core: it calls `translateTo(…, 0)` and updates the classes.
    - One is a vertical text Swiper; the other is a phone Swiper with `EffectCreative` (`next` = translate `110%` up from below, `prev` = stays).
    - `allowTouchMove: false`, because the scroll drives it.
  - **Mobile / reduced motion:** the rows become a horizontal swipeable Swiper (Keyboard, A11y).
  - **No JS:** stacked rows.
- **Gotchas:**
  - A `.swiper` that sizes itself with `aspect-ratio` grows without limit (width 33,554,432px), because Swiper sizes the slides from it and the two feed each other. Put the aspect ratio on a wrapper and make the swiper `absolute inset-0`.
  - The creative effect sets `zIndex` from rounded progress, so the incoming phone sat **under** the current one for the first half of each move. After `setProgress`, set `zIndex = i + 1` (later screens on top).
  - The phone Swiper needs `overflow-visible!`, otherwise the incoming screen is clipped to the phone box.
- **Checked:**
  - Desktop (real wheel in Chrome): over 105 settled frames through all 3 rows, text and phone positions are within 0.0001px of the custom version.
  - 375px (Playwright): the scroll panel is hidden, swipeable cards show, a touch swipe moves to card 2, no overflow, one h1, no console errors.
- **DEMO ONLY:** once a version is chosen, delete the page and the option, and delete scroll-swiper.js (or scroll-slider.js).

### 2026-11-19: Decision: keep the custom scroll slider; mobile swipe added; Swiper demo removed
- Comparison:
  - Custom version: 59 lines of JS and 67 of template.
  - Swiper version: 96 lines of JS and 77 of template, plus 2 workarounds.
  - They look identical on desktop. Swiper has no "progress follows page scroll" mode, so the Swiper version needed the same scroll maths **plus** Swiper setup.
  - **Rule:** use Swiper for swipe/drag/arrow/autoplay sliders, where it replaces our code, not for scroll-driven ones.
- Migration `m261119_130000_remove_feature_swiper_demo` hard-deletes `/feature-slider-demo` and removes the `stickySwiper` option (only if no block still uses it).
- Code:
  - `src/js/scroll-swiper.js` deleted (`git rm`).
  - `featureRows.twig` and `main.js` restored to before commit `b2ec14f`; only that commit had touched them.
- **Mobile swipe on the custom slider:**
  - The stickyPhone fallback (shown on mobile, with reduced motion, or without JS) is now a Swiper card row using the **plain `data-carousel` variant** in carousels.js, the same as the other carousels. No new JS.
  - Cards are `w-80!` / `md:w-96!`: image, heading, text, button.
  - Without JS the cards stack (`.feature-cards .swiper:not(.swiper-initialized) .swiper-wrapper`, flex column with gap 64px).
- Checked (Playwright):
  - 375px: cards, and a touch swipe puts card 2 at the left edge, no overflow.
  - 768px (after resizing from desktop): the Swiper that was initialised while hidden lays out correctly (its resize observer picks it up), cards 384px.
  - 1440px: the custom scroll slider is unchanged (track 405vh, cards hidden).
  - `/feature-slider-demo` returns 404.

### 2026-11-19: "A million reasons to hang" rebuilt from reference measurements
Measured on partiful.com (Claude in Chrome with real hovers and drags; Playwright at 1600×731, 1440×900, 1440×600, 1600×1100, 900×800 and 375×800).
- **Structure:** 20 `<a>` cards, absolutely positioned at the centre of a container, moved with `translate`. Inside each: a wrapper (hover) and an `<img>` with `skewY` (tan 0.264, about 14.8°) and an 8px radius. Left cards have the higher z-index.
- **Responsive (re-flows live on resize):**
  - Container: viewport height from 1200px up, ~596px below.
  - Card: 0.161 × width (0.322 below 810px).
  - Spacing: 0.403 × card.
  - Vertical limit: H/2 − 0.632·card − 12 (matches all sizes within 0.5px).
  - **Slope = −limit / (9.5 × spacing)**: the path reaches the limit at the outermost cards, so taller galleries are steeper (measured −0.153 … −0.532).
  - On top of that, a wave about 0.07 × card high, one wavelength per loop, travelling left at about 1.5 steps/s.
- **Motion:**
  - Drift about 0.175 steps/s to the left.
  - Page scroll pushes the row **in the scroll direction** (down → left, up → right), about 0.3px per px. The old version always went forward.
  - **Hover pauses the drift.**
- **Drag:** the row eases after the pointer (smoothing about 0.6s), then a short capped glide. Reference: a 600px drag moves the row about 690px, peaking at about 710px/s.
- **Hover:**
  - The hovered card's wrapper gets `rotate(12deg) translateY(−0.705·card)` (−180px at a 255px card).
  - The two cards on its left (which overlap it) slide away along the path by 0.286 and 0.044 × card.
  - Cards to its right don't move. Spring-like timing (`--ease-spring`, 600ms).
- **Implementation:**
  - `src/js/gallery-wave.js` rewritten: layout() runs from a ResizeObserver, place() puts cards on the path, plus a hover handler, a drag handler and a rAF loop.
  - `.gallery-fan*` CSS rewritten (the 3D rotateY fan is gone).
  - `imageGallery.twig` fan branch: a full-height gallery with the heading on top (pointer-events: none). Mobile now gets the same gallery instead of a plain scroll row. Without JS: a horizontal scroll row.
- **Gotchas:**
  - Velocity from the last two pointer events made a synthetic 600px drag throw the row 5,189px, because events can arrive 1ms apart. Measure release speed over the last 100ms and cap the glide at 2.5 steps/s. Result: 703px, peaking at 713px/s (reference about 690 / 710).
  - Hover is detected on the card `li` (whose box doesn't move), not the lifted image, as on the reference's `<a>`. So the lifted card stays hovered.
  - Images are preloaded (eager plus decode) when the gallery is within a screen, and the media has a `line` background while loading. (A blank area in one screenshot was only paint timing; the images had loaded.)
- **Checked against the reference:**
  - Card size, spacing, vertical limit and container size match at all 6 sizes. Slope −0.159/−0.400/−0.378/−0.486 against −0.153/−0.392/−0.346/−0.532.
  - Hover: 12° / −179.9px; neighbours −73/+14 and −11/+2.2 (reference −73/+15, −11/+2.35). Drift paused.
  - Scroll direction is correct.
  - 375px: touch swipe works, no overflow, no console errors.

### 2026-11-19: Gallery hover: cards near the top were cut off
- User: the hovered card should look the same everywhere; in some places it was being cut off.
- **Reference (measured in Chrome):**
  - The lift is the same everywhere: `rotate(12deg) translateY(−180px)`. The left card went from top 381 to 145, the middle one from 283 to 46.
  - Its gallery wrapper is `overflow: clip visible`, so it clips **only horizontally**. A card lifted near the top rises above the gallery, over the previous section, and is never cut.
- **Ours** clipped both ways (`overflow: hidden` on `.gallery-fan.is-wave` and on the section), so high cards on the right were cut at the gallery's top edge.
- **Fix:**
  - `.gallery-fan.is-wave { overflow-x: clip; overflow-y: visible }`.
  - The section changes from `overflow-hidden` to `overflow-x-clip`.
  - `clip`, unlike `hidden`, doesn't force the other axis to scroll/clip.
- Checked in Chrome: no ancestor clips vertically. A right-hand card lifted 102px above the gallery top is the topmost element at every probed point, 28px above the edge included, and the screenshot shows it whole.
- **Testing gotcha:** the card is skewed and rotated, so the top of its bounding box at the centre is empty space. Probe inside the card (15–30% of its height), not 12px under the box top.

### 2026-11-19: Gallery hover stays inside the section; heading under the cards
- The previous fix (letting lifted cards rise above the section) was wrong. The user pointed out that on the reference a hovered card **never goes above the slider section**.
- **Measured on the reference** (real hovers at the left, middle and right):
  - The lift is always `rotate(12deg) translateY(−180px)`.
  - A card that sits high (on the right) is **first moved down**: its base went from about −115 to +11, so the lifted card's top lands about 12px inside the section.
  - Lifted tops: left 145, middle 46, right 13.
  - Rule: hovered y ≥ −H/2 + 12 + (0.705 + 0.72) × card, where 0.72 is half the turned card's height (−365.5 + 12 + 363 ≈ 10, against 11 measured).
- **Ours:**
  - `place()` computes `need = max(0, −H/2 + EDGE + HOVER_ROOM·card − y)` for the hovered card and sets `--hover-drop` on its lift wrapper.
  - CSS: `translate: 0 calc(var(--card) * −0.705 + var(--hover-drop, 0px))`. The same spring transition animates the drop.
  - Checked: right 10 (dropped 100px), middle 38, left 141 (reference 13 / 46 / 145).
- **Heading overlay:**
  - The reference's heading comes first in the markup with z auto; the gallery has `z-index: 1`, so the cards pass **over** the heading.
  - The heading sits 128px from the top.
  - Ours: `.gallery-fan { z-index: 1 }`, heading wrapper `z-0`, `md:pt-section` (128px). Checked: heading top is 128px from the gallery and under the cards.
- Overflow stays `overflow-x: clip; overflow-y: visible` (the same as the reference), but with the drop rule nothing needs to leave the section any more.
- **Lesson:** measure the reference at several positions (left, middle, right) before concluding the effect is the same everywhere. A single measurement hid the drop rule.

### 2026-11-19: "Get the app" section: turning image spiral, pill and subtitle as on the reference
- **Measured on the reference** (Chrome 1600×731 and Playwright 375×800):
  - 125 images (194 on a phone) in one 39px box at the centre, each placed by its own transform.
  - **The shape is an oval spiral** (1.34× wider than tall):
    - 22 images per turn.
    - About 121px further out per turn (≈ 8% of the section width).
    - About 5.3 turns, ending at the section corners (√((W/2 ÷ 1.34)² + (H/2)²) ≈ 687 against 673 measured).
  - Each image is turned along the spiral (90° to its angle). Its size grows with distance from the centre (tiny in the middle, about 0.85 × ring spacing at the edge) with random variation.
  - **Motion:** the spiral turns anticlockwise at about 2.5°/s. It also has a ±1% "breathing" pulse (not copied). Scroll doesn't affect it.
  - Pill: 350×114 centred on the section (the spiral centre); padding 32/56; 50/50px; +0.02em spacing; `#111`; halo `0 0 38px 30px #f7f7f7` (Figma 6-2046).
  - Subtitle: 8px below the pill and outside the centring; 237×67; padding 12; 18/21.7px light; `#4b4b49`; bg `#f7f7f7`; square corners.
  - Phone: section 360×388; pill 170×64 (20/28, 24px); subtitle on one line (16/19.2, padding 8).
  - No store buttons.
- **Built:**
  - `src/js/cta-spiral.js` (new, in main.js): layout() sizes and hides items from the section size (ResizeObserver); place() turns the spiral; rAF only while visible; reduced motion stays still.
  - `ctaBanner.twig`: 200 `li.cta-spiral__item` cycling through the block's images (JS shows about 117 on desktop and 178 on a phone). The old static 3-ring Alpine version is removed.
  - The content wrapper centres only the pill. The subtitle, button and badges sit in an absolute `top-full mt-2 left-1/2 w-max max-w-xs -translate-x-1/2` box with their own width, so the phone subtitle is one line.
  - Section `min-h-97 md:min-h-175` (388/700).
  - Tokens: `--shadow-halo`, `--tracking-loose` (0.02em).
- **Store buttons removed** (user request): migration `m261119_140000_cta_no_store_badges` turns off `showAppBadges` on Home's ctaBanner. The option stays in the CP.
- **Checked:**
  - Desktop: section 1585×700, pill centre at 350 (reference 346), height 114, 50/50px, +1px. Subtitle 240×68, 8px below. Spiral turns at −2.5°/s exactly. No store links.
  - Phone: section 360×388, pill 64px tall, subtitle one line 218×35 (reference 229×35), no overflow, no console errors.
- **Known difference:** the pill is wider (433 against 350 on desktop) because "GET THE APP" is set in Inter. The reference uses its own narrow display font. Same size, padding and height.
- **Follow-up (user):** spiral rings a little closer together. `GAP` changed from 0.08 to 0.068 of the width (reference ≈ 0.077).
  - `SIZE` changed from 0.85 to 1.0, so the images stay the same size (largest 124px at 1600).
  - Slots raised from 200 to 240, because a phone now needs about 209.
  - Checked: desktop gap 108px with 137 images; phone gap 24.5px with 209 images; no overflow.
  - To change the spacing again, edit `GAP` in `src/js/cta-spiral.js`. The image count follows automatically, up to the number of slots in the template.

### 2026-11-19: "Get the app": second background layout, collage rows
- User request: keep the spiral and add an option where the images form rows like a collage, with the rows moving randomly.
- Migration `m261119_150000_cta_layout_rows`:
  - New dropdown field `ctaLayout` ("Background layout"): `spiral` (default) or `rows` ("Collage rows (sliding)"). Added to the ctaBanner entry type after `theme`.
  - Home keeps the spiral (the default).
  - Demo page **/cta-rows-demo** has a copy of Home's banner set to `rows`. The copy is made field by field with `serializeValue`.
  - **DEMO PAGE ONLY:** delete it once a layout is chosen.
- `ctaBanner.twig` (layout `rows`): 6 rows × 14 images, each row's set rendered twice for a seamless loop, cycling through the block's images.
- `src/js/cta-rows.js` (new, in main.js):
  - On each page load, each row gets a random speed (14–42px/s), a random direction and a random starting point. Each image gets a random tilt of ±6°.
  - It moves by a fraction of one set's width, so the loop wraps without a jump.
  - rAF only while visible. Reduced motion keeps it still (tilt kept). Re-measures on resize.
- CSS `.cta-rows__row` (flex 1: the 6 rows share the section height) and `.cta-rows__item` (square, row height, `rounded-sm`, `shadow-card`).
- **Rule kept:** no inline styles. The random tilt is set by JS, not a Twig `style="--tilt"`.
- Checked:
  - Desktop 1585×700: rows 107px, images about 110px, every row covers the width, one h1, directions random (+ and −).
  - Phone 360×388: rows 58px, rows cover the width, no overflow, no console errors.
- **Testing note:** both test browsers ran rAF at about 2fps when not in front. The 0.05s per-frame step cap then slows movement about 10× (measured 1.5–2.7px/s for the configured 14–42), so judge speed in a focused browser.
- **Follow-up (user): smoother movement** (`cta-rows.js`):
  - Calmer speeds (10–26px/s instead of 14–42).
  - Each row's speed drifts ±25% on a random 8–14s sine, so it reads as organic rather than mechanical.
  - Rows ease in over 1.2s (smoothstep) each time the section comes into view.
  - The per-frame step cap went from 0.05s to 0.1s, so a briefly slow frame doesn't visibly stall the rows.
  - Checked: about 10–21px in the first 1.5s (ramp), then 15–32px/s; directions still random; no errors.
- **Follow-up (user): don't change the items' shape.** The random ±6° tilt per image was removed from `cta-rows.js`, so the images stay straight squares. Checked: 168 items, 0 rotated, 107×107, all 6 rows moving, no errors.
- **Follow-up (user): no gradient; the images should fit the section, not be cut.**
  - I had briefly added top/bottom fades (`.cta-fade`, plus a `--page-bg` variable on `<body>`). The user didn't want them, so they were removed again before committing.
  - The rows container now has an even top/bottom margin (`py-3 md:py-6`). The 6 rows share the remaining height (flex: 1), so every row sits fully inside the section.
  - Checked: desktop 700px section with 24px margins top and bottom; phone 388px section with 54px rows and 12px margins; 0 images cut at the top or bottom; no overflow.
  - Left/right: the rows slide, so an image is always entering or leaving at each side. Fading those in and out per image is possible if wanted.

### 2026-11-19: One demo page with every "Get the app" option
- User request: keep both layouts and put all the options on one demo page.
- Migration `m261119_160000_cta_options_demo`:
  - The old `/cta-rows-demo` entry is renamed to **/cta-options-demo** ("Get the app — all options"). The old URL now returns 404; it was only a demo.
  - Its blocks are rebuilt as four copies of Home's banner, which differ only in `ctaLayout` and `theme`: **spiral·light, rows·light, spiral·dark, rows·dark**.
  - Home is unchanged (spiral·light).
- Checked:
  - 4 sections of 1585×700 in that order; the spiral ones lay out 137 images, the rows ones 6 rows.
  - 1 h1 (first block), the rest h2.
  - Dark versions checked by eye: white pill with ink text, `night` subtitle box, images on black.
  - The only console error is my own test fetch of the old URL (404).
- **DEMO PAGE ONLY:** delete it before launch. Both layouts and both themes stay available in the CP ("Background layout", "Theme").

### 2026-11-19: "Get the app": third option, grayscale rows with a colour spotlight
- User request: the collage rows in grayscale, with the original colours showing in a circle around the mouse cursor, **as a separate option**.
- Migration `m261119_170000_cta_rows_spotlight`:
  - Adds the `rowsSpot` option to `ctaLayout` ("Collage rows — grayscale, colour spotlight").
  - Appends rowsSpot·light and rowsSpot·dark to `/cta-options-demo`.
- Migration `m261119_171000_cta_options_demo_order`: appending with a computed sortOrder had interleaved the blocks, so this sets `elements_owners.sortOrder` to light → dark, and within each spiral → rows → rowsSpot.
- `ctaBanner.twig`: `rows` and `rowsSpot` share the rows markup; rowsSpot adds `data-cta-spotlight`.
- `cta-rows.js`:
  - With `data-cta-spotlight`, the rows container is **cloned** into `.cta-rows--colour`, inserted right after it and under the pill. Both copies get the same transform every frame.
  - `setupSpotlight(section, colourLayer)`: pointermove on the section sets the target x/y and radius 160; pointerleave/pointercancel sets radius 0. The circle eases (0.12s time constant) and sets `--mx`/`--my`/`--spot-r` on the colour layer.
  - Works with reduced motion and with touch while a finger drags.
- CSS:
  - `[data-cta-spotlight] { filter: grayscale(1) }`.
  - `.cta-rows--colour { mask-image: radial-gradient(circle var(--spot-r) at var(--mx) var(--my), black 55%, transparent 100%) }`. A radius of 0 means no colour.
- Checked in Chrome with real hovers: the whole section is grayscale, and the images around the cursor show in colour (light and dark). Demo order is correct, with 1 h1.
- The demo page now shows all **6 combinations** (3 layouts × 2 themes). DEMO ONLY: delete it before launch.

### 2026-11-19: "Get the app": reverse spotlight option, bigger circle, slower and smoother
- **Reverse option** (user): `rowsSpotInvert` ("Collage rows — colour, grayscale spotlight"). The rows stay in colour and a **grey** circle follows the cursor.
  - Migration `m261119_180000_cta_rows_spotlight_invert`: dropdown option, plus light and dark versions on the demo page.
  - Template: `data-cta-spotlight="colour"` (rowsSpot) or `"gray"` (rowsSpotInvert).
  - JS: the clone gets `cta-rows--spot` plus `--spot-colour` or `--spot-gray`.
  - CSS: grayscale goes on the base for "colour" and on the copy for "gray"; one shared mask on `.cta-rows--spot`.
- **Demo order:** `m261119_181000_cta_options_demo_reorder` sets the order to light then dark; spiral → rows → rowsSpot → rowsSpotInvert (8 blocks).
  - **Gotcha:** the previous migration re-sorted `$page->getFieldValue('pageBuilder')->all()` after saving new blocks. That query's results were already loaded, so the new blocks were missing from the sort. Query fresh with `Entry::find()->ownerId($page->id)->field('pageBuilder')`.
- **Bigger circle** (user): `SPOT_RADIUS` changed from 160 to 220px.
- **Smoother and slower** (user):
  - Rows run at 6–16px/s (was 10–26), and the gentle speed changes are kept.
  - The circle follows with a 0.25s time constant (was 0.12s).
- **Bug fix: the circle drifted away from a still cursor when the page scrolled.** Its position is relative to the section, and it was only updated on pointermove.
  - Now the pointer's client x/y is remembered and recalculated on `scroll` while the pointer is inside, snapping to stay exactly under the cursor.
  - Checked: hover at (400, 300), then scrolling 150px: the circle moves from my=280 to 430, the same as the cursor's new position over the section.
- Demo `/cta-options-demo` now shows **8 versions**: spiral, rows, rowsSpot and rowsSpotInvert, each light and dark. DEMO ONLY.

### 2026-11-19: Mega menu tweaks + Tabler icons everywhere
- **Menu dropdown** (`_partials/header.twig`):
  - Column headings are uppercase (`uppercase font-medium`), on desktop and in the mobile accordion.
  - The featured box on the right is **white** (`bg-white p-5`). No shadow, at the user's request.
  - Link icons use the dark text colour (`text-ink`, at the user's request).
- **Tabler icons on the whole site:**
  - Package `@tabler/icons` 3.52.0 (raw SVGs: `icons/outline/*.svg`, `icons/filled/*.svg`).
  - **`_components/icon.twig`**: `{% include '_components/icon.twig' with { name: 'arrow-up-right', class: 'size-4', filled: false, label: null } only %}`.
    - It inlines the SVG with Craft's `svg('@root/node_modules/@tabler/icons/icons/<outline|filled>/<name>.svg', sanitize: false, namespace: false)` and adds `|attr` for class, removed width/height, aria-hidden or role="img" with aria-label, and focusable=false.
    - The name is checked against `^[a-z0-9-]+$`. A missing icon renders nothing (Craft logs a warning).
    - **Production needs `node_modules` at runtime.** The deploy already runs npm to build web/dist; keep that.
  - Replaced every hand-drawn SVG:
    - header: menu-2, x, chevron-down;
    - cityList: arrow-up-right;
    - testimonials carousel: chevron-left and chevron-right;
    - `_components/stars.twig`: filled star.
  - **Menu content:** migration `m261119_190000_menu_tabler_icons` rewrites the `icon:` lines in `simplerpmenu_items.data_json` from emoji to Tabler names. 25 items, for example 🎂 → cake, 🍝 → tools-kitchen-2, 🎆 → confetti (`fireworks` doesn't exist in Tabler), 📱 → brand-apple, 🤖 → brand-android. It clears the `simple-rp-menu` cache tag.
  - **Editors:** in a menu item's Data JSON, write `icon: <name>` using any name from https://tabler.io/icons.
- Checked:
  - Chrome: Invites panel heading "OCCASIONS" uppercase, box `rgb(255,255,255)` with no shadow, 9 Tabler link icons in `rgb(17,17,17)`, 60 stars and 2 carousel arrows rendered.
  - 375px: menu-2 ↔ x toggles, 5 accordion chevrons, uppercase headings, no errors.

### 2026-11-19: Footer store links with icons
- `_components/store-links.twig` (used in the footer, and by banners with showAppBadges): badge-style buttons.
  - Tabler `brand-apple` / `brand-google-play` (size-6).
  - Two lines: small "Download on the" / "Get it on" (text-xs, 80% opacity) and bold "App Store" / "Google Play" (text-base semibold). Each string uses `|t`.
  - The full phrase is the link's `aria-label`; the visual text is aria-hidden.
  - 48px tall, same border and colours as before.
- Checked in Chrome: footer badges 160×48 and 156×48, correct icons, labels "Download on the App Store" and "Get it on Google Play".

### 2026-11-19: Footer social links with icons
- `_partials/footer.twig`: the footer menus are no longer printed with `getRpMenuHTML` (plugin HTML, which can't carry icons).
  - Links are built from `simplerpmenuItems.getResolvedMenuItems(menu.id)` (the same service as the header), using the same `data()` macro for Data JSON.
  - An item with `icon: <tabler-name>` gets that icon before its label. `target` and `aria-current` are kept; the classes are as before.
- Migration `m261119_200000_footer_social_icons` sets `icon:` on the footerSocials items that have no Data JSON yet:
  - Instagram → brand-instagram, Twitter → brand-x, TikTok → brand-tiktok, LinkedIn → brand-linkedin.
  - Substack → `news` (Tabler has no Substack brand icon).
  - Mappings for Facebook, YouTube, Threads and Pinterest are included for future items.
- Checked: all 5 footer columns have the same links as before; the 5 social links have their icons and open in a new tab; no errors.
- Editors: to add an icon to any footer (or header) menu link, write `icon: <name>` in its Data JSON, using names from https://tabler.io/icons.

### 2026-11-19: Header: one "Contact Us" button
- Content change only; the template is unchanged (`_partials/header.twig` already skips an empty button).
- Migration `m261119_210000_header_contact_button` (backup first: `ddev export-db --file=/tmp/before-header-contact.sql.gz`):
  - `siteSettings.primaryButton` → URL `/contact`, label "Contact Us". This is the same page as the footer's Support › Contact link.
  - `siteSettings.secondaryButton` → cleared. This removes "Log in" on desktop and at the bottom of the mobile menu.
- Checked with Playwright:
  - 1440px: one dark "Contact Us" pill on the right.
  - 375px: "Contact Us" (sm) next to the menu button. The open mobile panel has no extra button, and there is no horizontal overflow.
- Editors: change the button text or link in CP › Globals › Site settings › Header primary button. Fill "Header secondary button" to bring a second button back.
- Note: `/contact` has no page yet (placeholder, as in the footer).

### 2026-11-19: Rule: reuse fields through handle overrides
- Added a rule to CLAUDE.md (Content model): reuse an existing field when its settings are the same, adding it again to the layout with its own handle and label (Craft 5 multi-instance fields). Create a new field only when the settings must differ, for example dropdown options. Handles must be meaningful, never generic (`plainText`).
- Why: the old sites used generic fields (`plainText`, `plainTextSub`, `Description`), which made templates hard to read. Craft 5's per-instance handles keep the single shared field and still give readable handles.
- Possible later cleanup (not done):
  - `eyebrow`, `personName`, `personDetail`, `location` and `publication` could become one PlainText field.
  - `comparisonThem` / `comparisonUs` could become one field, if their columns are the same.
  - `appStoreLink` / `googlePlayLink` could become one field.

### 2026-11-19: Globals and categories → entries (as in Site7 Engine)
- **Why:** everything is an entry. Settings get drafts, revisions and section permissions, and it's the direction Craft is heading in (the `entrify` commands).
- **Migration** `m261119_220000_entrify_globals_categories` (backup: `/tmp/before-entrify.sql.gz`):
  - It creates the sections and entry types itself, then runs Craft's own `entrify/categories` and `entrify/global-set` actions through `Craft::$app->runAction(…, interactive: false)`.
  - **Category groups → Structure sections (no URLs):**
    - `blogCategories` (entry type `blogCategory`) and `departments` (entry type `department`);
    - the Categories fields `blogCategories` and `department` became Entries fields (`maintainHierarchy`).
  - **Global sets → Singles (no URLs):** `siteSettings` and `newsletter`, with entry types of the same handles.
  - Element IDs (8 and 9) and their content are kept. There were no categories yet; newsletter was already empty.
- **Templates:**
  - `_layouts/base.twig` loads both Singles once per request into Craft's front-end `_globals` collection (`vendor/craftcms/cms/src/web/twig/FeExtension.php`).
  - `_partials/header.twig` and `_components/store-links.twig` read `_globals.get('siteSettings')`.
  - This also fixes `only` includes: ctaBanner → store-links still sees the settings. Cost: two queries per request, the same as global sets. Site7's problem was each block running the same query again.
- **Gotcha found by testing the other-environment path** (restore the backup, then `craft up`):
  - Craft applied the project config first, which trashed the global sets, so the migration skipped them and the content wasn't converted.
  - Fixed by looking groups and sets up `withTrashed`, reusing sections that already exist, and pinning the section and entry-type UIDs from the committed YAML.
  - After the fix, both orders work. `project-config/diff` reports no changes, and the page still shows "Contact Us" and the store links.
- **Docs:** CLAUDE.md (content model table, the "everything is an entry" rule, `_globals` usage) and `.claude/skills/craft-cms/SKILL.md` (settings and taxonomies, migration rules for sections, front-end gotchas from earlier work).

### 2026-11-19: Shopify-style step 1: colour schemes + spacing presets
- **Migration** `m261119_230000_scheme_and_spacing` (backup: `/tmp/before-scheme.sql.gz`):
  - **`theme` → `scheme`** ("Colour scheme"; same field and UID, so the light/dark values are kept). Two new options: `white` and `soft`.
  - **New field `spacing`** (default / noTop / noBottom / none, UID pinned), added to cardGrid, cityList, testimonials and ctaBanner, right after the scheme where there is one.
- **CSS** (`src/css/app.css`):
  - `[data-scheme]` in the components layer: background and text colour from tokens. Block utilities still win.
  - `[data-spacing]` unlayered: it beats the blocks' `py-*` utilities.
- **Templates:**
  - Blocks put `data-scheme` / `data-spacing` on their `<section>` instead of `bg-black text-white` / `text-ink`.
  - featureRows keeps `bg-ink` for dark (Figma), which overrides the scheme's black.
  - On `soft`, the card component's empty media panel and the CTA subtitle box become white so they stay visible.
- **Why the spacing options are "remove padding" and not "compact":** each block has its own Figma padding, so a "compact" preset would mean something different in every block. Removing top/bottom padding means the same everywhere; it's for joining two sections with the same scheme.
- **Not added:** spacing on hero (its top padding clears the fixed header) and featureRows (padding on inner wrappers). No scheme on imageGallery, cityList or testimonials, because their designs are light-only and their inner elements don't switch.
- **Checked (Playwright, 1440):**
  - Existing sections compute the same colours and padding as before. Hero dark = #000, featureRows dark = #111.
  - Switching the attributes on a live section: page background = transparent, white = #fff, soft = #f7f7f7, dark = #000 with white text; noTop, noBottom and none remove the right padding.
  - Other-environment path (restore the backup, then `craft up`): no project-config diff, values kept.

### 2026-11-20: Page tabs + the banner as a fixed part of every page (as in Site7 Engine)
- **Rule first** (the user's new working order): CLAUDE.md › Workflow "every request" (record the rule, then build, then the next step) and the skill's "Page entry types" section were written before the code. Also saved as a memory.
- **Migration** `m261120_100000_page_tabs` (backup: `/tmp/before-page-tabs.sql.gz`):
  - **home, landingPage, careers, press** → tabs **Page** (title, new `pageBackground`) · **Banner** (heroLayout, scheme, eyebrow, heading, rotatingWords, text, primaryButton, secondaryButton, images, chatMessages) · **Content** (pageBuilder) · **SEO** (seo).
  - **blogPost** → **Post** · **Content** (body) · **SEO**.
  - **Home's hero block** (the only hero) was copied into Home's Banner fields. Then the `hero` block type was removed from pageBuilder and deleted.
  - **Home `pageBackground` = surface.** This replaces the hard-coded `section.handle == 'home'` check in page.twig.
- **Templates:**
  - `_blocks/hero.twig` → `_partials/banner.twig` (`git mv`): it receives `page`, and its heading is always `<h1>`.
  - `_entries/page.twig` renders the banner when it has a heading or images. Every block gets `headingLevel: 2`. Without a banner heading, it outputs `<h1 class="sr-only">{{ entry.title }}</h1>`.
- **Gotchas:**
  - A migration method named `uid()` clashes with `craft\db\Migration::uid()` (PHP fatal error). It's called `stableUid()`.
  - Existing layout elements are moved between tabs, never recreated: their UIDs key the stored content (SEO values, for example).
  - New tabs and elements get deterministic UIDs (an md5 of a key), so every environment builds the same layout.
  - **Other-environment order:** `craft up` applies the project config *before* content migrations, which deletes the `hero` type and trashes its block first. The migration then falls back to the trashed block's raw `elements_sites.content` (keyed by the old hero layout's element UIDs) plus its `relations` rows for the images.
- **Checked:**
  - Home looks the same at 1440: one `<h1>` ("Parties are back"), blocks at h2/h3, chat bubbles and both buttons.
  - Careers, Press and Organizers return 200 with a hidden `<h1>`.
  - Both orders tested: migration first (local), and restore backup + `craft up` (project config first, fallback path). No project-config diff.

### 2026-11-20: Shopify-style step 2: Live Preview check and fixes
- **Rule first:** skill › "Live Preview" (how Craft refreshes the preview, preview targets, how to test it without logging in); CLAUDE.md › Live Preview of settings.
- **How Craft 5 refreshes** (`vendor/craftcms/cms/src/web/assets/cp/dist/cp.js`): every change loads the page in a **new iframe** with `?x-craft-live-preview=…&token=…`, then restores the scroll position on `load` with `contentWindow.scrollTo()`. `useIframeResizer` is off (the default).
- **Tested without a CP login:**
  1. Created a preview token with a throwaway script in `storage/runtime/` (deleted afterwards).
  2. Loaded Home in a same-origin iframe at 720px (the CP preview pane) and 1280px, and restored the scroll position the way Craft does.
- **Results:**
  - The scroll position holds (2500 stays 2500), and wheel scrolling continues from there with Lenis.
  - Reveal animations are visible after the restore.
  - The sticky featureRows slider restored mid-way shows one clean step ("Collect info upfront").
  - No console errors and no sideways scroll.
  - **No fixes were needed in the front end.**
- **Added:** a preview target for the `siteSettings` Single → Home (`/`). Migration `m261120_110000_settings_preview_target`.
  - Craft swaps the previewed element into ordinary queries (`PreviewController` → `Elements::setPlaceholderElement`), so base.twig's `_globals` query returns the unsaved draft.
  - Proved it with a draft that changes the header button label: the preview shows "LP draft label" while the live site shows "Contact Us". The test drafts were deleted.
- **Later:** a `newsletter` preview target → `blog`, once the blog listing exists.
- **Gotchas:**
  - `Tokens::createPreviewToken($route, $usageLimit, $token)`: the 2nd argument is a usage limit, not an expiry date.
  - Don't redefine `CRAFT_BASE_PATH` in a script that requires `bootstrap.php`.
  - Read the URL from a plain `echo`, not from escaped JSON.

### 2026-11-20: Common Matrix fields (buttons, socialLinks, contactDetails) + shared base fields
- **Rule first:**
  - CLAUDE.md › "Common Matrix fields" and "Shared base fields".
  - Skill › "Common Matrix fields and shared fields": how to render buttons, how to merge two fields, how to move values between fields.
  - **User's decision:** `buttonStyle` = Primary / Secondary (automatic per scheme) / Dark / Light (fixed).
- **Migration** `m261120_120000_common_matrix_fields` (backup: `/tmp/before-common-matrix.sql.gz`):
  - **Shared base fields** (Craft's own `fields/merge` steps, non-interactive; see `vendor/.../console/controllers/FieldsController::updateFieldUsages`):
    - Each layout element is pointed at the shared field, keeping its UID (content is keyed by it), its old handle and its label. Then the outgoing field is deleted.
    - `eyebrow` → "Short text": personName, personDetail, location, publication.
    - `comparisonUs` → "Comparison list": comparisonThem.
    - `appStoreLink` → "Store link": googlePlayLink.
  - **New fields:**
    - `buttons` (entry type `button`: `buttonLink` = an instance of primaryButton, `buttonStyle`, `iconName` = an instance of the Short text field; max 3).
    - `socialLinks` (entry type `socialLink`: `platform` + `externalLink`).
    - `contactDetails` (entry type `contactDetail`: `contactType` + `detail`).
  - **`buttons` replaces the button Link fields** on home, landingPage, careers, press, cardGrid, ctaBanner (its old secondary button), imageGallery, jobList and siteSettings (header).
    - 9 values moved, read from each owner's raw content at the old layout element UIDs, so it also works when the project config has already been applied.
    - Single links stay Link fields, relabelled: ctaBanner "Heading link", card "Link", promoBar "Link".
  - **siteSettings.socialLinks** was filled from the footerSocials menu (5 links). **The menu itself is kept but no longer rendered.** Delete it in the CP when you no longer need it as a backup.
- **Templates:**
  - New: `_components/buttons.twig` (group; the block passes `variants` for primary/secondary; dark/light resolved there), `_components/social-links.twig` (icon chosen by platform), `_components/contact-details.twig` (mailto:, tel:, wa.me; not placed yet, it's for the contact page).
  - `button.twig` takes an optional `icon`.
  - Updated: banner, cardGrid (both layouts), ctaBanner, imageGallery, header (desktop: all buttons; mobile: the first beside the menu button, the rest in the panel), footer ("Socials" column from socialLinks).
  - base.twig eager-loads `buttons`, `socialLinks` and `contactDetails` with siteSettings.
- **Bug found while checking, fixed:**
  - **Footer store badges were squeezed to 121px** (the text wrapped onto 4 lines). Five 180px columns + 48px gaps (1092px) didn't fit beside them: the columns overflowed at 1024/1280, and at 1440 only 121px was left. This came from the old layout, not this change.
  - **Now:** side by side from xl, with columns at natural width spread evenly (`xl:flex-1 justify-between`) and badges `shrink-0` + `whitespace-nowrap`. Below xl the badges sit under the columns (store-links `responsive` now stacks from xl).
  - **Measured:** badges 160×48 / 155×48 at 375–1440, no horizontal overflow.
- **Gotchas:**
  - `link` is a reserved handle (`Field::RESERVED_HANDLES`), hence `buttonLink`.
  - `LinkData.label` falls back to the URL host. Use `getLabel(true)` for "only the label the editor typed".
  - Button labels: trim whitespace before `</a>` (`{{ … -}}`).
- **Checked:**
  - Home: every button with the same colours and links as before ("Contact Us" header, banner "Create event" / "Get the app", "Create for free", "For organizers").
  - Merged fields still render (reviews "Quiviler / Sept 3", press names).
  - Socials column with icons, at 1440 and 375.
  - Both orders: migration first, and restore + `craft up` (project config first). Both give 9 buttons and 5 social links, with no project-config diff.

### 2026-11-20: Base field library (create generic fields once, reuse with per-instance handles)
- **User's rule** (as in the old sites, but with Craft 5 instance overrides): create generic fields once (Plain text, Textarea, Rich text…) and add them to sections and blocks many times, each time with its own handle and label.
- **Rule first:** CLAUDE.md › "Base field library" (the table of base fields + the purpose-specific list) and "Adding a field to a section or block"; skill step 3 updated.
- **Migration** `m261120_130000_base_field_library` (backup: `/tmp/before-base-fields.sql.gz`):
  - **Renamed into base fields.** Every existing instance first got its current handle and label as an explicit override, so templates and content didn't change:
    - eyebrow → `plainText`, text → `textarea`, body → `richText`;
    - primaryButton → `linkWithLabel`, externalLink → `urlLink`, featured → `lightswitch`.
  - **Merged** (Craft's fields/merge steps): heading → plainText (the 255-character limit goes), appStoreLink → urlLink, showAppBadges → lightswitch.
  - **Deleted:** `secondaryButton` (unused since the buttons Matrix).
  - **New base fields:** `number`, `date`, `email`.
  - **Layouts outside entry types are included:** the `images` volume's "Subtitle" (handle `text`) now uses `textarea`. This uses `Fields::findFieldUsages()` + the project-config update copied from `FieldsController::updateFieldUsages()`.
- **Kept purpose-specific:** Dropdowns (options), Tables (columns), Matrix fields, Entries fields, `emoji` (8-character limit), `rating` (0–5), `seo`, `newsletterForm`.
- **Checked:**
  - HTML snapshot of 8 pages (Home, the CTA demo, 4 landing pages, Careers, Press) before and after: **byte-for-byte identical**.
  - Same result for the other-environment path (restore + `craft up`, project config applied first).
  - `project-config/diff`: no changes.
- **Testing tip:** for refactors that must not change output, snapshot the pages (`curl` → file, strip CSRF tokens) before and after, then `cmp`.

### 2026-11-20: Two Lightswitch base fields (default off / default on)
- **User's request.** Rule first (CLAUDE.md table, skill field list).
- **Migration** `m261120_140000_lightswitch_on_off` (backup: `/tmp/before-lightswitch.sql.gz`): `lightswitch` → `lightswitchOff` "Lightswitch (default off)" (its instances featured / showAppBadges keep their handles), plus a new `lightswitchOn` "Lightswitch (default on)".
- **Checked:** 8-page HTML snapshot identical; no project-config diff.
- **Note:** the previous push (`1577afc`) failed with "/dev/tty: No such device or address" (git asked for credentials). It's pushed together with this commit.

### 2026-11-20: Two CKEditor base fields (Rich text / Rich text (full))
- **User's request.** Rule first:
  - CLAUDE.md table with two rows;
  - a new rule: "CKEditor toolbars never include font size, font colour or alignment" (inline styles);
  - the skill's field list.
- **Migration** `m261120_150000_rich_text_simple_full` (backup: `/tmp/before-richtext.sql.gz`):
  - **`richText` → `richTextFull` "Rich text (full)":**
    - Toolbar: heading | bold italic underline strikethrough | link | bulleted/numbered list, outdent, indent | blockQuote, insertTable, insertImage, mediaEmbed, horizontalLine | removeFormat, fullscreen, sourceEditing (admins).
    - Headings limited to **h2–h4**, because the page title is the `<h1>`.
    - Its instances (blog `body`, and the richText / faq blocks' body) keep their handles and content.
  - **New `richText` "Rich text":** bold, italic, link | lists | removeFormat; no headings, no images, no source editing. For short formatted text in blocks.
  - Toolbar button names come from `vendor/craftcms/ckeditor/src/helpers/CkeditorConfig.php`.
- **Checked:** 8-page snapshot identical; no project-config diff.

### 2026-11-20: More base fields: Image/Video, Video, Color Code, Simple RP Menu, Money
- **User's requests.** Rule first (CLAUDE.md table + the colorcode rule; skill field list).
- **Migration** `m261120_160000_assets_and_color_fields` (backup: `/tmp/before-assets-color.sql.gz`):
  - `images` restricted to images (it allowed image, video and JSON; all 380 existing relations were images).
  - New `imageVideo` "Image/Video" (1 image or video) and `video` "Video" (1 video), with the same volume settings as `image`.
  - New `colorcode` "Color Code":
    - Color field with **palette = the 16 `@theme` colour tokens** (label = token name), custom colours off.
    - Templates map the label to a full class string and never output the hex.
    - Keep the palette in sync when tokens change.
  - New `simpleRpMenu` "Simple RP Menu" (the plugin's RpMenuField; value = a menu handle; read `vendor/remoteprogrammer/simple-rp-menu/src/fields/RpMenuField.php`).
- **Migration** `m261120_170000_money_field`: `money` "Money" (USD).
- **Not created:**
  - **Multi-select:** options belong to the field, so it's purpose-specific like Dropdown. Created with its options when a block needs it.
  - **Icon:** Craft's Icon field uses Font Awesome, but the site uses Tabler (`_components/icon.twig`). Waiting for the user's decision.
- **Checked:** 8-page snapshot identical; no project-config diff.

### 2026-11-20: One Plain Text field only
- **User's rule:** keep a single Plain Text field and use it everywhere. Rule first: CLAUDE.md table (+ "exactly one field" rule) and the skill's field list.
- **Migration** `m261120_180000_single_plain_text` (backup: `/tmp/before-single-plaintext.sql.gz`):
  - `plainText` now allows multi-line text, starts at 1 row (it grows), and has no character limit.
  - `textarea` (text and the volume's "Subtitle") and `emoji` were merged into it with Craft's fields/merge steps; their handles, labels and content are kept.
  - **Trade-off:** emoji's 8-character limit and the separate tall textarea are gone, because those settings can't be per instance.
- **Checked:** only one PlainText field in config/project. The 8-page snapshot is identical on both paths (migration first; restore + `craft up` with the project config first). No project-config diff.

### 2026-11-20: Shopify-style step 3: entry type icons and colours + `/blocks` library
- **Icon decision (user):** the site uses Tabler only. An icon input is a `plainText` instance holding a Tabler name; Craft's Icon field (Font Awesome) isn't used.
- **Rule first:** CLAUDE.md › Icons, Entry type icons and colours (table), Block library page; skill step 3 (icon + colour for new entry types) and step 5 (check `/blocks`).
- **Migration** `m261120_190000_entry_type_icons` (backup: `/tmp/before-icons.sql.gz`): 32 entry types got a CP icon (Craft's icon set, `vendor/craftcms/cms/src/icons/solid`) and a colour by kind:
  - fuchsia = CTA, blue = text, emerald = cards/lists, amber = media, violet = social proof;
  - gray = items inside blocks; no colour = pages and settings.
- **`templates/blocks.twig` → `/blocks`:**
  - Every pageBuilder block in use, grouped by type, one example per layout × scheme, with a link to its page. Then all page banners, plus "No template yet" / "Not used on any page yet" badges.
  - `?scheme=light|white|soft|dark` re-renders scheme-aware blocks via `setFieldValue` (in memory only; Twig's sandbox is off outside `{% sandbox %}`).
  - Login required except in devMode (so it can be checked locally); `noindex` via a new `headExtra` block in base.twig.
  - banner.twig takes an optional `headingLevel` (library = 3), so the page has one `<h1>`.
- **Gotcha:** don't `.with(['images'])` on entries passed to banner.twig. It calls `page.images.eagerly()`, which needs a query, not a collection (500: "ElementCollection::eagerly does not exist").
- **Checked:** `/blocks`, `?scheme=dark` and `?scheme=soft` return 200. One h1, an h2 per type, blocks at h3. 13 examples; 8 types correctly flagged "No template yet". No horizontal overflow at 1440.

### 2026-11-20: Only Home is a page Single; Pages structure; system and settings Singles
- **User's rules** (as in Site7 Engine):
  - Only Home is a page Single; every other page is a standard page built with the page builder.
  - Page not found / Page error / Page maintenance are Singles.
  - Site settings, Google Structured Data and LLMs Text are Singles.
- **Rule first:** CLAUDE.md content-model table + "Only Home is a page Single"; icon table; skill "Page entry types".
- **Migration** `m261120_200000_pages_structure` (backup: `/tmp/before-pages.sql.gz`):
  - **Pages:** `landingPages` → `pages` "Pages" (Structure, nested pages allowed, URI `{parent ? parent.uri ~ '/'}{slug}`); `landingPage` → `page` "Page".
  - **Careers (#4) and Press (#6) moved into `pages` keeping their IDs** (menus link to them by entry ID), their URIs and field values. Their drafts/revisions were removed (they were of the old types), then the two Singles and entry types deleted.
    - **Fallback:** if the project config already deleted the Singles, the trashed entries are restored into `pages` (raw `entries`/`elements` update, then appended to the structure).
  - **System Singles** (no URLs, `page` entry type, starter content filled whenever the heading is empty): `pageNotFound`, `pageError`, `pageMaintenance`.
  - **Settings Singles:**
    - `googleStructureData` (`structuredData` = a plainText instance; preview target `/`);
    - `llmsText` (`llmsContent` = a plainText instance; preview target `llms.txt`).
- **Templates:**
  - `404.twig`, `500.twig`, `503.twig` → `_partials/system-page.twig` (renders the Single through `_entries/page.twig`; `|t` fallback copy if it's missing).
  - `llms.txt.twig` (text/plain; 404 when empty).
  - base.twig prints the structured data as JSON-LD: `json_decode` then `json_encode`, so invalid JSON is skipped and `</script>` comes out as `<\/script>` (tested).
  - `/blocks` reads `home` + `pages`.
  - **Banner fix:** rotating words are filtered before the length check, so an empty table no longer prints an empty Alpine span.
- **Gotchas:**
  - In devMode, Craft shows its exception page for 404/500 (`ErrorHandler::showExceptionDetails`). Test the system pages at `/404`, `/500`, `/503` directly.
  - `moveEntryToSection()` needs the entry's type to be allowed in the target section. For a type change plus a move, update `entries.sectionId` / `typeId` and copy the field values.
- **Checked:**
  - `/`, `/careers`, `/press`, `/organizers`, `/blocks`, `/404`, `/500`, `/503` → 200; `/llms.txt` → 404 when empty, 200 text/plain with content.
  - The 8 page snapshots are unchanged apart from the rebuilt asset hash and whitespace.
  - Both orders tested (migration first; restore + `craft up` with the project config first): IDs 4/6 in `pages`, menu links intact, system pages with starter content, no project-config diff.

### 2026-11-20: Pages is a Channel (not a Structure)
- **User's choice** (as Site7 Engine's standard pages). Rule first: CLAUDE.md table + "Only Home is a page Single", skill, build plan.
- **Why it had been a Structure:** "Landing pages" was created as a Structure in the first content model (`m261118_120000`). I had also recommended a Structure earlier (nesting, drag ordering, breadcrumbs), so I only renamed it when the pages were merged, instead of following Site7's Channel. The user wants the Site7 approach.
- **Migration** `m261120_210000_pages_channel` (backup: `/tmp/before-pages-channel.sql.gz`): `pages` → Channel, URI `{slug}`. Entry IDs and URLs are unchanged; there are no nested pages. Navigation order comes from the menus.
- `m261120_200000_pages_structure` now only appends to the structure when `pages` still is one (on another environment the project config makes it a Channel first).
- **Checked:** all pages 200 (/, /careers, /press, 4 landing pages, the CTA demo, /blocks, /404). Both orders tested (restore `/tmp/before-pages.sql.gz` + `craft up` runs both migrations): IDs 4/6/10 in the `pages` Channel, no project-config diff.

### 2026-11-20: Craft 5.9 features: CP entry index pages + grouped "Add block" menu
- **Discussed first, then approved by the user.** Rule first: CLAUDE.md › "CP sidebar: entry index pages" and "Page builder 'Add block' menu groups"; skill step 3 (a new block gets a group, a new section or Single gets a sidebar page).
- **Also (user request): "Pages = Channel" made hard to get wrong.** It's in the skill's "Never" list (no page Singles, `pages` isn't a Structure, no section per page, the list of allowed Singles) and saved to memory.
- **Migration** `m261120_220000_cp_pages_and_block_groups` (backup: `/tmp/before-cp-pages.sql.gz`):
  - **CP entry pages:** Pages (file-lines: All entries, Home, Pages), Blog (rss), Careers (briefcase: Jobs, Departments), Press (newspaper: Press mentions), Settings (gear: all settings and system Singles).
    - Saved with `ElementSources::saveSources()` (each source with a `page`) + `savePageSettings()` (icons) → `elementSources` / `elementSourcesPages` in project.yaml.
    - Singles use the Expanded Singles plugin's source keys `single:<section uid>`.
    - Craft matches configs to the sources registered at runtime **by key**, so this works although the plugin only registers its sources for a logged-in user (none in a console migration). A source without config lands on the first page.
  - **pageBuilder groups:** Call to action · Text · Cards & lists · Media · Social proof. Set with `setEntryTypes([['uid' => …, 'group' => …]])`; stored per field (`EntryType::getUsageConfig()`).
- **Checked:** no project-config diff; the 8-page snapshot is identical to the previous one.
- **Not checked here:** the CP itself (I have no login). The user confirms the sidebar pages and the grouped Add menu in the CP.

### 2026-11-20: CP sidebar: rule is a principle, not a fixed table; "Settings" → "Site setup"
- **User's feedback:**
  - The sidebar pages won't be the same for every site, and page names change when pages are added, so the skill must describe how to choose pages, not a fixed list.
  - There were two "Settings" items (ours + Craft's own).
- **Rule first:** CLAUDE.md › "CP sidebar: entry index pages" is now a principle (Pages first; one page per content area; "Site setup" last; never a name used by a Craft CP item; how to add a new section/Single; how it's stored), with the current layout given only as an example. Skill step 3 updated.
- **Migration** `m261120_230000_cp_page_site_setup`: page "Settings" → **"Site setup"** (icon `sliders`). It reads the stored source list, renames the page, re-saves with `saveSources()`, and updates `savePageSettings()`.
- **Checked:** 8 sources on "Site setup", none left on "Settings"; no project-config diff. The CP itself is for the user to confirm (no login here).
- **Note:** two pushes timed out (`git push` exit 124); they are pushed with this commit.

### 2026-11-20: "Site setup" → "Site Setup"
- The user's wording. Rule text updated first (CLAUDE.md, skill).
- **Migration** `m261120_231000_cp_page_site_setup_case`: renames the page (from "Settings" or "Site setup") in the stored source list and the page settings (icon `sliders`).
- **Checked:** 8 sources on 'Site Setup'; no project-config diff.

### 2026-11-20: Shopify-style step 4: reusable sections
- **Rule first:** CLAUDE.md › "Reusable sections" + content-model row + colour table (cyan = reusable) + "Add block" group "Reusable" + the CP sidebar's current layout; skill step 3 ("a section repeated on several pages is a reusable section, never a copied block").
- **Migration** `m261120_240000_reusable_sections` (backup: `/tmp/before-reusable.sql.gz`):
  - **Channel `reusableSections`** (no URLs), entry type `reusableContent` "Reusable section" (title + `pageBuilder`; icon recycle, cyan).
  - **Field `reusableEntry`** (Entries: that section only, max 1) and **block `reusableSection`** (icon clone, cyan) in pageBuilder, group "Reusable". The other groups are kept: `$t->group` is read from the field's entry types.
  - **CP sidebar:** the section goes after `pages` on the "Pages" page.
  - **First use:** Home's "Get the app" CTA banner block was **moved** (`elements_owners.ownerId` + `entries.primaryOwnerId`, so it keeps its ID, content, images and buttons) into the new reusable entry "Get the app". A `reusableSection` block now sits in its place on Home (same sortOrder).
- **Template** `_blocks/reusableSection.twig`: renders the entry's pageBuilder blocks with the page's heading level; skips a nested reusableSection, so there are no loops.
- **Checked:**
  - Home's HTML is identical (ignoring whitespace) before and after: the CTA renders exactly the same through the reusable section.
  - The other 7 pages are byte-identical.
  - `/blocks` lists "Reusable section".
  - Both orders tested (restore + `craft up` with the project config first): same result, no project-config diff.
- **For editors:** Entries › Pages › Reusable sections → edit "Get the app" once. To show it on another page: Add block › Reusable › Reusable section → choose it.

### 2026-11-20: Shopify-style step 5: page presets
- **Rule first:** CLAUDE.md › "Page presets" + content-model row + `modules/` in the front-end layout list + Page tab (`pagePreset`) + colour table + CP sidebar's current layout; skill step 3.
- **Module (new):**
  - `modules/Module.php` (registered in `config/app.php` `modules` + `bootstrap`; autoload `"modules\\": "modules/"` in composer.json, then `ddev composer dump-autoload -o`).
  - `modules/PagePresets.php`: on `Elements::EVENT_AFTER_SAVE_ELEMENT`, for a `pages` entry saved **not as a draft/revision** with a `pagePreset` and an empty pageBuilder, each preset block is copied with `Elements::duplicateElement(['ownerId','primaryOwnerId','siteId','sortOrder'])`. Nested items are copied too (NestedElementManager duplicates them because the copy has `duplicateOf`). A static guard prevents re-entry.
  - **Drafts are skipped on purpose:** the CP's draft autosave posts the form's pageBuilder value, which would delete server-added blocks.
- **Migration** `m261120_250000_page_presets` (backup: `/tmp/before-presets.sql.gz`):
  - Channel `pagePresets` (no URLs), entry type `pagePreset` (title + pageBuilder; object-group, cyan).
  - Field `pagePreset` (Entries, max 1) on `page` › Page tab after the title.
  - CP sidebar: after Reusable sections.
  - Starter preset **"Landing page"**: a card grid ("Section heading", 3 placeholder cards) + the "Get the app" reusable section.
- **Checked** (throwaway script in `storage/runtime/`, deleted):
  1. A new page saved with the preset gets cardGrid (3 cards) + reusableSection → Get the app, and renders them.
  2. Saving it again doesn't copy twice.
  3. An unpublished draft gets nothing.
  - The test pages are deleted (`/preset-test` → 404).
  - The 8-page snapshot is byte-identical.
  - Other-environment path (restore + `craft up` with the project config first) creates the preset; no project-config diff.
- **For editors:** Entries › Pages › Page presets: build or change presets there. New page → Page tab → "Page preset" → choose one → **Create**: the blocks appear in Content, ready to edit. Not checked in the CP here (no login).

### 2026-11-20: Bug fix: "Buttons should contain at most 3 entries" when saving a page
- **Reported by the user:** every page save showed the error.
- **Cause (my bug):**
  - `m261120_120000_common_matrix_fields` looped over all cardGrid / ctaBanner… blocks with `draftId = null`. But **nested entries of a draft aren't drafts themselves**: Home had a provisional draft (#247, the user's unsaved edits from before that migration), and its block copies (#249, #252) were included.
  - So the draft blocks got new buttons (#260, #261, #262) on top of the buttons the draft shares with the live page (#257, #258).
  - A later CP autosave added Craft's own draft copy (#301). Block #252 ended up with 4 buttons (max 3), and Home's draft failed validation.
- **Rule first:** skill › "Nested entries of drafts are not drafts themselves" (how Craft shares nested items with drafts; skip blocks whose primary owner is a draft/revision; the SQL check).
- **Fix:**
  - `m261120_120000` now skips blocks whose primary owner is a draft or revision (for fresh environments).
  - **New** `m261120_260000_fix_draft_buttons` (backup: `/tmp/before-fix-buttons.sql.gz`) deletes `button` entries owned by a draft-owned block that aren't derived from a live button (`canonicalId` null): #260, #261, #262. Craft's own draft copy #301 (the editor's label change) and the shared buttons are kept.
- **Checked:**
  - Every owner has ≤ 2 buttons (block #252 = shared #258 + draft copy #301).
  - Every page, Single, preset and **Home's draft #247** passes `validate()` in the live scenario (what a CP save runs).
  - The 8-page snapshot is identical.
- **Note:** Home still has the user's provisional draft #247 (unsaved edits). Publish or discard it in the CP.

### 2026-11-20: Block: promoBar (Figma 17-1076, Birthday Invitations)
- **The user's decision on reusable sections: keep them** (recorded in CLAUDE.md).
- **Rule/token first:**
  - `--color-promo: #3c0a7a` in `@theme`; the Color Code palette was synced (the palette = tokens rule).
  - CLAUDE.md › pageBuilder blocks › promoBar (fields and behaviour).
- **Migration** `m261120_270000_promo_bar` (backup: `/tmp/before-promo.sql.gz`):
  - promoBar gets `emoji` (a plainText instance, first);
  - colorcode palette + `promo`;
  - test content: a promo bar on Birthday Invitations ("🎈 Get $400 to throw your birthday party", link → Invitation Maker).
- **Template** `_blocks/promoBar.twig`:
  - `<aside aria-label="Promotion">`; the whole bar is the link when set (`group-hover:underline`, a `focus-visible` outline in `surface`), otherwise a `<div>`.
  - Emojis are `aria-hidden`. Nothing renders without text.
  - `text-sm` matches Figma exactly (14/16.8, −0.04em).
- **Checked (Playwright via `/blocks`) at 375 / 768 / 1440:** 36px tall, bg rgb(60,10,122), text 14px/16.8px 700 −0.56px rgb(247,247,247), gap 10px, no overflow; focus ring visible. Matches the Figma frame.

### 2026-11-20: Block: richText (Figma 17-1343, Birthday Invitations SEO text)
- **Rule/token first:**
  - `--container-text: 70rem` (1120) in `@theme`.
  - CLAUDE.md › pageBuilder blocks › richText: fields; the `.rich-text` rule (CKEditor content can't carry utilities, so prose styling always goes through `.rich-text`); heading levels inside the body.
- **CSS:** `.rich-text` component (`@apply` with tokens only): `text-xs` black/40; h2–h4 like subheads (`mt-5`, the first `mt-0`); 8px between a heading and its text; links ink with hover underline and a `focus-visible` outline; lists; strong black/60.
- **Migration** `m261120_280000_rich_text` (backup: `/tmp/before-richtext-block.sql.gz`): richText + `spacing`; test content = the Figma copy (3 h2 + paragraphs + a link) on Birthday Invitations after the promo bar.
- **Template** `_blocks/richText.twig`: optional block heading (`headingLevel`), body in `.rich-text`, `max-w-text`, `gap-15` to an `<hr class="border-black/30">`; nothing renders when both are empty.
- **Checked (Playwright via `/blocks`, full reload) at 375 / 768 / 1440:** column 328 / 673 / 1120; padding 40 (16px sides on a phone); text 12px/16.8px −0.24px black/40; heading→text 8px, text→next heading 20px; link rgb(17,17,17); hr 1px black/30, 60px below the text; no overflow. Matches the Figma frame.
- **Gotcha:** `page.goto()` to the same URL with only a different `#hash` doesn't reload (same-document navigation). Add a query string (`?t=…`) to force a fresh page.

### 2026-11-20: Block: comparison (Figma 17-2074, Invitation Maker)
- **Rule/tokens first:**
  - Tokens `--color-warning` #ffbf00 (sampled from the Figma icon PNG), `--color-aqua` #85f5ff, `--color-orchid` #f49eff, `--shadow-glow`, `--tracking-heading` −0.04em. Color Code palette synced.
  - ✓ = existing `success` (#31c431 from the SVG); ✕ #5f5f5f ≈ `muted`.
  - CLAUDE.md › pageBuilder blocks › comparison.
- **The Figma frame shows a third-party brand logo** (imported reference). Our column shows the block's `image`, else the site logo, else `siteName`.
- **Migration** `m261120_290000_comparison` (backup: `/tmp/before-comparison.sql.gz`):
  - `themLabel` (plainText instance) before comparisonThem, plus `spacing`;
  - labels: image "Logo (our column)", tables "Our column" / "Other column", body "Closing note";
  - palette + warning/aqua/orchid;
  - test content = the Figma copy on Invitation Maker.
- **Template** `_blocks/comparison.twig`:
  - Rows (Table: label + status) with Tabler **filled** icons (circle-check / alert-triangle / circle-x) and an `sr-only` status label.
  - Our column is a card: `bg-linear-to-b from-aqua/10 to-orchid/10 rounded-xl shadow-glow p-4`.
  - `max-w-187.5` (750) grid, 2 columns from `sm`; heading h2 + column heading h3.
- **Checked (Playwright) at 375 / 768 / 1440:**
  - heading 36/43.2 700 −1.44px (28px on a phone); subtitle 24/33.6 black/60;
  - grid 750, 2 columns, gap 24 (stacked on a phone); card radius 20 + glow + pad 16 + gradient;
  - rows 18/25.2 black/80 vs /60, icons 20px; row gap 24; note 24 italic black/60; no overflow.
  - Matches the Figma frame.
- **Gotchas:**
  - A Table field stores rows by column ID (`col1`, `col2`); the value also exposes the handles (`label`, `status`), so templates use handles.
  - Playwright's browser restarted without trusting the DDEV certificate (`ERR_CERT_AUTHORITY_INVALID`). Use `browser.newContext({ ignoreHTTPSErrors: true })` in `browser_run_code_unsafe`.

### 2026-11-20: Block: faq (Figma 17-2418, Invitation Maker)
- **Rule first:** CLAUDE.md › pageBuilder blocks › faq (fields, layout, accessibility, `.faq-panel`, `.rich-text--base`).
- **CSS (components layer):**
  - `.rich-text--base` (16px black/60, for readable body text; plain `.rich-text` stays the small SEO style).
  - `.faq-panel`: `grid-template-rows` 0fr → 1fr with `--ease-out-soft`, off for reduced motion. This avoids adding the Alpine collapse plugin.
- **Migration** `m261120_300000_faq` (backup: `/tmp/before-faq.sql.gz`): faq + `spacing`; test content = the 6 Figma questions on Invitation Maker, with placeholder answers (the frame shows them closed; editors replace them).
- **Template** `_blocks/faq.twig`:
  - The heading (h2) on the left; on the right, cards `rounded-lg bg-white shadow-soft ring-1 ring-inset ring-black/10` (Figma's border is inside the card, so it's a ring, not a border).
  - Each question is a `<button aria-expanded aria-controls>` in an h3; Tabler `plus` rotates 45° to ×.
  - The answer is `role=region aria-labelledby`; its content gets `:inert="!open"`, so closed answers aren't focusable.
  - Side by side from `lg`.
- **Checked (Playwright, 375 / 768 / 1440):**
  - Padding 100/60 (64/16 on a phone), bg white/30.
  - Heading 36/43.2 700 −1.44px; at 1440, 390px wide on 2 lines as in Figma.
  - List 500px, gap 4; cards 76px tall (exactly Figma); question 20/28 500 black/60, padding 24.
  - Click opens (`aria-expanded=true`, answer 16px black/60, + → ×); Enter closes; focus outline visible; no overflow.
- **Fixed during the check:**
  - `text-balance` changed the desktop line break ("Frequently / Asked Questions"): `lg:text-wrap`.
  - The border made the cards 78px instead of 76: inset ring.
  - At 768 the heading was squeezed to 166px: side by side only from `lg`.

### 2026-11-20: Where the database connection comes from (documented)
- **User's question:** "where did you link the DB? I can't see it in .env".
- **Answer, now in CLAUDE.md › Environment variables:**
  - DDEV (`type: craftcms`) injects `CRAFT_DB_*` and `PRIMARY_SITE_URL` into the web container. Craft 5 reads `CRAFT_DB_*` directly; the site `baseUrl` is `$PRIMARY_SITE_URL`.
  - So `.env` only has the app settings, and there is no `config/db.php`.
  - phpMyAdmin is at :8037.
- **Example files:**
  - `.env.example.dev` explains the DDEV-provided values (no secrets).
  - `.env.example.staging` / `.production` now also list `PRIMARY_SITE_URL=`. It was missing: a server set up from them would have had an empty site URL.

### 2026-11-20: Block: textMedia (Figma Careers 6-6646 right, 6-6862 left)
- **Rule/tokens first:**
  - `--text-title` (32 / 1.2 / −0.04em) and `--text-headline` (48 / 1 / −0.02em).
  - CLAUDE.md › pageBuilder blocks › textMedia.
  - CSS: `.rich-text--lead` (20px medium black/60, 20px between paragraphs) and `.glow-bg` (radial gradients of the aqua / orchid / glow-blue tokens, approximating Figma's blurred photos).
- **Migration** `m261120_310000_text_media` (backup: `/tmp/before-textmedia.sql.gz`):
  - textMedia + `headingAbove` and `glow` (lightswitchOff instances: the base-field rule) + `spacing`; body relabelled "Text".
  - Test content on Careers: "Our mission…" (right) and "Perks & Benefits" (left, heading above, glow). The copy is from Figma (brand-specific perks generalised).
- **Placeholder images** (to replace before launch): the two Figma images were downloaded into `web/uploads/images` (`careers-mission-collage.png`, `careers-team-photo.png`) and registered with `ddev craft index-assets/one images`. The migration links them by filename only when they exist.
- **Template** `_blocks/textMedia.twig`:
  - The image is cropped to the design's frame (536×351 right / 460×402 left, `ratio` prop) with `rounded-lg`.
  - Side by side from `lg` (media `lg:w-134` / `lg:w-115`, `order-first/last`), stacked below.
  - The column heading is `text-title`; with `headingAbove` it's centred `text-headline`.
- **Checked (Playwright) at 1440:**
  - Right: heading 32/38.4 700 −1.28px; image 536×351 r12; text 20/28 500 black/60, gap 20; image→text 40px.
  - Left: heading 48/48 500 −0.96px centred; image 460×402 on the left; glow on.
  - 768 / 375: stacked, no overflow.
- **Fixed:** `text-balance` changed the heading's line breaks: `lg:text-wrap`. The remaining one-word difference is the font (Figma TWK Lausanne vs the site's Inter).
- **Next on Careers:** the photo strip under 6-6646 = an imageGallery (marquee) block, added with the Careers page.

### 2026-11-20: Block: jobList (Figma Careers 6-6899, "Open Roles")
- **Rule/token first:** `--shadow-subtle` (the 3-layer card shadow); CLAUDE.md › pageBuilder blocks › jobList (data source, grouping, meta rule, link behaviour, empty state).
- **Migration** `m261120_320000_job_list` (backup: `/tmp/before-joblist.sql.gz`):
  - jobList + `glow` (lightswitchOff instance) + `spacing`.
  - Test data from the Figma frame: departments Design / Engineering / General / Sales / Support, and 10 jobs (generic titles, New York, full time, workplace as in the design; post dates spaced 1 minute apart so the design's order holds).
  - An "Open Roles" block on Careers (glow on) with a dark "See all roles" button.
  - **Placeholders:** job and button links → https://example.com/jobs. Replace them with the real job board.
- **Template** `_blocks/jobList.twig`:
  - Jobs (`.with(['department'])`, no N+1) grouped in the Departments structure order; jobs without a department under "Other"|t.
  - Card `rounded-lg bg-white p-6 shadow-subtle ring-1 ring-inset ring-black/5`, the whole card links (new tab from the field), Tabler `arrow-up-right` nudges on hover, focus outline.
  - Meta = department • location • employment type • workplace, with **On-site hidden** (the design only shows Hybrid).
  - Empty state: `|t` "No open roles right now." Buttons via `_components/buttons.twig`.
- **Checked (Playwright) at 375 / 768 / 1440:** heading 48/48 500 (36 on a phone); list 800; card r12, pad 24; title 20/28 500 black; meta 16/22.4 black/60; card gap 12; group gap 32; links `_blank`; no overflow. The meta lines match the design exactly.
- **Fixed:** a 2px title/meta gap made the cards 100px instead of 98.4 → removed.
- **Known difference:** the button is 54px tall vs 48 in Figma (the shared button size, kept consistent site-wide).

### 2026-11-20: Block: image (Figma Careers 6-7092)
- **Rule first:** CLAUDE.md › pageBuilder blocks › image (one image edge to edge at its own ratio, optional caption, no scheme or spacing).
- **No content-model change:** `image` + `text` (caption) already existed.
- **Placeholder image** (replace before launch): the Figma photo was downloaded as `web/uploads/images/careers-team-office.png` (2048×1365) and registered with `ddev craft index-assets/one images`.
- **Migration** `m261120_330000_image_block` (backup: `/tmp/before-image-block.sql.gz`): sets the asset's alt text from the Figma layer name and adds the block at the end of Careers.
- **Template** `_blocks/image.twig`: `<figure>` + `_components/image.twig` (width 1600, `sizes="100vw"`, never cropped) + an optional `<figcaption>`.
- **Checked (375 / 768 / 1440):** edge to edge (360 / 753 / 1425 wide), ratio 1.500 like Figma, alt present, no overflow.
- **Found while checking (fixed in the next commit):** `_components/image.twig` builds `srcset` with density descriptors (`0.25x…1x`) next to `sizes`, which browsers ignore. It also always transforms at `width × 2` (3200px from this 2048px original = upscaling, and slow first loads).

### 2026-11-20: Fix: image srcset used density descriptors with `sizes` + upscaled transforms
- **Found** while checking the image block. `_components/image.twig` called `getSrcset(['0.25x','0.5x','0.75x','1x'])` next to a `sizes` attribute, which browsers ignore with density descriptors, so they couldn't pick by layout width. It also always transformed at `width × 2`, even past the original's size (Craft upscales by default): a 3200px transform from a 2048px photo, slow to generate on first load.
- **Rule first:** skill › Images checklist: width descriptors via the component; never wider than the original.
- **Fix:**
  - The largest width = `min(width × 2, asset width)`, and for crops also `≤ asset height × ratio`.
  - `srcset` = width descriptors at 25/50/75/100% of that (steps under 160px skipped), e.g. 512w/1024w/1536w/2048w for the Careers photo.
- **Checked:**
  - /, /careers, /invitation-maker, /birthday-invitations, /cta-options-demo, /blocks, /404 all return 200 with 0 density descriptors and 0 Twig errors.
  - Home: 369 images, 0 broken, no console errors, no overflow; the city tiles and gallery look as before.

### 2026-11-20: Block: pressList (Figma Press 18-6161)
- **Rule first:** CLAUDE.md › pageBuilder blocks › pressList (list from the Press mentions channel, newest first; number, headline, publication — date, arrow; whole row links).
- **Migration** `m261120_340000_press_list` (backup: `/tmp/before-press-list.sql.gz`):
  - pressList gets `spacing`. `heading` stays optional: the Press page banner holds the h1, and Figma shows no heading above the list.
  - Test data: the 20 mentions from the Figma frame (only when the channel is empty), with post dates from the design, and a pressList block on Press.
  - **Placeholders:** the headlines and publications come from the imported reference design; links → https://example.com/press. Replace them with real coverage before launch.
- **Template** `_blocks/pressList.twig`:
  - `<ol>` of rows; the number (`%02d` of `loop.index`) is `aria-hidden`; headlines are h2 (h3 under a block heading).
  - The whole row is the link (the field sets the new tab); hover `bg-surface`, `rounded-xl`; focus outline; the arrow nudges on hover. A row without a link has no arrow.
  - Empty state: `|t` "No press mentions yet."
- **Sizes:** number 28 / 56 / 70 px and headline 20 / 28 / 36 px (phone / md / lg). Figma's number is 72px: the nearest token `text-display` (70) is used, with no new token for 2px.
- **Checked (Playwright) at 375 / 768 / 1440:** row 120px at 1440 (Figma 120); headline 36px, tracking −1.44px; meta 18px black/60; row gap 36; one h1; links `_blank`; no overflow; no console errors; hover and focus work.
- **Fixed while checking:** at 768 the 36px headline wrapped to three lines, so the sizes now step up at md and then lg.

### 2026-11-20: imageGallery › marquee: the Careers photo strip (Figma 6-6667)
- **Rule first:** CLAUDE.md › pageBuilder blocks › imageGallery › marquee, the spacing list, and the new `--radius-photo` token (10px).
- **Design:** Figma's strip is one row of 5 photos repeated, 220px tall, gap 10, radius 10. Sell Tickets (17-3649) uses the same marquee with portrait posters. So the marquee now shows **fixed-height tiles at each image's own ratio** (the old version cropped every image to a 224px square). Editors crop photos in Craft's image editor for a uniform strip.
- **Placeholder photos** (replace before launch): 5 images downloaded from Figma as `web/uploads/images/careers-strip-1…5.png`.
  - The two portrait originals were cropped to Figma's tile ratios with ImageMagick inside DDEV (`ddev exec "convert … -gravity center -crop 683x484+0+0 +repage …"`).
  - Then registered with `ddev craft index-assets/one images`.
  - Gotcha: Figma MCP asset URLs need the `.png` suffix to download (without it, curl returns HTTP 4xx, exit 22).
- **Migration** `m261120_350000_gallery_marquee` (backup: `/tmp/before-gallery-marquee.sql.gz`):
  - imageGallery gets `spacing` ("Not used by the Fan layout").
  - The photos get alt text; the strip goes on Careers **right after the first textMedia**, with spacing `noTop`.
  - To insert a nested entry mid-list, the migration shifts `elements_owners.sortOrder` (+1 from that position) for the page's blocks, then saves the new block at the freed position.
- **Template** `_blocks/imageGallery.twig`:
  - `data-spacing` on the non-fan `<section>`; the heading wrapper (and its `mt-10` gap) only renders when there's an eyebrow, heading or text.
  - Marquee tiles: `h-40 md:h-55`, `w-auto`, `rounded-photo`, `gap-2.5`; transform width = 220 × the image's ratio, `sizes` = that px.
  - Each half of the track (the CSS loop moves it by −50%) must be wider than any screen, so the set repeats until a half is ≥ 2400px. Only the first set is read out; the copies are `aria-hidden` with empty alt.
- **Checked (Playwright) at 375 / 768 / 1440 (+ 2560):** tiles 160px tall on phones and 220px from md (310×220 / 331 / 321 / 331 / 293 at 1440); radius 10; gap 10; 0 broken; alt only on the first set; half-track 2403px at 375 and 3269px at 2560 (no gap in the loop); no overflow; no console errors. `/blocks` returns 200 and shows the marquee.

### 2026-11-20: cardGrid › textCards: Careers "Our Values" (Figma 6-6770)
- **Rule first:** CLAUDE.md › pageBuilder blocks › cardGrid › textCards.
- **Design:** a centred 48px heading, 6 white cards (320 wide, p-30, r12, the existing `shadow-card` matches Figma exactly), each with emoji 48, title 22 semibold and text 16 black/60, 3 per row, gap 10; blurred photo blobs behind (approximated with `.glow-bg`).
- **Migration** `m261120_360000_card_grid_text_cards` (backup: `/tmp/before-text-cards.sql.gz`):
  - cardLayout gets the option `textCards` "Text cards (emoji, no image)".
  - cardGrid gets `glow` (lightswitchOff instance; "Text cards layout only").
  - "Our Values" with the 6 cards from Figma goes on Careers right after the photo strip (the same `elements_owners.sortOrder` shift as the marquee migration).
- **Templates:**
  - `_components/card.twig`: new variant `text` (emoji, title, text, an optional underlined link; no image panel). The image query and aspect only run for the other variants.
  - `_blocks/cardGrid.twig`: a `textCards` branch with a sentence-case heading group (eyebrow / heading / text, `gap-3`), the grid `sm:grid-cols-2 lg:grid-cols-3` in `max-w-narrow`, buttons below; scheme + spacing as before, glow only on light schemes.
- **Checked (Playwright) at 375 / 768 / 1440 + `/blocks?scheme=dark`:** heading 36 → 48; heading-to-cards gap 60 (40 on a phone); cards 327 wide at 1440 (Figma 320: `max-w-narrow` is 1000 vs 980), all 239 tall (Figma 236); pad 30; r12; emoji 48; text 16 black/60; no overflow; no console errors; all pages 200 with no Twig errors.
- **Fixed while checking:**
  - Content was vertically centred, so the titles sat at different heights. It's now top-aligned, and the titles line up.
  - Equal-height rows only from `sm` (on a phone they added empty space to the shorter cards).
- **Known difference:** the title is 24px (`text-2xl`) vs Figma 22; there's no 22px token.

### 2026-11-20: Banners: Careers (Figma 6-6336, fullBleed) + Press (Figma 18-6155, simple)
- **Rule first:** CLAUDE.md › Page tabs › banner › Layouts (fullBleed, simple), plus the `--text-banner` token (100px / 1.4 / −0.02em).
- **Design:**
  - Careers: a 1600×745 photo edge to edge, with "Join the party" (100px) and a 24px subtitle in white near the top, centred.
  - Press: a white band with a centred 48px "Press" (80px padding).
- **Placeholder photo** (replace before launch): `web/uploads/images/careers-banner.png` (2048×1270) downloaded from Figma, then `ddev craft index-assets/one images`.
- **Template** `_partials/banner.twig`:
  - **fullBleed:** `h-140 md:h-186` instead of `min-h-svh` (Figma's 745px; a full-screen banner hid all the content below). Text at the top (`pt-32 md:pt-40`); heading `text-5xl md:text-banner` medium; subtitle `text-xl md:text-2xl` medium, no gap from `md` (the 140px line height already spaces it, as in Figma).
    - The flat `bg-black/40` overlay became a top-only gradient (`from-black/40`, top two thirds), so the photo keeps Figma's colours and the white text stays readable.
    - Without an image, it uses normal padding and dark text.
  - **simple:** heading `md:text-headline` (48) instead of 56; bottom padding `md:pb-20` (80, as in Figma).
- **Migration** `m261120_370000_careers_press_banners` (backup: `/tmp/before-banners.sql.gz`), content only, and only when the banner heading is empty:
  - Careers: fullBleed, scheme dark, heading + text from Figma, the photo (alt text from the Figma layer name).
  - Press: simple, scheme white, "Press".
- **Checked (Playwright) at 375 / 768 / 1440:**
  - Careers: banner 560 / 744 / 744 tall; h1 100px/140 −2px 500 white at 160px from the top (Figma 162.8); image `fetchpriority=high` with alt.
  - Press: h1 48px 500, white band.
  - Both: exactly one h1, no overflow, no console errors. /, /careers, /press, /blocks, /invitation-maker, /birthday-invitations and /404 all return 200 with no Twig errors.

### 2026-11-20: Blog listing (Figma 18-4865)
- **Rule first:** CLAUDE.md › Blog listing (title, category filter, grid, card, author fallback, pagination), plus the `--text-card` token (26px / 1.2).
- **Design:** "the Guest List" wordmark (an image), a filter row (Latest / Party 101 / Culture / IMHO; 24px bold, active underlined), then 31 cards in 3 columns (347px, gap 40). Each card: square image, 26px title, 18px black/60 summary, author (40px round avatar + 18px bold name). No pagination in the design.
- **Decisions:**
  - The wordmark is the `newsletter` Single's `image` (the content model already calls it the "blog title image"); it goes inside the `<h1>`, with its alt as the heading text.
  - The filter is server-side (`?category=<slug>`, links + `aria-current`). The Blog categories have no URLs, and `blog/{slug}` belongs to posts, so a query string avoids a route clash.
  - Pagination is 24 per page (the design's 31 posts would make two pages), shown only when needed.
  - **Author fallback:** the user's full name, else `siteName`; the user's photo, else the site logo. The only user is `admin` with no name or photo, and the design shows the brand as author. No user was created or changed.
- **Placeholder content** (replace before launch):
  - The images were downloaded from Figma as `web/uploads/images/blog-post-1…6` + `blog-title.png` (300×100, soft when shown at 692px), then `ddev craft index-assets/one images`.
  - **Gotcha:** three Figma "png" assets were really JPEGs; I renamed them to `.jpg` before indexing (check with `file`).
- **Migration** `m261120_380000_blog_listing` (backup: `/tmp/before-blog-listing.sql.gz`), content only:
  - Categories Party 101 / Culture / IMHO.
  - The first 6 posts from Figma (summary in `text`, body = the summary, category, image with the title as alt, author = the first admin, post dates 2 days apart).
  - The wordmark set on `newsletter.image`.
- **Templates:** `blog/index.twig` (extends base, `bg-white`) and `_components/blog-card.twig` (the whole card links; the image scales on hover and the title underlines; focus outline).
- **Checked (Playwright) at 375 / 768 / 1440:**
  - Exactly one h1 ("The Guest List" from the alt); filter Latest*, Party 101, Culture, IMHO.
  - 1 / 2 / 3 columns; cards 347 wide at 1440 (Figma 346.7), square images; title 26/31.2 600; filter-to-grid gap 80 (40 on a phone).
  - `?category=party-101` → 2 posts with Party 101 current; an unknown slug → all 6. 0 broken images, no overflow, no console errors.
- **Not shown yet:** the author avatar, because the site logo (siteSettings `image`) isn't set; the logo export is still pending in the build plan. Next: the newsletter partial under the grid, then the post page.

### 2026-11-20: Newsletter partial (Figma 18-5576) + Wheel Form success-message fix
- **Rule first:** CLAUDE.md › Newsletter partial (layout, a no-JS Wheel Form flow, success / error states, Live Preview target, "every Wheel Form needs a success message"); the `--color-danger` token; skill gotchas (reset radius/colour scales, `form.getHoneypot()`).
- **Design:** "Get on the list." (42px) on the left, an embedded sign-up form (650 wide) on the right, a note "💡 Have an idea? Pitch us: …" below, and aqua/orchid blur blobs (→ `.glow-bg`).
- **Read first** (`vendor/xpertbot/craft-wheelform`, and the old site's `_components/newsletter-form.twig` for reference):
  - `wheelform.form({id, redirect, submitButton})` returns a FormService.
  - `open()` prints `<form>` + CSRF + `form_id` + `action` + a hashed `redirect`.
  - `close()` prints the honeypot (if configured and not printed yet), the submit button (`submitButton.html` wins) and `</form>`.
  - A non-AJAX submit: success → the flashes `wheelformSuccess` / `wheelformSubmittedForm` + a redirect; errors → the same page re-renders with `wheelformErrors` / `values`.
- **Template** `_partials/newsletter.twig` (included by `blog/index.twig`, which passes `wheelformErrors` / `values` through):
  - The `<form>` itself is the pill: sr-only label + email input + hidden honeypot + our `|t` button.
  - Success shows a `role="status"` box with the form's message; errors show a `role="alert"` line tied to the input (`aria-invalid`, `aria-describedby`), keeping the typed value.
  - Admins see a hint when no form is chosen.
- **Migrations:**
  - `m261120_390000_newsletter` (backup: `/tmp/before-newsletter.sql.gz`):
    - A Wheel Form "Newsletter" (email field required; honeypot `website`; saves entries; sends no email).
    - Newsletter Single content (heading, note, form); preview target Blog (`blog`); palette + `danger`.
  - `m261120_391000_wheelform_submit_messages`: **bug found while testing.** A successful submit gave a 500 (`SessionBehavior::setNotice(): null given`), because the plugin's Success Message setting was never saved and the forms had no `submit_message`. Every form without one now gets a default (Newsletter: "Watch your inbox for the next issue."; Contact Form: "Thanks! We’ll get back to you soon."). The 390000 insert now includes it too.
- **Checked (Playwright) at 375 / 768 / 1440:**
  - Heading 36 → 48 (Figma 42; no 42 token); form 328 / 625 / 650; stacked below `lg`, a row at 1440; no overflow; no console errors.
  - **Error path** (browser validation bypassed, "not-an-email"): page re-rendered, "Email address is not a valid email address.", `aria-invalid`, value kept.
  - **Success path:** 200, redirect to `#newsletter`, "Thanks, you’re on the list! Watch your inbox for the next issue.", form hidden; after a reload the form is back.
- **Fixed while checking:** `rounded-3xl` doesn't exist (the radius scale is reset to tokens), so the phone layout had square corners. It's `rounded-xl` now.
- **Note:** the test submissions (test@example.com, test3@example.com) are saved as Wheel Form entries locally; delete them in CP › Wheel Form if wanted.
- **Before launch (user):** fill in Wheel Form's plugin settings (From email, Success Message), and replace the placeholder pitch address in the note.

### 2026-11-20: Blog post page (Figma 18-5645 / article 18-5804)
- **Rule first:** CLAUDE.md › Blog post (header, hero, body styles, Next link); the `.rich-text--article` variant; skill: don't name a token after a Tailwind static utility.
- **Design:** a 700px centred column. Grey tag pills (Featured / Latest, 24px), the title (48 semibold), the summary (24, black/60), a 700×311 hero, the body (36 / 24 bold headings, 18px black/60 text, bullet lists, full-width images, underlined black links), then "Next: …" (24, centred, 600 wide) and the newsletter.
- **Template** `blog/_entry.twig` (was empty):
  - Tags = "Featured"|t (when `featured`) + the post's categories. "Latest" in the design isn't a category, so it isn't shown.
  - The title is the only h1. The hero uses `ratio: 700/311` and is eager.
  - The body goes through `.rich-text .rich-text--article`: CKEditor h2 → 36px, h3 → 24px (the title is the h1, so heading order stays correct).
  - "Next" = `entry.getNext({ section: 'blog' })` (the next older post, newest-first order), hidden on the oldest post. The newsletter partial goes below.
- **CSS:** `.rich-text--article` (spacing 40 before headings, 20 between text, 32 before lists and figures; links underlined; images full width; blockquote, hr, figcaption).
- **Fixed while checking:** the column was 654px, not 700. The token `--container-prose` lost to Tailwind's built-in `max-w-prose` (65ch). It's renamed `--container-article` / `max-w-article` (also used by cardGrid's textCards intro).
- **Placeholder content:** the 2 body images downloaded from Figma (`blog-cards-1.png`, `blog-cards-2.png`). Migration `m261120_400000_blog_post` (backup: `/tmp/before-blog-post.sql.gz`) gives "Introducing Cards on Quick Craft" the full article from Figma (images as `{asset:ID:url}` reference tags with alt text, links to /invitation-maker for now) and marks it Featured.
- **Checked (Playwright) at 375 / 768 / 1440:**
  - One h1 (48/48 600; 36 on a phone); tags Featured + Party 101 (24px #777772 on black/5); summary 24 black/60; hero 700×310 `fetchpriority=high`.
  - Body p 18/25.2 black/60, h2 36 bold, h3 24 bold; gaps h2→h3 40, before list 32, before figure 32; disc bullets; links black and underlined.
  - "Next: Parti Recap…"; the oldest post (Hot Literati) has no Next link. No overflow, no console errors; all pages 200 with no Twig errors.
- **Known gap:** images inside the CKEditor body are output at their original size (no transform, srcset or lazy loading). Option for later: set a default image transform on the `richTextFull` field, or post-process `<img>` in the template.

### 2026-11-20: Organizers page (Figma 17-267)
- **Rule first:** CLAUDE.md › Organizers page (banner collage, quotes, alternating rows, text cards with images, photo cards, "Get started" CTA, `plain` section heading). I also restored the `imageGallery › marquee` heading line that my textCards edit had swallowed earlier.
- **Design:**
  - **Banner (17-426):** "Quick Craft for Organizers" / "The best way to organize your *Writers Group*" (rotating, green gradient) / text / "Try it out", with 15 tilted event cards and avatars around it.
  - **Quotes (17-591):** a photo + club logo per quote. **Feature rows (17-640):** 4 rows, text 500 / image 660. **"Take the stress out of planning" (17-716):** 3 image cards + a button. **"Join thousands…" (17-782):** 3 photo cards. **"Get started for free":** 2 buttons.
- **Templates and CSS:**
  - `section-heading.twig` gets `plain: true` (sentence case, 48 medium; Home keeps uppercase).
  - `banner.twig` centered: the eyebrow / heading / text sizes from Figma; the rotating word in `from-success to-lime` gradient text; `glow-bg`. The old square image grid is replaced by `.banner-collage` (15 Figma slots in %, from `xl`; avatars cropped round; images decorative).
  - `testimonials.twig`: a dedicated quotes branch (photo `-rotate-10` round + logo `rotate-10` `rounded-lg`, overlapping; quote 24 semibold; name · detail 20 black/60).
  - `featureRows.twig` alternating: a 12-column grid (5/7), gap 40, rows 100 apart; title 36 bold; text 24 black/60 with blank-line paragraphs; image ratio 660/422; `glow`.
  - `card.twig`: the `text` variant shows the image on top (4:3; text 20px); a new `photo` variant (text over a bottom gradient, the whole card can link).
  - `cardGrid.twig`: `photoCards` shares the textCards branch; a textCards block with no cards (heading + text + buttons) now renders.
- **Migration** `m261120_410000_organizers` (backup: `/tmp/before-organizers.sql.gz`):
  - cardLayout + `photoCards`; testimonial + `logo` (an image instance); featureRows + `glow`; palette + `lime`.
  - The page banner and 5 blocks with the Figma copy and 31 placeholder images (`org-*`, downloaded from Figma, JPEGs saved as `.jpg`).
  - **Tested both orders:** after the first run, `ddev import-db` from the backup → `ddev craft up` (project config first, then the migration) → the same content, and `project-config/diff` shows no changes.
- **Checked (Playwright) at 375 / 768 / 1440:**
  - One h1, then h2 sections and h3 cards; collage shown at 1440 (15 items) and hidden below `xl`; no overflow; 0 broken images; no console errors.
  - Quote 24/600 (20 on a phone); row 389px tall at 1440; photo card 323×266 (Figma 340×280; the 1000px content width with gap 16).
  - Careers / Home / Blog / Press / `/blocks` (+ dark) all 200 with no Twig errors.
- **Fixed while checking:** "Join thousands" and "Get started" had no gap between them (`noBottom` + `noTop`); the photo cards keep their default padding now.
- **Note:** full-page screenshots show `data-reveal` sections as blank (they appear on scroll), so check sections one by one after scrolling them into view.
- **Placeholders:** all club names, quotes and photos come from the imported design; buttons → https://example.com/app.

### 2026-11-20: Birthday Invitations page (Figma 17-890)
- **Rule first:** CLAUDE.md › Birthday Invitations page:
  - Banner headings are uppercase only on Home; split banner on landing pages.
  - Gallery grid 3:2 + `note`; textCards image-card pill link; ctaBanner `simple`.
- **Design:**
  - Split banner (17-1049: "Fun birthday invitations", text, "Create free invite", a 580×330 image with radius 20).
  - Promo bar (built earlier).
  - "Endless design options" (17-1088): 18 captioned 3:2 tiles in 3 columns, then "Choose from thousands of options" + "Start creating".
  - "Get your birthday party started" (17-1271): 3 image cards with a "Try it ↗" pill.
  - Rich text (built earlier).
  - "Want to keep the party going? Get the app" (17-1368) with store badges.
- **Templates:**
  - `banner.twig`: `isHome` (entry type `home`) decides uppercase vs sentence case (Home keeps its split dark hero); a landing split gets `glow-bg` and a `max-w-145 rounded-xl` image.
  - `imageGallery.twig`: plain headings; grid tiles 3:2 with gap 20 × 52 (the row layout keeps squares); a `note` line above the buttons.
  - `card.twig`: a text card with an image shows its link as a dark pill with `arrow-up-right`.
  - `ctaBanner.twig`: a `simple` branch (plain section heading, buttons, `store-links`), no min height or images.
- **Migration** `m261120_420000_birthday` (backup: `/tmp/before-birthday.sql.gz`):
  - ctaLayout + `simple`; imageGallery + `note`.
  - The 18 tile assets get title = caption and alt text from the Figma layer names.
  - Banner content; the gallery + cards inserted after the promo bar (`elements_owners.sortOrder` +2 for the later blocks); the CTA at the end.
  - The 22 placeholder images (`bday-*`) were downloaded from Figma.
- **Checked (Playwright) at 375 / 1440:**
  - Order: banner → promo → gallery → cards → rich text → CTA. h1 48px sentence case (36 on a phone).
  - Tiles 320×213 (Figma 320×213); 18 captions; "Try it" pills; 2 store badges.
  - No overflow; 0 broken images. Card images 320×240; the first screenshot was taken while their transforms were still generating.
  - All pages 200 with no Twig errors; no uppercase h1 except on Home.
- **Known differences:**
  - Card text is 20px (Organizers' size) vs 16 here.
  - The banner's purple wash (Figma: transparent → violet 48%) uses the shared `glow-bg` instead.
  - Gallery tiles aren't links (Figma links each to its template; there's no per-image link field).

### 2026-11-20: Invitation Maker page (Figma 17-1440)
- **Rule first:** CLAUDE.md › Invitation Maker page:
  - Gallery row with a left heading; image-card text settled at `text-lg`; reviews redesign; ctaBanner `glow`.
- **Design and build:**
  - **Banner (17-1599):** split with the eyebrow pill "Free · No Ads · No App Required"; the heading as plain text (Figma's italic "invitation maker" can't be set in a plain text field); "Make a free invite →".
  - **"Find your vibe instantly" (17-1931):** imageGallery `row`. Eyebrow + heading + text left-aligned in `max-w-narrow`; 6 square tiles `rounded-xl` (147px at 1440, Figma 152), gap 24; captions 20 semibold; "View all →".
  - **"More than a maker" (17-2004):** textCards with images + "Try it" pills. The image-card text is now `text-lg` everywhere (Figma uses 20 / 16 / 18 on the three pages).
  - **Comparison:** built earlier, stays third.
  - **"People are obsessed" (17-2211):** testimonials `reviews` rebuilt — `bg-surface`, left plain heading with an accent eyebrow, white cards (title + stars left; date + name right in black/40; quote `text-sm`).
  - **Rich text (17-2385):** Figma's SEO copy (third-party tool names removed; "birthday invitations" links to /birthday-invitations).
  - **FAQ:** built earlier, sixth.
  - **CTA (17-2485):** ctaBanner `simple` + `glow` + "Create invite".
- **Migration** `m261120_430000_invitation_maker` (backup: `/tmp/before-invitation-maker.sql.gz`):
  - ctaBanner + `glow`.
  - Banner content; the existing comparison / FAQ moved to sortOrder 3 / 6 (`elements_owners`); 5 new blocks at 1, 2, 4, 5, 7.
  - Tile captions as asset titles, with alt text.
  - 10 placeholder images (`im-*`) downloaded from Figma.
- **Checked (Playwright) at 375 / 1440:**
  - Order: banner → gallery row → cards → comparison → reviews → rich text → FAQ → CTA.
  - Tiles 147 (152 on a phone) with radius 20; no overflow; 0 broken images; no console errors. All pages 200 with no Twig errors.
- **Fixed while checking:** row captions at 24px wrapped ("Kids’ Birthday" on two lines) → 20px.

### 2026-11-20: Sell Tickets page (Figma 17-2557, dark)
- **Rule first:** CLAUDE.md › Sell Tickets page:
  - `pageBackground` dark; left-header cardGrid layouts (`showcase` new; `carousel` and `bento` implemented for the first time).
  - imageGallery `scheme` + poster marquee; ctaBanner simple with an image.
- **Design and build:**
  - **Banner (17-2716):** a dark split; "Where Events Grow", text, white "Get started"; the ticket image (Figma's video poster) on the right.
  - **"Easy AF ticketing" (17-2788):** cardGrid `showcase`. Heading left 56 bold + button right; 3 cards (image ratio 450/532, `rounded-lg`; title 28 bold; text 20 at 80%).
  - **"Built for events that spread" (17-3649):** imageGallery marquee with a heading = poster strip. Left heading, tiles 300 tall, gap 24, `rounded-md`. The headingless Careers strip keeps 220 / 10 / `rounded-photo` (checked: Careers still renders `rounded-photo`).
  - **"Don’t process payments, fill the room" (17-3995):** cardGrid `bento`. Translucent panels; the first spans two rows with its collage full width; "Word of mouth" / "Search" have centred images. The panel images are Figma renders of those groups (`get_screenshot`), since the design builds them from live UI.
  - **"We’ve got you covered" (17-4296):** cardGrid `carousel`. The same showcase cards at 300px (image ratio 300/236) in `.scroll-row`, a CSS scroll-snap row, no JS, no scrollbar, focusable with an `aria-label`. It starts at the content's left edge (padding = max(gutter, (100% − site width) / 2 + gutter)) and runs to the screen edge.
  - **"See you at the door." (17-2992):** ctaBanner simple + an image → heading `md:text-banner` bold, the ticket roll below (`max-w-128 shadow-glow`). Figma's blue "door" and the image's glow halo aren't reproduced (plain text field / one shadow token).
- **Templates and CSS:**
  - `_entries/page.twig`: the body background map (+ `dark` → `bg-black text-white`).
  - `cardGrid.twig`: a left-header branch for showcase / carousel / bento.
  - `card.twig`: `showcase` and `panel` variants.
  - `imageGallery.twig`: `data-scheme`; poster marquee; a left heading for posters.
  - `ctaBanner.twig`: simple + image. `app.css`: `.scroll-row`.
- **Migration** `m261120_440000_sell_tickets` (backup: `/tmp/before-sell-tickets.sql.gz`):
  - Options pageBackground `dark` and cardLayout `showcase`; `scheme` added to imageGallery.
  - The page (banner + 5 blocks).
  - 29 placeholder images (`st-*`).
- **Checked (Playwright) at 375 / 1440:**
  - Every section dark on a black body; showcase images 416×493 at 1440; posters 300px tall, radius 8.
  - Carousel row starts at x = 64 (same as the headings), scrollWidth 2028 > 1425; no page overflow; 0 broken images; no console errors.
  - All pages 200 with no Twig errors.

### 2026-11-20: SEO defaults, sitemap, favicon + full-site accessibility pass
- **Rule first:** CLAUDE.md › SEO, sitemap and favicon.
- **Read first** (`vendor/ether/seo`):
  - Settings: `title` template, `description`, `socialImage`, `robotsTxt`.
  - The meta template calls `getSeoField()`, which checks a context variable `seo` before `entry.seo`; otherwise it falls back to `craft.seo.custom(siteName)`.
  - Sitemap rows live in `seo_sitemap` (`group` = sections, `url` = the section id); `/sitemap.xml` → `sitemap_sections_<id>_<page>.xml`.
- **Migration** `m261120_450000_seo_defaults` (backup: `/tmp/before-seo.sql.gz`):
  - A default meta description (plugin settings → project config; placeholder copy).
  - Sitemap rows Home (daily, 1.0), Pages (weekly, 0.8), Blog (weekly, 0.6).
- **`blog/index.twig`:** `{% set seo = craft.seo.custom('Blog'|t) %}` → "Blog - Quick Craft".
  - **Gotcha:** `custom()` adds the site-name suffix itself; passing `'Blog - ' ~ siteName` gave "Blog - Quick Craft - Quick Craft".
- **Favicon:** `web/favicon.svg` (the header's qc tile) + `web/favicon.ico` (32px, `ddev exec "cd web && convert -background none -density 384 favicon.svg -resize 32x32 favicon.ico"`), both linked in base.twig. This also ends the one-off console 404 for /favicon.ico. Placeholder until the real brand mark exists.
- **Checked:**
  - `/sitemap.xml` lists the 3 section sitemaps; the Pages one has all landing pages; the Blog one has the 6 posts.
  - `/robots.txt` = Disallow all (correct outside production).
  - Every page has a title "{Title} - Quick Craft", a description, a canonical (`/blog?category=…` → canonical `/blog`) and the icon links.
- **Accessibility / quality pass (Playwright, 1440):** Home, Organizers, Birthday, Invitation Maker, Sell Tickets, Careers, Press, Blog, a blog post, a filtered blog list.
  - Checks: one h1; no skipped heading levels; no empty headings; every img has `alt`; every visible link and button has a name; every field has a label; no duplicate ids; `<main>` present; every `<nav>` labelled; `lang` set; no console errors. **0 issues on every page.**
  - The unknown URL shows Craft's devMode error page locally (404.twig renders when devMode is off).
- **Overflow pass at 375 and 768:** no horizontal scroll on any page (including /404).
- **Keyboard:** 12 Tabs on Home, Invitation Maker and Blog — every focused element shows a visible focus style; the first stop is "Skip to content".

### 2026-11-20: Craft MCP (built-in server) for Claude Code
- **Rule first:** CLAUDE.md › Craft MCP; skill › Craft MCP.
  - Use it for reading and content edits; content-model changes stay in migrations; destructive tools only on request.
- **Found:** Craft 5.12.1 itself requires `mcp/sdk` and ships an MCP server (`vendor/craftcms/cms/src/mcp/`) — no plugin needed. It works on Solo; only the user-group and per-user permission tools need Team or Pro.
  - **stdio:** `php craft mcp/serve --user=<id|username|email>`, as that user. This is the one we use.
  - **HTTP:** `/mcp` + OAuth (`/mcp/auth`), switched on in CP › Settings › MCP. Left off.
- **Added** `.mcp.json` (server `craft`): `wsl -d Ubuntu -- bash -lc "cd ~/my-project/quick-craft && ddev exec php craft mcp/serve --user=admin"`. It runs as admin (Solo has one user). DDEV must be running.
- **Tested (read-only):**
  - initialize → "Craft CMS MCP server" 5.12.1; tools/list → about 50 tools (elements, drafts, revisions, assets, configuration, plugins, caches, queue, search, …).
  - `tools/call system_status_get` → `{"live": true}`.
- **Gotchas:**
  - `ddev exec --raw` drops piped stdin (the server gets nothing); use plain `ddev exec`.
  - Piping JSON from Windows PowerShell 5.1 into a native exe adds a BOM, so the first line fails with "Syntax error". Git Bash (and Claude Code itself) pipe clean bytes.
- **To use:** restart Claude Code in this project and approve the `craft` server; `/mcp` should show it as connected.

### 2026-11-20: Craft MCP moved to Claude Code's local scope; README setup guide
- **Rule first:** CLAUDE.md › Craft MCP and skill › Craft MCP. The server is registered per developer in Claude Code's local scope, not in a committed `.mcp.json` (the user's decision): the command is machine-specific and the server runs as admin.
- **Registered locally** (Windows, from Git Bash in the project folder):
  `claude mcp add --scope local craft -- wsl -d Ubuntu -- bash -lc "cd ~/my-project/quick-craft && ddev exec php craft mcp/serve --user=admin"`
  → stored in `C:\Users\<you>\.claude.json` under this project. `claude mcp get craft` → Scope: Local config, **✔ Connected**; the args are stored as 6 separate items (the `bash -lc` command is one argument).
- **Removed from Git:** `git rm .mcp.json`, and `/.mcp.json` added to `.gitignore`.
- **README.md created** (there wasn't one): a short intro, quick start, and **Craft MCP setup** with commands for macOS/Linux (DDEV), Windows with WSL, and servers without DDEV (local PHP, SSH, HTTP transport via CP › Settings › MCP + OAuth; the SSH and HTTP ones are marked untested).
- **Gotchas:**
  - In Windows PowerShell, `claude` is `claude.ps1`, so PowerShell eats the `--` separators (`error: unknown option '-d'`) and strips the inner quotes for `claude mcp add-json` (`Invalid configuration`). Git Bash passes the arguments unchanged.
  - Local scope is keyed by the folder Claude Code runs in, so run `claude mcp add` from the project root.

### 2026-11-20: Team setup — portable .mcp.json + "New developer setup" in README
- **Rule first:** CLAUDE.md › Craft MCP and skill › Craft MCP: a committed, portable `.mcp.json` for the team (the user's decision, replacing the local-only setup), plus a local-scope override for Windows users who run Claude Code outside WSL.
- **`.mcp.json` (committed):** `ddev exec php craft mcp/serve --user=${CRAFT_MCP_USER:-admin}`, with no paths or WSL names; it runs from the project root wherever Claude Code and DDEV share a shell (macOS, Linux, inside WSL). `/.mcp.json` was removed from `.gitignore`.
- **Kept:** the owner's local-scope `craft` (Windows → `wsl … ddev exec …`). Checked: on Windows, `claude mcp get craft` still resolves to "Local config", because local beats project scope.
- **Tested** the shared command inside WSL, from the project root with `CRAFT_MCP_USER` unset (→ admin): initialize OK; `system_status_get` → `{"live": true}`.
- **README.md:**
  - **New developer setup:** requirements (DDEV + Docker; Windows = WSL2, project in the WSL filesystem, Claude Code inside WSL) and steps (clone, `cp .env.example.dev .env`, ddev start, composer and npm install, `craft setup/app-id` + `setup/security-key` — both checked in SetupController — import a DB snapshot, `craft up`, dev server). Uploads aren't in Git (copy `web/uploads/images`, then `index-assets/one images`). How to make a snapshot.
  - **HTTPS:** `mkcert -install` + `ddev restart`; `libnss3-tools` for Chromium/Playwright on Linux/WSL; on Windows + WSL2, also on Windows, sharing the CA as in DDEV's WSL2 guide; `ignoreHTTPSErrors` as a fallback.
  - **Craft MCP setup:** works automatically from `.mcp.json`; set `CRAFT_MCP_USER` (Craft Solo = one user, so admin for now); the Windows-outside-WSL override (Git Bash, not PowerShell); servers without DDEV (local PHP, SSH and HTTP, the last two marked untested).
- Not pushed (as asked).

### 2026-11-20: .env check + database block in .env
- **Checked `.env`** (key names only, values never printed): all 6 keys from `.env.example.dev` are set (CRAFT_APP_ID, CRAFT_ENVIRONMENT, CRAFT_SECURITY_KEY, CRAFT_DEV_MODE, CRAFT_ALLOW_ADMIN_CHANGES, CRAFT_DISALLOW_ROBOTS). Nothing was missing.
- **Rule first:** CLAUDE.md › Environment variables: `.env` now also lists the database block (the user's decision).
- **Database block** added to `.env` (local, gitignored) and `.env.example.dev` (committed), with DDEV's default values: `CRAFT_DB_DRIVER=mysql`, `SERVER=db`, `PORT=3306`, `DATABASE=db`, `USER=db`, `PASSWORD=db`, `PRIMARY_SITE_URL=https://quick-craft.ddev.site`. These are DDEV's standard local credentials, not secrets.
- **Precedence:** `bootstrap.php` uses `Dotenv::createImmutable(...)->safeLoad()`, which never overwrites a variable that's already set, so DDEV's injected values still win inside the container. The `.env` copy is for visibility, and for running without DDEV.
- **Checked:** `ddev craft env/show CRAFT_DB_SERVER` → "db"; `/`, `/blog` and `/admin/login` → 200.
- README's `cp .env.example.dev .env` comment updated.

## 2026-11-20 · Contact + About pages, contact form (Figma 76:2, 76:299)

**Done**
- Read both frames in Figma and checked the content in Craft via the Craft MCP (read-only): there were no About/Contact entries, siteSettings had 5 social links without handles and empty contactDetails, and the Home press logos already existed.
- Migration `m261120_460000_contact_about` (backup `/tmp/before-contact-about.sql.gz`):
  - New instances: `contactDetail.label`, `socialLink.handle`, `textMedia.cropImage` (lightswitchOn), `ctaBanner.imageAbove` (lightswitchOff).
  - New options: `heroLayout` imageOnly, `testimonialLayout` pressRow.
  - New block `contactInfo` (Text group, blue, address-book): heading, text, contactHeading, socialHeading, formHeading, contactForm (a `newsletterForm` instance), spacing.
  - Wheel Form "Contact Form": name / email / message (textarea), labelled and required, honeypot, `submit_message`.
  - Content: siteSettings contact details and handles; the Contact and About entries in `pages`; the footer menu items About/Contact now link by entry ID.
- Migration `m261120_470000_wheelform_settings` (backup `/tmp/before-wheelform-settings.sql.gz`): Wheel Form plugin settings get placeholders (From = Craft's system email, Success Message).
- Templates:
  - `_components/wheel-form.twig`: a generic renderer for any Wheel Form.
  - `_blocks/contactInfo.twig`.
  - `banner.twig`: imageOnly layout; simple layout with photo + emblem.
  - `testimonials.twig`: pressRow.
  - `textMedia.twig`: uncropped images.
  - `ctaBanner.twig`: sticker above the heading.
  - `_entries/page.twig`: passes `wheelformErrors` / `values` to blocks.

**Checked (Playwright, 1440 / 375)**
- One h1 per page (Contact: visually hidden entry title; About: banner heading); h2/h3 order is correct; no horizontal overflow.
- Contact: the three contact details and five socials come from Site settings, and the form has Name / Email / Message.
  - Error path: an invalid email gets an inline error with `aria-invalid`, and the other values are kept.
  - Success path: 200, the redirect goes to `#contact-form`, and the `role="status"` message shows.
- About: the press row scrolls, the four polaroid rows are uncropped, and the CTA shows the sticker and the Careers button.
- `/`, `/careers`, `/press`, `/blog`, `/organizers`, `/sell-tickets`, `/blocks` → 200; no console errors.

**Gotchas**
- A Twig comment inside an `include … with { }` hash gives "Unclosed block". Put it above the tag.
- The contact form sends email, so it needs the plugin's From email; without one, a successful submit returns a 500 ("Plugin settings need to be configured.", found in `storage/logs/web-*.log`).
- The success box showed our heading and the form's message together ("Thanks… Thanks!"). It now shows the form's message, falling back to `|t` text.
- Playwright `:nth-of-type` counts siblings of the same tag; index `querySelectorAll` instead.

**Before launch:** replace the Wheel Form From email; real contact emails and social handles.

**Commit:** 9d0b87e

## 2026-11-20 · Image helper: Site7 Engine's image macro features in `_components/image.twig`

**Decision (user):** keep our component (the `include … only` rule, alt from the asset's alt field, no external placeholders) and add the useful parts of Site7 Engine's `themes/site7-theme/templates/_macros/images.twig` (read-only reference).

**Done**
- `sources` (art direction): `[{ media, ratio, width, sizes }]` → `<picture class="contents">` + one `<source>` per entry, each with its own crop, srcset and width/height. `class="contents"` keeps the `<img>` as the layout box.
- `quality` prop (passed to the transform when set).
- GIFs (and SVGs) served as-is: before this, a GIF was converted to WebP and lost its animation.
- Lazy images get `sizes="auto, …"`; eager images keep plain `sizes`.
- Contact banner (imageOnly) uses `sources`: below `lg` a real 3:2 crop (2048×1365 max) instead of the 1560/484 crop re-cut by a CSS `aspect-*` switch.
- Not taken from Site7: `alt = image.title`, `placehold.co` placeholders, Bootstrap classes. `preloadBg` / `bgImageUrl` / `iconMask` wait until a design needs them.
- Rules: CLAUDE.md › Content & data › Images; skill › Images checklist.

**Checked (Playwright):** `/contact` at 375 → `<picture>`, 328×219 from the 3:2 source; at 1440 → 1385×430 wide crop. 11 pages (incl. `/blocks`, 2669 images) → 200; every `<img>` has src and alt, and every lazy one with a srcset has `sizes="auto, …"`; no console errors. (No GIF in the library yet, so the GIF path is checked in code only.)

**Gotcha:** a Twig macro can't be defined inside an `{% if %}` and called with `_self.` (Twig 3 needs a top-level macro + `{% import _self %}`); a `{% for %}` over the sources + the main set was simpler.

## 2026-11-20 · Image helper as a Site7-style macro: `images.singleImg()`

**Decision (user):** use the image helper the way Site7 Engine does: `{% import "_macros/images" as images %}` + `images.singleImg(image, size, options, classes, attr)` (Site7 calls `singleImg` 59 times and `responsiveImg` never).

**Done**
- New `templates/_macros/images.twig` › `singleImg(image, size = 800, options = {}, classes = '', attr = {})`, with the same logic as the old component:
  - WebP transforms, width-descriptor srcset, 2× cap at the original, `sizes="auto, …"` for lazy images, SVG/GIF as the original file, video.
  - Options: Site7's `ratio`, `lazy`, `quality`/`jpegQuality`, `pictureClasses`, plus ours `sizes`, `decorative`, `sources`.
  - `attr`: extra `<img>` attributes; an `alt` there overrides the asset's alt.
- `_components/image.twig` removed. All 38 calls in 14 templates converted (a script: hash props → positional args; `eager: x` → `lazy: not (x)`), each file importing the macro itself.
- Rules: CLAUDE.md (Front-end layout › `_macros/`, Content & data › Images, incl. what an SVG does), skill (skeleton, components, Images checklist).

**Checked**
- Old (HEAD, component) vs new (macro) HTML for 12 pages (`/`, careers, press, blog, a post, organizers, birthday, invitation maker, sell tickets, about, contact, `/blocks`): all 200, and all 2,515 `<img>/<source>/<video>/<picture>` tags identical.
- A throwaway template: `attr` alt + `data-*` applied, `lazy: false` → fetchpriority=high, an SVG keeps its own size (ratio ignored), and a null asset prints nothing.

**Gotchas**
- A template variable called `images` (ctaBanner, banner) and the macro alias `images` coexist: Twig 3 keeps imported macros apart from context variables.
- A Twig macro must be at the top level of its file and imported (`_self.x()` without `{% import _self %}` doesn't work in Twig 3).
- Windows Python writes CRLF; this working copy is CRLF anyway (`core.autocrlf=true`), so diffs stay small.

## 2026-11-20 · Full-site check (Playwright, 375 + 1440)

**Checked**
- Pages: all 16 sitemap URLs + `/blog` (pages, all categories, unknown category), `/blocks` in every scheme, `/404` `/500` `/503`, `/sitemap.xml`, `/robots.txt`. All 200; one `<h1>` each; no skipped heading levels; no horizontal overflow; no broken images; every `<img>` has alt; no JS/console errors.
- The phone menu opens (`aria-expanded=true`) and Escape closes it.
- No new web errors in `storage/logs` (only the expected `__vite_ping` when the Vite dev server isn't running; Craft then uses `web/dist`).

**Found and fixed**
- Every page returned 500 after the image-macro comparison (`git stash` of templates): Twig reused a stale compiled `banner.twig`. Fix: `ddev craft clear-caches/compiled-templates`. Recorded in the skill gotchas.

**Not bugs (for the user)**
- `/llms.txt` → 404: by design while the "LLMs Text" Single is empty.
- An unknown URL locally shows Craft's devMode exception page; with devMode off visitors get `404.twig` (checked at `/404`).
- 22 menu links go to pages that don't exist yet (Invites ×8, Explore ×7, Create, Help, Terms, Privacy, Cookies, Community Guidelines, Download).

## 2026-11-20 · Pages without a Figma design: legal, 8 occasion pages, Download; Explore removed

**Why:** the full-site check found 22 menu links to pages that didn't exist. Figma has no frames for any of them (only the 12 built pages), so the user chose: simple legal pages with a placeholder body, copies of the Birthday layout for the occasions, and re-pointing or removing the rest.

**Done** (migration `m261120_480000_more_pages`, backup `/tmp/before-more-pages.sql.gz`)
- `textLayout` Dropdown (small / article) on richText; `_blocks/richText.twig` gets the article layout (`max-w-article`, `.rich-text--article`, no divider).
- Legal pages `/terms`, `/privacy`, `/cookies`, `/community-guidelines`: Banner simple + richText article with a **placeholder** body (the user pastes the real text).
- Page preset **"Occasion invitations"** (Birthday's gallery, cards and CTA blocks; card heading "Get your party started").
- 8 occasion pages duplicated from Birthday (`Elements::duplicateElement`, nested blocks included). Each gets its own title, slug, banner heading/text and SEO; the Birthday-only promo bar and SEO text are deleted; the block order is renumbered.
- `/download`: Banner simple + ctaBanner simple (text + store badges).
- Menus: every item links by entry ID; Create → Invitation Maker, Help Center → Contact; the Explore mega menu, "Explore Events" (Products, footer) and the /explore items removed.

**Checked (Playwright 1440 + 375):** all 14 pages 200, one h1, no overflow, no console errors; 23 internal links, none broken; the header shows Invites / Products / For Organizers / Company; `/blocks` 200.

**Gotchas**
- Yii's `Migration::update()` / `delete()` return nothing (they echo), so don't use them for counts.
- `Elements::duplicateElement()` on a page copies its nested Matrix blocks too; the PagePresets module skips it (`duplicateOf` is set).
- Windows Python string replacements miss on CRLF files; use Edit, or match `\r\n`.

**Before launch:** the real legal text on the 4 legal pages; occasion-specific images and SEO text.

## 2026-11-20 · Organizers as partiful.com/org-profiles: rotating words, logo row, "Get started for free"

**Reference measured with Playwright** (1440):
- Rotating words: a new word every ~1s. The old word moves up ~60px and fades, the new one comes up from below (~400ms, a slight scaleX stretch). Each word has its own 277° two-colour gradient.
- A static press-logo row sits right under the banner.
- The "Get started" glow has no edges; its subtitle is one line.

**Done** (migration `m261120_490000_organizers_reference`, backup `/tmp/before-organizers-reference.sql.gz`)
- `src/js/rotating-words.js` + `.rotating-words` (app.css):
  - The words share one inline-grid cell; `is-active` / `is-leaving` with 450ms transitions.
  - Reduced motion: none. Without JS the first word shows.
  - Screen readers get the first word once (sr-only); the animated words are `aria-hidden`.
- New colour tokens for the word gradients (magenta, tangerine, teal, violet, indigo, scarlet, leaf, sky, cobalt, forest, mint); `bg-linear-277`.
- `banner.twig`: the heading's last word stays with the rotating word (`md:whitespace-nowrap`).
- testimonials › `logoRow` (new option): a static centred logo row. Organizers gets one as its first block (NYT, WSJ, TIME, The Atlantic, USA Today; reusing Home's press logos).
- `.glow-bg` → a masked `::before` layer, so glow sections fade out at the top and bottom (site-wide; this removes the seam above "Get started for free").
- cardGrid textCards without cards: subtitle `max-w-200`, gaps 16 / 36.

**Checked:** the animation frames (old word up + out, new word up + in); one h1; logo alts; 375 has no overflow and the logos wrap. Ten pages incl. `/blocks` → 200 with no overflow or JS errors; glow sections render with soft edges (Careers checked).

**Not copied:** the reference's display font and brand. Copy and images stay Quick Craft's (placeholder logos from the imported design).

## 2026-11-20 · Organizers logo row → Swiper ticker

**Decision (user):** the logo row under the Organizers banner is a Swiper slider.

**Done**
- `testimonials.twig` › logoRow: `data-carousel="logos"` markup. The set is rendered 3×; copies are `aria-hidden` with empty alt. Slides are `w-auto!`.
- `src/js/carousels.js` › `logos` variant: `loop`, `autoplay` delay 0 + `speed` 5000, linear timing (app.css `--swiper-wrapper-transition-timing-function: linear`), spaceBetween 32 / 48 (md), no drag or keyboard; reduced motion → still.
- Without JS (or before Swiper loads): one flex row with a 48px gap.

**Checked (Playwright):** the wrapper moves at a steady ~58px/s at 1440 with a logo always coming in on the right (no gap in the loop). Readable: the 5 outlet names once. 375: one moving row, no overflow. Reduced motion: transform stays at 0. No console errors.

**Gotcha:** comparing `getComputedStyle().transform` before and after Swiper starts shows a change (`none` → matrix) even when nothing moves. Sample the matrix x (`DOMMatrix.m41`) after start-up instead.
