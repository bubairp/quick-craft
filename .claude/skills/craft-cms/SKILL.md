---
name: craft-cms
description: Use this skill for any Craft CMS work in Quick Craft: building a page section or pageBuilder block from a Figma frame, adding fields or entry types (content migrations), writing Twig templates, components, menus (Simple RP Menu), images and transforms, Tailwind tokens, or checking a section with Playwright.
---

# Craft CMS: Quick Craft workflow

Read `CLAUDE.md` first. It defines the stack and the rules. This skill gives the steps and the checklists.

**Order of work for every request:** (1) write the rule or decision into this skill and/or CLAUDE.md, (2) build or fix it, (3) go on to the next step in `docs/build-plan.md`.

## Page entry types: tabs and the fixed banner

Every page entry type (`home`, `page`) uses four tabs, as in Site7 Engine. Only Home is a Single; every other page is an entry in the `pages` Channel (CLAUDE.md › Only Home is a page Single):

| Tab | Fields |
|---|---|
| **Page** | title, `pageBackground` |
| **Banner** | `heroLayout`, `scheme`, `eyebrow`, `heading`, `rotatingWords`, `text`, `buttons` (common Matrix), `images` (banner / featured image), `chatMessages` |
| **Content** | `pageBuilder` |
| **SEO** | `seo` |

- **The banner is part of the page, not a block.** `_entries/page.twig` includes `_partials/banner.twig` with `{ page: entry }`. It renders only when the banner has a heading or images.
- **Heading levels:** banner heading = `<h1>`; every pageBuilder block gets `headingLevel: 2`. Without a banner heading, the page prints `<h1 class="sr-only">{{ entry.title }}</h1>`.
- **A new page entry type** copies this layout. In a migration, move the existing elements (title, pageBuilder, seo) between tabs instead of recreating them. Their layout element UIDs key the stored content, so a new `CustomField` for an existing field would lose its values.
- **Other entry types with a URL** follow the same idea: e.g. blogPost has Post / Content / SEO.

## Building a section from a Figma frame

