<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fields\Assets;
use craft\fields\Color;
use remoteprogrammer\simplerpmenu\fields\RpMenuField;

/**
 * More base fields (the user's requests, CLAUDE.md › Base field library):
 *  - Assets: `images` is now images only (all 380 existing relations are images); new `imageVideo`
 *    "Image/Video" (1 image or video) and `video` "Video" (1 video), with the same volume settings as `image`.
 *  - `colorcode` "Color Code": a Color field whose palette is the `@theme` colour tokens
 *    (src/css/app.css), label = token name, custom colours off, so the tokens rule holds.
 *  - `simpleRpMenu` "Simple RP Menu": the plugin's menu selector (value = a menu handle).
 */
class m261120_160000_assets_and_color_fields extends Migration
{
    /** token name => hex, from src/css/app.css (@theme --color-*). Keep in sync. */
    private const COLOR_TOKENS = [
        'white' => '#ffffff', 'black' => '#000000', 'ink' => '#111111', 'ink-soft' => '#30302f',
        'night' => '#1c1c1a', 'graphite' => '#4b4b49', 'muted' => '#5f5f5b', 'subtle' => '#777772',
        'paper' => '#fdfdfb', 'surface' => '#f7f7f7', 'line' => '#ececec', 'accent' => '#7b41d9',
        'success' => '#31c431', 'star' => '#fe8207', 'glow-pink' => '#ffc4fe', 'glow-blue' => '#5fd8ff',
    ];

    public function safeUp(): bool
    {
        $f = Craft::$app->getFields();

        // Images: images only
        $images = $f->getFieldByHandle('images');
        if ($images instanceof Assets && $images->allowedKinds !== ['image']) {
            $images->restrictFiles = true;
            $images->allowedKinds = ['image'];
            $this->save($images, 'images: images only');
        }

        // Image/Video and Video, based on `image`
        $image = $f->getFieldByHandle('image');
        foreach (['imageVideo' => ['Image/Video', ['image', 'video']], 'video' => ['Video', ['video']]] as $handle => [$name, $kinds]) {
            if ($f->getFieldByHandle($handle) || !$image instanceof Assets) {
                continue;
            }
            $settings = $image->getSettings();
            $field = new Assets(array_merge($settings, [
                'name' => $name,
                'handle' => $handle,
                'uid' => $this->stableUid("field:$handle"),
                'restrictFiles' => true,
                'allowedKinds' => $kinds,
                'maxRelations' => 1,
            ]));
            $this->save($field, "$handle created");
        }

        // Color Code: token palette only
        if (!$f->getFieldByHandle('colorcode')) {
            $palette = [];
            foreach (self::COLOR_TOKENS as $token => $hex) {
                $palette[] = ['color' => $hex, 'label' => $token, 'default' => false];
            }
            $field = new Color([
                'name' => 'Color Code',
                'handle' => 'colorcode',
                'uid' => $this->stableUid('field:colorcode'),
                'instructions' => 'Only the site’s design colours can be chosen.',
                'palette' => $palette,
                'allowCustomColors' => false,
            ]);
            $this->save($field, 'colorcode created (token palette)');
        }

        // Simple RP Menu selector
        if (!$f->getFieldByHandle('simpleRpMenu')) {
            $field = new RpMenuField([
                'name' => 'Simple RP Menu',
                'handle' => 'simpleRpMenu',
                'uid' => $this->stableUid('field:simpleRpMenu'),
            ]);
            $this->save($field, 'simpleRpMenu created');
        }

        return true;
    }

    private function save($field, string $message): void
    {
        if (!Craft::$app->getFields()->saveField($field)) {
            throw new \RuntimeException("{$field->handle}: " . json_encode($field->getErrors()));
        }
        echo "    > $message\n";
    }

    /** A UUID derived from a key: the same on every environment. */
    private function stableUid(string $key): string
    {
        $h = md5("quick-craft:$key");
        return sprintf('%s-%s-4%s-%x%s-%s', substr($h, 0, 8), substr($h, 8, 4), substr($h, 13, 3), 8 | (hexdec($h[16]) & 3), substr($h, 17, 3), substr($h, 20, 12));
    }

    public function safeDown(): bool
    {
        return false;
    }
}
