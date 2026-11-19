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
| Home | Single | `__home__` → `_entries/page` | `home`: page tabs (below) |
| **Pages** | **Channel** (the user's choice, as Site7's standard pages) | `{slug}` → `_entries/page` | `page`: page tabs. **Every standard page:** landing pages, Careers, Press, About… |
| Blog | Channel | `blog/{slug}` → `blog/_entry` | `blogPost`: tabs **Post** (title, image, text = summary, blogCategories, featured) · **Content** (body) · **SEO** (seo); author = Craft user |
| Jobs | Channel | no URLs | `job`: department, location, employmentType, workplace, externalLink |
| Page not found | Single | no URL → `404.twig` | `page`: page tabs |
| Page error | Single | no URL → `500.twig` | `page`: page tabs |
| Page maintenance | Single | no URL → `503.twig` | `page`: page tabs |
| Press mentions | Channel | no URLs | `pressMention`: publication, externalLink (title = headline, postDate = date) |
| Site settings | Single | no URLs | `siteSettings`: image (logo), buttons (header CTAs), appStoreLink, googlePlayLink, socialLinks, contactDetails |
| Newsletter | Single | no URLs | `newsletter`: image (blog title image), heading, text, newsletterForm (Wheel Form) |
| Google Structured Data | Single | no URL; printed in `<head>` by base.twig | `googleStructureData`: `structuredData` (a plainText instance holding JSON-LD). It must be valid JSON; it is re-encoded with `json_encode`, so a broken or unsafe value is never output |
| LLMs Text | Single | no URL; served at `/llms.txt` (`templates/llms.txt.twig`, text/plain) | `llmsText`: `llmsContent` (a plainText instance, Markdown for AI crawlers) |
| Blog categories | Structure | no URLs | `blogCategory`: title. Related via the Entries field `blogCategories` |
| Departments | Structure | no URLs | `department`: title. Related via the Entries field `department` |

