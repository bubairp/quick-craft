<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fields\Dropdown;

/**
 * The galleryLayout "slider" option is now the Swiper version of the diagonal fan
 * (no longer coverflow): update its label in the CP.
 */
class m261118_230000_gallery_slider_label extends Migration
{
    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $field = $fields->getFieldByHandle('galleryLayout');
        if (!$field instanceof Dropdown) {
            return true;
        }
        $changed = false;
        $options = array_map(function ($option) use (&$changed) {
            if (($option['value'] ?? null) === 'slider' && $option['label'] !== 'Fan slider (Swiper)') {
                $option['label'] = 'Fan slider (Swiper)';
                $changed = true;
            }
            return $option;
        }, $field->options);
        if ($changed) {
            $field->options = $options;
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
