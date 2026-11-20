<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;

/**
 * Image block (Figma Careers 6-7092; CLAUDE.md › pageBuilder blocks › image).
 * No content-model change (image + text already exist). Test content: the Figma team photo
 * (placeholder careers-team-office.png in the images volume, when present) at the end of Careers,
 * with its alt text from the Figma layer name.
 */
class m261120_330000_image_block extends Migration
{
    private const ALT = 'A group of people gathered in a bright, modern space, sitting together on sofas and the floor.';

    public function safeUp(): bool
    {
        $asset = Asset::find()->filename('careers-team-office.png')->one();
        if (!$asset) {
            echo "    > careers-team-office.png not in the images volume — test content skipped\n";
            return true;
        }
        if (!$asset->alt) {
            $asset->alt = self::ALT;
            Craft::$app->getElements()->saveElement($asset);
        }

        $fields = Craft::$app->getFields();
        $pageBuilder = $fields->getFieldByHandle('pageBuilder');
        $page = Entry::find()->section('pages')->slug('careers')->status(null)->one();
        if ($page && !Entry::find()->ownerId($page->id)->field($pageBuilder)->type('image')->status(null)->exists()) {
            $sort = (int)Entry::find()->ownerId($page->id)->field($pageBuilder)->status(null)->count();
            $block = new Entry([
                'fieldId' => $pageBuilder->id, 'ownerId' => $page->id, 'primaryOwnerId' => $page->id,
                'typeId' => Craft::$app->getEntries()->getEntryTypeByHandle('image')->id,
                'siteId' => $page->siteId, 'sortOrder' => $sort + 1,
            ]);
            $block->setFieldValue('image', [$asset->id]);
            if (!Craft::$app->getElements()->saveElement($block)) {
                throw new \RuntimeException('image block: ' . json_encode($block->getErrors()));
            }
            echo "    > Careers: + full-width image\n";
        }
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