1. **Read the frame.** Get the Figma link (file `WdjEJ9NeK2FTVsJcGF6lby`). The file has no variables or components, so read colours, type sizes, spacing and radii directly from the frame. Screenshot it for comparison later.
2. **Tokens.** Map every value to an existing `@theme` token in `src/css/app.css`. If no token fits, add one with a semantic name (`--color-brand-…`, `--spacing-section`, `--text-display`). Don't use arbitrary values in templates.
3. **Content model in a migration.**
   - `ddev craft migrate/create add_<handle>_block`
   - Create the fields and the entry type in `safeUp()` using `Craft::$app->getFields()` and `Craft::$app->getEntries()`. Add the entry type to the `pageBuilder` Matrix field's `entryTypes`. Make it idempotent: check whether the field or entry type exists before creating it.
   - **Fields come from the base field library** (CLAUDE.md › Base field library): `plainText` (the only Plain Text field), `richText`, `richTextFull`, `image`, `images`, `imageVideo`, `video`, `colorcode`, `simpleRpMenu`, `linkWithLabel`, `urlLink`, `lightswitchOff`, `lightswitchOn`, `number`, `money`, `date`, `email`.
     - Add each one as an instance with the block's own handle and label: `new CustomField($fields->getFieldByHandle('plainText'), ['handle' => 'personDetail', 'label' => 'Person detail', 'uid' => <stable uid>])`.
     - Repeating groups come from the common Matrix fields (`buttons`, `socialLinks`, `contactDetails`, `cards`, `faqItems`…).
     - A new page can start from a **page preset** (a `pagePresets` entry, copied once by `modules/PagePresets.php`). Content that must stay in sync is a reusable section instead.
     - A section that repeats on several pages is a **reusable section** (an entry in `reusableSections`, placed with the `reusableSection` block), never a copied block.
     - Create a new field only for a purpose-specific setting (a Dropdown's options, a Table's columns, a character limit), and add it to the CLAUDE.md table.
     - Templates use the instance handle (`block.personDetail`), never the base handle.
   - Give the new entry type an `icon` and a `color` from CLAUDE.md › Entry type icons and colours (CP only; Craft's icon names, e.g. `grid-2`).
   - A new pageBuilder block gets an "Add block" **group** (CLAUDE.md › Page builder "Add block" menu groups). A new section or Single gets a **CP sidebar page** by the principle in CLAUDE.md › CP sidebar: Pages first, one page per content area, "Site Setup" last; never a page named like a Craft CP item (Settings, Entries…).
   - **Section options:** add `scheme` only if the template supports all four schemes, and `spacing` only if the padding sits on the `<section>` (CLAUDE.md › Section options). A new layout is a new option on the block's layout dropdown, not a new block.
   - A new **section with URLs** also gets a sitemap row (`seo_sitemap`, see `m261120_450000_seo_defaults`).
   - Read the Craft source in `vendor/craftcms/cms/src/` for the exact APIs and don't guess.
   - Run `ddev craft up`, then commit the migration together with `config/project/`.
4. **Template** `templates/_blocks/{entryTypeHandle}.twig`:
   - It receives `block` and `headingLevel` (always 2: the page banner holds the `<h1>`). Use `<h{{ headingLevel }}>` for the block's main heading and `headingLevel + 1` inside it. A block with a form also receives `wheelformErrors` / `values` (passed by `_entries/page.twig`).
   - Put shared pieces in `templates/_components/` and include them with `with {...} only`. The existing components are: `button`/`buttons`, `section-heading` (`plain: true` = sentence-case landing-page heading), `card`, `blog-card`, `icon` (Tabler), `social-links`, `contact-details`, `store-links`, `stars`, `wheel-form`. Images use `images.singleImg()` from `_macros/images.twig`, as in Site7 Engine.
   - **A form in a block** = a `newsletterForm` instance (Wheel Form field) + `_components/wheel-form.twig` (`form`, `anchor`, `errors: wheelformErrors`, `values: values`, `dark`). Wrap it in an element with `id="<anchor>"`. The form needs a `submit_message`, and sending email needs the plugin's From email.
   - CKEditor output always goes in `.rich-text` (+ `--base` / `--lead` / `--article`), never Tailwind's `prose`.
5. **Verify.** Run `ddev npm run dev` and add test content in the control panel. Then use Playwright at **375, 768 and 1440 px**:
   - screenshot each width and compare it with the Figma frame
   - open `/blocks` (logged in) and check the block in every scheme (`?scheme=dark` …) next to the others
   - tab through the page and check the focus states
   - empty one optional field at a time and confirm nothing breaks
6. **Commit** the section by itself, e.g. `feat(blocks): add hero block`.

## Block template skeleton

```twig
{% import "_macros/images" as images %}
{# _blocks/example.twig — receives: block, headingLevel
 # Fields: scheme, spacing, heading, body, image #}
{% set image = block.image.eagerly().one() %}
{% set heading = block.heading ?? null %}
{% set hasBody = block.body and block.body|length %}
{% set scheme = block.scheme.value ?? 'light' %}
{% set tag = 'h' ~ (headingLevel ?? 2) %}

{% if heading or hasBody or image %}
<section data-scheme="{{ scheme }}" data-spacing="{{ block.spacing.value ?? 'default' }}" class="px-4 py-16 md:px-10 md:py-25">
  <div class="mx-auto flex max-w-content flex-col gap-10">
    {% if heading %}<{{ tag }} class="text-4xl font-medium md:text-headline">{{ heading }}</{{ tag }}>{% endif %}
    {% if hasBody %}<div class="rich-text rich-text--base">{{ block.body }}</div>{% endif %}
    {% if image %}
      {{ images.singleImg(image, 1200, { sizes: '(min-width: 1280px) 1200px, 100vw' }, 'h-auto w-full rounded-lg') }}
    {% endif %}
  </div>
</section>
{% endif %}
```

## Checklists

**Empty fields**
- [ ] Every optional field is wrapped in an `{% if %}`.
- [ ] Wrapper elements don't render when everything inside them is empty.
- [ ] No `href=""`, empty `<img>` or empty heading.
- [ ] Links come from link fields and are guarded: `{% if block.cta.url ?? false %}`.

**Images**
- [ ] Use Craft native transforms.
- [ ] Render images with `images.singleImg(image, width, options, classes, attr)` as in Site7 Engine (`{% import "_macros/images" as images %}` at the top of the file; an included template imports it again). Options: `ratio`, `lazy`, `sizes`, `decorative`, `quality`, `sources`, `pictureClasses`. It builds `srcset` with **width descriptors** (`…w`) plus a matching `sizes`. Never use density descriptors (`0.5x`) together with `sizes`: browsers then ignore `sizes` (a real bug until 2026-11-20).
- [ ] The ratio changes by breakpoint? Pass `options.sources: [{ media, ratio, width }]` (art direction), not a CSS `aspect-*` switch on one crop.
- [ ] GIFs and SVGs are never transformed (the component serves them as-is); lazy images get `sizes="auto, …"` from the component.
- [ ] Never request a transform wider than the original (Craft upscales by default): the component caps the largest width at the asset's own width.
- [ ] Set `width` and `height` from the transformed image.
- [ ] Use `loading="lazy"`, except the hero, which gets `fetchpriority="high"` and no lazy loading.
- [ ] Take `alt` from the asset's alt field. Use `alt=""` only for decorative images.
- [ ] Eager-load asset and entry relations: `.eagerly()` inside loops, `.with([...])` on top-level queries.

**Accessibility**
- [ ] One `<h1>` per page and no skipped heading levels.
- [ ] Visible `:focus-visible` on every link, button and input.
- [ ] `<nav aria-label>` on navigation.
- [ ] Alpine toggles set `aria-expanded` and `aria-controls`, and Escape closes them.
- [ ] Colour contrast meets AA, using token colours only.

**Copy**
- [ ] No hard-coded content. Microcopy goes through `|t`.

## Common Matrix fields and shared fields

- **Repeating groups:** use the common Matrix fields (CLAUDE.md › Common Matrix fields): `buttons`, `socialLinks`, `contactDetails`. For a new group, reuse an existing item entry type (`button`, `card`, `socialLink`…) in the new Matrix before creating a type.
- **Buttons in a block template:**
  ```twig
  {% include '_components/buttons.twig' with {
      buttons: block.buttons.eagerly().all(),
      variants: { primary: dark ? 'light' : 'dark', secondary: dark ? 'darkOnDark' : 'light' },
      dark: dark, shape: 'rounded', class: 'flex flex-wrap gap-3',
  } only %}
  ```
  `buttonStyle` options are primary, secondary, dark and light:
  - The block maps primary and secondary per scheme.
  - `dark` and `light` are fixed colours, resolved in `_components/buttons.twig`.
  - Any new style must be an existing variant in `_components/button.twig`.
- **Merging two fields into one shared field (keeping content):**
  1. Point the existing layout element at the shared field (`$el->setField($shared)` or set its `fieldUid`), keeping the element's UID.
  2. Set the old handle and label on the element (`$el->handle = 'personName'; $el->label = 'Person name'`).
  3. Save the entry type, then delete the old field.
  - Content is keyed by the layout element's UID, so it carries over, and the project config applied first on another environment gives the same result.
- **Moving values between fields** (e.g. Link fields → `buttons` entries): read each owner's raw `elements_sites.content` at the old layout element UIDs, so it works whether or not the project config already removed the old elements. Then create the nested entries with `new Entry(['fieldId' => …, 'ownerId' => …, 'primaryOwnerId' => …, 'typeId' => …, 'siteId' => …, 'sortOrder' => …])`.

## Settings and taxonomies (everything is an entry)

- **Site-wide settings** are Singles without URLs (`siteSettings`, `newsletter`), not global sets.
  - `templates/_layouts/base.twig` loads them once per request into Craft's front-end `_globals` collection.
  - Read them with `{% set siteSettings = _globals.get('siteSettings') %}`. This works in `only` includes too.
  - Never write `craft.entries.section('siteSettings').one()` in a partial or block. That repeats the query, which is the Site7 problem.
- **Taxonomies** (blog categories, departments) are Structure sections, related through an **Entries** field with `maintainHierarchy`. Don't create category groups.
- A new settings Single: create it in a migration, then add it to the `_globals` block in base.twig.

## Live Preview

- **How Craft refreshes it:** every change reloads the page in a **new iframe** (`?x-craft-live-preview=…&token=…`). On load it calls `contentWindow.scrollTo(x, y)` to restore the scroll position. Lenis keeps that position. Each refresh is a full page load, so every script and animation starts again.
- **Preview targets:**
  - Every section with URLs has `Primary entry page` = `{url}`.
  - Settings Singles have no URL, so give them a target page instead: `siteSettings` → `/` (Home), so header, footer and store-link changes show live. This works because Craft swaps the previewed element into ordinary queries (`Elements::setPlaceholderElement()`), so base.twig's `_globals` query returns the unsaved version.
  - `newsletter` → `blog` (done).
- **Test without logging in:**
  1. Create a token with a throwaway script in `storage/runtime/` (gitignored; delete it afterwards). Use `Craft::$app->getTokens()->createPreviewToken(['preview/preview', ['elementType' => Entry::class, 'canonicalId' => …, 'siteId' => …, 'draftId' => …, 'revisionId' => null, 'userId' => <admin id>]])`.
  2. Load the page URL with `?x-craft-live-preview=x&token=…` inside a same-origin `<iframe>`.
  3. On `load`, call `contentWindow.scrollTo()` the way Craft does.
  4. Check the scroll position stays, wheel scrolling continues from there, and the sticky featureRows slider shows one clean step.
  5. Check at the preview pane's width (~720) and at 375.
  - To prove that unsaved content shows, create a draft (`Craft::$app->getDrafts()->createDraft()`), change a field, preview with its `draftId`, then delete the draft.
- **Never** use `{% cache %}` around content that Live Preview must update without a key based on the element's `dateUpdated`. Drafts keep their canonical's ID, so an ID-only key would show stale content.

## Migrations that create sections or entry types

- **Back up first:** `ddev export-db --file=/tmp/<name>.sql.gz`.
- **Pin UIDs.** After the first local run, copy the new section and entry type UIDs from `config/project/sections|entryTypes/*.yaml` into the migration (`'uid' => …`). On another environment the project config can be applied **before** the migration; pinned UIDs make the two match instead of creating duplicates. See `m261119_220000_entrify_globals_categories`.
- **Expect the project config to have run first.** Look up anything the config may have already removed with `withTrashed` (e.g. `getGroupByHandle($h, true)`, `getSetByHandle($h, withTrashed: true)`), and reuse a section or entry type when it already exists.
- **Test the other-environment path:** `ddev import-db --file=<backup>`, then `ddev craft up`. Check the content, and that `ddev craft project-config/diff` reports no changes.
- **Craft's console actions can be reused** from a migration with `Craft::$app->runAction('entrify/categories', [$handle, 'section' => …, 'interactive' => false])`. Read the action first: some steps only happen in interactive mode (e.g. `sections/create` creates the entry type only then).
- **After saving nested entries,** query them fresh (`Entry::find()->ownerId()->field()`). Block order lives in `elements_owners.sortOrder`.
- **Inserting a block mid-page:** shift `elements_owners.sortOrder` +1 for the page's blocks from the target position, then save the new block with `sortOrder` = that position (see `m261120_350000_gallery_marquee`).
- **Nested entries of drafts are not drafts themselves.**
  - A block inside a page's draft has `draftId = null`; only its owner (`entries.primaryOwnerId`) is the draft. Craft shares the live page's nested items with the draft and only copies the ones that change (a copy has `canonicalId` = the live item).
  - **When a migration loops over nested entries (blocks, cards…), skip those whose primary owner is a draft or revision.** Otherwise the draft gets extra items on top of the shared ones.
  - Real bug, 2026-11-20: Home's provisional draft got duplicate buttons from `m261120_120000`, and saving showed "Buttons should contain at most 3 entries". Fixed by `m261120_260000_fix_draft_buttons`.
  - Check: `elements e join entries n on n.id = e.id join elements o on o.id = n.primaryOwnerId where o.draftId is null and o.revisionId is null`.

## Front-end gotchas (found while building)

- Tailwind v4 spacing and leading utilities only exist in 0.25 steps (`leading-3.5`, not `leading-3.6`). A missing step is silently not generated.
- Swiper: never combine `loop` with `freeMode`. For long drags, render the slide set several times and raise `loopAdditionalSlides`. Swiper's CSS is unlayered, so set easing through `--swiper-wrapper-transition-timing-function`.
- Swiper's CSS is unlayered, so it beats every rule in our `@layer components`, whatever the specificity (`.swiper { position: relative; overflow: hidden }`, the creative effect's slide `overflow: hidden`). Override those with `!important` (or set Swiper's own CSS variables). A vertical Swiper that isn't absolutely positioned inside an auto-height box sizes its slides from its own height and grows without limit (the rotating words box reached 33,554,432px).
- Lenis undoes `window.scrollTo`. In browser tests, scroll with a real wheel event.
- Background Playwright and Chrome windows throttle `requestAnimationFrame` (about 2fps). Judge animation smoothness in a foreground window.
- Craft `svg()` returns `''` for a missing file (and logs a warning), so always guard the output.
- The radius scale is reset to the project tokens (`xs sm md lg xl 2xl photo`), so Tailwind's own `rounded-3xl` etc. silently don't exist. The same goes for colours (`text-red-700` produces nothing; use a token such as `text-danger`).
- Don't name a token after a Tailwind static utility: `--container-prose` lost to the built-in `max-w-prose` (65ch), so the blog column was 654px instead of 700. It's `--container-article` now. Check a new token's utility in the built CSS.
- Wheel Form: in Twig, `form.honeypot` calls `honeypot()` (prints a field or throws); use `form.getHoneypot()` to test for one. A form without `submit_message` (and no plugin Success Message) gives a 500 on a successful submit. A form with "send email" on also needs the plugin's From email, or a successful submit throws "Plugin settings need to be configured." (500); check `storage/logs/web-*.log`.
- Ether SEO: a template without an entry sets `{% set seo = craft.seo.custom('Title'|t, description) %}` at the top (outside blocks). Pass the bare title: `custom()` adds " - siteName" itself.
- Twig: no `{# #}` comments inside a hash literal (`include … with { … }`): it fails with a misleading "Unclosed block" error. Put the comment above the tag.
- After `git stash` / `checkout` of templates, clear Twig's compiled cache (`ddev craft clear-caches/compiled-templates`): restored files can keep an older mtime, so Twig reuses a stale compiled template (every page 500'd: "Unable to find _components/image.twig").
- Locally (devMode) a 404 shows Craft's developer exception page, not `404.twig`: check the error pages at `/404`, `/500`, `/503` directly.
- Playwright: `main section:nth-of-type(2)` counts siblings of the same tag, not matches; use `document.querySelectorAll('main section')[1]`.
- Figma MCP asset URLs only download with the extension appended (`…/asset/<id>.png`). Figma often shows photos cropped (`object-cover`); check the original size before using one as a placeholder.
- A CSS marquee (`translateX(-50%)` loop) needs each half of the track wider than the widest screen; repeat the set and `aria-hidden` the copies. Playwright can't `scrollIntoViewIfNeeded` a moving track; scroll with `evaluate` instead.

