<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Dropdown;

/**
 * Shopify-style section options (step 1):
 *  - `theme` → `scheme` ("Colour scheme"): light (page background), white, soft (grey), dark.
 *    Existing light/dark values are kept. The colours live in src/css/app.css ([data-scheme]).
 *  - new `spacing` dropdown: default, noTop, noBottom, none. These mean the same thing in every
 *    block (remove the block's own top/bottom padding), handled once in app.css ([data-spacing]).
 *    Added to the blocks whose padding sits on their <section>: cardGrid, cityList, testimonials, ctaBanner.
 */
class m261119_230000_scheme_and_spacing extends Migration
{
    // Pinned so another environment creates the same field as config/project/fields/spacing--….yaml
    private const SPACING_UID = '3f6d2a8e-5b1c-4e7a-9c2d-8a4b6e1f0c37';
    private const SPACING_BLOCKS = ['cardGrid', 'cityList', 'testimonials', 'ctaBanner'];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();

        // 1. theme → scheme
        $scheme = $fields->getFieldByHandle('scheme') ?? $fields->getFieldByHandle('theme');
        if (!$scheme instanceof Dropdown) {
            throw new \RuntimeException('theme/scheme dropdown not found.');
        }
        $scheme->handle = 'scheme';
        $scheme->name = 'Colour scheme';
        $scheme->instructions = 'Section background and text colours. “Page background” has no background of its own.';
        $scheme->options = [
            ['label' => 'Page background', 'value' => 'light', 'default' => true],
            ['label' => 'White', 'value' => 'white', 'default' => false],
            ['label' => 'Soft grey', 'value' => 'soft', 'default' => false],
            ['label' => 'Dark', 'value' => 'dark', 'default' => false],
        ];
        if (!$fields->saveField($scheme)) {
            throw new \RuntimeException('scheme: ' . json_encode($scheme->getErrors()));
        }

        // 2. spacing
        $spacing = $fields->getFieldByHandle('spacing');
        if (!$spacing) {
            $spacing = new Dropdown([
                'name' => 'Spacing',
                'handle' => 'spacing',
                'uid' => self::SPACING_UID,
                'instructions' => 'Remove the section’s own top and/or bottom padding, e.g. to join two sections with the same colour scheme.',
                'options' => [
                    ['label' => 'Default', 'value' => 'default', 'default' => true],
                    ['label' => 'No top padding', 'value' => 'noTop', 'default' => false],
                    ['label' => 'No bottom padding', 'value' => 'noBottom', 'default' => false],
                    ['label' => 'No padding', 'value' => 'none', 'default' => false],
                ],
            ]);
            if (!$fields->saveField($spacing)) {
                throw new \RuntimeException('spacing: ' . json_encode($spacing->getErrors()));
            }
        }

        foreach (self::SPACING_BLOCKS as $handle) {
            $type = $entries->getEntryTypeByHandle($handle);
            if (!$type) {
                echo "    > entry type $handle not found — skipped.\n";
                continue;
            }
            $layout = $type->getFieldLayout();
            if ($layout->getFieldByHandle('spacing')) {
                continue;
            }
            $tab = $layout->getTabs()[0];
            $elements = $tab->getElements();
            // Right after the colour scheme if the block has one, otherwise at the end
            $at = count($elements);
            foreach ($elements as $i => $el) {
                if ($el instanceof CustomField && $el->getField()->id === $scheme->id) {
                    $at = $i + 1;
                }
            }
            array_splice($elements, $at, 0, [new CustomField($spacing)]);
            $tab->setElements($elements);
            $type->setFieldLayout($layout);
            if (!$entries->saveEntryType($type)) {
                throw new \RuntimeException("$handle: " . json_encode($type->getErrors()));
            }
            echo "    > spacing added to $handle\n";
        }

        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
