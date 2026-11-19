<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;

/**
 * New York City's event tiles: the Figma set (placeholders/city-01…08) repeats images
 * (Stage Break and Point Break twice). Use the reference site's 8 distinct NYC images
 * (placeholders/city-nyc-1…8) instead, with the same event titles/subtitles as the other cities.
 * Only replaces the set while it is still the original Figma one.
 *
 * PLACEHOLDER: images and event names are the reference site's. Replace before launch.
 */
class m261119_111000_city_nyc_tiles extends Migration
{
    private const EVENTS = [
        ['‘Tini night <3', '97 going'],
        ['Craft workshop!!', '20 going'],
        ['Dad rock concert', '4021 going'],
        ['Gals run NYC', '34 going'],
        ['Local night market', '2000 going'],
        ['Lovers & friends', '576 going'],
        ['Monday line dance', '200 going'],
        ['Poetry reading', '130 going'],
    ];

    public function safeUp(): bool
    {
        $home = Entry::find()->section('home')->one();
        $block = $home?->getFieldValue('pageBuilder')->type('cityList')->one();
        $card = $block?->getFieldValue('cards')->status(null)->all()[0] ?? null;
        if (!$card || $card->getFieldValue('heading') !== 'New York City') {
            echo "    > New York City card not found — skipped.\n";
            return true;
        }
        $current = $card->getFieldValue('images')->all();
        if ($current && !str_starts_with($current[0]->filename, 'city-0')) {
            return true; // already changed (by this migration or an editor)
        }

        $assets = [];
        foreach (range(1, 8) as $n) {
            $asset = Asset::find()->volume('images')->folderPath('placeholders/')->filename("city-nyc-$n.*")->one();
            if (!$asset) {
                echo "    > placeholders/city-nyc-$n missing — skipped.\n";
                return true;
            }
            [$title, $subtitle] = self::EVENTS[$n - 1];
            $asset->title = $title;
            $asset->alt = $title;
            $asset->setFieldValue('text', $subtitle);
            if (!Craft::$app->getElements()->saveElement($asset)) {
                throw new \RuntimeException($asset->filename . ': ' . json_encode($asset->getErrors()));
            }
            $assets[] = $asset->id;
        }

        $card->setFieldValue('images', $assets);
        if (!Craft::$app->getElements()->saveElement($card)) {
            throw new \RuntimeException('New York City: ' . json_encode($card->getErrors()));
        }
        Craft::$app->getElements()->invalidateCachesForElement($home);
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
