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
