<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Entry;
use craft\helpers\Db;
use craft\models\Section;
use craft\models\Section_SiteSettings;

/**
 * Only Home is a page Single (the user's rule, as in Site7 Engine; CLAUDE.md › Only Home is a page Single).
 *  1. Section `landingPages` → `pages` "Pages" (Structure, nested pages allowed,
 *     URI `{parent ? parent.uri ~ '/'}{slug}`); entry type `landingPage` → `page` "Page".
 *  2. Careers (Single) and Press (Single) entries are moved into `pages` with their IDs (menus link
 *     to them by entry ID), URIs and field values; then the Singles and their entry types are deleted.
 *     If the project config was applied first (the Singles already deleted, their entries trashed), the
 *     trashed entries are restored into `pages`.
 *  3. System pages, Singles without URLs using the `page` entry type: pageNotFound, pageError,
 *     pageMaintenance, rendered by templates/404.twig, 500.twig and 503.twig.
 *  4. Settings Singles googleStructureData (JSON-LD in <head>) and llmsText (/llms.txt).
 */
class m261120_200000_pages_structure extends Migration
{
    private const MOVE = ['careers', 'press'];
    private const SYSTEM = [
        // handle => [name, starter heading, starter text]
        'pageNotFound' => ['Page not found', 'Page not found', 'The page you’re looking for doesn’t exist or has moved.'],
        'pageError' => ['Page error', 'Something went wrong', 'We’re looking into it. Please try again in a moment.'],
        'pageMaintenance' => ['Page maintenance', 'Back soon', 'We’re making some improvements. Please check back shortly.'],
    ];
    /** Fields copied when an entry changes to the `page` entry type (they all exist on it). */
    private const PAGE_FIELDS = ['pageBackground', 'heroLayout', 'scheme', 'eyebrow', 'heading', 'rotatingWords', 'text', 'images', 'chatMessages', 'seo'];

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();

        // 1. Rename
        $pageType = $entries->getEntryTypeByHandle('page') ?? $entries->getEntryTypeByHandle('landingPage');
        if ($pageType->handle !== 'page') {
            $pageType->handle = 'page';
            $pageType->name = 'Page';
            $this->check($entries->saveEntryType($pageType), $pageType, 'page type');
        }
        $pages = $entries->getSectionByHandle('pages') ?? $entries->getSectionByHandle('landingPages');
        if ($pages->handle !== 'pages') {
            $pages->handle = 'pages';
            $pages->name = 'Pages';
            $pages->maxLevels = null;
            foreach ($pages->getSiteSettings() as $settings) {
                $settings->uriFormat = "{parent ? parent.uri ~ '/'}{slug}";
            }
            $this->check($entries->saveSection($pages), $pages, 'pages section');
            echo "    > landingPages → pages (Structure, nested pages allowed)\n";
        }

        // 2. Careers / Press → pages
        foreach (self::MOVE as $handle) {
            $section = $entries->getSectionByHandle($handle);
            $entry = $section ? Entry::find()->section($handle)->status(null)->one() : null;
            $values = [];
            if ($entry) {
                foreach (self::PAGE_FIELDS as $h) {
                    if ($entry->getFieldLayout()?->getFieldByHandle($h)) {
                        $values[$h] = $entry->getFieldLayout()->getFieldByHandle($h)->serializeValue($entry->getFieldValue($h), $entry);
                    }
                }
                $id = $entry->id;
            } else {
                // The project config already deleted the Single: find its trashed entry by URI
                $id = (new Query())->select(['es.elementId'])
                    ->from(['es' => Table::ELEMENTS_SITES])
                    ->innerJoin(['e' => Table::ELEMENTS], '[[e.id]] = [[es.elementId]]')
                    ->where(['es.uri' => $handle, 'e.type' => Entry::class, 'e.draftId' => null, 'e.revisionId' => null])
                    ->andWhere(['not', ['e.dateDeleted' => null]])
                    ->scalar();
                if (!$id) {
                    echo "    > $handle: nothing to move\n";
                    continue;
                }
            }

            // Drafts/revisions of the old type can't follow it; remove them
            Db::delete(Table::ELEMENTS, ['canonicalId' => $id]);
            Db::update(Table::ENTRIES, ['sectionId' => $pages->id, 'typeId' => $pageType->id], ['id' => $id]);
            Db::update(Table::ELEMENTS, ['dateDeleted' => null], ['id' => $id]);

            $moved = Entry::find()->id($id)->status(null)->one();
            if (!$moved) {
                throw new \RuntimeException("$handle ($id) could not be loaded after the move.");
            }
            if (!$moved->lft) {
                Craft::$app->getStructures()->appendToRoot($pages->structureId, $moved);
            }
            $moved->setFieldValues($values);
            $moved->slug = $handle;
            if (!Craft::$app->getElements()->saveElement($moved)) {
                throw new \RuntimeException("$handle: " . json_encode($moved->getErrors()));
            }
            echo "    > $handle (#$id) moved into pages → /{$moved->uri}\n";

            if ($section) {
                $entries->deleteSection($section);
            }
            if ($oldType = $entries->getEntryTypeByHandle($handle)) {
                $entries->deleteEntryType($oldType);
            }
        }

