<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

/**
 * Header: one "Contact Us" button instead of "Create an invite" + "Log in".
 * siteSettings.primaryButton → /contact (same page as the footer's Contact link),
 * siteSettings.secondaryButton → cleared (the header skips an empty button).
 */
class m261119_210000_header_contact_button extends Migration
{
    public function safeUp(): bool
    {
        $set = Craft::$app->getGlobals()->getSetByHandle('siteSettings');
        if (!$set) {
            echo "    > siteSettings global not found — skipped.\n";
            return true;
        }
        $set->setFieldValues([
            'primaryButton' => ['type' => 'url', 'value' => '/contact', 'label' => 'Contact Us'],
            'secondaryButton' => null,
        ]);
        if (!Craft::$app->getElements()->saveElement($set)) {
            throw new \RuntimeException('siteSettings: ' . json_encode($set->getErrors()));
        }
        echo "    > Header button set to Contact Us.\n";
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
