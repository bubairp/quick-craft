<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Dropdown;

/**
 * cardGrid "compact" variant (Figma Home 6-1719): heading left + two buttons right,
 * small square tiles with an inline caption/link.
 *  - add 'compact' option to cardLayout
 *  - add secondaryButton to the cardGrid entry type
 *  - switch the Home "More than an invite" test block to it
 */
class m261118_170000_card_grid_compact extends Migration
{
    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();

        // 1. Dropdown option
        $layoutField = $fields->getFieldByHandle('cardLayout');
        if ($layoutField instanceof Dropdown) {
            $values = array_column($layoutField->options, 'value');
            if (!in_array('compact', $values, true)) {
                $options = $layoutField->options;
                $options[] = ['label' => 'Compact (heading left, small tiles)', 'value' => 'compact', 'default' => false];
                $layoutField->options = $options;
                if (!$fields->saveField($layoutField)) {
                    throw new \RuntimeException('cardLayout: ' . json_encode($layoutField->getErrors()));
                }
            }
        }

        // 2. secondaryButton on cardGrid
        $type = $entries->getEntryTypeByHandle('cardGrid');
        $secondary = $fields->getFieldByHandle('secondaryButton');
        $layout = $type->getFieldLayout();
        if (!$layout->getFieldByHandle('secondaryButton')) {
            $tab = $layout->getTabs()[0];
            $tab->setElements(array_merge($tab->getElements(), [new CustomField($secondary)]));
            $type->setFieldLayout($layout);
            if (!$entries->saveEntryType($type)) {
                throw new \RuntimeException('cardGrid: ' . json_encode($type->getErrors()));
            }
        }

        // 3. Test content
        $home = Entry::find()->section('home')->one();
        if ($home) {
            $block = $home->getFieldValue('pageBuilder')->type('cardGrid')->heading('More than an invite')->one();
            if ($block && ($block->cardLayout->value ?? null) !== 'compact') {
                $block->setFieldValues([
                    'cardLayout' => 'compact',
                    'secondaryButton' => ['type' => 'url', 'value' => '/download', 'label' => 'Get the app'],
                ]);
                if (!Craft::$app->getElements()->saveElement($block)) {
                    throw new \RuntimeException('Home block: ' . json_encode($block->getErrors()));
                }
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