## Menus (Simple RP Menu)

Use `craft.simplerpmenu` (lowercase). **Its resolved menus are cached for 24h** (`getResolvedMenuItems`, tag `simple-rp-menu`; cleared only by CP menu edits and entry saves). A migration that changes `simplerpmenu_items` directly must call `\yii\caching\TagDependency::invalidate(Craft::$app->getCache(), ['simple-rp-menu'])`, or the site keeps the old menu (the featured-card images didn't show until it did). Always guard the output with `getMenu(handle)`, because `getRpMenuHTML` prints an error paragraph when the handle is missing. Supported config keys: `menu-id`, `menu-class`, `ul-class`, `li-class`, `link-class`, `sub-menu-ul-class`, `without-container`, `without-ul`. The full API is in CLAUDE.md. If you need anything beyond that, read `vendor/remoteprogrammer/simple-rp-menu/src/services/SimpleRpMenuService.php`.

## Craft MCP (`craft` server: committed `.mcp.json` + optional local override)
- Craft 5.12's built-in MCP server over stdio. The team config is the committed `.mcp.json`: `ddev exec php craft mcp/serve --user=${CRAFT_MCP_USER:-admin}`, with no machine-specific paths. Claude Code running outside WSL on Windows uses a local-scope `craft` override (README › Craft MCP setup). Call `info_get` first; it explains the identifiers, schemas and workflows.
- Keep `.mcp.json` portable: no absolute paths, distro names or usernames; per-developer values come from environment variables (`${VAR:-default}`).
- Good for: checking real content and field values before writing a template or migration, finding entries, drafts and revisions, system status, and content edits the user asks for.
- **Not for content-model changes.** Fields, entry types, sections, volumes, plugins and project config go through content migrations (and the build log). MCP runs as admin and would bypass both.
- **CP › Settings › MCP stays off** (both switches). It controls only the HTTP servers: the authenticated `/mcp` (OAuth, acts as the logged-in user) and the public, no-login `/mcp` (read-only content the admin picks, advertised in `/.well-known/ai-catalog.json`). Our stdio server doesn't need them. Turning either on is a launch decision for the user.
- Testing it by hand: pipe JSON-RPC lines into `ddev exec php craft mcp/serve --user=admin` (never `--raw`, which drops stdin): `initialize`, `notifications/initialized`, `tools/list`.

## Never
- Edit or run write commands in `/home/rp-bubai/my-project/site7-engine`. It's read-only.
- Add Site7 Studio, Device Detect or RP Payment Gateway.
- Guess a plugin API. Read `vendor/` or the docs first.
- **Pages:**
  - Make a Single for a page (only Home is a page Single).
  - Make `pages` a Structure: it's a **Channel**, as Site7's standard pages.
  - Add a section for a new page. A new page is an entry in `pages`.
  - Singles exist only for Home, settings (siteSettings, newsletter, googleStructureData, llmsText) and system pages (pageNotFound, pageError, pageMaintenance).
- Create a second Plain Text field: `plainText` is the only one.
- Use Craft's Icon field (Font Awesome): site icons are Tabler.
- Change a content-model rule the user decided without asking. When in doubt, check Site7 Engine (read-only) and ask.
