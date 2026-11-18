---
name: craft-cms
description: Use this skill for any Craft CMS work in Quick Craft: building a page section or pageBuilder block from a Figma frame, adding fields or entry types (content migrations), writing Twig templates, components, menus (Simple RP Menu), images and transforms, Tailwind tokens, or checking a section with Playwright.
---

# Craft CMS: Quick Craft workflow

Read `CLAUDE.md` first. It defines the stack and the rules. This skill gives the steps and the checklists.

## Building a section from a Figma frame

1. **Read the frame.** Get the Figma link (file `WdjEJ9NeK2FTVsJcGF6lby`). The file has no variables or components, so read colours, type sizes, spacing and radii directly from the frame. Screenshot it for comparison later.
2. **Tokens.** Map every value to an existing `@theme` token in `src/css/app.css`. If no token fits, add one with a semantic name (`--color-brand-…`, `--spacing-section`, `--text-display`). Don't use arbitrary values in templates.
3. **Content model in a migration.**
   - `ddev craft migrate/create add_<handle>_block`
   - Create the fields and the entry type in `safeUp()` using `Craft::$app->getFields()` and `Craft::$app->getEntries()`. Add the entry type to the `pageBuilder` Matrix field's `entryTypes`. Make it idempotent: check whether the field or entry type exists before creating it.
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

## Menus (Simple RP Menu)

Use `craft.simplerpmenu` (lowercase). Always guard the output with `getMenu(handle)`, because `getRpMenuHTML` prints an error paragraph when the handle is missing. Supported config keys: `menu-id`, `menu-class`, `ul-class`, `li-class`, `link-class`, `sub-menu-ul-class`, `without-container`, `without-ul`. The full API is in CLAUDE.md. If you need anything beyond that, read `vendor/remoteprogrammer/simple-rp-menu/src/services/SimpleRpMenuService.php`.

## Never
- Edit or run write commands in `/home/rp-bubai/my-project/site7-engine`. It's read-only.
- Add Site7 Studio, Device Detect or RP Payment Gateway.
- Guess a plugin API. Read `vendor/` or the docs first.