        // 3. System pages
        foreach (self::SYSTEM as $handle => [$name, $heading, $text]) {
            if (!$entries->getSectionByHandle($handle)) {
                $section = new Section([
                    'name' => $name,
                    'handle' => $handle,
                    'type' => Section::TYPE_SINGLE,
                    'enableVersioning' => true,
                    'uid' => $this->stableUid("section:$handle"),
                    'previewTargets' => [],
                ]);
                $section->setSiteSettings(array_map(fn($site) => new Section_SiteSettings([
                    'siteId' => $site->id,
                    'hasUrls' => false,
                ]), Craft::$app->getSites()->getAllSites(true)));
                $section->setEntryTypes([$pageType]);
                $this->check($entries->saveSection($section), $section, $handle);
            }

            // A Single gets its entry when the section is saved (here, or by the project config);
            // give it starter content if it has none yet (editors change it)
            $entry = Entry::find()->section($handle)->status(null)->one();
            if ($entry && !trim((string)$entry->getFieldValue('heading'))) {
                $entry->title = $name;
                $entry->setFieldValues(['heroLayout' => 'centered', 'heading' => $heading, 'text' => $text]);
                Craft::$app->getElements()->saveElement($entry);
            }
            echo "    > Single $handle created\n";
        }

        // 4. Settings Singles: Google Structured Data, LLMs Text (each its own entry type, one plainText instance)
        $plainText = Craft::$app->getFields()->getFieldByHandle('plainText');
        foreach ([
            'googleStructureData' => ['Google Structured Data', 'structuredData', 'Structured data (JSON-LD)', 'Valid JSON-LD, e.g. {"@context":"https://schema.org","@type":"Organization",…}. Printed in every page’s <head>; invalid JSON is skipped.', 'code', '/'],
            'llmsText' => ['LLMs Text', 'llmsContent', 'llms.txt content', 'Markdown served at /llms.txt for AI crawlers.', 'robot', 'llms.txt'],
        ] as $handle => [$name, $fieldHandle, $label, $instructions, $icon, $previewUrl]) {
            if ($entries->getSectionByHandle($handle) || !$plainText) {
                continue;
            }
            $type = $entries->getEntryTypeByHandle($handle);
            if (!$type) {
                $layout = new \craft\models\FieldLayout(['type' => Entry::class, 'uid' => $this->stableUid("layout:$handle")]);
                $tab = new \craft\models\FieldLayoutTab(['name' => 'Content', 'layout' => $layout, 'uid' => $this->stableUid("tab:$handle:Content")]);
                $tab->setElements([new \craft\fieldlayoutelements\CustomField($plainText, [
                    'handle' => $fieldHandle, 'label' => $label, 'instructions' => $instructions,
                    'uid' => $this->stableUid("el:$handle:$fieldHandle"),
                ])]);
                $layout->setTabs([$tab]);
                $type = new \craft\models\EntryType([
                    'name' => $name, 'handle' => $handle, 'icon' => $icon, 'showStatusField' => false,
                    'uid' => $this->stableUid("entryType:$handle"),
                ]);
                $type->setFieldLayout($layout);
                $this->check($entries->saveEntryType($type), $type, "$handle type");
            }
            $section = new Section([
                'name' => $name, 'handle' => $handle, 'type' => Section::TYPE_SINGLE, 'enableVersioning' => true,
                'uid' => $this->stableUid("section:$handle"),
                'previewTargets' => [['label' => $name, 'urlFormat' => $previewUrl, 'refresh' => true]],
            ]);
            $section->setSiteSettings(array_map(fn($site) => new Section_SiteSettings(['siteId' => $site->id, 'hasUrls' => false]), Craft::$app->getSites()->getAllSites(true)));
            $section->setEntryTypes([$type]);
            $this->check($entries->saveSection($section), $section, $handle);
            echo "    > Single $handle created\n";
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
