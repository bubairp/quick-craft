<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * Header mega menu: an image for every dropdown's featured card (the user's request, 2026-11-20).
 * CLAUDE.md › Header mega menu featured cards.
 *
 * Figma (17:2) only designs the Invites dropdown (balloons, already set). The other three get
 * PLACEHOLDER images from our uploads until real art exists. The image is the `image:` line of the
 * Simple RP Menu item's Data JSON; matched by the item's name + the `menu-featured` class, and only
 * set when the item has no image yet.
 */
class m261120_500000_menu_featured_images extends Migration
{
    private const IMAGES = [
        'For a more social life' => '/uploads/images/placeholders/home-step-1.png',
        'Grow your community' => '/uploads/images/placeholders/home-tile-1.png',
        'Meet the team' => '/uploads/images/careers-mission-collage.png',
    ];

    public function safeUp(): bool
    {
        foreach (self::IMAGES as $name => $path) {
            $rows = (new Query())->select(['id', 'data_json'])->from('{{%simplerpmenu_items}}')
                ->where(['name' => $name])->andWhere(['like', 'class', 'menu-featured'])->all();
            foreach ($rows as $row) {
                $data = (string)($row['data_json'] ?? '');
                if (preg_match('/^\s*image\s*:/m', $data)) {
                    continue; // an editor already chose one
                }
                $data = rtrim($data) . ($data !== '' ? "\n" : '') . "image: $path";
                $this->update('{{%simplerpmenu_items}}', ['data_json' => $data], ['id' => $row['id']], [], false);
                echo "    > $name: $path\n";
            }
        }
        // Simple RP Menu caches the resolved menus for 24h (tag `simple-rp-menu`); it only clears that
        // on CP edits and entry saves, so a direct table update must clear it itself.
        \yii\caching\TagDependency::invalidate(\Craft::$app->getCache(), ['simple-rp-menu']);
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
