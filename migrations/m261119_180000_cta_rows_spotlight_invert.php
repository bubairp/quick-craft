<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\elements\Entry;
use craft\fields\Dropdown;

/**
 * "Get the app" banner: fourth background layout `rowsSpotInvert` — the reverse of rowsSpot:
 * the collage rows in colour, with a grayscale circle following the cursor.
 * Adds it (light + dark) to /cta-options-demo and re-orders that page:
 * light then dark; within each spiral → rows → rowsSpot → rowsSpotInvert. Home unchanged.
 */
class m261119_180000_cta_rows_spotlight_invert extends Migration
{
    private const ORDER = ['spiral', 'rows', 'rowsSpot', 'rowsSpotInvert'];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();
        $elements = Craft::$app->getElements();

        // 1. Dropdown option
        $field = $fields->getFieldByHandle('ctaLayout');
        if ($field instanceof Dropdown && !in_array('rowsSpotInvert', array_column($field->options, 'value'), true)) {
            $options = $field->options;
            $options[] = ['label' => 'Collage rows — colour, grayscale spotlight', 'value' => 'rowsSpotInvert', 'default' => false];
            $field->options = $options;
            if (!$fields->saveField($field)) {
                throw new \RuntimeException('ctaLayout: ' . json_encode($field->getErrors()));
            }
        }

        // 2. Demo page
        $page = Entry::find()->section('landingPages')->slug('cta-options-demo')->status(null)->one();
        $home = Entry::find()->section('home')->one();
        $source = $home?->getFieldValue('pageBuilder')->type('ctaBanner')->one();
        if (!$page || !$source) {
            echo "    > Demo page or Home banner not found — option added, demo skipped.\n";
            return true;
        }
        if (!$page->getFieldValue('pageBuilder')->type('ctaBanner')->ctaLayout('rowsSpotInvert')->status(null)->exists()) {
            $base = [];
            foreach ($source->getFieldLayout()->getCustomFields() as $f) {
                $base[$f->handle] = $f->serializeValue($source->getFieldValue($f->handle), $source);
            }
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
            foreach (['light', 'dark'] as $theme) {
                $block = new Entry([
                    'fieldId' => $fields->getFieldByHandle('pageBuilder')->id,
                    'ownerId' => $page->id,
                    'primaryOwnerId' => $page->id,
                    'typeId' => $entries->getEntryTypeByHandle('ctaBanner')->id,
                    'siteId' => $siteId,
                ]);
                $block->setFieldValues(array_merge($base, ['ctaLayout' => 'rowsSpotInvert', 'theme' => $theme]));
                if (!$elements->saveElement($block)) {
                    throw new \RuntimeException("rowsSpotInvert/$theme: " . json_encode($block->getErrors()));
                }
            }
        }

        // 3. Order: light then dark; spiral → rows → rowsSpot → rowsSpotInvert
        $blocks = $page->getFieldValue('pageBuilder')->status(null)->all();
        usort($blocks, function (Entry $a, Entry $b) {
            $key = fn(Entry $e) => (($e->getFieldValue('theme')->value ?? 'light') === 'dark' ? 10 : 0)
                + (int)array_search($e->getFieldValue('ctaLayout')->value ?? 'spiral', self::ORDER, true);
            return $key($a) <=> $key($b);
        });
        foreach ($blocks as $i => $block) {
            $this->update(Table::ELEMENTS_OWNERS, ['sortOrder' => $i + 1], ['ownerId' => $page->id, 'elementId' => $block->id]);
        }
        $elements->invalidateCachesForElement($page);
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
