<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Dropdown;
use craft\models\EntryType;
use craft\models\FieldLayoutTab;

/**
 * Page tabs (as in Site7 Engine) + the banner as a fixed part of every page.
 *
 *  - Page entry types (home, landingPage, careers, press) get four tabs:
 *      Page (title, pageBackground) · Banner (the hero fields) · Content (pageBuilder) · SEO (seo)
 *  - blogPost gets Post · Content (body) · SEO.
 *  - Home's hero block (the only one) is moved into Home's Banner fields; then the `hero`
 *    block type is removed from pageBuilder and deleted. Rendering: templates/_partials/banner.twig.
 *  - New field `pageBackground` (paper / surface); Home = surface (was hard-coded in page.twig).
 *
 * Existing layout elements (title, pageBuilder, seo, blog fields) are moved, not recreated: their
 * UIDs key the stored content. New tabs and elements get deterministic UIDs (stableUid()), so every
 * environment builds the same layout as config/project/*.yaml.
 */
class m261120_100000_page_tabs extends Migration
{
    private const PAGE_TYPES = ['home', 'landingPage', 'careers', 'press'];
    private const BANNER = ['heroLayout', 'scheme', 'eyebrow', 'heading', 'rotatingWords', 'text', 'primaryButton', 'secondaryButton', 'images', 'chatMessages'];
    private const PAGE_BACKGROUND_UID = 'b7e2c4a1-6d3f-4a8e-9b5c-2f1e7d0a4c93';

    /** The deleted hero block type's layout element UIDs → field handle (from its last YAML), for the fallback. */
    private const HERO_ELEMENTS = [
        'ba7aa676-8235-41f5-bf6d-c5c5c1b5b00b' => 'heroLayout',
        '09cd984e-3fa7-4b56-a9e2-5dddaea3d45c' => 'scheme',
        'fadac342-6659-4fae-aa4e-affeef485542' => 'eyebrow',
        '00d2b047-98c9-4e7a-8f2d-b684e55d72a4' => 'heading',
        'c75de6a3-66b1-4257-a436-e5b62b4f80f5' => 'rotatingWords',
        'e7a298f6-bfd1-45db-980e-96562b019a56' => 'text',
        '88a8c63f-ffa3-41c1-8c81-aa55161a3ad8' => 'primaryButton',
        'c811bf17-f4e7-4b05-aa20-d71e89246117' => 'secondaryButton',
        '15908276-9bc0-4835-b8a2-56afc160fa22' => 'chatMessages',
    ];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();

        // 1. pageBackground
        $pageBackground = $fields->getFieldByHandle('pageBackground');
        if (!$pageBackground) {
            $pageBackground = new Dropdown([
                'name' => 'Page background',
                'handle' => 'pageBackground',
                'uid' => self::PAGE_BACKGROUND_UID,
                'instructions' => 'Background colour behind sections that use the “Page background” colour scheme.',
                'options' => [
                    ['label' => 'Paper (off-white)', 'value' => 'paper', 'default' => true],
                    ['label' => 'Surface (light grey)', 'value' => 'surface', 'default' => false],
                ],
            ]);
            if (!$fields->saveField($pageBackground)) {
                throw new \RuntimeException('pageBackground: ' . json_encode($pageBackground->getErrors()));
            }
        }

        // 2. Page entry types: Page · Banner · Content · SEO
        foreach (self::PAGE_TYPES as $handle) {
            $type = $entries->getEntryTypeByHandle($handle);
            if (!$type) {
                continue;
            }
            $this->tabs($type, [
                'Page' => ['@title', $pageBackground],
                'Banner' => array_map(fn($h) => $fields->getFieldByHandle($h), self::BANNER),
                'Content' => ['pageBuilder'],
                'SEO' => ['seo'],
            ]);
        }

        // 3. blogPost: Post · Content · SEO
        if ($blog = $entries->getEntryTypeByHandle('blogPost')) {
            $this->tabs($blog, [
                'Post' => ['@title', 'image', 'text', 'blogCategories', 'featured'],
                'Content' => ['body'],
                'SEO' => ['seo'],
            ]);
        }

        // 4. Home: hero block → Banner fields; background = surface
        $home = Entry::find()->section('home')->status(null)->one();
        if ($home) {
            $values = $this->heroValues($home) ?? [];
            $values['pageBackground'] = 'surface';
            $home->setFieldValues($values);
            if (!Craft::$app->getElements()->saveElement($home)) {
                throw new \RuntimeException('Home: ' . json_encode($home->getErrors()));
            }
            echo '    > Home banner: ' . (count($values) - 1) . " fields copied from the hero block\n";
        }

