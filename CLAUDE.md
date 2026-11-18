# Quick Craft

Craft CMS 5 website for the brand **Quick Craft**, built from a Figma design.

## Stack

- Craft CMS 5 (≥ 5.12.1), PHP 8.3, MySQL 8.0 — local dev with **DDEV** on WSL Ubuntu
- Twig templates, **Tailwind CSS v4** (CSS-first `@theme`, no tailwind.config.js), **Vite 8** via `nystudio107/craft-vite`, **Alpine.js**
- Font: **Inter** (Google Fonts `<link>` in `templates/_layouts/base.twig`)
- Plugins: Vite, CKEditor, SEO (`ether/seo`), Wheel Form, Embedded Assets, Expanded Singles, Simple RP Menu
- Add only if needed: Guest Entries, Cookies, Image Resizer
- **Never use:** Site7 Studio, Device Detect, RP Payment Gateway

## Old site (reference only)

`/home/rp-bubai/my-project/site7-engine` is **READ-ONLY**. Read it for reference; never edit files, install packages, or run commands that change anything there (no `ddev`, `composer`, `npm`, `git` writes in that folder).

## Commands

All tooling runs inside DDEV (Node is not installed on the WSL host). From the Windows-side shell, run via `wsl -d Ubuntu -- bash <script>`; inside WSL, run directly.

| Task | Command |
|---|---|
| Start / stop | `ddev start` / `ddev stop` |
| Vite dev server (HMR, Twig live-reload) | `ddev npm run dev` → served at `https://quick-craft.ddev.site:5173` |
| Production build → `web/dist` | `ddev npm run build` |
| Craft CLI | `ddev craft <command>` |
| Apply project config + migrations | `ddev craft up` |
| Create content migration | `ddev craft migrate/create <name>` |
| Composer | `ddev composer <command>` |

Site: https://quick-craft.ddev.site · CP: https://quick-craft.ddev.site/admin

## Front-end layout

