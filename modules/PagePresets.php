<?php

namespace modules;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Entry;

/**
 * Page presets (Shopify-style step 5; CLAUDE.md › Page presets).
 *
 * When an entry in the `pages` section is saved (not as a draft or revision) with a `pagePreset`
 * chosen and an empty `pageBuilder`, the preset's blocks are copied into it once, nested items
 * included (Elements::duplicateElement). After that the page owns its copy.
 *
 * Drafts are skipped on purpose: the CP autosaves drafts with the form's pageBuilder value, which
 * doesn't contain blocks added on the server, so they would be deleted again.
 */
class PagePresets
{
    private static bool $running = false;

    public static function apply(ElementInterface $page): void
    {
        if (
            self::$running ||
            !$page instanceof Entry ||
            $page->getIsDraft() ||
            $page->getIsRevision() ||
            $page->propagating ||
            $page->resaving ||
            $page->duplicateOf !== null ||
            $page->getSection()?->handle !== 'pages' ||
            !$page->getFieldLayout()?->getFieldByHandle('pagePreset')
        ) {
            return;
        }

        $preset = $page->getFieldValue('pagePreset')->status(null)->one();
        if (!$preset) {
            return;
        }

        $hasBlocks = Entry::find()->ownerId($page->id)->field('pageBuilder')->siteId($page->siteId)->status(null)->exists();
        if ($hasBlocks) {
            return; // presets only fill an empty page
        }

        $blocks = Entry::find()->ownerId($preset->id)->field('pageBuilder')->siteId($preset->siteId)->status(null)->all();
        if (!$blocks) {
            return;
        }

        self::$running = true;
        try {
            $elements = Craft::$app->getElements();
            $sortOrder = 1;
            foreach ($blocks as $block) {
                $elements->duplicateElement($block, [
                    'ownerId' => $page->id,
                    'primaryOwnerId' => $page->id,
                    'siteId' => $page->siteId,
                    'sortOrder' => $sortOrder++,
                ]);
            }
            Craft::info(sprintf('Page preset “%s” copied into page #%d (%d blocks).', $preset->title, $page->id, count($blocks)), __METHOD__);
        } finally {
            self::$running = false;
        }
    }
}
