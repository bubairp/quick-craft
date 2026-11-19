<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;
use craft\elements\Entry;

/**
 * /cta-options-demo: order all eight banners — light then dark; within each
 * spiral → rows → rowsSpot → rowsSpotInvert.
 * (The previous migration re-used an already-loaded block list, so the two blocks it had just
 * added were left out of its re-ordering. This one queries the blocks fresh.)
 */
class m261119_181000_cta_options_demo_reorder extends Migration
{
    private const ORDER = ['spiral', 'rows', 'rowsSpot', 'rowsSpotInvert'];

    public function safeUp(): bool
    {
        $page = Entry::find()->section('landingPages')->slug('cta-options-demo')->status(null)->one();
        if (!$page) {
            return true;
        }
        $blocks = Entry::find()
            ->ownerId($page->id)
            ->field('pageBuilder')
            ->status(null)
            ->all();
        usort($blocks, function (Entry $a, Entry $b) {
            $key = fn(Entry $e) => (($e->getFieldValue('theme')->value ?? 'light') === 'dark' ? 10 : 0)
                + (int)array_search($e->getFieldValue('ctaLayout')->value ?? 'spiral', self::ORDER, true);
            return $key($a) <=> $key($b);
        });
        foreach ($blocks as $i => $block) {
            $this->update(Table::ELEMENTS_OWNERS, ['sortOrder' => $i + 1], ['ownerId' => $page->id, 'elementId' => $block->id]);
        }
        echo '    > Ordered ' . count($blocks) . " blocks.\n";
        Craft::$app->getElements()->invalidateCachesForElement($page);
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
