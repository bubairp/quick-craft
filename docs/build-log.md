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
