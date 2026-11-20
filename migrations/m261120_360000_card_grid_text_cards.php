<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Dropdown;

/**
 * cardGrid › textCards (Figma Careers 6-6770 "Our Values"; CLAUDE.md › pageBuilder blocks › cardGrid › textCards):
 *  - cardLayout gets the option `textCards` "Text cards (emoji, no image)".
 *  - cardGrid gets `glow` (lightswitchOff instance).
 *  - Test content: "Our Values" with the 6 cards from Figma on Careers, right after the photo strip
 *    (or after the first textMedia when there's no strip).
 */
class m261120_360000_card_grid_text_cards extends Migration
{
    /** [emoji, heading, text] */
    private const VALUES = [
        ['🛠️', 'Ownership', "We don't wait for permission. We see what needs to happen and we drive it forward, start to finish."],
        ['🎯', 'Craft', 'We care about quality, precision, and the details most people miss. Excellence is the baseline, not the goal.'],
        ['⚡️', 'Speed', "We move fast and ship multiple times a week. We're committed to working both smarter and harder to deliver for our users."],
        ['🔎', 'Openness', "We share feedback early and often. We're on the same team, and trust one another to get to the best answer."],
        ['⚖️', 'Integrity', 'We say what we mean and do what we say. If we commit to something, we follow through.'],
        ['🎨', 'Creativity', 'Do your most creative work with us. We give you a canvas and an operating structure that lets crazy ideas actually come to life.'],
    ];

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();
        $elements = Craft::$app->getElements();

        // 1. Dropdown option
        $layoutField = $fields->getFieldByHandle('cardLayout');
        if ($layoutField instanceof Dropdown && !in_array('textCards', array_column($layoutField->options, 'value'), true)) {
            $options = $layoutField->options;
            $options[] = ['label' => 'Text cards (emoji, no image)', 'value' => 'textCards', 'default' => false];
            $layoutField->options = $options;
            $this->check($fields->saveField($layoutField), $layoutField, 'cardLayout');
        }

        // 2. glow on cardGrid
        $type = $entries->getEntryTypeByHandle('cardGrid');
        $layout = $type->getFieldLayout();
        if (!$layout->getFieldByHandle('glow')) {
            $tab = $layout->getTabs()[0];
            $els = $tab->getElements();
            $els[] = new CustomField($fields->getFieldByHandle('lightswitchOff'), [
                'handle' => 'glow', 'label' => 'Soft glow background', 'instructions' => 'Text cards layout only.',
                'uid' => $this->stableUid('el:cardGrid:glow'),
            ]);
            $tab->setElements($els);
            $type->setFieldLayout($layout);
            $this->check($entries->saveEntryType($type), $type, 'cardGrid');
        }

        // 3. "Our Values" on Careers
        $page = Entry::find()->section('pages')->slug('careers')->status(null)->one();
        $pageBuilder = $fields->getFieldByHandle('pageBuilder');
        $query = fn() => Entry::find()->ownerId($page->id)->field($pageBuilder)->status(null);
        if ($page && !$query()->type('cardGrid')->exists()) {
            $after = $query()->type('imageGallery')->one() ?? $query()->type('textMedia')->one();
            $position = $after ? $after->getSortOrder() + 1 : (int)$query()->count() + 1;
            $this->update(Table::ELEMENTS_OWNERS, ['sortOrder' => new \yii\db\Expression('[[sortOrder]] + 1')], [
                'and', ['ownerId' => $page->id], ['>=', 'sortOrder', $position], ['elementId' => $query()->ids()],
            ], [], false);

            $block = new Entry([
                'fieldId' => $pageBuilder->id, 'ownerId' => $page->id, 'primaryOwnerId' => $page->id,
                'typeId' => $type->id, 'siteId' => $page->siteId, 'sortOrder' => $position,
            ]);
            $block->setFieldValues(['cardLayout' => 'textCards', 'scheme' => 'light', 'heading' => 'Our Values', 'glow' => true]);
            $this->check($elements->saveElement($block), $block, 'Our Values');

            $cardsField = $fields->getFieldByHandle('cards');
            $cardType = $entries->getEntryTypeByHandle('card');
            foreach (self::VALUES as $i => [$emoji, $heading, $text]) {
                $card = new Entry([
                    'fieldId' => $cardsField->id, 'ownerId' => $block->id, 'primaryOwnerId' => $block->id,
                    'typeId' => $cardType->id, 'siteId' => $page->siteId, 'sortOrder' => $i + 1,
                ]);
                $card->setFieldValues(['emoji' => $emoji, 'heading' => $heading, 'text' => $text]);
                $this->check($elements->saveElement($card), $card, "card $heading");
            }
            echo "    > Careers: + Our Values (6 cards) at position $position\n";
        }

        return true;
    }

    private function check(bool $ok, $model, string $what): void
    {
        if (!$ok) {
            throw new \RuntimeException("$what: " . json_encode($model->getErrors()));
        }
    }

    /** A UUID derived from a key: the same on every environment. */
    private function stableUid(string $key): string
    {
        $h = md5("quick-craft:$key");
        return sprintf('%s-%s-4%s-%x%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 13, 3), 8 | (hexdec($h[16]) & 3), substr($h, 17, 3), substr($h, 20, 12));
    }

    public function safeDown(): bool
    {
        return false;
    }
}
