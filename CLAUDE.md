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

**Environment variables:**
- **Locally, DDEV provides the database and site URL** (`.ddev/config.yaml` `type: craftcms` injects `CRAFT_DB_DRIVER/SERVER/PORT/DATABASE/USER/PASSWORD` and `PRIMARY_SITE_URL` into the web container; Craft 5 reads `CRAFT_DB_*` directly, and the site's `baseUrl` is `$PRIMARY_SITE_URL`). So `.env` only holds the app settings (app ID, environment, security key, devMode, admin changes, robots), and there is no `config/db.php`.
- **Database tools:** phpMyAdmin https://quick-craft.ddev.site:8037 or `ddev mysql`.
- **On a server without DDEV** (staging/production), `.env` must also set `CRAFT_DB_*` and `PRIMARY_SITE_URL`. See `.env.example.staging` / `.env.example.production`; `.env.example.dev` explains the DDEV defaults. Never commit `.env` (gitignored) or real values.

## Front-end layout

- `src/main.js`: the one Vite entry. It imports `src/css/app.css` and starts Alpine.
- `src/css/app.css`: `@import "tailwindcss"` plus the `@theme` design tokens.
- `vite.config.js`: DDEV dev server on 5173, manifest build into `web/dist`, and Twig full-reload.
- `config/vite.php`: `useDevServer` in dev with `checkDevServer`. When the dev server isn't running, Craft falls back to the `web/dist` build.
- `templates/_layouts/base.twig`: the base layout, with `{% hook 'seo' %}` (Ether SEO renders `<title>`, meta, OG and canonical, so don't add your own `<title>`), Inter, and `craft.vite.script('src/main.js', false)`.
- `templates/_blocks/`: one file per pageBuilder block.
- `templates/_components/`: reusable UI pieces (button, image, card…), included with `{% include %}` and explicit `with {...} only`.
- `templates/_partials/`: site chrome (header, footer, nav).
- `modules/`: the Craft module (`Module.php` registers the event handlers; `PagePresets.php` copies preset blocks into new pages).

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
| Reusable sections | Channel | no URLs (shown through the `reusableSection` block) | `reusableContent`: title, pageBuilder |
| Page presets | Channel | no URLs (copied into new pages by `modules/PagePresets.php`) | `pagePreset`: title, pageBuilder |
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
  1. **Page:** title, `pageBackground` (paper / surface: the page's background colour), and on `page` only `pagePreset` (step 5).
  2. **Banner:** the page's fixed banner (hero). Fields: `heroLayout`, `scheme`, `eyebrow`, `heading`, `rotatingWords`, `text`, `buttons`, `images` (the banner/featured image), `chatMessages`.
  3. **Content:** `pageBuilder`.
  4. **SEO:** `seo`.
- **The banner is fixed per page, not a pageBuilder block** (there is no `hero` block any more).
  - `_entries/page.twig` renders it with `_partials/banner.twig` from the entry's own Banner fields.
  - The banner renders when it has a heading or images.
  - **Layouts (`heroLayout`):** split (Home 6-2210), centered (landing pages), and:
    - **fullBleed** (Figma Careers 6-6336): the first image edge to edge, `h-140 md:h-186` (745px in Figma), never `min-h-svh`. White text **near the top** (`pt-40`, as in Figma, so the faces below stay visible): heading `text-5xl md:text-banner` (new token: 100px / 1.4 / −0.02em) medium, text `text-xl md:text-2xl` medium. A top-down `from-black/40` gradient keeps the white text readable (Figma has none, but a light photo would make it unreadable). Without an image it falls back to dark text on the page background.
    - **simple** (Figma Press 18-6155): a centred heading only, `text-4xl md:text-headline` medium; it clears the fixed header and keeps 80px below (`md:pb-20`), so the first block's own padding gives Figma's spacing. The Press page uses scheme `white` (Figma's white page).
  - **Headings:** the banner heading is the page's `<h1>`, and pageBuilder blocks start at `<h2>`. If the banner has no heading, the page outputs a visually hidden `<h1>` with the entry title, so every page still has exactly one `<h1>`.
- **Blog listing** is `templates/blog/index.twig` (Figma 18-4865; migration `m261120_380000_blog_listing`). It isn't a section.
  - **Title:** the `newsletter` Single's `image` (the "the Guest List" wordmark) inside the `<h1>`, its alt as the heading text; without an image the h1 is the text "Blog"|t.
  - **Category filter** (Figma 18-5029): "Latest"|t (all posts) + the Blog categories in structure order, as links to `?category=<slug>` (server-side; no JS). `text-2xl font-bold`, gap 24, the current one underlined with `aria-current="page"`, inside `<nav aria-label="Blog categories"|t>`. An unknown slug shows all posts.
  - **Grid:** `max-w-text`, 1 / 2 (`sm`) / 3 (`lg`) columns, gap 40. Cards come from `_components/blog-card.twig`; posts are newest first, eager-loaded `.with(['image', 'author'])`, and **paginated 24 per page** (`{% paginate %}`, Previous / Next + "Page X of Y", only when there's more than one page).
  - **Blog card** (Figma 18-5045): the whole card is the link; square image (`ratio: 1`, no radius, as in Figma); title `text-card` (new token: 26px / 1.2) semibold; summary (`text`) `text-lg text-black/60`; author row = 40px round avatar + `text-lg font-bold` name. **Author fallback:** the user's full name, else `siteName`; the user's photo, else the site logo (siteSettings `image`). Title as `<h2>` (h1 is the page title).
  - Empty: "No posts yet."|t. The newsletter partial goes under the grid.
- **Blog post** `templates/blog/_entry.twig` (Figma 18-5645, article 18-5804; migration `m261120_400000_blog_post`): a centred `max-w-article` (700) column on white. (The token isn't called `prose`: Tailwind's built-in `max-w-prose` = 65ch wins over a `--container-prose` token.)
  - **Header:** tag pills (`featured` → "Featured"|t, then the post's categories; `rounded-md bg-black/5 px-2 text-subtle text-xl md:text-2xl`), the title as the `<h1>` (`text-4xl md:text-headline font-semibold`, centred), the summary (`text`, `text-xl md:text-2xl text-black/60`), gap 24.
  - **Hero image:** the post's `image`, `ratio: 700/311` (Figma's crop), eager (`fetchpriority=high`), 40px below the header.
  - **Body:** `body` (Rich text full) in `.rich-text .rich-text--article`: 18px black/60; **h2 = 36px bold** and **h3 = 24px bold** in black (the post title is the h1, so CKEditor's h2/h3 map to Figma's two body heading sizes); 40px before a heading, 20px between paragraphs, 32px before lists and images; links black and underlined; images full width.
  - **Next link:** "Next:"|t + the next older post (`entry.getNext({ section: 'blog' })` follows the newest-first order), `text-xl md:text-2xl` centred, `max-w-150`; hidden on the oldest post.
  - Then the newsletter partial. No author line (the design shows none).
- **Newsletter partial** `_partials/newsletter.twig` (Figma 18-5576 / 18-5927; migration `m261120_390000_newsletter`): included under the blog listing and the blog post. It reads the `newsletter` Single from `_globals` (no new query) and renders when it has a heading or a form.
  - Layout: heading left (`text-4xl md:text-headline` medium; Figma 42) and the form + note right (`max-w-162.5`, Figma 650), side by side from `lg`, centred, on `.glow-bg`. The note is `text` (`text-lg text-black/60`).
  - **Form = Wheel Form** (`newsletterForm` → a FormService; read `vendor/xpertbot/craft-wheelform` before changing it). **No JS:** a plain POST, `redirect` = the current page + `#newsletter`.
    - **Success:** the flashes `wheelformSuccess` + `wheelformSubmittedForm` (= this form id) → a `role="status"` thank-you message instead of the form.
    - **Errors:** Wheel Form re-renders the page with `wheelformErrors` / `values` → the message under the input, linked with `aria-describedby`, and the email kept.
  - Markup: a visible-to-screen-readers `<label>` + email input (`autocomplete="email"`, required) and a dark submit button in one pill (`rounded-full`, stacked on phones). Honeypot via `form.honeypot()` when the form has one. Button and messages use `|t`.
  - The `newsletter` Single has the Live Preview target **Blog** (`/blog`).
  - **Every Wheel Form needs a success message:** the form's own `submit_message` option, or the plugin's "Success Message" setting. Without one, a successful (non-AJAX) submit throws `setNotice(): … null given` (500). Migrations that create a form set `submit_message`. The plugin settings (From email, Success Message) are still unset; the user fills them in before launch.
  - Without a chosen form: admins see "Choose a form in the Newsletter settings."|t; visitors see only the heading and note.
- **Everything is an entry (as in Site7 Engine):** no global sets and no category groups. Migration `m261119_220000_entrify_globals_categories` converted them, keeping element IDs and content. Don't add global sets or category groups; use a Single (settings) or a Structure (taxonomies) instead.
- **Live Preview of settings:** `siteSettings` has a preview target, Home (`/`). Editing it in the CP shows header, footer and store-link changes live on Home. Each new settings Single gets a preview target pointing at a page that shows it. Testing procedure: skill › Live Preview.
- **Settings Singles in templates:** `templates/_layouts/base.twig` loads `siteSettings` and `newsletter` **once per request** into Craft's front-end `_globals` collection. Read them anywhere, including `only` includes, with `{% set siteSettings = _globals.get('siteSettings') %}`. Never query these sections again in a partial or block. For a new settings Single, add it to that same block in base.twig.
- **Volume:** `images` (`web/uploads/images`, gitignored). Alt text uses the native alt field.
- **pageBuilder blocks** (each one is `_blocks/{handle}.twig`, receives `block` and `headingLevel`):
  - cardGrid, featureRows, imageGallery, cityList, testimonials, textMedia, comparison, richText, faq, ctaBanner, promoBar, image, jobList, pressList
  - **promoBar** (Figma 17-1076; migration `m261120_270000_promo_bar`): `emoji` (shown on both sides), `text`, `primaryButton` (when set, the whole bar is the link). Full-width, 36px tall, `bg-promo` + `text-surface`; no scheme or spacing (it's a thin strip).
  - **richText** (Figma 17-1343, the SEO text under a page; migration `m261120_280000_rich_text`): optional `heading`, `body` (Rich text (full)), `spacing`. Small muted text (`text-xs`, black/40) in `max-w-text`, with a divider below.
    - CKEditor output is styled by the `.rich-text` component class in app.css (headings like small subheads, 20px before a heading, 8px between a heading and its text, links in ink). Content can't carry utility classes, so prose styling always goes through `.rich-text`.
    - Headings inside the body start at h2 (no block heading) or h3 (with one).
  - **comparison** (Figma 17-2074, Invitation Maker; migration `m261120_290000_comparison`): `heading`, `text` (subtitle), `image` (our column's logo; falls back to the site logo, then the site name), `comparisonUs` / `comparisonThem` (Table: label + status yes / warning / no), `themLabel` (the other column's heading), `body` (closing note, italic).
    - Status icons are Tabler filled: circle-check `text-success`, alert-triangle `text-warning`, circle-x `text-muted`. The status is also given as text for screen readers.
    - Our column is a card (`bg-linear-to-b from-aqua/10 to-orchid/10`, `rounded-xl`, `shadow-glow`); the columns stack on phones.
  - **faq** (Figma 17-2418, Invitation Maker; migration `m261120_300000_faq`): `heading`, `faqItems` (faqItem: title = question, `body` = answer), `spacing`.
    - Heading left, question cards right from `lg` (`max-w-narrow`, cards 500px); stacked below `lg`. The card border is an inset ring (Figma draws it inside the card).
    - Each question is a `<button aria-expanded aria-controls>` inside an h3. The "+" (Tabler `plus`) turns into "×" when open. Several answers can be open at once.
    - The answer opens with the `.faq-panel` component class (a `grid-template-rows` 0fr → 1fr transition; no collapse plugin). It honours reduced motion.
    - Answers are CKEditor HTML in `.rich-text .rich-text--base` (16px, black/60). The `--base` variant is for readable body text; plain `.rich-text` is the small SEO style.
  - **textMedia** (Figma Careers 6-6646 "right", 6-6862 "left"; migration `m261120_310000_text_media`): `mediaPosition` (right / left), `heading`, `headingAbove` (lightswitchOff instance: heading centred above both columns, `text-headline`), `body` (lead text), `images` (the first is shown, `rounded-lg`), `glow` (lightswitchOff instance: soft pastel background), `spacing`.
    - Columns side by side from `lg` (`max-w-narrow`, gap 40; media 536px when right, 460px when left), stacked below. The heading in the text column is `text-title` bold.
    - The body uses `.rich-text .rich-text--lead` (20px black/60, 20px between paragraphs).
    - `.glow-bg` approximates Figma's blurred photo blobs with radial gradients of the aqua / orchid / glow-blue tokens.
    - The photo strip under 6-6646 is a separate imageGallery block (marquee).
  - **jobList** (Figma Careers 6-6899; migration `m261120_320000_job_list`): `heading` (centred `text-headline`), `glow` (lightswitchOff instance), `buttons` (e.g. "See all roles"), `spacing`.
    - **The list comes from the Jobs channel**, eager-loaded with `department`, and grouped by department in the Departments structure order. Jobs without a department go under "Other"|t.
    - Card: `rounded-lg bg-white p-6 shadow-subtle ring-1 ring-inset ring-black/5`; title `text-xl font-medium`; meta `text-base text-black/60` = department • location • employment type • workplace (the workplace is hidden when On-site, as in Figma); Tabler `arrow-up-right`.
    - The whole card is the link (`externalLink`, opens in a new tab when set there). Without a link it's a plain card with no arrow.
    - With no jobs: the heading and a `|t` "No open roles right now." message.
  - **image** (Figma Careers 6-7092; migration `m261120_330000_image_block`): `image` (one asset, shown edge to edge at its own ratio, never cropped), `text` (optional caption under it, `text-sm text-black/60`). `<figure>` + `<figcaption>`; `alt` from the asset; lazy-loaded with `sizes="100vw"`. No scheme or spacing (no padding of its own).
  - **cardGrid › textCards** (Figma Careers 6-6770 "Our Values"; migration `m261120_360000_card_grid_text_cards`): `cardLayout` option `textCards` "Text cards (emoji, no image)", plus `glow` (lightswitchOff instance, `.glow-bg`) on cardGrid.
    - Heading: centred `text-4xl md:text-headline` medium, sentence case (Careers style, not the uppercase Home heading); `gap-15` to the cards.
    - Cards: white `rounded-lg p-7.5 shadow-card` (white/10 on dark), left- and top-aligned, equal heights from `sm` (`sm:auto-rows-fr`), 3 columns from `lg` in `max-w-narrow` (2 from `sm`), gap 10. Emoji `text-headline`, title `text-2xl font-semibold` (Figma 22), text `text-base text-black/60`, gap 4. A card's `primaryButton` shows as an underlined link under the text. With a card `image`, it shows on top (see Organizers below).
  - **Organizers page (Figma 17-267; migration `m261120_410000_organizers`)**. Landing-page sections use **sentence-case headings**: `_components/section-heading.twig` takes `plain: true` (`text-4xl md:text-headline font-medium`, no uppercase); Home keeps the uppercase display style.
    - **Banner › centered** (17-426): eyebrow `text-xl md:text-2xl font-medium text-black/60`; heading `md:text-headline` medium; rotating words in a green gradient (`from-success to-lime`, new token `lime` #9ed104); text `text-xl md:text-2xl text-black/60`; `glow-bg`. The banner `images` become a **collage of tilted cards** around the text from `xl` (`.banner-collage`, 15 slots from Figma; slots 4, 8, 14, 15 are round avatars; images keep their own ratio, `rounded-lg shadow-card`, decorative). Below `xl` the collage is hidden (it needs the width).
    - **testimonials › quotes** (17-591): a round photo (`image`) overlapped by a tilted club logo (new `logo` instance of the base `image` field on `testimonial`, "Logo (beside the photo)"); quote `text-2xl font-semibold` in quotes; name · detail `text-xl text-black/60`; plain heading; `glow-bg`.
    - **featureRows › alternating** (17-640): from `lg` a 12-column grid (text 5 / image 7, gap 40), rows 100 apart, sides alternate; title `text-4xl font-bold`; text `text-xl md:text-2xl text-black/60`, a blank line = a new paragraph; image `rounded-lg`, ratio 660/422; button `rounded` dark. featureRows gets `glow` (lightswitchOff instance) → `glow-bg`.
    - **cardGrid › textCards** shows the card `image` on top when set (ratio 4/3, `rounded-t-lg`; the card is `overflow-hidden` with the text padded); text is then `text-xl` (Figma 17-728).
    - **cardGrid › photoCards** (new `cardLayout` option "Photo cards (text over the image)", 17-782): cards `rounded-lg` ratio 340/280 with the image full-bleed, a bottom `from-transparent to-black/70` gradient, title `text-title font-medium` white, text `text-xl text-white/80`; 3 columns from `md`, gap 16; the whole card links when `primaryButton` is set. The same heading group and buttons as textCards.
    - "Get started for free" is a **textCards block with no cards** (heading, text, two buttons).
  - **Birthday Invitations page (Figma 17-890; migration `m261120_420000_birthday`)**:
    - **Banner headings are uppercase only on Home** (entry type `home`); every other page's banner heading is sentence case (`text-4xl md:text-headline font-medium`), like the plain section headings.
    - **Banner › split** (17-1049) on a landing page: text left (heading, `text-xl` black/60 text, button), image right `rounded-xl` (Figma 580×330, 20px radius), `glow-bg`. Home's split keeps its dark chat layout.
    - **imageGallery › grid** (17-1088): plain heading + subtitle (`text`), tiles **3:2** `rounded-md` in 3 columns from `md` (`max-w-narrow`, gap 20 × 52), caption = the asset title, `text-lg font-bold` centred, 12px below. New `note` (plainText instance, "Note above the buttons", e.g. "Choose from thousands of options") in `text-xl md:text-2xl text-black/60` above the buttons. (The `row` layout keeps square tiles.)
    - **cardGrid › textCards with images** (17-1271): a card's `primaryButton` is a small dark pill (`rounded-full bg-ink px-4 py-2 font-bold text-white` + Tabler `arrow-up-right`) instead of an underlined link.
    - **ctaBanner › simple** (new `ctaLayout` option "Simple (heading, text, badges)", 17-1368): no images or min height; plain centred heading + `text` (black/60) + buttons + store badges (`showAppBadges`), `py-16 md:py-25`, gap 60. ctaBanner has `glow` (lightswitchOff instance) → `glow-bg` on the simple layout (Invitation Maker 17-2485).
  - **Invitation Maker page (Figma 17-1440; migration `m261120_430000_invitation_maker`)**:
    - Banner › split with the eyebrow pill (17-1599), as Birthday.
    - **imageGallery › row** (17-1931): eyebrow (accent) + plain heading + text **left-aligned** in `max-w-narrow`; square tiles `rounded-xl` (Figma 152px, radius 20) in 6 columns from `lg` (3 from `sm`), gap 24; caption `text-xl font-semibold` (Figma 22; at 24 "Kids’ Birthday" wrapped); button centred.
    - **Image text cards** (Organizers 20px, Birthday 16, here 18): one size for all, `text-lg`.
    - **testimonials › reviews** (17-2211): `bg-surface` section; eyebrow + plain heading + text left-aligned in `max-w-narrow`; 3 white cards (`rounded-md shadow-card p-4`): title (`heading`, bold) + stars on the left, `personDetail` (date) and `personName` stacked right (`text-xs text-black/40`), the quote below (`text-sm`).
    - Rich text (17-2385) and FAQ, comparison: built earlier; the rich text gets its Figma copy.
  - **Sell Tickets page (Figma 17-2557, a dark page; migration `m261120_440000_sell_tickets`)**:
    - `pageBackground` gets **`dark`** (black body, so gaps and the area around the footer are black). Every block on the page uses scheme `dark`.
    - **Banner › split, dark** (17-2716): sentence-case white heading, text `text-line`, light button, the ticket image (Figma's video poster) on the right.
    - **Left-header card layouts** (cardGrid `showcase` (new option "Showcase (big image cards)"), `carousel`, `bento`): heading **left** (`text-4xl md:text-6xl font-bold`, Figma 56) + optional `text` (`text-xl md:text-title`, 80% opacity) with the buttons on the right, in `max-w-site`.
      - **showcase** (17-2788): 3 columns from `lg`, gap 24; card = image `rounded-lg` ratio 450/532 + title `text-3xl font-bold` + text `text-xl` (80% opacity).
      - **carousel** (17-4296): the same cards, 300px wide (image ratio 300/236), in a horizontal **scroll-snap row** (`.scroll-row`, no JS; focusable with an `aria-label`) that runs to the right edge.
      - **bento** (17-3995): 2 columns from `lg`; the **first card spans two rows**; panels `rounded-lg bg-linear-to-b from-white/10 to-white/15` (black/5 → black/10 on light), title `text-2xl font-bold`, text `text-xl` (65%), the card image under the text (the first one full width, the others centred, at their own ratio).
    - **imageGallery** gets `scheme` (light / white / soft / dark: the heading and captions follow it). **Marquee with a heading** (17-3649: posters) uses tiles `h-55 md:h-75`, gap 24, `rounded-md`, and a left-aligned heading; the headingless Careers photo strip keeps 220 / 10 / `rounded-photo`.
    - **ctaBanner › simple with an image** (17-2992 "See you at the door."): heading `md:text-banner` bold, the first `images` asset centred below it (`max-w-128`, `shadow-glow`).
  - **imageGallery › marquee** (Figma Careers 6-6667 photo strip, Sell Tickets 17-3649 posters; migration `m261120_350000_gallery_marquee`):
    - A row that scrolls on its own (`.gallery-marquee__track`, paused under reduced motion). Tiles are a **fixed height** (160px phone, 220px from `md`) at **each image's own ratio** (never cropped by the template), gap 10, `rounded-photo` (10px token).
    - For a uniform strip, editors crop the photos in Craft's image editor. Only the first pass is read by screen readers; the copy is `aria-hidden` with empty alt.
    - Without a heading, the strip has no top margin of its own. imageGallery has `spacing` (except the fan layout, whose padding isn't on the `<section>`); the Careers strip uses `noTop` under textMedia, as in Figma (6-6646 is one 100px-padded section).
  - **pressList** (Figma Press 18-6161; migration `m261120_340000_press_list`): `heading` (optional; the Press page's banner already has the h1), `spacing`.
    - **The list comes from the Press mentions channel**, newest first (`postDate desc`). Each row: a big muted number (01, 02…, `text-3xl md:text-6xl lg:text-display` (70px; Figma 72), black/20, `tabular-nums`), the headline (entry title, `text-xl md:text-3xl lg:text-4xl` medium; row 120px at 1440 as in Figma), and the meta `text-lg text-black/60` = publication — date (`F j, Y`), then Tabler `arrow-up-right`.
    - Rows are an `<ol>` (the number is decorative, `aria-hidden`). The whole row is the link (`externalLink`, its own new-tab setting); hover = `bg-surface`, `rounded-xl`. A mention without a link is a plain row with no arrow.
    - With no mentions: the heading (if any) and a `|t` "No press mentions yet." message.
- **Nested Matrix fields:**
  - `cards`: `card` (image, emoji, heading, text, primaryButton = the card's text link)
  - `testimonials`: `testimonial` (text = quote, heading, personName, personDetail, image, rating) and `stat`
- **Page presets (Shopify-style step 5; migration `m261120_250000_page_presets`, module `modules/`).** A new page can start from a ready set of blocks:
  - Presets are entries in the **`pagePresets` Channel** (no URLs; entry type `pagePreset`: title + `pageBuilder`), built like a page.
  - The `page` entry type has a **`pagePreset`** field on its Page tab (Entries: that section, max 1).
  - **The Craft module `modules/PagePresets.php` copies the preset's blocks into the page once**: when the page is saved (not as a draft) with a preset chosen and an empty Content tab. Each block is copied with `Elements::duplicateElement()`, nested items included. Editors then change the copy freely, and later preset changes don't touch existing pages. Use a reusable section for anything that must stay in sync.
  - **Drafts are skipped on purpose:** the CP's draft autosave would send the page without the copied blocks and delete them.
  - The module is registered in `config/app.php` (`modules` + `bootstrap`), autoloaded via `composer.json` `"modules\\": "modules/"`. Keep it small, with one class per feature.
  - `faqItems`: `faqItem` (title = question, body)
- **Reusable sections (Shopify-style step 4; migration `m261120_240000_reusable_sections`; kept: the user's decision, 2026-11-20).** For a section that appears the same on several pages ("Get the app", newsletter, contact CTA):
  - Build it **once** as an entry in the **`reusableSections` Channel** (no URLs; entry type `reusableContent`: title + `pageBuilder`). Then place it on any page with the **`reusableSection` block** (field `reusableEntry`: Entries, that section only, max 1).
  - Editing the entry updates every page that uses it. Prefer this over copying a block onto several pages.
  - `_blocks/reusableSection.twig` renders the entry's pageBuilder blocks with the page's heading level. **A reusableSection block inside a reusable entry is skipped**, so they can never loop.
  - CP: the "Pages" sidebar page; "Add block" group **Reusable**; colour cyan.
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
  | cyan | reusable | reusableSection block (clone); the `reusableContent` entries (recycle); the `pagePreset` entries (object-group) |
  | gray | items inside a block | card (id-card), faqItem (circle-question), testimonial (comments), stat (chart-simple), button (hand-pointer), socialLink (share-nodes), contactDetail (address-card) |
  | none | pages and settings | home (house), page (file-lines), blogPost (rss), job (briefcase), pressMention (link), siteSettings (gear), newsletter (envelope), googleStructureData (code), llmsText (robot), blogCategory (hashtag), department (layer-group) |

  **A new entry type gets an icon and a colour from this table in its migration.**
- **CP sidebar: entry index pages** (Craft 5.9+). The sidebar pages **follow the site's content, not a fixed list.** Page names and pages change as sections are added; every site (and every new site) decides its own. The principle:
  1. **First page: "Pages"**: All entries, Home, and the `pages` Channel (everything editors build with the page builder).
  2. **One page per content area that has its own sections** (a channel plus its taxonomies or related items). Name it after the area as editors know it, e.g. Blog (Blog + Blog categories), Careers (Jobs + Departments).
  3. **Last page: "Site Setup"**: the settings and system Singles.
  4. **Never name a page after one of Craft's own CP items** (Settings, Entries, Assets, Users, Utilities, Dashboard, Plugin Store, GraphQL). A page called "Settings" appeared twice next to Craft's Settings; that's why it's "Site Setup" (icon `sliders`).
  5. **A new section or Single:** add it to the matching page in its migration, or create a new page for a new content area (Craft CP icon from `vendor/craftcms/cms/src/icons/solid`). A source with no config lands on the first page.
  - **How it's stored:** `elementSources.craft\elements\Entry` (each source has a `page`) and `elementSourcesPages` (icons) in the project config. Sections use the source key `section:<uid>`; Singles use the Expanded Singles plugin's `single:<section uid>`. To change it, re-save the whole list with `ElementSources::saveSources()` + `savePageSettings()` (see migrations `m261120_220000…` and `m261120_230000…`).
  - **Current layout (2026-11-20):** Pages (All entries, Home, Pages, Reusable sections, Page presets) · Blog (Blog, Blog categories) · Careers (Jobs, Departments) · Press (Press mentions) · Site Setup (Site settings, Newsletter, Google Structured Data, LLMs Text, Page not found / error / maintenance).
- **Page builder "Add block" menu groups** (Craft 5.9+, same migration). The groups match the colours above:
  - **Call to action:** ctaBanner, promoBar
  - **Text:** richText, faq, textMedia, comparison
  - **Cards & lists:** cardGrid, featureRows, cityList, jobList, pressList
  - **Media:** imageGallery, image
  - **Social proof:** testimonials
  - **Reusable:** reusableSection

  The group is stored per Matrix field (`pageBuilder` entry types `['uid' => …, 'group' => …]`). **A new block gets a group in its migration.** Small Matrix fields (buttons, cards, faqItems…) don't use groups.
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
    - Only add it to blocks whose padding sits on the `<section>` (cardGrid, cityList, testimonials, ctaBanner, textMedia, jobList, faq, richText, pressList, imageGallery except fan). Hero (fixed-header clearance) and featureRows (padding on inner wrappers) don't have it.
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
