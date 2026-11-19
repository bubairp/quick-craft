<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

/**
 * Live Preview for the site settings: the `siteSettings` Single (no URL) gets a preview target,
 * the home page. Craft swaps the previewed (unsaved) settings entry into ordinary queries
 * (Elements::setPlaceholderElement), so templates/_layouts/base.twig's `_globals` query shows the
 * edited header, footer and store links live.
 * (`newsletter` gets its target, `blog`, when the blog listing is built.)
 */
class m261120_110000_settings_preview_target extends Migration
{
    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $section = $entries->getSectionByHandle('siteSettings');
        if (!$section) {
            return true;
        }
        $section->previewTargets = [
            ['label' => 'Home page', 'urlFormat' => '/', 'refresh' => true],
        ];
        if (!$entries->saveSection($section)) {
            throw new \RuntimeException('siteSettings: ' . json_encode($section->getErrors()));
        }
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
