<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fields\Dropdown;

/**
 * Swiper version of the sticky scroll slider ("Text blast / Collect info / Build hype"),
 * to compare with the custom one (src/js/scroll-slider.js), which stays on Home.
 *  - 'stickySwiper' option on featureLayout: page scroll drives two Swipers (text + phone
 *    stack) on desktop; on mobile the rows become a swipeable Swiper (src/js/scroll-swiper.js)
 *  - Demo landing page /feature-slider-demo with a copy of Home's stickyPhone block
 *
 * DEMO ONLY: delete the page (and the option if unused) once a version is chosen.
 */
class m261119_120000_feature_swiper_demo extends Migration
{
    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();
        $elements = Craft::$app->getElements();

        // 1. Dropdown option
        $layout = $fields->getFieldByHandle('featureLayout');
        if ($layout instanceof Dropdown && !in_array('stickySwiper', array_column($layout->options, 'value'), true)) {
            $options = $layout->options;
            $options[] = ['label' => 'Sticky phone — Swiper (demo)', 'value' => 'stickySwiper', 'default' => false];
            $layout->options = $options;
            if (!$fields->saveField($layout)) {
                throw new \RuntimeException('featureLayout: ' . json_encode($layout->getErrors()));
            }
        }

        // 2. Demo page with a copy of Home's sticky block
        if (Entry::find()->section('landingPages')->slug('feature-slider-demo')->status(null)->exists()) {
            return true;
        }
        $home = Entry::find()->section('home')->one();
        $source = $home?->getFieldValue('pageBuilder')->type('featureRows')->featureLayout('stickyPhone')->one();
        if (!$source) {
            echo "    > Home sticky feature block not found — option added, demo page skipped.\n";
            return true;
        }
        $siteId = Craft::$app->getSites()->getPrimarySite()->id;

        $page = new Entry([
            'sectionId' => $entries->getSectionByHandle('landingPages')->id,
            'typeId' => $entries->getEntryTypeByHandle('landingPage')->id,
            'siteId' => $siteId,
            'title' => 'Feature slider demo',
            'slug' => 'feature-slider-demo',
        ]);
        $this->save($page);

        // Home's block has no heading; the demo needs one so the page has a single <h1>
        $block = $this->nested($page, 'pageBuilder', 'featureRows', $siteId, 1, [
            'featureLayout' => 'stickySwiper',
            'theme' => $source->getFieldValue('theme')->value,
            'heading' => 'Swiper version (demo)',
        ]);

        foreach ($source->getFieldValue('cards')->status(null)->all() as $i => $card) {
            $values = [];
            foreach ($card->getFieldLayout()->getCustomFields() as $field) {
                // serialised value round-trips any field type (text, assets, link)
                $values[$field->handle] = $field->serializeValue($card->getFieldValue($field->handle), $card);
            }
            $this->nested($block, 'cards', $card->getType()->handle, $siteId, $i + 1, $values);
        }

        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }

    private function nested(Entry $owner, string $fieldHandle, string $typeHandle, int $siteId, int $sortOrder, array $values): Entry
    {
        $entry = new Entry([
            'fieldId' => Craft::$app->getFields()->getFieldByHandle($fieldHandle)->id,
            'ownerId' => $owner->id,
            'primaryOwnerId' => $owner->id,
            'typeId' => Craft::$app->getEntries()->getEntryTypeByHandle($typeHandle)->id,
            'siteId' => $siteId,
            'sortOrder' => $sortOrder,
        ]);
        $entry->setFieldValues($values);
        $this->save($entry);
        return $entry;
    }

    private function save(Entry $entry): void
    {
        if (!Craft::$app->getElements()->saveElement($entry)) {
            throw new \RuntimeException(($entry->title ?: $entry->getType()->handle) . ': ' . json_encode($entry->getErrors()));
        }
    }
}