        // 5. Remove the hero block type
        $hero = $entries->getEntryTypeByHandle('hero');
        if ($hero) {
            $pageBuilder = $fields->getFieldByHandle('pageBuilder');
            $pageBuilder->setEntryTypes(array_values(array_filter($pageBuilder->getEntryTypes(), fn(EntryType $t) => $t->handle !== 'hero')));
            if (!$fields->saveField($pageBuilder)) {
                throw new \RuntimeException('pageBuilder: ' . json_encode($pageBuilder->getErrors()));
            }
            foreach (Entry::find()->type('hero')->status(null)->drafts(null)->revisions(null)->all() as $block) {
                Craft::$app->getElements()->deleteElement($block, true);
            }
            $entries->deleteEntryType($hero);
            echo "    > hero block type removed\n";
        }

        return true;
    }

    /**
     * Rebuild a layout's tabs. Items: a field handle (moves the existing element, keeping its
     * UID and content), a Field model (new element), or '@title'.
     */
    private function tabs(EntryType $type, array $tabs): void
    {
        $layout = $type->getFieldLayout();
        if (in_array(array_key_last($tabs), array_map(fn($t) => $t->name, $layout->getTabs()), true)
            && count($layout->getTabs()) === count($tabs)) {
            return; // already done (e.g. the project config applied it first)
        }

        $existing = [];
        foreach ($layout->getTabs() as $tab) {
            foreach ($tab->getElements() as $el) {
                if ($el instanceof EntryTitleField) {
                    $existing['@title'] = $el;
                } elseif ($el instanceof CustomField) {
                    $existing[$el->getField()->handle] = $el;
                }
            }
        }

        $newTabs = [];
        foreach ($tabs as $name => $items) {
            $tab = new FieldLayoutTab([
                'name' => $name,
                'layout' => $layout,
                'uid' => $this->stableUid("tab:{$type->handle}:$name"),
            ]);
            $elements = [];
            foreach ($items as $item) {
                if (is_string($item)) {
                    if (isset($existing[$item])) {
                        $elements[] = $existing[$item];
                    } elseif ($item === '@title') {
                        $elements[] = new EntryTitleField(['uid' => $this->stableUid("el:{$type->handle}:title")]);
                    }
                } elseif ($item) {
                    $elements[] = $existing[$item->handle] ?? new CustomField($item, ['uid' => $this->stableUid("el:{$type->handle}:{$item->handle}")]);
                }
            }
            $tab->setElements($elements);
            $newTabs[] = $tab;
        }
        $layout->setTabs($newTabs);
        $type->setFieldLayout($layout);
        if (!Craft::$app->getEntries()->saveEntryType($type)) {
            throw new \RuntimeException("{$type->handle}: " . json_encode($type->getErrors()));
        }
        echo "    > {$type->handle}: " . implode(' · ', array_keys($tabs)) . "\n";
    }

    /** Home's hero values: from the live block, or (fallback) from the trashed block's raw data. */
    private function heroValues(Entry $home): ?array
    {
        $fields = Craft::$app->getFields();
        $block = Craft::$app->getEntries()->getEntryTypeByHandle('hero')
            ? Entry::find()->ownerId($home->id)->field('pageBuilder')->type('hero')->status(null)->one()
            : null;

        if ($block) {
            $values = [];
            foreach (self::BANNER as $h) {
                $field = $fields->getFieldByHandle($h);
                $values[$h] = $field->serializeValue($block->getFieldValue($h), $block);
            }
            return $values;
        }

        // Fallback: the project config already deleted the hero type (and trashed its blocks).
        $blockId = (new Query())
            ->select(['o.elementId'])
            ->from(['o' => Table::ELEMENTS_OWNERS])
            ->innerJoin(['e' => Table::ENTRIES], '[[e.id]] = [[o.elementId]]')
            ->innerJoin(['t' => Table::ENTRYTYPES], '[[t.id]] = [[e.typeId]]')
            ->where(['o.ownerId' => $home->id, 't.handle' => 'hero'])
            ->orderBy(['o.sortOrder' => SORT_ASC])
            ->scalar();
        if (!$blockId) {
            return null;
        }
        $content = json_decode((string)(new Query())->select('content')->from(Table::ELEMENTS_SITES)
            ->where(['elementId' => $blockId])->scalar(), true) ?: [];
        $values = [];
        foreach (self::HERO_ELEMENTS as $uid => $h) {
            if (array_key_exists($uid, $content)) {
                $values[$h] = $content[$uid];
            }
        }
        $values['images'] = (new Query())->select('targetId')->from(Table::RELATIONS)
            ->where(['sourceId' => $blockId, 'fieldId' => $fields->getFieldByHandle('images')->id])
            ->orderBy(['sortOrder' => SORT_ASC])->column();
        return $values;
    }

    /** A UUID derived from a key: the same on every environment. */
    private function stableUid(string $key): string
    {
        $h = md5("quick-craft:$key");
        return sprintf('%s-%s-4%s-%x%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 13, 3), 8 | (hexdec($h[16]) & 3), substr($h, 17, 3), substr($h, 20, 12));
    }

    public function safeDown(): bool
    {
        return false;
    }
}
