<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\StringHelper;

/**
 * SEO defaults (CLAUDE.md › SEO, sitemap and favicon):
 *  - Ether SEO plugin settings: a default meta description (the title template stays
 *    "{title} - {{ siteName }}"; robots.txt stays the plugin default, which blocks everything
 *    outside production).
 *  - Sitemap: Home (daily, 1.0), Pages (weekly, 0.8), Blog (weekly, 0.6).
 * PLACEHOLDER: the description is draft copy; edit it in CP › SEO › Settings.
 */
class m261120_450000_seo_defaults extends Migration
{
    private const SITEMAP = [
        'home' => ['daily', 1.0],
        'pages' => ['weekly', 0.8],
        'blog' => ['weekly', 0.6],
    ];

    public function safeUp(): bool
    {
        $plugins = Craft::$app->getPlugins();
        $seo = $plugins->getPlugin('seo');
        if ($seo) {
            $settings = $seo->getSettings()->toArray();
            if (empty($settings['description'])) {
                $settings['description'] = 'Free invitations, RSVPs and ticketing for every kind of party. Design it, send it by text, and see who’s coming.';
                $plugins->savePluginSettings($seo, $settings);
                echo "    > SEO: default description\n";
            }
        }

        $now = Db::prepareDateForDb(new \DateTime());
        foreach (self::SITEMAP as $handle => [$frequency, $priority]) {
            $section = Craft::$app->getEntries()->getSectionByHandle($handle);
            if (!$section) {
                continue;
            }
            $exists = (new Query())->from('{{%seo_sitemap}}')->where(['group' => 'sections', 'url' => (string)$section->id])->exists();
            if ($exists) {
                $this->update('{{%seo_sitemap}}', ['enabled' => 1, 'frequency' => $frequency, 'priority' => $priority],
                    ['group' => 'sections', 'url' => (string)$section->id], [], false);
            } else {
                $this->insert('{{%seo_sitemap}}', [
                    'group' => 'sections', 'url' => (string)$section->id, 'frequency' => $frequency,
                    'priority' => $priority, 'enabled' => 1,
                    'dateCreated' => $now, 'dateUpdated' => $now, 'uid' => StringHelper::UUID(),
                ]);
            }
            echo "    > sitemap: $handle ($frequency, $priority)\n";
        }

        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
