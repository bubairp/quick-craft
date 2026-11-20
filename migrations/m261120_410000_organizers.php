<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\ElementInterface;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Color;
use craft\fields\Dropdown;

/**
 * Organizers page (Figma 17-267; CLAUDE.md › pageBuilder blocks › Organizers page):
 *  - cardLayout + "photoCards"; testimonial + `logo` (image instance); featureRows + `glow`;
 *    Color Code palette + `lime`.
 *  - Content (only when the page has no banner heading): the centered banner with rotating words and
 *    a 15-image collage, quotes, 4 alternating feature rows, "Take the stress out of planning"
 *    (text cards with images), "Join thousands…" (photo cards) and "Get started for free".
 * PLACEHOLDER: copy and images come from the imported reference design (third-party club names and
 * photos); links point at example.com.
 */
class m261120_410000_organizers extends Migration
{
    private const APP = 'https://example.com/app'; // PLACEHOLDER

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();

        // 1. Content model
        $cardLayout = $fields->getFieldByHandle('cardLayout');
        if ($cardLayout instanceof Dropdown && !in_array('photoCards', array_column($cardLayout->options, 'value'), true)) {
            $options = $cardLayout->options;
            $options[] = ['label' => 'Photo cards (text over the image)', 'value' => 'photoCards', 'default' => false];
            $cardLayout->options = $options;
            $this->check($fields->saveField($cardLayout), $cardLayout, 'cardLayout');
        }
        $this->addInstance('testimonial', 'image', 'logo', 'Logo (beside the photo)', 'Quotes layout: a club or brand logo shown tilted next to the round photo.');
        $this->addInstance('featureRows', 'lightswitchOff', 'glow', 'Soft glow background', 'Alternating layout, light schemes.');
        $color = $fields->getFieldByHandle('colorcode');
        if ($color instanceof Color && !in_array('lime', array_column($color->palette, 'label'), true)) {
            $palette = $color->palette;
            $palette[] = ['color' => '#9ed104', 'label' => 'lime', 'default' => false];
            $color->palette = $palette;
            $this->check($fields->saveField($color), $color, 'colorcode');
        }

        // 2. Content
        $page = Entry::find()->section('pages')->slug('organizers')->status(null)->one();
        if (!$page || $page->heading) {
            return true;
        }

        // Banner
        $collage = ['org-banner-01-speed-date', 'org-banner-02-brunch', 'org-banner-03-run-club', 'org-banner-04-cowboy',
            'org-banner-05-jazz', 'org-banner-06-groove', 'org-banner-07-diy-craft', 'org-banner-08-peace',
            'org-banner-09-poker', 'org-banner-10-board-gamers', 'org-banner-11-kickball', 'org-banner-12-pickleball',
            'org-banner-13-plot-thickens', 'org-banner-14-hands-chin', 'org-banner-15-long-hair'];
        $page->setFieldValues([
            'heroLayout' => 'centered', 'scheme' => 'light',
            'eyebrow' => 'Quick Craft for Organizers',
            'heading' => 'The best way to organize your',
            'rotatingWords' => array_map(fn($w) => ['col1' => $w], ['Writers Group', 'Craft Circle', 'Run Club', 'Book Club', 'Supper Club']),
            'text' => 'Plan faster, invite effortlessly, and keep your community coming back— all for free.',
            'images' => $this->assetIds($collage),
        ]);
        $this->save($page, 'Organizers banner');
        $this->button($page, 'buttons', 'Try it out', 'primary', 1);

        $sort = (int)Entry::find()->ownerId($page->id)->field($fields->getFieldByHandle('pageBuilder'))->status(null)->count();

        // Quotes
        $quotes = $this->block($page, 'testimonials', ++$sort, ['testimonialLayout' => 'quotes', 'heading' => 'A home for your events']);
        foreach ([
            ['It\'s given me great community, career advice, and fun times.', 'Hank', 'hackNY', 1],
            ['It brings a special comfort to trying new things.', 'Azuré', 'Muñeca', 2],
            ['I\'ve improved my chess skills and created long-lasting friendships.', 'Amanda', 'Rookie Chess', 3],
        ] as $i => [$quote, $name, $club, $n]) {
            $this->nested($quotes, 'testimonials', 'testimonial', $i + 1, [
                'text' => $quote, 'personName' => $name, 'personDetail' => $club,
                'image' => $this->assetIds(["org-quote-$n-person"]), 'logo' => $this->assetIds(["org-quote-$n-logo"]),
            ]);
        }

        // Feature rows
        $rows = $this->block($page, 'featureRows', ++$sort, ['featureLayout' => 'alternating', 'scheme' => 'light', 'glow' => true]);
        foreach ([
            ['Your events and people, all in one place', 'Send invites, collect RSVPs, and showcase all your events with one shareable link.', 'Get started', 1],
            ['Event pages that match your aesthetic', 'Make your invites unmistakably yours with easy-to-use designs, custom animations, and a vibe that’s all about you.', 'Try it for free', 2],
            ['Share updates in real-time', "Last minute change? No problem.\n\nEasily send updates with Text Blasts and automated reminders.", 'Get started', 3],
            ['Sell tickets or request money', 'Add ticket tiers, promo codes, and QR code check in to your event. Or, use the Chip In feature to request money.', 'Try it for free', 4],
        ] as $i => [$heading, $text, $label, $n]) {
            $this->nested($rows, 'cards', 'card', $i + 1, [
                'heading' => $heading, 'text' => $text, 'image' => $this->assetIds(["org-row-$n"]),
                'primaryButton' => ['type' => 'url', 'value' => self::APP, 'label' => $label],
            ]);
        }

