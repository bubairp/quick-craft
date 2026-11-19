<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Dropdown;

/**
 * "Get the app" banner (ctaBanner): a second background layout.
 *  - New dropdown field `ctaLayout` ("Background layout"): spiral (default, the turning
 *    spiral) or rows (collage rows, each row sliding sideways at a random speed/direction),
 *    added to the ctaBanner entry type after `theme`.
 *  - Demo landing page /cta-rows-demo with a copy of Home's ctaBanner set to "rows", to
 *    compare with Home (which keeps the spiral).
 *
 * DEMO PAGE ONLY: delete it once a layout is chosen (the field/option can stay).
 */
class m261119_150000_cta_layout_rows extends Migration
{
    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();

        // 1. Field
        $field = $fields->getFieldByHandle('ctaLayout');
        if (!$field) {
            $field = new Dropdown([
                'name' => 'Background layout',
                'handle' => 'ctaLayout',
                'instructions' => 'How the images behind the heading are arranged.',
                'options' => [
                    ['label' => 'Spiral (turning)', 'value' => 'spiral', 'default' => true],
                    ['label' => 'Collage rows (sliding)', 'value' => 'rows', 'default' => false],
                ],
            ]);
            if (!$fields->saveField($field)) {
                throw new \RuntimeException('ctaLayout: ' . json_encode($field->getErrors()));
            }
        }

        // 2. Add it to the ctaBanner entry type, after `theme`
        $type = $entries->getEntryTypeByHandle('ctaBanner');
        $layout = $type->getFieldLayout();
        if (!$layout->getFieldByHandle('ctaLayout')) {
            $tab = $layout->getTabs()[0];
            $elements = [];
            $added = false;
            foreach ($tab->getElements() as $element) {
                $elements[] = $element;
                if ($element instanceof CustomField && $element->getField()->handle === 'theme') {
                    $elements[] = new CustomField($field);
                    $added = true;
                }
            }
            if (!$added) {
                $elements[] = new CustomField($field);
            }
            $tab->setElements($elements);
            $type->setFieldLayout($layout);
            if (!$entries->saveEntryType($type)) {
                throw new \RuntimeException('ctaBanner: ' . json_encode($type->getErrors()));
            }
        }

        // 3. Demo page with a copy of Home's banner, set to rows
        if (Entry::find()->section('landingPages')->slug('cta-rows-demo')->status(null)->exists()) {
            return true;
        }
        $home = Entry::find()->section('home')->one();
        $source = $home?->getFieldValue('pageBuilder')->type('ctaBanner')->one();
        if (!$source) {
            echo "    > Home ctaBanner not found — field added, demo page skipped.\n";
            return true;
        }
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $page = new Entry([
            'sectionId' => $entries->getSectionByHandle('landingPages')->id,
            'typeId' => $entries->getEntryTypeByHandle('landingPage')->id,
            'siteId' => $siteId,
            'title' => 'Get the app — rows demo',
            'slug' => 'cta-rows-demo',
        ]);
        if (!Craft::$app->getElements()->saveElement($page)) {
            throw new \RuntimeException('page: ' . json_encode($page->getErrors()));
        }
        $values = [];
        foreach ($source->getFieldLayout()->getCustomFields() as $f) {
            if ($f->handle === 'ctaLayout') {
                continue;
            }
            $values[$f->handle] = $f->serializeValue($source->getFieldValue($f->handle), $source);
        }
        $values['ctaLayout'] = 'rows';
        $block = new Entry([
            'fieldId' => $fields->getFieldByHandle('pageBuilder')->id,
            'ownerId' => $page->id,
            'primaryOwnerId' => $page->id,
            'typeId' => $type->id,
            'siteId' => $siteId,
            'sortOrder' => 1,
        ]);
        $block->setFieldValues($values);
        if (!Craft::$app->getElements()->saveElement($block)) {
            throw new \RuntimeException('block: ' . json_encode($block->getErrors()));
        }
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
