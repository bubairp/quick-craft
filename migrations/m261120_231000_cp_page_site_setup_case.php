<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;

/**
 * CP sidebar page "Site setup" → "Site Setup" (the user's wording).
 */
class m261120_231000_cp_page_site_setup_case extends Migration
{
    private const FROM = ['Settings', 'Site setup'];
    private const TO = 'Site Setup';

    public function safeUp(): bool
    {
        $service = Craft::$app->getElementSources();
        $sources = Craft::$app->getProjectConfig()->get('elementSources.' . Entry::class) ?? [];
        $changed = false;
        foreach ($sources as &$source) {
            if (in_array($source['page'] ?? null, self::FROM, true)) {
                $source['page'] = self::TO;
                $changed = true;
            }
        }
        unset($source);
        if ($changed) {
            $service->saveSources(Entry::class, $sources);
        }

        $pages = $service->getPageSettings(Entry::class);
        $icon = 'sliders';
        foreach (self::FROM as $old) {
            unset($pages[$old]);
        }
        $pages[self::TO] = ['icon' => $icon];
        $service->savePageSettings(Entry::class, $pages);
        echo "    > CP page → Site Setup\n";
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
