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
 * Page presets (Shopify-style step 5; CLAUDE.md › Page presets). The copying is done by the module
 * (modules/PagePresets.php); this migration sets up the content model:
 *  1. Channel `pagePresets` (no URLs), entry type `pagePreset` (title + pageBuilder).
 *  2. Field `pagePreset` (Entries: that section, max 1) on the `page` entry type's Page tab.
 *  3. CP sidebar: on the "Pages" page, after Reusable sections.
 *  4. A starter preset "Landing page": a card grid (3 placeholder cards) + the "Get the app" reusable section.
 */
class m261120_250000_page_presets extends Migration
{
    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();
        $pageBuilder = $fields->getFieldByHandle('pageBuilder');

        // 1. Entry type + section
        $presetType = $entries->getEntryTypeByHandle('pagePreset');
        if (!$presetType) {
            $layout = new FieldLayout(['type' => Entry::class, 'uid' => $this->stableUid('layout:pagePreset')]);
            $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout, 'uid' => $this->stableUid('tab:pagePreset:Content')]);
            $tab->setElements([
                new EntryTitleField(['uid' => $this->stableUid('el:pagePreset:title'), 'instructions' => 'The name editors pick when creating a page, e.g. “Landing page”.']),
                new CustomField($pageBuilder, ['uid' => $this->stableUid('el:pagePreset:pageBuilder')]),
            ]);
            $layout->setTabs([$tab]);
            $presetType = new EntryType([
                'name' => 'Page preset', 'handle' => 'pagePreset', 'icon' => 'object-group', 'color' => Color::Cyan,
                'uid' => $this->stableUid('entryType:pagePreset'),
            ]);
            $presetType->setFieldLayout($layout);
            $this->check($entries->saveEntryType($presetType), $presetType, 'pagePreset type');
        }

        $section = $entries->getSectionByHandle('pagePresets');
        if (!$section) {
            $section = new Section([
                'name' => 'Page presets', 'handle' => 'pagePresets', 'type' => Section::TYPE_CHANNEL,
                'enableVersioning' => true, 'previewTargets' => [],
                'uid' => $this->stableUid('section:pagePresets'),
            ]);
            $section->setSiteSettings(array_map(fn($site) => new Section_SiteSettings(['siteId' => $site->id, 'hasUrls' => false]), Craft::$app->getSites()->getAllSites(true)));
            $section->setEntryTypes([$presetType]);
            $this->check($entries->saveSection($section), $section, 'pagePresets');
            echo "    > Channel pagePresets created\n";
        }

        // 2. Field on the page entry type's Page tab (after the title)
        $field = $fields->getFieldByHandle('pagePreset');
        if (!$field) {
            $field = new Entries([
                'name' => 'Page preset', 'handle' => 'pagePreset',
                'instructions' => 'Optional. When this page is saved with an empty Content tab, the preset’s blocks are copied into it once. Change them freely afterwards.',
                'sources' => ["section:$section->uid"], 'maxRelations' => 1,
                'uid' => $this->stableUid('field:pagePreset'),
            ]);
            $this->check($fields->saveField($field), $field, 'pagePreset field');
        }
        $pageType = $entries->getEntryTypeByHandle('page');
        $layout = $pageType->getFieldLayout();
        if (!$layout->getFieldByHandle('pagePreset')) {
            $tab = $layout->getTabs()[0]; // Page
            $elements = $tab->getElements();
            $at = 1;
            foreach ($elements as $i => $el) {
                if ($el instanceof EntryTitleField) {
                    $at = $i + 1;
                }
            }
            array_splice($elements, $at, 0, [new CustomField($field, ['uid' => $this->stableUid('el:page:pagePreset')])]);
            $tab->setElements($elements);
            $pageType->setFieldLayout($layout);
            $this->check($entries->saveEntryType($pageType), $pageType, 'page type');
            echo "    > page: + pagePreset (Page tab)\n";
        }

        // 3. CP sidebar
        $sources = Craft::$app->getProjectConfig()->get('elementSources.' . Entry::class) ?? [];
        $key = "section:$section->uid";
        if ($sources && !in_array($key, array_column($sources, 'key'), true)) {
            $after = 'section:' . $entries->getSectionByHandle('reusableSections')?->uid;
            $out = [];
            foreach ($sources as $source) {
                $out[] = $source;
                if (($source['key'] ?? null) === $after) {
                    $out[] = ['type' => 'native', 'key' => $key, 'page' => $source['page'] ?? 'Pages'];
                }
            }
            if (count($out) === count($sources)) {
                $out[] = ['type' => 'native', 'key' => $key, 'page' => 'Pages'];
            }
            Craft::$app->getElementSources()->saveSources(Entry::class, $out);
        }

        // 4. Starter preset "Landing page"
        $elements = Craft::$app->getElements();
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        if (!Entry::find()->section('pagePresets')->slug('landing-page')->status(null)->exists()) {
            $preset = new Entry(['sectionId' => $section->id, 'typeId' => $presetType->id, 'siteId' => $siteId, 'title' => 'Landing page', 'slug' => 'landing-page']);
            $this->check($elements->saveElement($preset), $preset, 'preset');

            $grid = new Entry([
                'fieldId' => $pageBuilder->id, 'ownerId' => $preset->id, 'primaryOwnerId' => $preset->id,
                'typeId' => $entries->getEntryTypeByHandle('cardGrid')->id, 'siteId' => $siteId, 'sortOrder' => 1,
            ]);
            $grid->setFieldValues(['cardLayout' => 'grid', 'scheme' => 'light', 'heading' => 'Section heading', 'text' => 'A short introduction to this section.']);
            $this->check($elements->saveElement($grid), $grid, 'preset card grid');
            $cards = $fields->getFieldByHandle('cards');
            for ($i = 1; $i <= 3; $i++) {
                $card = new Entry([
                    'fieldId' => $cards->id, 'ownerId' => $grid->id, 'primaryOwnerId' => $grid->id,
                    'typeId' => $entries->getEntryTypeByHandle('card')->id, 'siteId' => $siteId, 'sortOrder' => $i,
                ]);
                $card->setFieldValues(['heading' => "Card title $i", 'text' => 'Describe this point in a sentence.']);
                $this->check($elements->saveElement($card), $card, 'preset card');
            }

            $getTheApp = Entry::find()->section('reusableSections')->slug('get-the-app')->status(null)->one();
            $reusableType = $entries->getEntryTypeByHandle('reusableSection');
            if ($getTheApp && $reusableType) {
                $ref = new Entry([
                    'fieldId' => $pageBuilder->id, 'ownerId' => $preset->id, 'primaryOwnerId' => $preset->id,
                    'typeId' => $reusableType->id, 'siteId' => $siteId, 'sortOrder' => 2,
                ]);
                $ref->setFieldValue('reusableEntry', [$getTheApp->id]);
                $this->check($elements->saveElement($ref), $ref, 'preset reusable section');
            }
            echo "    > starter preset “Landing page” created\n";
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
