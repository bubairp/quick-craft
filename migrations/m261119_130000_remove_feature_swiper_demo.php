<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fields\Dropdown;

/**
 * Decision: keep the custom scroll slider (scroll-slider.js); the Swiper version is dropped.
 * Mobile swiping was added to the custom one instead (its fallback rows are now a Swiper row).
 *  - Hard-deletes the /feature-slider-demo landing page (and its nested block + cards)
 *  - Removes the 'stickySwiper' option from featureLayout (only if no block still uses it)
 */
class m261119_130000_remove_feature_swiper_demo extends Migration
{
    public function safeUp(): bool
    {
        $page = Entry::find()->section('landingPages')->slug('feature-slider-demo')->status(null)->trashed(null)->one();
        if ($page) {
            Craft::$app->getElements()->deleteElement($page, true);
        }

        if (Entry::find()->type('featureRows')->featureLayout('stickySwiper')->status(null)->exists()) {
            echo "    > A block still uses the 'stickySwiper' layout — option kept.\n";
            return true;
        }
        $fields = Craft::$app->getFields();
        $field = $fields->getFieldByHandle('featureLayout');
        if ($field instanceof Dropdown && in_array('stickySwiper', array_column($field->options, 'value'), true)) {
            $field->options = array_values(array_filter($field->options, fn($o) => ($o['value'] ?? null) !== 'stickySwiper'));
            if (!$fields->saveField($field)) {
                throw new \RuntimeException('featureLayout: ' . json_encode($field->getErrors()));
            }
        }
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
