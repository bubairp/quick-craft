<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Color;

/**
 * Comparison block (Figma 17-2074; CLAUDE.md › pageBuilder blocks › comparison):
 *  - `themLabel` (plainText instance) before comparisonThem; clearer labels on image / tables / body.
 *  - Color Code palette: + warning, aqua, orchid (palette = tokens).
 *  - Test content on Invitation Maker (the Figma copy).
 */
class m261120_290000_comparison extends Migration
{
    private const LABELS = [
        'image' => 'Logo (our column)',
        'comparisonUs' => 'Our column',
        'comparisonThem' => 'Other column',
        'body' => 'Closing note',
    ];

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();

        // 1. Layout
        $type = $entries->getEntryTypeByHandle('comparison');
        $layout = $type->getFieldLayout();
        $tab = $layout->getTabs()[0];
        $elements = [];
        foreach ($tab->getElements() as $el) {
            if ($el instanceof CustomField) {
                $handle = $el->handle ?? $el->getField()->handle;
                if ($handle === 'comparisonThem' && !$layout->getFieldByHandle('themLabel')) {
                    $elements[] = new CustomField($fields->getFieldByHandle('plainText'), [
                        'handle' => 'themLabel', 'label' => 'Other column heading',
                        'instructions' => 'e.g. “Other free makers”.',
                        'uid' => $this->stableUid('el:comparison:themLabel'),
                    ]);
                }
                if (isset(self::LABELS[$handle])) {
                    $el->label = self::LABELS[$handle];
                }
            }
            $elements[] = $el;
        }
        if (!$layout->getFieldByHandle('spacing')) {
            $elements[] = new CustomField($fields->getFieldByHandle('spacing'), ['uid' => $this->stableUid('el:comparison:spacing')]);
        }
        $tab->setElements($elements);
        $type->setFieldLayout($layout);
        $this->check($entries->saveEntryType($type), $type, 'comparison');
        echo "    > comparison: + themLabel, spacing, labels\n";

        // 2. Palette
        $color = $fields->getFieldByHandle('colorcode');
        if ($color instanceof Color) {
            $palette = $color->palette;
            $have = array_column($palette, 'label');
            foreach (['warning' => '#ffbf00', 'aqua' => '#85f5ff', 'orchid' => '#f49eff'] as $token => $hex) {
                if (!in_array($token, $have, true)) {
                    $palette[] = ['color' => $hex, 'label' => $token, 'default' => false];
                }
            }
            $color->palette = $palette;
            $this->check($fields->saveField($color), $color, 'colorcode');
        }

        // 3. Test content on Invitation Maker
        $page = Entry::find()->section('pages')->slug('invitation-maker')->status(null)->one();
        $pageBuilder = $fields->getFieldByHandle('pageBuilder');
        if ($page && !Entry::find()->ownerId($page->id)->field($pageBuilder)->type('comparison')->status(null)->exists()) {
            $sort = (int)Entry::find()->ownerId($page->id)->field($pageBuilder)->status(null)->count() + 1;
            $block = new Entry([
                'fieldId' => $pageBuilder->id, 'ownerId' => $page->id, 'primaryOwnerId' => $page->id,
                'typeId' => $type->id, 'siteId' => $page->siteId, 'sortOrder' => $sort,
            ]);
            $block->setFieldValues([
                'heading' => 'Quick Craft’s free invitation maker vs. design-only tools',
                'text' => 'Most “invitation makers” hand you a pretty picture and call it a day.',
                'comparisonUs' => [
                    ['label' => 'Free 🎉', 'status' => 'yes'],
                    ['label' => 'Stunning designs', 'status' => 'yes'],
                    ['label' => 'No ads 👍', 'status' => 'yes'],
                    ['label' => 'Track who’s coming', 'status' => 'yes'],
                    ['label' => 'Send reminders & updates', 'status' => 'yes'],
                    ['label' => 'Shared photo album after', 'status' => 'yes'],
                ],
                'themLabel' => 'Other free makers',
                'comparisonThem' => [
                    ['label' => 'Free', 'status' => 'yes'],
                    ['label' => 'Designs are often dated', 'status' => 'warning'],
                    ['label' => 'Often had ads', 'status' => 'warning'],
                    ['label' => 'No RSVP tracking', 'status' => 'no'],
                    ['label' => 'No reminders or updates', 'status' => 'no'],
                    ['label' => 'Guests can’t add photos', 'status' => 'no'],
                ],
                'body' => '<p>Quick Craft is the whole party — not just the flyer.</p>',
            ]);
            $this->check(Craft::$app->getElements()->saveElement($block), $block, 'comparison block');
            echo "    > Invitation Maker: + comparison\n";
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
