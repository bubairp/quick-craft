<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

/**
 * Wheel Form plugin settings: placeholder From email + Success Message.
 *
 * Without them, a form that sends email (the Contact Form) throws
 * "Plugin settings need to be configured." (500) on a successful submit.
 * From = Craft's system email for now; replace with the real address before launch
 * (CP › Wheel Form › Settings). Locally DDEV catches the mail in Mailpit.
 */
class m261120_470000_wheelform_settings extends Migration
{
    public function safeUp(): bool
    {
        $plugin = Craft::$app->getPlugins()->getPlugin('wheelform');
        if (!$plugin) {
            return true;
        }

        $settings = $plugin->getSettings()->toArray();
        $settings['from_email'] = $settings['from_email'] ?: (Craft::$app->getProjectConfig()->get('email.fromEmail') ?: 'admin@quick-craft.ddev.site');
        $settings['from_name'] = $settings['from_name'] ?: (Craft::$app->getProjectConfig()->get('email.fromName') ?: 'Quick Craft');
        $settings['success_message'] = $settings['success_message'] ?: 'Thanks! Your message has been sent.';

        return Craft::$app->getPlugins()->savePluginSettings($plugin, $settings);
    }

    public function safeDown(): bool
    {
        echo "m261120_470000_wheelform_settings cannot be reverted.\n";
        return false;
    }
}