- `src/main.js`: the one Vite entry. It imports `src/css/app.css` and starts Alpine.
- `src/css/app.css`: `@import "tailwindcss"` plus the `@theme` design tokens.
- `vite.config.js`: DDEV dev server on 5173, manifest build into `web/dist`, and Twig full-reload.
- `config/vite.php`: `useDevServer` in dev with `checkDevServer`. When the dev server isn't running, Craft falls back to the `web/dist` build.
- `templates/_layouts/base.twig`: the base layout, with `{% hook 'seo' %}` (Ether SEO renders `<title>`, meta, OG and canonical, so don't add your own `<title>`), Inter, and `craft.vite.script('src/main.js', false)`.
- `templates/_blocks/`: one file per pageBuilder block.
- `templates/_components/`: reusable UI pieces (button, image, card…), included with `{% include %}` and explicit `with {...} only`.
- `templates/_partials/`: site chrome (header, footer, nav).

## Rules

### Content model
- Pages are built with a Matrix field **`pageBuilder`**. Each block (entry type) renders from `templates/_blocks/{entryTypeHandle}.twig`:
  ```twig
  {% for block in entry.pageBuilder.all() %}
    {% include '_blocks/' ~ block.type.handle ~ '.twig' ignore missing with { block: block } only %}
  {% endfor %}
  ```
- **Content model changes (fields, entry types, sections) go in content migrations** (`ddev craft migrate/create`), committed to Git together with the resulting `config/project/*.yaml`. Don't rely on clicking through the CP alone.

### Current content model (migration `m261118_120000_content_model`)
| Section | Type | URI → template | Entry type: fields |
|---|---|---|---|
| Home | Single | `__home__` → `_entries/page` | `home`: pageBuilder, seo |
| Landing pages | Structure (1 level) | `{slug}` → `_entries/page` | `landingPage`: pageBuilder, seo |
| Blog | Channel | `blog/{slug}` → `blog/_entry` | `blogPost`: image, text (summary), blogCategories, featured, body, seo; author = Craft user |
| Careers | Single | `careers` → `_entries/page` | `careers`: pageBuilder, seo |
| Jobs | Channel | no URLs | `job`: department, location, employmentType, workplace, externalLink |
| Press | Single | `press` → `_entries/page` | `press`: pageBuilder, seo |
| Press mentions | Channel | no URLs | `pressMention`: publication, externalLink (title = headline, postDate = date) |

- **Blog listing** is `templates/blog/index.twig`. It isn't a section.
- **Globals:**
  - `siteSettings`: image (logo), primaryButton and secondaryButton (header CTAs), appStoreLink, googlePlayLink
  - `newsletter`: image (blog title image), heading, text, newsletterForm (Wheel Form)
- **Categories:** `blogCategories`, `departments`
- **Volume:** `images` (`web/uploads/images`, gitignored). Alt text uses the native alt field.
- **pageBuilder blocks** (each one is `_blocks/{handle}.twig`, receives `block` and `headingLevel`):
  - hero, cardGrid, featureRows, imageGallery, cityList, testimonials, textMedia, comparison, richText, faq, ctaBanner, promoBar, image, jobList, pressList
- **Nested Matrix fields:**
  - `cards`: `card` (image, emoji, heading, text, primaryButton)
  - `testimonials`: `testimonial` (text = quote, heading, personName, personDetail, image, rating) and `stat`
  - `faqItems`: `faqItem` (title = question, body)
- **Option fields:** `theme` and one layout dropdown per block (`heroLayout`, `cardLayout`, `featureLayout`, `galleryLayout`, `testimonialLayout`, `mediaPosition`).
- Field handles must avoid `craft\base\Field::RESERVED_HANDLES`. For example, `icon` is reserved, which is why the field is called `emoji`.

### Content & data
- **No hard-coded copy.** All text and images come from Craft fields (or `siteName`/globals). UI microcopy (e.g. "Skip to content", "Read more") must use `|t` for translation.
- **Always handle empty fields**: wrap optional output in `{% if %}`, skip wrapper elements when content is empty, never emit empty `<img>`, `<a href="">`, or empty headings.
- **Eager-load** relations in queries (`.eagerly()` or `.with([...])`) — no N+1 queries inside loops.
- **Images**: Craft native transforms with `srcset` + `sizes`, explicit `width`/`height`, `loading="lazy"` (except the above-the-fold hero: `fetchpriority="high"`), and `alt` from the asset's alt field (empty `alt=""` only for decorative images).

### Design
- Design source: Figma file **`WdjEJ9NeK2FTVsJcGF6lby`**. It's an imported reference with **no Figma variables or components**, so read values from the frames and turn them into tokens.
- **Colours, fonts and spacing come only from Tailwind theme tokens** defined in `@theme` in `src/css/app.css`. Don't use arbitrary values (`text-[#123456]`, `p-[13px]`) or inline styles. When Figma needs a new value, add a token first.
- Fully responsive, mobile-first with Tailwind breakpoints.
- Accessibility:
  - Meaningful `alt` text.
  - Logical heading order: one `<h1>` per page and no skipped levels. Blocks take a heading level from context where needed.
  - Visible `:focus-visible` states on every interactive element.
  - Semantic landmarks, `aria-label` on `<nav>`, and `aria-expanded`/`aria-controls` on Alpine toggles.

### Menus — Simple RP Menu (`remoteprogrammer/simple-rp-menu` 1.1.0)
Read `vendor/remoteprogrammer/simple-rp-menu/src/` (the variable, plus `services/SimpleRpMenuService.php`) and the old site's `themes/site7-theme/templates/_includes/header|footer/*.twig` before using it. **Never guess its API.** What the source confirms:
- The Twig variable is **`craft.simplerpmenu`** (all lowercase).
- `craft.simplerpmenu.getMenu(handle)` returns the menu record or `null`. Use it to guard output.
- `craft.simplerpmenu.getRpMenuHTML(handle, config)` echoes markup: `<div id class="menu …"><ul class><li class="menu-item …"><a class>…`. If the handle doesn't exist, it prints "A menu with this handle does not exist!", so always guard:
  ```twig
  {% if craft.simplerpmenu.getMenu('headerMenu') %}
    <nav aria-label="{{ 'Main navigation'|t }}">
      {{ craft.simplerpmenu.getRpMenuHTML('headerMenu', {
        'menu-id': 'headerMenu', 'menu-class': '', 'ul-class': 'flex gap-6',
        'li-class': '', 'link-class': 'focus-visible:…', 'sub-menu-ul-class': '…'
      }) }}
    </nav>
  {% endif %}
  ```
- The only config keys it supports are `menu-id`, `menu-class`, `ul-class`, `li-class`, `link-class`, `sub-menu-ul-class`, `without-container` (bool) and `without-ul` (bool).
- Classes it outputs that you can style:
  - Labels: `span.menu-title`, plus `span.menu-short-content` when an item has a description.
  - Links: `.active` on the current link.
  - List items: `.current-menu-item`, `.current-menu-ancestor`, `.dropdown`, `.has-mega-menu`.
  - Submenus are wrapped in `div.dropdown-menu > ul`, and mega menus get `ul.mega-menu`.
- The plugin also provides an `RpMenuField` field type for picking a menu on an entry or global.

### Plugin APIs
- For **any** plugin or Craft API, read the `vendor/` source or the official docs first. **Never guess** method names, config keys or Twig variables.

### Workflow: build one section at a time
1. Get a **Figma frame link** for the section and read its layout, tokens and content.
2. Add any missing tokens to `@theme`.
3. Create or extend the entry type and fields in a **content migration**, then run `ddev craft up`.
4. Build `templates/_blocks/{handle}.twig`, reusing `_components`.
5. Add test content, then **check with Playwright at 375, 768 and 1440 px**. Compare against the Figma frame and check empty fields, focus states and heading order.
6. Commit the section by itself.
7. **Record every step in `docs/build-log.md`** (what was done, commands, gotchas, commit hash), and update the status in `docs/build-plan.md`. The log is the how-to reference for future features and updates. Never skip it.

Reference site for behaviour (scroll, hover, sliders): https://partiful.com. Use it for interactions only; copy, images and branding must be Quick Craft's own.
- Smooth scroll uses **Lenis** (`src/js/smooth-scroll.js`); carousels use **Swiper** (`src/js/carousels.js`, `data-carousel` markup); the header auto-hides via Alpine `siteHeader` (`src/main.js`).

## Git
Commit `composer.lock`, `package-lock.json`, `config/project/` and `migrations/`. These stay ignored: `.env`, `vendor/`, `node_modules/`, `storage/`, `web/cpresources/`, `web/dist/` (build on deploy) and `web/uploads/` (user uploads).

Before running a content migration, back up the database with `ddev export-db --file=/tmp/<name>.sql.gz`. If a migration fails, restore with `ddev import-db --file=…`, fix it, then run `ddev craft up` again.
