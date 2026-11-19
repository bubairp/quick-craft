---
name: craft-cms
description: Use this skill for any Craft CMS work in Quick Craft: building a page section or pageBuilder block from a Figma frame, adding fields or entry types (content migrations), writing Twig templates, components, menus (Simple RP Menu), images and transforms, Tailwind tokens, or checking a section with Playwright.
---

# Craft CMS: Quick Craft workflow

Read `CLAUDE.md` first. It defines the stack and the rules. This skill gives the steps and the checklists.

**Order of work for every request:** (1) write the rule or decision into this skill and/or CLAUDE.md, (2) build or fix it, (3) go on to the next step in `docs/build-plan.md`.

## Page entry types: tabs and the fixed banner

Every page entry type (home, landingPage, careers, press, and new ones) uses four tabs, as in Site7 Engine:

| Tab | Fields |
|---|---|
| **Page** | title, `pageBackground` |
| **Banner** | `heroLayout`, `scheme`, `eyebrow`, `heading`, `rotatingWords`, `text`, `primaryButton`, `secondaryButton`, `images` (banner / featured image), `chatMessages` |
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
   - **Reuse fields first.** For each field the block needs, look in `config/project/fields/` for one of the same type with the same settings.
     - If one exists, add it to the layout again as a new instance with its own meaningful handle and label: `new CustomField($field, ['handle' => 'personDetail', 'label' => 'Person detail'])`.
     - Create a new field only when the settings must differ (dropdown options, link types, asset sources, CKEditor config, character limit).
     - Never use generic handles in templates (`plainText`, `plainTextSub`). See CLAUDE.md › Content model.
   - Read the Craft source in `vendor/craftcms/cms/src/` for the exact APIs and don't guess.
   - Run `ddev craft up`, then commit the migration together with `config/project/`.
4. **Template** `templates/_blocks/{entryTypeHandle}.twig`:
   - It receives only `block` and `headingLevel` (1 for the first block on the page, otherwise 2). Use `<h{{ headingLevel }}>` for the block's main heading.
   - Put shared pieces in `templates/_components/` and include them with `with {...} only`.
5. **Verify.** Run `ddev npm run dev` and add test content in the control panel. Then use Playwright at **375, 768 and 1440 px**:
   - screenshot each width and compare it with the Figma frame
   - tab through the page and check the focus states
   - empty one optional field at a time and confirm nothing breaks
6. **Commit** the section by itself, e.g. `feat(blocks): add hero block`.

## Block template skeleton

```twig
{# _blocks/hero.twig — receives: block #}
{% set image = block.image.eagerly().one() %}
{% set heading = block.heading ?? null %}

{% if heading or image %}
<section class="py-section">
  <div class="mx-auto max-w-site px-4 md:px-8">
    {% if heading %}<h2 class="text-display">{{ heading }}</h2>{% endif %}
    {% if block.body|length %}<div class="prose">{{ block.body }}</div>{% endif %}
    {% if image %}
      {% include '_components/image.twig' with { image: image, sizes: '(min-width: 1024px) 50vw, 100vw' } only %}
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
- [ ] Generate `srcset` with `image.getSrcset(['640w','1024w','1600w'], transform)` and add a matching `sizes`.
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
  - When the blog listing exists, give `newsletter` a target → `blog`.
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

## Front-end gotchas (found while building)

- Tailwind v4 spacing and leading utilities only exist in 0.25 steps (`leading-3.5`, not `leading-3.6`). A missing step is silently not generated.
- Swiper: never combine `loop` with `freeMode`. For long drags, render the slide set several times and raise `loopAdditionalSlides`. Swiper's CSS is unlayered, so set easing through `--swiper-wrapper-transition-timing-function`.
- Lenis undoes `window.scrollTo`. In browser tests, scroll with a real wheel event.
- Background Playwright and Chrome windows throttle `requestAnimationFrame` (about 2fps). Judge animation smoothness in a foreground window.
- Craft `svg()` returns `''` for a missing file (and logs a warning), so always guard the output.

## Menus (Simple RP Menu)

Use `craft.simplerpmenu` (lowercase). Always guard the output with `getMenu(handle)`, because `getRpMenuHTML` prints an error paragraph when the handle is missing. Supported config keys: `menu-id`, `menu-class`, `ul-class`, `li-class`, `link-class`, `sub-menu-ul-class`, `without-container`, `without-ul`. The full API is in CLAUDE.md. If you need anything beyond that, read `vendor/remoteprogrammer/simple-rp-menu/src/services/SimpleRpMenuService.php`.

## Never
- Edit or run write commands in `/home/rp-bubai/my-project/site7-engine`. It's read-only.
- Add Site7 Studio, Device Detect or RP Payment Gateway.
- Guess a plugin API. Read `vendor/` or the docs first.
