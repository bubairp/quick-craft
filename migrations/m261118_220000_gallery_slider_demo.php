<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fields\Dropdown;

/**
 * Swiper alternative for the "A million reasons to hang" gallery (to compare with the
 * custom diagonal fan, which stays on Home).
 *  - 'slider' option on galleryLayout (Swiper coverflow, loop, free drag, continuous autoplay)
 *  - Demo landing page /gallery-slider-demo with a copy of Home's fan gallery in slider layout
 *
 * DEMO ONLY: delete the page (and this option if unused) once a layout is chosen.
 */
class m261118_220000_gallery_slider_demo extends Migration
{
    public function safeUp(): bool
    {
        // 1. Dropdown option
        $fields = Craft::$app->getFields();
        $layoutField = $fields->getFieldByHandle('galleryLayout');
        if ($layoutField instanceof Dropdown && !in_array('slider', array_column($layoutField->options, 'value'), true)) {
            $options = $layoutField->options;
            $options[] = ['label' => 'Slider (Swiper coverflow)', 'value' => 'slider', 'default' => false];
            $layoutField->options = $options;
            if (!$fields->saveField($layoutField)) {
                throw new \RuntimeException('galleryLayout: ' . json_encode($layoutField->getErrors()));
            }
        }

        // 2. Demo page, copying Home's fan gallery content
        if (Entry::find()->section('landingPages')->slug('gallery-slider-demo')->status(null)->exists()) {
            return true;
        }
        $home = Entry::find()->section('home')->one();
        $fan = $home?->getFieldValue('pageBuilder')->type('imageGallery')->galleryLayout('fan')->one();
        if (!$fan) {
            echo "    > Home fan gallery not found — option added, demo page skipped.\n";
            return true;
        }

        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $section = Craft::$app->getEntries()->getSectionByHandle('landingPages');
        $page = new Entry([
            'sectionId' => $section->id,
            'typeId' => Craft::$app->getEntries()->getEntryTypeByHandle('landingPage')->id,
            'siteId' => $siteId,
            'title' => 'Gallery slider demo',
            'slug' => 'gallery-slider-demo',
        ]);
        if (!Craft::$app->getElements()->saveElement($page)) {
            throw new \RuntimeException('page: ' . json_encode($page->getErrors()));
        }

        $block = new Entry([
            'fieldId' => $fields->getFieldByHandle('pageBuilder')->id,
            'ownerId' => $page->id,
            'primaryOwnerId' => $page->id,
            'typeId' => Craft::$app->getEntries()->getEntryTypeByHandle('imageGallery')->id,
            'siteId' => $siteId,
            'sortOrder' => 1,
        ]);
        $block->setFieldValues(array_filter([
            'galleryLayout' => 'slider',
            'eyebrow' => $fan->getFieldValue('eyebrow'),
            'heading' => $fan->getFieldValue('heading'),
            'text' => $fan->getFieldValue('text'),
            'images' => $fan->getFieldValue('images')->ids(),
        ]));
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