- **Only Home is a page Single** (the user's rule, as in Site7 Engine; migration `m261120_200000_pages_structure`).
  - **Every other page is an entry in the `pages` Channel** (not a Structure: the user's choice, 2026-11-20; migration `m261120_210000_pages_channel`), built with the Banner + page builder. URLs are `/{slug}`; there are no nested pages. To add a page, add an entry there; never create a new Single or section for a page.
  - **Singles without URLs:**
    - settings: `siteSettings`, `newsletter`, `googleStructureData`, `llmsText`;
    - system pages: `pageNotFound`, `pageError`, `pageMaintenance`. They use the `page` entry type and are rendered by `templates/404.twig`, `500.twig` and `503.twig` via `_partials/system-page.twig`, with `|t` fallback copy if the entry is missing.
  - Careers (ID 4) and Press (ID 6) were moved into `pages` with their IDs, so menu links by entry ID still work. Menu order, not the section, controls navigation order.
- **Page tabs (as in Site7 Engine), every page entry type** (`home`, `page`, and any new page type). Migration `m261120_100000_page_tabs`:
  1. **Page:** title, `pageBackground` (paper / surface: the page's background colour).
  2. **Banner:** the page's fixed banner (hero). Fields: `heroLayout`, `scheme`, `eyebrow`, `heading`, `rotatingWords`, `text`, `buttons`, `images` (the banner/featured image), `chatMessages`.
  3. **Content:** `pageBuilder`.
  4. **SEO:** `seo`.
- **The banner is fixed per page, not a pageBuilder block** (there is no `hero` block any more).
  - `_entries/page.twig` renders it with `_partials/banner.twig` from the entry's own Banner fields.
  - The banner renders when it has a heading or images.
  - **Headings:** the banner heading is the page's `<h1>`, and pageBuilder blocks start at `<h2>`. If the banner has no heading, the page outputs a visually hidden `<h1>` with the entry title, so every page still has exactly one `<h1>`.
- **Blog listing** is `templates/blog/index.twig`. It isn't a section.
- **Everything is an entry (as in Site7 Engine):** no global sets and no category groups. Migration `m261119_220000_entrify_globals_categories` converted them, keeping element IDs and content. Don't add global sets or category groups; use a Single (settings) or a Structure (taxonomies) instead.
- **Live Preview of settings:** `siteSettings` has a preview target, Home (`/`). Editing it in the CP shows header, footer and store-link changes live on Home. Each new settings Single gets a preview target pointing at a page that shows it. Testing procedure: skill › Live Preview.
- **Settings Singles in templates:** `templates/_layouts/base.twig` loads `siteSettings` and `newsletter` **once per request** into Craft's front-end `_globals` collection. Read them anywhere, including `only` includes, with `{% set siteSettings = _globals.get('siteSettings') %}`. Never query these sections again in a partial or block. For a new settings Single, add it to that same block in base.twig.
- **Volume:** `images` (`web/uploads/images`, gitignored). Alt text uses the native alt field.
- **pageBuilder blocks** (each one is `_blocks/{handle}.twig`, receives `block` and `headingLevel`):
  - cardGrid, featureRows, imageGallery, cityList, testimonials, textMedia, comparison, richText, faq, ctaBanner, promoBar, image, jobList, pressList
- **Nested Matrix fields:**
  - `cards`: `card` (image, emoji, heading, text, primaryButton = the card's text link)
  - `testimonials`: `testimonial` (text = quote, heading, personName, personDetail, image, rating) and `stat`
  - `faqItems`: `faqItem` (title = question, body)
- **Common Matrix fields** (reusable anywhere; migration `m261120_120000_common_matrix_fields`). Before adding a repeating group, use one of these, or reuse their item entry type in another Matrix.
  - **`buttons`** (entry type `button`: `buttonLink` = a Link with label, `buttonStyle` dropdown, `iconName` = an optional Tabler icon name). Max 3.
    - **Every button group uses it:** page Banner, cardGrid, ctaBanner, imageGallery, jobList, siteSettings (header buttons).
    - **`buttonStyle` options** (the user's decision, 2026-11-20):
      - `primary` / `secondary`: automatic. The block maps them to colours for its scheme, as in the Figma design.
      - `dark` / `light`: a fixed colour wherever the button is placed. On a dark section, `dark` uses the dark-on-dark border (`darkOnDark`).
      - Only the button component's variants are offered (`_components/button.twig`); never free colours.
    - Render with `{% include '_components/buttons.twig' with { buttons: …, variants: { primary: '…', secondary: '…' }, dark: <bool>, shape, size, class } only %}`. The block passes its primary/secondary map; `dark`/`light` are resolved by the component.
    - **A single inline link stays a Link field:** a card's link (`primaryButton`), a city's link, ctaBanner's heading link (`primaryButton`, labelled "Heading link"), promoBar.
  - **`socialLinks`** on siteSettings (entry type `socialLink`: `platform` dropdown → the Tabler icon is chosen by the template, `externalLink`). Rendered by `_components/social-links.twig` (the footer's "Socials" column).
  - **`contactDetails`** on siteSettings (entry type `contactDetail`: `contactType` email / phone / whatsapp / address, `detail`). Rendered by `_components/contact-details.twig` (makes mailto:, tel: and wa.me links). Used by the contact page and anywhere the design shows contact info.
  - **Next, with their pages:** `person` (Careers), `logo` and `download` (Press kit).
- **Base field library: create once, reuse everywhere** (the user's rule, 2026-11-20; migration `m261120_130000_base_field_library`).
  - **The fields:** generic fields, each added to sections and blocks many times, every time with its own **handle and label** (a Craft 5 field-instance override).
  - **In templates, always use the instance handle** (`block.heading`, `item.personName`), never the base handle.

  | Base field (handle) | Type | Instances (examples) |
  |---|---|---|
  | `plainText` "Plain text" | PlainText: **the only Plain Text field** (user rule). Multi-line allowed, starts at 1 row and grows | heading, eyebrow, text (summary/intro/quote), personName, personDetail, location, publication, detail, iconName, emoji, the image volume's "Subtitle" |
  | `richText` "Rich text" | CKEditor, simple: bold, italic, link, lists, remove formatting | short formatted text in blocks (intro, card text) |
  | `richTextFull` "Rich text (full)" | CKEditor, full: headings, text styles, link, lists, indent, quote, table, image, video embed, horizontal line, fullscreen, source (admins) | body (blog post) |
  | `image` "Image" | Assets: 1 image | image, logo |
  | `images` "Images" | Assets: many images | images |
  | `imageVideo` "Image/Video" | Assets: 1 image or video | (ready) |
  | `video` "Video" | Assets: 1 video | (ready) |
  | `colorcode` "Color Code" | Color: **palette = the `--color-*` tokens only, custom colours off** | (ready) |
  | `simpleRpMenu` "Simple RP Menu" | Simple RP Menu Selector (value = a menu handle) | (ready). Render: `{% set menu = craft.simplerpmenu.getMenu(block.<instance handle>) %}` and guard with `{% if menu %}` |
  | `linkWithLabel` "Link (with label)" | Link: entry / URL / email, label | buttonLink, primaryButton (a card's link, ctaBanner's "Heading link") |
  | `urlLink` "URL link" | Link: URL only, optional new tab | externalLink, appStoreLink, googlePlayLink |
  | `lightswitchOff` "Lightswitch (default off)" | Lightswitch, off by default | featured, showAppBadges |
  | `lightswitchOn` "Lightswitch (default on)" | Lightswitch, on by default | (ready) |
  | `number` "Number" | Number | (ready) |
  | `money` "Money" | Money, USD | (ready; e.g. ticket prices) |
  | `date` "Date" | Date | (ready) |
  | `email` "Email" | Email | (ready) |

  - **Purpose-specific fields** stay their own field, because their settings can't be shared per instance:
    - every Dropdown and **Multi-select** (options belong to the field, so there's no generic one; create it with its options when a block needs it): scheme, spacing, the layout dropdowns, buttonStyle, platform, contactType, pageBackground, employmentType, workplace;
    - Tables (columns): comparisonUs (instances comparisonUs, comparisonThem), rotatingWords, chatMessages;
    - Matrix fields;
    - Entries fields (sources);
    - `rating` (0–5, one decimal), `seo`, `newsletterForm`.
  - **Plain Text: there is exactly one field (`plainText`).** Every plain text input in every section and block is an instance of it with its own handle and label. Never create another PlainText field; a character limit or single-line rule can't be per instance, so it isn't used.
  - **CKEditor toolbars never include font size, font colour or alignment:** they write inline styles, which breaks the tokens rule.
  - **`colorcode` keeps the tokens rule:**
    - Its palette is the `@theme` colour tokens (label = token name, e.g. `accent`), and editors can't enter custom colours.
    - **Templates never output the hex** (no `style=""`). Map the chosen token to a full class string, e.g. `{ accent: 'bg-accent', ink: 'bg-ink' }[block.colorcode.label] ?? ''`, so Tailwind finds the class.
    - **When a colour token is added or changed in `app.css`, update the palette** in a migration (`m261120_160000_assets_and_color_fields` shows how).
  - **A new field is created only when no base field has the needed settings.** Then add it to this table: as a base field if it's generic, or to the purpose-specific list.
  - **How existing fields became base fields (keeping content):**
    - Content is keyed by the layout element's UID, not the field.
    - So every existing instance first gets its current handle and label as an explicit override; then the field is renamed. Or, when merging, the element is pointed at the base field (`oldFieldUid` + `setField()`, Craft's own `fields/merge` steps) and the old field is deleted.
    - Layouts outside entry types (e.g. the `images` volume) are included, via `Fields::findFieldUsages()` and the project config paths that hold those layouts.
- **Icons on the site are Tabler only** (the user's decision, 2026-11-20).
  - An icon input is a `plainText` instance (e.g. `iconName`) holding a Tabler name from https://tabler.io/icons, rendered with `_components/icon.twig`.
  - Craft's Icon field (Font Awesome) is **not used**.
  - When the icon follows from data (social platform, contact type), the template picks it; editors don't type it.
- **Entry type icons and colours in the CP** (migration `m261120_190000_entry_type_icons`). These are only for editors: Craft's own CP icon set (`vendor/craftcms/cms/src/icons/solid/*.svg`), separate from the site's Tabler icons. Colour = what kind of block it is:

  | Colour | Kind | Entry types (icon) |
  |---|---|---|
  | fuchsia | call to action | ctaBanner (bullhorn), promoBar (rectangle-ad) |
  | blue | text content | richText (paragraph), faq (circle-question), textMedia (columns-3), comparison (scale-balanced) |
  | emerald | cards and lists | cardGrid (grid-2), featureRows (list), cityList (city), jobList (briefcase), pressList (newspaper) |
  | amber | media | imageGallery (images), image (image) |
  | violet | social proof | testimonials (quote-left) |
  | gray | items inside a block | card (id-card), faqItem (circle-question), testimonial (comments), stat (chart-simple), button (hand-pointer), socialLink (share-nodes), contactDetail (address-card) |
  | none | pages and settings | home (house), page (file-lines), blogPost (rss), job (briefcase), pressMention (link), siteSettings (gear), newsletter (envelope), googleStructureData (code), llmsText (robot), blogCategory (hashtag), department (layer-group) |

  **A new entry type gets an icon and a colour from this table in its migration.**
- **Block library page `/blocks`** (`templates/blocks.twig`; login required, except in devMode so it can be checked locally; not indexed):
  - Shows every pageBuilder block in use on the site, grouped by type: one example per layout × scheme, with the page it comes from. Also every page Banner, and the block types that have no template or no example yet.
  - `?scheme=light|white|soft|dark` re-renders every scheme-aware block in that scheme, in memory only (`setFieldValue`, never saved).
  - **Check it after any template or CSS change** (as Site7's `/blocks`). New block types appear automatically.
- **Option fields:** `scheme`, `spacing` and one layout dropdown per block (`heroLayout`, `cardLayout`, `featureLayout`, `galleryLayout`, `testimonialLayout`, `mediaPosition`).
- **Section options (Shopify-style), migration `m261119_230000_scheme_and_spacing`:**
  - **`scheme`** (Colour scheme): `light` (page background, none of its own), `white`, `soft` (surface grey), `dark`.
    - The block puts `data-scheme="{{ scheme }}"` on its `<section>`. The colours are defined once in `src/css/app.css` (`[data-scheme]`, components layer), so a block's own utility can still override them (featureRows' dark is `bg-ink`).
    - Inner elements switch on `scheme == 'dark'` (button variants, muted text), and on `soft` where a light inner panel would vanish against grey.
    - Used by hero, cardGrid, featureRows, ctaBanner.
  - **`spacing`**: `default`, `noTop`, `noBottom`, `none`.
    - It means the same thing in every block: it removes the section's own top and/or bottom padding (`[data-spacing]`, unlayered CSS so it beats the `py-*` utilities).
    - Only add it to blocks whose padding sits on the `<section>` (cardGrid, cityList, testimonials, ctaBanner). Hero (fixed-header clearance) and featureRows (padding on inner wrappers) don't have it.
  - **New blocks:** add `scheme` only if the template supports every scheme, and `spacing` only if the padding sits on the `<section>`. Never add free colour or padding values.
- Field handles must avoid `craft\base\Field::RESERVED_HANDLES`. For example, `icon` is reserved, which is why the field is called `emoji`.
- **Adding a field to a section or block:**
  - Take it from the base field library above and add it to the layout with its own **handle, label and instructions** (in a migration: `new CustomField($baseField, ['handle' => 'personName', 'label' => 'Person name'])`).
  - Always give the instance a **meaningful handle** (`personName`), never the generic base handle (`plainText`), so templates stay readable.
  - Create a new field only when no base field has the needed settings (settings belong to the field, not the instance).

### Content & data
- **No hard-coded copy.** All text and images come from Craft fields (or `siteName`/the settings Singles). UI microcopy (e.g. "Skip to content", "Read more") must use `|t` for translation.
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

### Workflow: every request
1. **Record the rule or decision first:** update `.claude/skills/craft-cms/SKILL.md` and/or this file before touching code.
2. **Then build or fix it.**
3. **Then continue with the next planned step** (`docs/build-plan.md`).

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
