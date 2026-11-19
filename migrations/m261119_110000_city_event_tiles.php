<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;

/**
 * City list ("The best events in your city", Figma 6-4774) — event tiles like the reference
 * (partiful.com): every city shows 8 event images; hovering an image shows its title and a
 * subtitle underneath.
 *
 *  1. Images volume: add the plain-text `text` field (label "Subtitle") to the asset layout.
 *     Tile text = asset title (event name) + text (e.g. "34 going").
 *  2. Event title/subtitle on New York City's 8 Figma images (city-01…08).
 *  3. Los Angeles, San Francisco, Chicago, Everywhere: the reference's 8 images each
 *     (placeholders/city-{la,sf,chi,everywhere}-1…8, downloaded from the reference site),
 *     set on the city card when it has fewer than 8 images.
 *
 * PLACEHOLDER: images and event names are the reference site's. Replace before launch.
 */
class m261119_110000_city_event_tiles extends Migration
{
    /** The reference shows the same 8 events (title, subtitle) for every city. */
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

    private const CITIES = [
        'Los Angeles' => 'city-la-',
        'San Francisco' => 'city-sf-',
        'Chicago' => 'city-chi-',
        'Everywhere' => 'city-everywhere-',
    ];

    public function safeUp(): bool
    {
        $this->addSubtitleToAssetLayout();

        // New York City: the existing Figma images city-01…08
        $nyc = [];
        foreach (range(1, 8) as $n) {
            $nyc[] = $this->asset(sprintf('city-%02d', $n));
        }
        $this->label($nyc);

        $home = Entry::find()->section('home')->one();
        $block = $home?->getFieldValue('pageBuilder')->type('cityList')->one();
        if (!$block) {
            echo "    > City list block not found — layout updated, content skipped.\n";
            return true;
        }
        $cards = [];
        foreach ($block->getFieldValue('cards')->status(null)->all() as $card) {
            $cards[$card->getFieldValue('heading')] = $card;
        }

        foreach (self::CITIES as $city => $prefix) {
            $assets = array_map(fn($n) => $this->asset($prefix . $n), range(1, 8));
            if (in_array(null, $assets, true)) {
                echo "    > $city: placeholder images missing — skipped.\n";
                continue;
            }
            $this->label($assets);
            $card = $cards[$city] ?? null;
            if ($card && $card->getFieldValue('images')->count() < 8) {
                $card->setFieldValue('images', array_map(fn(Asset $a) => $a->id, $assets));
                if (!Craft::$app->getElements()->saveElement($card)) {
                    throw new \RuntimeException("$city: " . json_encode($card->getErrors()));
                }
            }
        }

        Craft::$app->getElements()->invalidateCachesForElement($home);
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }

    private function addSubtitleToAssetLayout(): void
    {
        $volumes = Craft::$app->getVolumes();
        $volume = $volumes->getVolumeByHandle('images');
        $text = Craft::$app->getFields()->getFieldByHandle('text');
        $layout = $volume->getFieldLayout();
        if ($layout->getFieldByHandle('text')) {
            return;
        }
        $tab = $layout->getTabs()[0];
        $tab->setElements(array_merge($tab->getElements(), [
            new CustomField($text, [
                'label' => 'Subtitle',
                'instructions' => 'Short line under the title where the image is shown with text, e.g. “34 going” (city list event tiles).',
            ]),
        ]));
        $volume->setFieldLayout($layout);
        if (!$volumes->saveVolume($volume)) {
            throw new \RuntimeException('images volume: ' . json_encode($volume->getErrors()));
        }
    }

    /** Find a placeholder by file name without extension (files are .webp or .png). */
    private function asset(string $base): ?Asset
    {
        return Asset::find()->volume('images')->folderPath('placeholders/')->filename($base . '.*')->one();
    }

    /** Give the assets (in order) the reference's event titles/subtitles; never overwrite a subtitle. */
    private function label(array $assets): void
    {
        foreach (array_values($assets) as $i => $asset) {
            if (!$asset || !isset(self::EVENTS[$i])) {
                continue;
            }
            [$title, $subtitle] = self::EVENTS[$i];
            if (trim((string)$asset->getFieldValue('text')) !== '') {
                continue;
            }
            $asset->title = $title;
            $asset->alt = $asset->alt ?: $title;
            $asset->setFieldValue('text', $subtitle);
            if (!Craft::$app->getElements()->saveElement($asset)) {
                throw new \RuntimeException($asset->filename . ': ' . json_encode($asset->getErrors()));
            }
        }
    }
}
