<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;

/**
 * The CP sidebar page "Settings" clashed with Craft's own Settings item (two "Settings" in the
 * sidebar). Renamed to "Site setup" (icon sliders). CLAUDE.md › CP sidebar: never name a page
 * after a Craft CP item.
 */
class m261120_230000_cp_page_site_setup extends Migration
{
    public function safeUp(): bool
    {
        $service = Craft::$app->getElementSources();
        $projectConfig = Craft::$app->getProjectConfig();

        $sources = $projectConfig->get('elementSources.' . Entry::class) ?? [];
        $changed = false;
        foreach ($sources as &$source) {
            if (($source['page'] ?? null) === 'Settings') {
                $source['page'] = 'Site setup';
                $changed = true;
            }
        }
        unset($source);
        if ($changed) {
            $service->saveSources(Entry::class, $sources);
        }

        $pages = $service->getPageSettings(Entry::class);
        if (isset($pages['Settings']) || !isset($pages['Site setup'])) {
            unset($pages['Settings']);
            $pages['Site setup'] = ['icon' => 'sliders'];
            $service->savePageSettings(Entry::class, $pages);
        }
        echo "    > CP page Settings → Site setup\n";
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
