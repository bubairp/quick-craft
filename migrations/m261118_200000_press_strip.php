<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fields\Dropdown;

/**
 * Press strip (Figma Home 6-1259, previously skipped; reference: partiful.com).
 *  - 'press' option on testimonialLayout (logo buttons; the active one shows its quote)
 *  - Home test content: a testimonials block right after the hero
 *    (testimonial: image = outlet logo, personName = outlet, text = quote)
 *
 * PLACEHOLDER: real publications' logos and quotes from the reference design.
 * Replace with Quick Craft's real press before launch.
 */
class m261118_200000_press_strip extends Migration
{
    private const OUTLETS = [
        ['The New York Times', 'press-nyt.svg', ''],
        ['TIME', 'press-time.png', ''],
        ['The Wall Street Journal', 'press-wsj.png', 'Evites are so last decade'],
        ['The Atlantic', 'press-atlantic.png', 'Best solution to the party-invitation problem'],
        ['USA Today', 'press-usatoday.png', ''],
        ['Business Insider', 'press-businessinsider.png', ''],
        ['Inc.', 'press-inc.png', ''],
        ['Google Play', 'press-googleplay.png', ''],
        ['App Store', 'press-appstore.png', ''],
    ];

    public function safeUp(): bool
    {
        // 1. Dropdown option
        $fields = Craft::$app->getFields();
        $layoutField = $fields->getFieldByHandle('testimonialLayout');
        if ($layoutField instanceof Dropdown && !in_array('press', array_column($layoutField->options, 'value'), true)) {
            $options = $layoutField->options;
            $options[] = ['label' => 'Press logos (strip)', 'value' => 'press', 'default' => false];
            $layoutField->options = $options;
            if (!$fields->saveField($layoutField)) {
                throw new \RuntimeException('testimonialLayout: ' . json_encode($layoutField->getErrors()));
            }
        }

        // 2. Home content
        $home = Entry::find()->section('home')->one();
        if (!$home) {
            return true;
        }
        $existing = $home->getFieldValue('pageBuilder')->type('testimonials')->testimonialLayout('press')->one();
        if ($existing) {
            return true;
        }
        $logos = [];
        foreach (self::OUTLETS as [$name, $file]) {
            $logos[$file] = Asset::find()->volume('images')->folderPath('placeholders/')->filename($file)->one();
        }
        if (in_array(null, $logos, true)) {
            echo "    > Press logo placeholders not found — option added, content skipped.\n";
            return true;
        }

        $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $block = $this->nested($home, 'pageBuilder', 'testimonials', $siteId, 99, [
            'testimonialLayout' => 'press',
        ]);
        foreach (self::OUTLETS as $i => [$name, $file, $quote]) {
            $logo = $logos[$file];
            $logo->title = $name;
            $logo->alt = $name;
            Craft::$app->getElements()->saveElement($logo);
            $this->nested($block, 'testimonials', 'testimonial', $siteId, $i + 1, array_filter([
                'personName' => $name,
                'text' => $quote ?: null,
                'image' => [$logo->id],
            ]));
        }

        // Move the new block to position 2 (right after the hero)
        $this->update(Table::ELEMENTS_OWNERS, ['sortOrder' => new \yii\db\Expression('[[sortOrder]] + 1')],
            ['and', ['ownerId' => $home->id], ['>=', 'sortOrder', 2], ['not', ['elementId' => $block->id]]]);
        $this->update(Table::ELEMENTS_OWNERS, ['sortOrder' => 2], ['ownerId' => $home->id, 'elementId' => $block->id]);
        Craft::$app->getElements()->invalidateCachesForElement($home);

        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }

    private function nested(Entry $owner, string $fieldHandle, string $typeHandle, int $siteId, int $sortOrder, array $fields): Entry
    {
        $entry = new Entry([
            'fieldId' => Craft::$app->getFields()->getFieldByHandle($fieldHandle)->id,
            'ownerId' => $owner->id,
            'primaryOwnerId' => $owner->id,
            'typeId' => Craft::$app->getEntries()->getEntryTypeByHandle($typeHandle)->id,
            'siteId' => $siteId,
            'sortOrder' => $sortOrder,
        ]);
        $entry->setFieldValues($fields);
        if (!Craft::$app->getElements()->saveElement($entry)) {
            throw new \RuntimeException("$typeHandle: " . json_encode($entry->getErrors()));
        }
        return $entry;
    }
}
