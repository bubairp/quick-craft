<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;

/**
 * Home "Get the app" banner: hide the App Store / Google Play buttons (user request; the
 * reference site shows only the pill and the subtitle there). Content only: the block's
 * showAppBadges lightswitch is turned off, so editors can switch them back on in the CP.
 */
class m261119_140000_cta_no_store_badges extends Migration
{
    public function safeUp(): bool
    {
        $home = Entry::find()->section('home')->one();
        $blocks = $home?->getFieldValue('pageBuilder')->type('ctaBanner')->status(null)->all() ?? [];
        foreach ($blocks as $block) {
            if (!$block->getFieldValue('showAppBadges')) {
                continue;
            }
            $block->setFieldValue('showAppBadges', false);
            if (!Craft::$app->getElements()->saveElement($block)) {
                throw new \RuntimeException('ctaBanner: ' . json_encode($block->getErrors()));
            }
        }
        if ($home) {
            Craft::$app->getElements()->invalidateCachesForElement($home);
        }
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
