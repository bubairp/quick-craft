<?php

namespace craft\contentmigrations;

use Craft;
use craft\elements\Entry;
use craft\db\Migration;
use craft\fields\Dropdown;

/**
 * Decision: keep the custom diagonal fan (gallery-wave.js); the Swiper version is dropped.
 *  - Hard-deletes the /gallery-slider-demo landing page (and its nested gallery block)
 *  - Removes the 'slider' option from galleryLayout (only if no block still uses it)
 */
class m261118_240000_remove_gallery_slider_demo extends Migration
{
    public function safeUp(): bool
    {
        $elements = Craft::$app->getElements();
        $page = Entry::find()->section('landingPages')->slug('gallery-slider-demo')->status(null)->trashed(null)->one();
        if ($page) {
            $elements->deleteElement($page, true);
        }

        $inUse = Entry::find()->type('imageGallery')->galleryLayout('slider')->status(null)->exists();
        if ($inUse) {
            echo "    > A gallery block still uses the 'slider' layout — option kept.\n";
            return true;
        }
        $fields = Craft::$app->getFields();
        $field = $fields->getFieldByHandle('galleryLayout');
        if ($field instanceof Dropdown && in_array('slider', array_column($field->options, 'value'), true)) {
            $field->options = array_values(array_filter($field->options, fn($o) => ($o['value'] ?? null) !== 'slider'));
            if (!$fields->saveField($field)) {
                throw new \RuntimeException('galleryLayout: ' . json_encode($field->getErrors()));
            }
        }
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
