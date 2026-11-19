<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Entry;

/**
 * Fix: "Buttons should contain at most 3 entries" when saving a page.
 *
 * m261120_120000_common_matrix_fields also created `button` entries for blocks that belong to a
 * draft (nested entries of drafts aren't drafts themselves), on top of the buttons the draft shares
 * with the live page. Those extra buttons are the ones whose primary owner is a draft-owned block and
 * that aren't derived from a live button (canonicalId empty). Craft's own draft copies (canonicalId
 * set) are kept, as they hold the editor's changes. See the skill › "Nested entries of drafts".
 */
class m261120_260000_fix_draft_buttons extends Migration
{
    public function safeUp(): bool
    {
        $buttonTypeId = (new Query())->select('id')->from(Table::ENTRYTYPES)->where(['handle' => 'button', 'dateDeleted' => null])->scalar();
        if (!$buttonTypeId) {
            return true;
        }

        $ids = (new Query())
            ->select(['b.id'])
            ->from(['b' => Table::ENTRIES])
            ->innerJoin(['bel' => Table::ELEMENTS], '[[bel.id]] = [[b.id]]')
            ->innerJoin(['blk' => Table::ENTRIES], '[[blk.id]] = [[b.primaryOwnerId]]')          // the block owning the button
            ->innerJoin(['page' => Table::ELEMENTS], '[[page.id]] = [[blk.primaryOwnerId]]')    // the page owning the block
            ->where(['b.typeId' => $buttonTypeId, 'bel.canonicalId' => null])
            ->andWhere(['or', ['not', ['page.draftId' => null]], ['not', ['page.revisionId' => null]]])
            ->column();

        $elements = Craft::$app->getElements();
        foreach ($ids as $id) {
            $button = Entry::find()->id($id)->status(null)->drafts(null)->revisions(null)->trashed(null)->one();
            if ($button) {
                $elements->deleteElement($button, true);
            }
        }
        echo '    > ' . count($ids) . ' extra draft buttons removed (' . implode(', ', $ids) . ")\n";
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
