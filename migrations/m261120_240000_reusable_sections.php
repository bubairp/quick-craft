<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\enums\Color;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Entries;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;

/**
 * Reusable sections (Shopify-style step 4; CLAUDE.md › Reusable sections):
 *  1. Channel `reusableSections` (no URLs), entry type `reusableContent` (title + pageBuilder).
 *  2. Field `reusableEntry` (Entries: that section, max 1) and block `reusableSection` in pageBuilder
 *     ("Add block" group Reusable; colour cyan).
 *  3. CP sidebar: the section goes on the "Pages" page.
 *  4. First use: Home's "Get the app" CTA banner becomes the reusable entry "Get the app"; on Home a
 *     reusableSection block takes its place (same position), so other pages can show it too.
 */
class m261120_240000_reusable_sections extends Migration
{
    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();
        $pageBuilder = $fields->getFieldByHandle('pageBuilder');

        // 1. Entry type + section
        $contentType = $entries->getEntryTypeByHandle('reusableContent');
        if (!$contentType) {
            $layout = new FieldLayout(['type' => Entry::class, 'uid' => $this->stableUid('layout:reusableContent')]);
            $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout, 'uid' => $this->stableUid('tab:reusableContent:Content')]);
            $tab->setElements([
                new EntryTitleField(['uid' => $this->stableUid('el:reusableContent:title'), 'instructions' => 'For editors only, e.g. “Get the app”.']),
                new CustomField($pageBuilder, ['uid' => $this->stableUid('el:reusableContent:pageBuilder')]),
            ]);
            $layout->setTabs([$tab]);
            $contentType = new EntryType([
                'name' => 'Reusable section', 'handle' => 'reusableContent',
                'icon' => 'recycle', 'color' => Color::Cyan,
                'uid' => $this->stableUid('entryType:reusableContent'),
            ]);
            $contentType->setFieldLayout($layout);
            $this->check($entries->saveEntryType($contentType), $contentType, 'reusableContent');
        }

        $section = $entries->getSectionByHandle('reusableSections');
        if (!$section) {
            $section = new Section([
                'name' => 'Reusable sections', 'handle' => 'reusableSections', 'type' => Section::TYPE_CHANNEL,
                'enableVersioning' => true, 'previewTargets' => [],
                'uid' => $this->stableUid('section:reusableSections'),
            ]);
            $section->setSiteSettings(array_map(fn($site) => new Section_SiteSettings(['siteId' => $site->id, 'hasUrls' => false]), Craft::$app->getSites()->getAllSites(true)));
            $section->setEntryTypes([$contentType]);
            $this->check($entries->saveSection($section), $section, 'reusableSections');
            echo "    > Channel reusableSections created\n";
        }

        // 2. Field + block
        $field = $fields->getFieldByHandle('reusableEntry');
        if (!$field) {
            $field = new Entries([
                'name' => 'Reusable section', 'handle' => 'reusableEntry',
                'instructions' => 'Choose a section from Entries › Reusable sections. Edit it there to update every page that shows it.',
                'sources' => ["section:$section->uid"], 'maxRelations' => 1,
                'uid' => $this->stableUid('field:reusableEntry'),
            ]);
            $this->check($fields->saveField($field), $field, 'reusableEntry');
        }

        $block = $entries->getEntryTypeByHandle('reusableSection');
        if (!$block) {
            $layout = new FieldLayout(['type' => Entry::class, 'uid' => $this->stableUid('layout:reusableSection')]);
            $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout, 'uid' => $this->stableUid('tab:reusableSection:Content')]);
            $tab->setElements([new CustomField($field, ['uid' => $this->stableUid('el:reusableSection:reusableEntry'), 'required' => true])]);
            $layout->setTabs([$tab]);
            $block = new EntryType([
                'name' => 'Reusable section', 'handle' => 'reusableSection',
                'hasTitleField' => false, 'showSlugField' => false, 'uiLabelFormat' => 'Reusable section',
                'icon' => 'clone', 'color' => Color::Cyan,
                'uid' => $this->stableUid('entryType:reusableSection'),
            ]);
            $block->setFieldLayout($layout);
            $this->check($entries->saveEntryType($block), $block, 'reusableSection');
        }

        $types = $pageBuilder->getEntryTypes();
        if (!in_array('reusableSection', array_map(fn($t) => $t->handle, $types), true)) {
            $config = array_map(fn($t) => ['uid' => $t->uid, 'group' => $t->group], $types);
            $config[] = ['uid' => $block->uid, 'group' => 'Reusable'];
            $pageBuilder->setEntryTypes($config);
            $this->check($fields->saveField($pageBuilder), $pageBuilder, 'pageBuilder');
            echo "    > pageBuilder: + reusableSection (group Reusable)\n";
        }

        // 3. CP sidebar: after the `pages` section, on the "Pages" page
        $elementSources = Craft::$app->getElementSources();
        $sources = Craft::$app->getProjectConfig()->get('elementSources.' . Entry::class) ?? [];
        $key = "section:$section->uid";
        if ($sources && !in_array($key, array_column($sources, 'key'), true)) {
            $pagesKey = 'section:' . $entries->getSectionByHandle('pages')?->uid;
            $out = [];
            foreach ($sources as $source) {
                $out[] = $source;
                if (($source['key'] ?? null) === $pagesKey) {
                    $out[] = ['type' => 'native', 'key' => $key, 'page' => $source['page'] ?? 'Pages'];
                }
            }
            $elementSources->saveSources(Entry::class, $out);
        }

        // 4. Home's CTA banner → the reusable entry "Get the app"
        $home = Entry::find()->section('home')->status(null)->one();
        $cta = $home ? Entry::find()->ownerId($home->id)->field($pageBuilder)->type('ctaBanner')->status(null)->one() : null;
        $existing = Entry::find()->section('reusableSections')->slug('get-the-app')->status(null)->one();
        if ($home && $cta && !$existing) {
            $elements = Craft::$app->getElements();
            $reusable = new Entry([
                'sectionId' => $section->id, 'typeId' => $contentType->id, 'siteId' => $home->siteId,
                'title' => 'Get the app', 'slug' => 'get-the-app',
            ]);
            $this->check($elements->saveElement($reusable), $reusable, 'reusable entry');
            // Move the block itself into the reusable entry (keeps its ID, content, images and buttons)
            $sortOrder = (new \craft\db\Query())->select('sortOrder')->from(\craft\db\Table::ELEMENTS_OWNERS)
                ->where(['elementId' => $cta->id, 'ownerId' => $home->id])->scalar();
            \craft\helpers\Db::update(\craft\db\Table::ELEMENTS_OWNERS, ['ownerId' => $reusable->id, 'sortOrder' => 1], ['elementId' => $cta->id, 'ownerId' => $home->id]);
            \craft\helpers\Db::update(\craft\db\Table::ENTRIES, ['primaryOwnerId' => $reusable->id], ['id' => $cta->id]);
            // …and put a reusableSection block in its place on Home
            $placeholder = new Entry([
                'fieldId' => $pageBuilder->id, 'ownerId' => $home->id, 'primaryOwnerId' => $home->id,
                'typeId' => $block->id, 'siteId' => $home->siteId, 'sortOrder' => (int)$sortOrder,
            ]);
            $placeholder->setFieldValue('reusableEntry', [$reusable->id]);
            $this->check($elements->saveElement($placeholder), $placeholder, 'Home reusableSection block');
            echo "    > Home's CTA banner is now the reusable section “Get the app”\n";
        }

        return true;
    }

    private function check(bool $ok, $model, string $what): void
    {
        if (!$ok) {
            throw new \RuntimeException("$what: " . json_encode($model->getErrors()));
        }
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
