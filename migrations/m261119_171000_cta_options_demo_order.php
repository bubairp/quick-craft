<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\elements\Entry;

/**
 * /cta-options-demo: put the six banners in a clear order — light first, then dark; within each,
 * spiral → rows → rowsSpot. (Appending blocks with a computed sortOrder had interleaved them.)
 */
class m261119_171000_cta_options_demo_order extends Migration
{
    private const ORDER = ['spiral', 'rows', 'rowsSpot'];

    public function safeUp(): bool
    {
        $page = Entry::find()->section('landingPages')->slug('cta-options-demo')->status(null)->one();
        if (!$page) {
            return true;
        }
        $blocks = $page->getFieldValue('pageBuilder')->status(null)->all();
        usort($blocks, function (Entry $a, Entry $b) {
            $key = fn(Entry $e) => (($e->getFieldValue('theme')->value ?? 'light') === 'dark' ? 10 : 0)
                + (int)array_search($e->getFieldValue('ctaLayout')->value ?? 'spiral', self::ORDER, true);
            return $key($a) <=> $key($b);
        });
        foreach ($blocks as $i => $block) {
            $this->update(Table::ELEMENTS_OWNERS, ['sortOrder' => $i + 1], ['ownerId' => $page->id, 'elementId' => $block->id]);
        }
        Craft::$app->getElements()->invalidateCachesForElement($page);
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
