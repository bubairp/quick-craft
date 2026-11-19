<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;

/**
 * Press strip (Home, Figma 6-1259): give the logos without a quote their quote, so every item
 * shows text when active. Quotes are the reference site's (partiful.com) press strip, verbatim.
 *
 * Skipped on purpose: The New York Times and USA Today — their reference quotes name the
 * reference brand; inventing a version that names Quick Craft would fabricate a quote from a
 * real publication. Add real quotes for them in the CP.
 *
 * PLACEHOLDER: all press logos/quotes are real publications' (from the reference). Replace
 * with Quick Craft's real press before launch.
 */
class m261119_100000_press_quotes extends Migration
{
    private const QUOTES = [
        'TIME' => 'Brings event planning into the social media age',
        'Business Insider' => 'The de facto invite tool for young Americans',
        'Inc.' => 'The platform of choice for event planning',
        'Google Play' => 'Google Play Store App of the Year 2024',
        'App Store' => 'Apple App Store Award for Cultural Impact (Finalist)',
    ];

    public function safeUp(): bool
    {
        $home = Entry::find()->section('home')->one();
        $block = $home?->getFieldValue('pageBuilder')->type('testimonials')->testimonialLayout('press')->one();
        if (!$block) {
            echo "    > Press block not found — skipped.\n";
            return true;
        }
        foreach ($block->getFieldValue('testimonials')->type('testimonial')->status(null)->all() as $item) {
            $quote = self::QUOTES[$item->getFieldValue('personName')] ?? null;
            if (!$quote || trim((string)$item->getFieldValue('text')) !== '') {
                continue; // unknown outlet, or it already has a quote (never overwrite)
            }
            $item->setFieldValue('text', $quote);
            if (!Craft::$app->getElements()->saveElement($item)) {
                throw new \RuntimeException($item->getFieldValue('personName') . ': ' . json_encode($item->getErrors()));
            }
        }
        Craft::$app->getElements()->invalidateCachesForElement($home);
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
