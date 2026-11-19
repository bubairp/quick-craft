<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\models\Section;

/**
 * Pages is a Channel, not a Structure (the user's choice, as Site7 Engine's standard pages;
 * CLAUDE.md › Only Home is a page Single). Entries keep their IDs; URI format becomes `{slug}`.
 */
class m261120_210000_pages_channel extends Migration
{
    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $pages = $entries->getSectionByHandle('pages');
        if (!$pages || $pages->type === Section::TYPE_CHANNEL) {
            return true;
        }
        $pages->type = Section::TYPE_CHANNEL;
        $pages->maxLevels = null;
        foreach ($pages->getSiteSettings() as $settings) {
            $settings->uriFormat = '{slug}';
        }
        if (!$entries->saveSection($pages)) {
            throw new \RuntimeException('pages: ' . json_encode($pages->getErrors()));
        }
        echo "    > pages: Structure → Channel, URI {slug}\n";
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
