<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Color;

/**
 * Promo bar block (Figma 17-1076; CLAUDE.md › pageBuilder blocks › promoBar):
 *  - promoBar gets an `emoji` instance of the plainText base field (first in its layout).
 *  - New colour token `promo` (#3c0a7a) is added to the Color Code palette (palette = tokens).
 *  - Test content: a promo bar on Birthday Invitations, as in the design.
 */
class m261120_270000_promo_bar extends Migration
{
    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();

        // 1. emoji on promoBar
        $type = $entries->getEntryTypeByHandle('promoBar');
        $layout = $type->getFieldLayout();
        if (!$layout->getFieldByHandle('emoji')) {
            $tab = $layout->getTabs()[0];
            $elements = $tab->getElements();
            array_unshift($elements, new CustomField($fields->getFieldByHandle('plainText'), [
                'handle' => 'emoji', 'label' => 'Emoji',
                'instructions' => 'Optional. Shown on both sides of the text, e.g. 🎈',
                'uid' => $this->stableUid('el:promoBar:emoji'),
            ]));
            $tab->setElements($elements);
            $type->setFieldLayout($layout);
            $this->check($entries->saveEntryType($type), $type, 'promoBar');
            echo "    > promoBar: + emoji\n";
        }

        // 2. Color Code palette: + promo
        $color = $fields->getFieldByHandle('colorcode');
        if ($color instanceof Color && !in_array('promo', array_column($color->palette, 'label'), true)) {
            $palette = $color->palette;
            $palette[] = ['color' => '#3c0a7a', 'label' => 'promo', 'default' => false];
            $color->palette = $palette;
            $this->check($fields->saveField($color), $color, 'colorcode');
            echo "    > colorcode palette: + promo\n";
        }

        // 3. Test content on Birthday Invitations
        $page = Entry::find()->section('pages')->slug('birthday-invitations')->status(null)->one();
        $pageBuilder = $fields->getFieldByHandle('pageBuilder');
        if ($page && !Entry::find()->ownerId($page->id)->field($pageBuilder)->type('promoBar')->status(null)->exists()) {
            $maker = Entry::find()->section('pages')->slug('invitation-maker')->one();
            $sort = (int)Entry::find()->ownerId($page->id)->field($pageBuilder)->status(null)->count() + 1;
            $bar = new Entry([
                'fieldId' => $pageBuilder->id, 'ownerId' => $page->id, 'primaryOwnerId' => $page->id,
                'typeId' => $type->id, 'siteId' => $page->siteId, 'sortOrder' => $sort,
            ]);
            $bar->setFieldValues([
                'emoji' => '🎈',
                'text' => 'Get $400 to throw your birthday party',
                'primaryButton' => $maker
                    ? ['type' => 'entry', 'value' => "{entry:$maker->id@$page->siteId:url}"]
                    : ['type' => 'url', 'value' => '/'],
            ]);
            $this->check(Craft::$app->getElements()->saveElement($bar), $bar, 'promo bar');
            echo "    > Birthday Invitations: + promo bar\n";
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
