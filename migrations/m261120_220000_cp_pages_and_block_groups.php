<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;

/**
 * Craft 5.9 features (CLAUDE.md › CP sidebar / Page builder "Add block" menu groups):
 *  1. Entry index pages in the CP sidebar: Pages · Blog · Careers · Press · Settings.
 *     Saved as source configs with a `page` (elementSources) + page icons (elementSourcesPages).
 *     Singles use the Expanded Singles plugin's keys (`single:<section uid>`); a source missing
 *     from the config lands on the first page at runtime.
 *  2. pageBuilder "Add block" menu groups: Call to action · Text · Cards & lists · Media · Social proof.
 */
class m261120_220000_cp_pages_and_block_groups extends Migration
{
    /** page => [icon, [source keys: '*', 'singles', 'section:<handle>', 'single:<handle>']] */
    private const PAGES = [
        'Pages' => ['file-lines', ['*', 'single:home', 'section:pages']],
        'Blog' => ['rss', ['section:blog', 'section:blogCategories']],
        'Careers' => ['briefcase', ['section:jobs', 'section:departments']],
        'Press' => ['newspaper', ['section:pressMentions']],
        'Settings' => ['gear', [
            'singles', 'single:siteSettings', 'single:newsletter', 'single:googleStructureData', 'single:llmsText',
            'single:pageNotFound', 'single:pageError', 'single:pageMaintenance',
        ]],
    ];

    private const GROUPS = [
        'ctaBanner' => 'Call to action', 'promoBar' => 'Call to action',
        'richText' => 'Text', 'faq' => 'Text', 'textMedia' => 'Text', 'comparison' => 'Text',
        'cardGrid' => 'Cards & lists', 'featureRows' => 'Cards & lists', 'cityList' => 'Cards & lists',
        'jobList' => 'Cards & lists', 'pressList' => 'Cards & lists',
        'imageGallery' => 'Media', 'image' => 'Media',
        'testimonials' => 'Social proof',
    ];

    public function safeUp(): bool
    {
        // 1. CP sidebar pages
        $entries = Craft::$app->getEntries();
        $sources = [];
        $pageSettings = [];
        foreach (self::PAGES as $page => [$icon, $keys]) {
            $pageSettings[$page] = ['icon' => $icon];
            foreach ($keys as $key) {
                if (str_contains($key, ':')) {
                    [$kind, $handle] = explode(':', $key, 2);
                    $section = $entries->getSectionByHandle($handle);
                    if (!$section) {
                        echo "    > $key: section not found — skipped\n";
                        continue;
                    }
                    $key = "$kind:$section->uid";
                }
                $sources[] = ['type' => 'native', 'key' => $key, 'page' => $page];
            }
        }
        $elementSources = Craft::$app->getElementSources();
        $elementSources->saveSources(Entry::class, $sources);
        $elementSources->savePageSettings(Entry::class, $pageSettings);
        echo '    > CP entry pages: ' . implode(' · ', array_keys(self::PAGES)) . "\n";

        // 2. pageBuilder "Add block" groups
        $fields = Craft::$app->getFields();
        $pageBuilder = $fields->getFieldByHandle('pageBuilder');
        if ($pageBuilder) {
            $types = array_map(fn($type) => [
                'uid' => $type->uid,
                'group' => self::GROUPS[$type->handle] ?? null,
            ], $pageBuilder->getEntryTypes());
            $pageBuilder->setEntryTypes($types);
            if (!$fields->saveField($pageBuilder)) {
                throw new \RuntimeException('pageBuilder: ' . json_encode($pageBuilder->getErrors()));
            }
            echo "    > pageBuilder: Add menu grouped\n";
        }

        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
