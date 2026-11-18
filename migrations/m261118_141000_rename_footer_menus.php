<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use remoteprogrammer\simplerpmenu\models\SimpleRpMenuModel;
use remoteprogrammer\simplerpmenu\SimpleRpMenu;
use yii\caching\TagDependency;

/**
 * Footer column headings are rendered from the menu name, so drop the
 * "Footer: " prefix used when seeding (handles stay footerCompany etc.).
 */
class m261118_141000_rename_footer_menus extends Migration
{
    public function safeUp(): bool
    {
        $service = SimpleRpMenu::$plugin->simplerpmenu;
        foreach (['footerCompany', 'footerProduct', 'footerSocials', 'footerLegal', 'footerSupport'] as $handle) {
            $record = $service->getMenuByHandle($handle);
            if (!$record || !str_starts_with($record->name, 'Footer: ')) {
                continue;
            }
            $service->saveMenu(new SimpleRpMenuModel([
                'id' => $record->id,
                'name' => substr($record->name, strlen('Footer: ')),
                'handle' => $record->handle,
                'site_id' => $record->site_id,
            ]));
        }
        TagDependency::invalidate(\Craft::$app->getCache(), ['simple-rp-menu']);
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
