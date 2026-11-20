<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;

/**
 * imageGallery › marquee (Figma Careers 6-6667; CLAUDE.md › pageBuilder blocks › imageGallery › marquee):
 *  - imageGallery gets `spacing` (used by every layout except fan).
 *  - Test content: the Careers photo strip (5 placeholder photos careers-strip-1…5.png, when they
 *    are in the images volume) placed right after the first textMedia block, spacing noTop.
 * PLACEHOLDER: the photos come from the imported reference design. Replace them before launch.
 */
class m261120_350000_gallery_marquee extends Migration
{
    private const PHOTOS = [
        'careers-strip-1.png' => 'Two friends chatting over drinks at a dimly lit bar.',
        'careers-strip-2.png' => 'Friends smiling around a dinner table at night.',
        'careers-strip-3.png' => 'A group of friends in paper crowns celebrating a birthday.',
        'careers-strip-4.png' => 'Friends sharing dinner at a long table.',
        'careers-strip-5.png' => 'A large group of friends at a candlelit dinner party.',
    ];

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();
        $elements = Craft::$app->getElements();

        // 1. Layout: + spacing
        $type = $entries->getEntryTypeByHandle('imageGallery');
        $layout = $type->getFieldLayout();
        if (!$layout->getFieldByHandle('spacing')) {
            $tab = $layout->getTabs()[0];
            $els = $tab->getElements();
            $els[] = new CustomField($fields->getFieldByHandle('spacing'), [
                'uid' => $this->stableUid('el:imageGallery:spacing'),
                'instructions' => 'Not used by the Fan layout.',
            ]);
            $tab->setElements($els);
            $type->setFieldLayout($layout);
            $this->check($entries->saveEntryType($type), $type, 'imageGallery');
        }

        // 2. Photos (alt text)
        $ids = [];
        foreach (self::PHOTOS as $filename => $alt) {
            $asset = Asset::find()->filename($filename)->one();
            if (!$asset) {
                continue;
            }
            if (!$asset->alt) {
                $asset->alt = $alt;
                $this->check($elements->saveElement($asset), $asset, $filename);
            }
            $ids[] = $asset->id;
        }
        if (!$ids) {
            echo "    > careers-strip-*.png not in the images volume — test content skipped\n";
            return true;
        }

        // 3. Marquee on Careers, right after the first textMedia
        $page = Entry::find()->section('pages')->slug('careers')->status(null)->one();
        $pageBuilder = $fields->getFieldByHandle('pageBuilder');
        $query = fn() => Entry::find()->ownerId($page->id)->field($pageBuilder)->status(null);
        if ($page && !$query()->type('imageGallery')->exists()) {
            $first = $query()->type('textMedia')->one();
            $position = $first ? $first->getSortOrder() + 1 : (int)$query()->count() + 1;

            // make room: shift the blocks from that position down by one
            $this->update(Table::ELEMENTS_OWNERS, ['sortOrder' => new \yii\db\Expression('[[sortOrder]] + 1')], [
                'and', ['ownerId' => $page->id], ['>=', 'sortOrder', $position],
                ['elementId' => $query()->ids()],
            ], [], false);

            $block = new Entry([
                'fieldId' => $pageBuilder->id, 'ownerId' => $page->id, 'primaryOwnerId' => $page->id,
                'typeId' => $type->id, 'siteId' => $page->siteId, 'sortOrder' => $position,
            ]);
            $block->setFieldValues(['galleryLayout' => 'marquee', 'images' => $ids, 'spacing' => 'noTop']);
            $this->check($elements->saveElement($block), $block, 'Careers photo strip');
            echo "    > Careers: + photo strip (marquee) at position $position\n";
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
