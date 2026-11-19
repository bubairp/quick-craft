<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fields\Dropdown;

/**
 * "Get the app" banner: third background layout `rowsSpot` — the collage rows in grayscale,
 * with the original colours showing in a soft circle around the cursor (src/js/cta-rows.js).
 * Also adds it (light + dark) to the demo page /cta-options-demo, which then shows all six
 * combinations. Home is unchanged (spiral).
 */
class m261119_170000_cta_rows_spotlight extends Migration
{
    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();
        $elements = Craft::$app->getElements();

        // 1. Dropdown option
        $field = $fields->getFieldByHandle('ctaLayout');
        if ($field instanceof Dropdown && !in_array('rowsSpot', array_column($field->options, 'value'), true)) {
            $options = $field->options;
            $options[] = ['label' => 'Collage rows — grayscale, colour spotlight', 'value' => 'rowsSpot', 'default' => false];
            $field->options = $options;
            if (!$fields->saveField($field)) {
                throw new \RuntimeException('ctaLayout: ' . json_encode($field->getErrors()));
            }
        }

        // 2. Demo page: append rowsSpot · light and rowsSpot · dark
        $page = Entry::find()->section('landingPages')->slug('cta-options-demo')->status(null)->one();
        $home = Entry::find()->section('home')->one();
        $source = $home?->getFieldValue('pageBuilder')->type('ctaBanner')->one();
        if (!$page || !$source) {
            echo "    > Demo page or Home banner not found — option added, demo skipped.\n";
            return true;
        }
        if ($page->getFieldValue('pageBuilder')->type('ctaBanner')->ctaLayout('rowsSpot')->status(null)->exists()) {
            return true;
        }
        $base = [];
        foreach ($source->getFieldLayout()->getCustomFields() as $f) {
            $base[$f->handle] = $f->serializeValue($source->getFieldValue($f->handle), $source);
        }
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $next = $page->getFieldValue('pageBuilder')->status(null)->count() + 1;
        foreach (['light', 'dark'] as $i => $theme) {
            $block = new Entry([
                'fieldId' => $fields->getFieldByHandle('pageBuilder')->id,
                'ownerId' => $page->id,
                'primaryOwnerId' => $page->id,
                'typeId' => $entries->getEntryTypeByHandle('ctaBanner')->id,
                'siteId' => $siteId,
                'sortOrder' => $next + $i,
            ]);
            $block->setFieldValues(array_merge($base, ['ctaLayout' => 'rowsSpot', 'theme' => $theme]));
            if (!$elements->saveElement($block)) {
                throw new \RuntimeException("rowsSpot/$theme: " . json_encode($block->getErrors()));
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
