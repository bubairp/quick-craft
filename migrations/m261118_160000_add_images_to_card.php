<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;

/**
 * Add the shared `images` field to the `card` entry type (after `image`),
 * for cards that need several images, e.g. cityList event thumbnails (Figma 6-4780).
 */
class m261118_160000_add_images_to_card extends Migration
{
    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $type = $entries->getEntryTypeByHandle('card');
        $images = Craft::$app->getFields()->getFieldByHandle('images');
        if (!$type || !$images) {
            throw new \RuntimeException('card entry type or images field missing');
        }

        $layout = $type->getFieldLayout();
        if ($layout->getFieldByHandle('images')) {
            return true;
        }

        $tab = $layout->getTabs()[0];
        $elements = [];
        foreach ($tab->getElements() as $element) {
            $elements[] = $element;
            if ($element instanceof CustomField && $element->getField()->handle === 'image') {
                $elements[] = new CustomField($images, [
                    'instructions' => 'Extra images, e.g. event thumbnails shown beside a city.',
                ]);
            }
        }
        $tab->setElements($elements);
        $type->setFieldLayout($layout);

        if (!$entries->saveEntryType($type)) {
            throw new \RuntimeException('card: ' . json_encode($type->getErrors()));
        }
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