        // Take the stress out of planning
        $stress = $this->block($page, 'cardGrid', ++$sort, ['cardLayout' => 'textCards', 'scheme' => 'light', 'glow' => true, 'heading' => 'Take the stress out of planning']);
        foreach ([
            ['Plan together', 'Work with co-admins to create and manage events without sharing logins.'],
            ['Build your community', 'Keep everyone in the loop. Grow a community that shows up.'],
            ['Switch between profiles', 'Toggle between personal and organization profiles for easier management.'],
        ] as $i => [$heading, $text]) {
            $this->nested($stress, 'cards', 'card', $i + 1, ['heading' => $heading, 'text' => $text, 'image' => $this->assetIds(['org-card-' . ($i + 1)])]);
        }
        $this->button($stress, 'buttons', 'Get started, 100% free!', 'primary', 1);

        // Join thousands of organizers
        $community = $this->block($page, 'cardGrid', ++$sort, ['cardLayout' => 'photoCards', 'scheme' => 'light', 'glow' => true,
            'heading' => 'Join thousands of organizers hosting worldwide with Quick Craft']);
        foreach ([['Bad Film Club', 'New York, NY'], ['Rookie Chess', 'Los Angeles, CA'], ['Hot Literati', 'New York, NY']] as $i => [$heading, $text]) {
            $this->nested($community, 'cards', 'card', $i + 1, ['heading' => $heading, 'text' => $text, 'image' => $this->assetIds(['org-community-' . ($i + 1)])]);
        }

        // Get started for free
        $start = $this->block($page, 'cardGrid', ++$sort, ['cardLayout' => 'textCards', 'scheme' => 'light', 'glow' => true, 'spacing' => 'noTop',
            'heading' => 'Get started for free', 'text' => 'Plan your next event in just a few clicks. Choose how you want to start:']);
        $this->button($start, 'buttons', 'Get the app', 'secondary', 1);
        $this->button($start, 'buttons', 'Use on web', 'primary', 2);

        echo "    > Organizers: banner + 5 blocks\n";
        return true;
    }

    // Helpers

    private function addInstance(string $typeHandle, string $baseHandle, string $handle, string $label, string $instructions): void
    {
        $entries = Craft::$app->getEntries();
        $type = $entries->getEntryTypeByHandle($typeHandle);
        $layout = $type->getFieldLayout();
        if ($layout->getFieldByHandle($handle)) {
            return;
        }
        $tab = $layout->getTabs()[0];
        $els = $tab->getElements();
        $els[] = new CustomField(Craft::$app->getFields()->getFieldByHandle($baseHandle), [
            'handle' => $handle, 'label' => $label, 'instructions' => $instructions,
            'uid' => $this->stableUid("el:$typeHandle:$handle"),
        ]);
        $tab->setElements($els);
        $type->setFieldLayout($layout);
        $this->check($entries->saveEntryType($type), $type, $typeHandle);
    }

    /** @return int[] asset ids for the filenames (any extension) that exist */
    private function assetIds(array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $asset = Asset::find()->filename(["$name.png", "$name.jpg"])->one();
            if ($asset) {
                $ids[] = $asset->id;
            }
        }
        return $ids;
    }

    private function block(Entry $page, string $type, int $sort, array $values): Entry
    {
        $block = new Entry([
            'fieldId' => Craft::$app->getFields()->getFieldByHandle('pageBuilder')->id,
            'ownerId' => $page->id, 'primaryOwnerId' => $page->id, 'siteId' => $page->siteId,
            'typeId' => Craft::$app->getEntries()->getEntryTypeByHandle($type)->id, 'sortOrder' => $sort,
        ]);
        $block->setFieldValues($values);
        $this->save($block, "$type block");
        return $block;
    }

    private function nested(ElementInterface $owner, string $field, string $type, int $sort, array $values): Entry
    {
        $entry = new Entry([
            'fieldId' => Craft::$app->getFields()->getFieldByHandle($field)->id,
            'ownerId' => $owner->id, 'primaryOwnerId' => $owner->id, 'siteId' => $owner->siteId,
            'typeId' => Craft::$app->getEntries()->getEntryTypeByHandle($type)->id, 'sortOrder' => $sort,
        ]);
        $entry->setFieldValues($values);
        $this->save($entry, "$type in $field");
        return $entry;
    }

    private function button(ElementInterface $owner, string $field, string $label, string $style, int $sort): void
    {
        $this->nested($owner, $field, 'button', $sort, [
            'buttonLink' => ['type' => 'url', 'value' => self::APP, 'label' => $label],
            'buttonStyle' => $style,
        ]);
    }

    private function save(ElementInterface $element, string $what): void
    {
        $this->check(Craft::$app->getElements()->saveElement($element), $element, $what);
    }

    private function check(bool $ok, $model, string $what): void
    {
        if (!$ok) {
            throw new \RuntimeException("$what: " . json_encode($model->getErrors()));
        }
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
