<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\ElementInterface;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Dropdown;

/**
 * Sell Tickets page (Figma 17-2557, dark; CLAUDE.md › pageBuilder blocks › Sell Tickets page):
 *  - pageBackground + "dark"; cardLayout + "showcase"; imageGallery + `scheme`.
 *  - Content (only when the page has no banner heading): dark split banner; showcase "Easy AF
 *    ticketing"; poster marquee "Built for events that spread"; bento "Don't process payments, fill
 *    the room"; carousel "We've got you covered"; CTA "See you at the door." with the ticket image.
 * PLACEHOLDER: copy and images come from the imported reference design (third-party posters).
 */
class m261120_440000_sell_tickets extends Migration
{
    private const APP = 'https://example.com/app'; // PLACEHOLDER

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();

        // 1. Content model
        $this->addOption('pageBackground', 'dark', 'Dark (black)');
        $this->addOption('cardLayout', 'showcase', 'Showcase (big image cards)');
        $this->addField('imageGallery', 'scheme', 'el:imageGallery:scheme');

        // 2. Content
        $page = Entry::find()->section('pages')->slug('sell-tickets')->status(null)->one();
        if (!$page || $page->heading) {
            return true;
        }
        $banner = $this->asset('st-banner', 'A glowing event ticket with a QR code.');
        $page->setFieldValues([
            'pageBackground' => 'dark', 'heroLayout' => 'split', 'scheme' => 'dark',
            'heading' => 'Where Events Grow',
            'text' => 'The social ticketing platform that turns guests into promoters.',
            'images' => $banner ? [$banner->id] : [],
        ]);
        $this->save($page, 'Sell Tickets banner');
        $this->button($page, 'buttons', 'Get started', 'primary', 1);

        // Easy AF ticketing
        $show = $this->block($page, 'cardGrid', 1, ['cardLayout' => 'showcase', 'scheme' => 'dark', 'heading' => 'Easy AF ticketing']);
        $this->button($show, 'buttons', 'Start selling', 'primary', 1);
        foreach ([
            ['Beautiful event pages', 'Customizable pages designed for sharing and discovery', 'An event page for a vinyl night on a phone'],
            ['Flexible tickets', 'Ticket tiers, promo codes, early access, and more', 'Two ticket tiers with quantity buttons'],
            ['Sales tracking', 'Track sales, payouts, and attendance in real time', 'Cards showing earnings, tickets sold and payouts'],
        ] as $i => [$heading, $text, $alt]) {
            $image = $this->asset('st-show-' . ($i + 1), $alt);
            $this->nested($show, 'cards', 'card', $i + 1, ['heading' => $heading, 'text' => $text, 'image' => $image ? [$image->id] : []]);
        }

        // Built for events that spread (poster marquee)
        $posters = [];
        for ($n = 1; $n <= 15; $n++) {
            $poster = $this->asset(sprintf('st-poster-%02d', $n), 'An event poster');
            if ($poster) {
                $posters[] = $poster->id;
            }
        }
        $this->block($page, 'imageGallery', 2, [
            'galleryLayout' => 'marquee', 'scheme' => 'dark', 'images' => $posters,
            'heading' => 'Built for events that spread', 'text' => 'Join millions of people planning events on Quick Craft',
        ]);

        // Don't process payments, fill the room (bento)
        $bento = $this->block($page, 'cardGrid', 3, ['cardLayout' => 'bento', 'scheme' => 'dark', 'heading' => 'Don’t process payments, fill the room']);
        foreach ([
            ['Local discovery', 'Reach people beyond your guest list through Explore and local event feeds', 'A collage of local event cards with dates and interest counts'],
            ['Word of mouth', 'Events spread naturally through our friends and mutuals network', 'Chat bubbles showing friends going to an event'],
            ['Search', 'Get found by people actively searching for events like yours', 'A search result for an event page'],
        ] as $i => [$heading, $text, $alt]) {
            $image = $this->asset('st-bento-' . ($i + 1), $alt);
            $this->nested($bento, 'cards', 'card', $i + 1, ['heading' => $heading, 'text' => $text, 'image' => $image ? [$image->id] : []]);
        }

        // We've got you covered (carousel)
        $cover = $this->block($page, 'cardGrid', 4, ['cardLayout' => 'carousel', 'scheme' => 'dark',
            'heading' => 'We’ve got you covered', 'text' => 'Everything you need before, during, and after the event']);
        $this->button($cover, 'buttons', 'Get started', 'primary', 1);
        foreach ([
            ['Custom pages', 'Your event can actually look like your event'],
            ['Promo codes', 'Offer exclusive pricing to special guests'],
            ['Capacity limits', 'Set limits on the number of people coming'],
            ['Scan tickets', 'Skip the clipboard. Scan guests in instantly.'],
            ['Text Blasts', 'Reach guests instantly with easy SMS updates'],
            ['Check-in controls', 'Let non-hosts like staff or bouncers check people in'],
        ] as $i => [$heading, $text]) {
            $image = $this->asset('st-cover-' . ($i + 1), $heading);
            $this->nested($cover, 'cards', 'card', $i + 1, ['heading' => $heading, 'text' => $text, 'image' => $image ? [$image->id] : []]);
        }

        // See you at the door.
        $cta = $this->asset('st-cta', 'A roll of blue tickets reading All Set.');
        $this->block($page, 'ctaBanner', 5, [
            'ctaLayout' => 'simple', 'scheme' => 'dark', 'heading' => 'See you at the door.',
            'images' => $cta ? [$cta->id] : [],
        ]);

        echo "    > Sell Tickets: dark banner + showcase, poster marquee, bento, carousel, CTA\n";
        return true;
    }

    // Helpers

    private function addOption(string $fieldHandle, string $value, string $label): void
    {
        $field = Craft::$app->getFields()->getFieldByHandle($fieldHandle);
        if ($field instanceof Dropdown && !in_array($value, array_column($field->options, 'value'), true)) {
            $options = $field->options;
            $options[] = ['label' => $label, 'value' => $value, 'default' => false];
            $field->options = $options;
            $this->check(Craft::$app->getFields()->saveField($field), $field, $fieldHandle);
        }
    }

    private function addField(string $typeHandle, string $fieldHandle, string $uidKey): void
    {
        $entries = Craft::$app->getEntries();
        $type = $entries->getEntryTypeByHandle($typeHandle);
        $layout = $type->getFieldLayout();
        if ($layout->getFieldByHandle($fieldHandle)) {
            return;
        }
        $tab = $layout->getTabs()[0];
        $els = $tab->getElements();
        $els[] = new CustomField(Craft::$app->getFields()->getFieldByHandle($fieldHandle), ['uid' => $this->stableUid($uidKey)]);
        $tab->setElements($els);
        $type->setFieldLayout($layout);
        $this->check($entries->saveEntryType($type), $type, $typeHandle);
    }

    private function asset(string $name, string $alt): ?Asset
    {
        $asset = Asset::find()->filename(["$name.png", "$name.jpg"])->one();
        if ($asset && !$asset->alt) {
            $asset->alt = $alt;
            $this->save($asset, $name);
        }
        return $asset;
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
