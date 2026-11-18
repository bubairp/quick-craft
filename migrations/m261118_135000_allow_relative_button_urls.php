<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fields\Link;

/**
 * Let button Link fields accept root-relative URLs (/login) and anchors (#section),
 * not just absolute URLs.
 */
class m261118_135000_allow_relative_button_urls extends Migration
{
    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        foreach (['primaryButton', 'secondaryButton'] as $handle) {
            $field = $fields->getFieldByHandle($handle);
            if (!$field instanceof Link) {
                continue;
            }
            $settings = $field->typeSettings;
            $settings['url'] = array_merge($settings['url'] ?? [], [
                'allowRootRelativeUrls' => true,
                'allowAnchors' => true,
            ]);
            $field->typeSettings = $settings;
            if (!$fields->saveField($field)) {
                throw new \RuntimeException("Field $handle: " . json_encode($field->getErrors()));
            }
        }
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
