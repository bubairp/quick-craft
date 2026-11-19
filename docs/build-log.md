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
