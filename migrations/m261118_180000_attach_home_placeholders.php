<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;

/**
 * Attach placeholder images (exported from Figma into images/placeholders/) to the
 * Home test content, set their alt text, and use Figma's review titles.
 *
 * Uploads are gitignored: on a machine without the placeholder files this
 * migration does nothing. Re-export them from Figma (see docs/build-log.md),
 * run `ddev craft index-assets/one images/placeholders`, then run this again
 * via `ddev craft migrate/redo` or a new migration.
 */
class m261118_180000_attach_home_placeholders extends Migration
{
    /** @var array<string, Asset> */
    private array $assets = [];

    public function safeUp(): bool
    {
        foreach (Asset::find()->volume('images')->folderPath('placeholders/')->all() as $asset) {
            $this->assets[$asset->filename] = $asset;
        }
        if (!$this->assets) {
            echo "    > No placeholder assets found in images/placeholders/ — skipping.\n";
            return true;
        }

        $this->setAlt('home-hero.png', 'Friends laughing at a party, with chat bubbles and an invite');
        $this->setAlt('home-step-1.png', 'Invite editor with theme, effect and font options');
        $this->setAlt('home-step-2.png', 'Phone showing guests RSVPing to an event');
        $this->setAlt('home-step-3.png', 'Phone showing a text blast to guests');
        $this->setAlt('home-phone-1.png', 'Phone showing sent text blasts');
        $this->setAlt('home-phone-2.png', 'Phone showing questions asked when guests RSVP');
        $this->setAlt('home-phone-3.png', 'Phone showing an event activity feed');
        $this->setAlt('home-tile-1.png', 'Roll of event tickets with money flying');
        $this->setAlt('home-tile-2.png', 'Phone showing an event guest list');
        $this->setAlt('home-tile-3.png', 'Paper plane next to the app icon');
        $this->setAlt('home-tile-4.png', 'Phone showing an automated event reminder');
        foreach ($this->assets as $name => $asset) {
            if (preg_match('/^(gallery|city|ring)-\d+/', $name) && !$asset->alt) {
                $this->setAlt($name, 'Event invite design');
            }
        }
        foreach (['home-stat-bg.png', 'home-quote-bg.jpg'] as $decorative) {
            $this->setAlt($decorative, '');
        }
        foreach (['home-review-1.png', 'home-review-2.png', 'home-review-3.png'] as $name) {
            $this->setAlt($name, 'Friends at a party');
        }

        $home = Entry::find()->section('home')->one();
        if (!$home) {
            return true;
        }
        $blocks = $home->getFieldValue('pageBuilder')->all();
        $byType = fn(string $type, ?string $heading = null) => array_values(array_filter(
            $blocks,
            fn(Entry $b) => $b->type->handle === $type && ($heading === null || $b->heading === $heading)
        ))[0] ?? null;

        // hero
        $this->set($byType('hero'), ['images' => $this->ids(['home-hero.png'])]);

        // "One link" cards
        $this->setChildren($byType('cardGrid', 'One link. Everyone’s in.'), 'cards', [
            ['image' => $this->ids(['home-step-1.png'])],
            ['image' => $this->ids(['home-step-2.png'])],
            ['image' => $this->ids(['home-step-3.png'])],
        ]);

        // gallery fan
        $this->set($byType('imageGallery'), ['images' => $this->ids(array_map(fn($i) => sprintf('gallery-%02d.png', $i), range(1, 20)))]);

        // city thumbnails: NYC gets 8, LA gets 2
        $this->setChildren($byType('cityList'), 'cards', [
            ['images' => $this->ids(array_map(fn($i) => sprintf('city-%02d.png', $i), range(1, 8)))],
            ['images' => $this->ids(['city-09.png', 'city-10.png'])],
        ]);

        // feature rows phones
        $this->setChildren($byType('featureRows'), 'cards', [
            ['image' => $this->ids(['home-phone-1.png'])],
            ['image' => $this->ids(['home-phone-2.png'])],
            ['image' => $this->ids(['home-phone-3.png'])],
        ]);

        // "More than an invite" tiles
        $this->setChildren($byType('cardGrid', 'More than an invite'), 'cards', [
            ['image' => $this->ids(['home-tile-1.png'])],
            ['image' => $this->ids(['home-tile-2.png'])],
            ['image' => $this->ids(['home-tile-3.png'])],
            ['image' => $this->ids(['home-tile-4.png'])],
        ]);

        // testimonials carousel — order: stat 200k, quote, stat 5.0, review x3
        $this->setChildren($byType('testimonials'), 'testimonials', [
            ['image' => $this->ids(['home-stat-bg.png'])],
            ['image' => $this->ids(['home-quote-bg.jpg'])],
            [],
            ['image' => $this->ids(['home-review-2.png']), 'heading' => 'I can’t believe no one’s made this before'], // Quiviler
            ['image' => $this->ids(['home-review-3.png']), 'heading' => 'A must'],                                     // r3boi
            ['image' => $this->ids(['home-review-1.png']), 'heading' => 'The absolute best'],                          // abundantcashflow
        ]);

        // "Get the app" rings
        $this->set($byType('ctaBanner'), ['images' => $this->ids(array_map(fn($i) => sprintf('ring-%02d.png', $i), range(1, 20)))]);

        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }

    private function ids(array $filenames): array
    {
        return array_values(array_filter(array_map(fn($f) => $this->assets[$f]->id ?? null, $filenames)));
    }

    private function setAlt(string $filename, string $alt): void
    {
        $asset = $this->assets[$filename] ?? null;
        if (!$asset || $asset->alt === $alt) {
            return;
        }
        $asset->alt = $alt ?: null;
        Craft::$app->getElements()->saveElement($asset);
    }

    private function set(?Entry $entry, array $values): void
    {
        if (!$entry) {
            return;
        }
        $entry->setFieldValues($values);
        if (!Craft::$app->getElements()->saveElement($entry)) {
            throw new \RuntimeException("{$entry->type->handle}: " . json_encode($entry->getErrors()));
        }
    }

    /** Apply values to the nested entries of $field on $owner, in order. */
    private function setChildren(?Entry $owner, string $field, array $valuesByIndex): void
    {
        if (!$owner) {
            return;
        }
        $children = $owner->getFieldValue($field)->all();
        foreach ($valuesByIndex as $i => $values) {
            if (isset($children[$i]) && $values) {
                $this->set($children[$i], $values);
            }
        }
    }
}
