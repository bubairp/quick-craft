<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;

/**
 * One demo page with every "Get the app" (ctaBanner) option, to compare them:
 *   /cta-options-demo — spiral·light, rows·light, spiral·dark, rows·dark
 * (copies of Home's banner; only ctaLayout and theme differ).
 * Replaces the single-option /cta-rows-demo page (same entry: renamed, blocks rebuilt;
 * the old URL simply stops working — it was only a demo).
 *
 * DEMO PAGE ONLY: delete it before launch. Both layouts stay available in the CP.
 */
class m261119_160000_cta_options_demo extends Migration
{
    private const VARIANTS = [
        ['spiral', 'light'],
        ['rows', 'light'],
        ['spiral', 'dark'],
        ['rows', 'dark'],
    ];

    public function safeUp(): bool
    {
        $elements = Craft::$app->getElements();
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;

        $home = Entry::find()->section('home')->one();
        $source = $home?->getFieldValue('pageBuilder')->type('ctaBanner')->one();
        if (!$source) {
            echo "    > Home ctaBanner not found — skipped.\n";
            return true;
        }

        $page = Entry::find()->section('landingPages')->slug(['cta-options-demo', 'cta-rows-demo'])->status(null)->one();
        if ($page && $page->slug === 'cta-options-demo') {
            return true; // already done
        }
        if (!$page) {
            $page = new Entry([
                'sectionId' => $entries->getSectionByHandle('landingPages')->id,
                'typeId' => $entries->getEntryTypeByHandle('landingPage')->id,
                'siteId' => $siteId,
            ]);
        }
        $page->title = 'Get the app — all options';
        $page->slug = 'cta-options-demo';
        if (!$elements->saveElement($page)) {
            throw new \RuntimeException('page: ' . json_encode($page->getErrors()));
        }

        // remove the page's existing blocks, then add one banner per variant
        foreach ($page->getFieldValue('pageBuilder')->status(null)->all() as $old) {
            $elements->deleteElement($old, true);
        }
        $base = [];
        foreach ($source->getFieldLayout()->getCustomFields() as $f) {
            $base[$f->handle] = $f->serializeValue($source->getFieldValue($f->handle), $source);
        }
        $type = $entries->getEntryTypeByHandle('ctaBanner');
        foreach (self::VARIANTS as $i => [$layout, $theme]) {
            $block = new Entry([
                'fieldId' => $fields->getFieldByHandle('pageBuilder')->id,
                'ownerId' => $page->id,
                'primaryOwnerId' => $page->id,
                'typeId' => $type->id,
                'siteId' => $siteId,
                'sortOrder' => $i + 1,
            ]);
            $block->setFieldValues(array_merge($base, ['ctaLayout' => $layout, 'theme' => $theme]));
            if (!$elements->saveElement($block)) {
                throw new \RuntimeException("block $layout/$theme: " . json_encode($block->getErrors()));
            }
        }
        $elements->invalidateCachesForElement($page);
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
