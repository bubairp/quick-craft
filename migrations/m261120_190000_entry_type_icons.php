<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\enums\Color;

/**
 * Entry type icons and colours in the CP (Shopify-style step 3; CLAUDE.md › Entry type icons and
 * colours). Icons are Craft's CP icon names (vendor/craftcms/cms/src/icons/solid/*.svg), not the
 * site's Tabler icons. Colour = kind of block.
 */
class m261120_190000_entry_type_icons extends Migration
{
    private const TYPES = [
        // handle => [icon, colour|null]
        'ctaBanner' => ['bullhorn', 'fuchsia'],
        'promoBar' => ['rectangle-ad', 'fuchsia'],
        'richText' => ['paragraph', 'blue'],
        'faq' => ['circle-question', 'blue'],
        'textMedia' => ['columns-3', 'blue'],
        'comparison' => ['scale-balanced', 'blue'],
        'cardGrid' => ['grid-2', 'emerald'],
        'featureRows' => ['list', 'emerald'],
        'cityList' => ['city', 'emerald'],
        'jobList' => ['briefcase', 'emerald'],
        'pressList' => ['newspaper', 'emerald'],
        'imageGallery' => ['images', 'amber'],
        'image' => ['image', 'amber'],
        'testimonials' => ['quote-left', 'violet'],
        'card' => ['id-card', 'gray'],
        'faqItem' => ['circle-question', 'gray'],
        'testimonial' => ['comments', 'gray'],
        'stat' => ['chart-simple', 'gray'],
        'button' => ['hand-pointer', 'gray'],
        'socialLink' => ['share-nodes', 'gray'],
        'contactDetail' => ['address-card', 'gray'],
        'home' => ['house', null],
        'landingPage' => ['file-lines', null],
        'careers' => ['briefcase', null],
        'press' => ['newspaper', null],
        'blogPost' => ['rss', null],
        'job' => ['briefcase', null],
        'pressMention' => ['link', null],
        'siteSettings' => ['gear', null],
        'newsletter' => ['envelope', null],
        'blogCategory' => ['hashtag', null],
        'department' => ['layer-group', null],
    ];

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $n = 0;
        foreach (self::TYPES as $handle => [$icon, $color]) {
            $type = $entries->getEntryTypeByHandle($handle);
            if (!$type) {
                echo "    > $handle not found — skipped\n";
                continue;
            }
            $newColor = $color ? Color::from($color) : null;
            if ($type->icon === $icon && $type->color === $newColor) {
                continue;
            }
            $type->icon = $icon;
            $type->color = $newColor;
            if (!$entries->saveEntryType($type)) {
                throw new \RuntimeException("$handle: " . json_encode($type->getErrors()));
            }
            $n++;
        }
        echo "    > $n entry types got an icon/colour\n";
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
