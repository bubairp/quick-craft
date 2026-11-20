<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\ElementInterface;
use craft\db\Migration;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;

/**
 * Invitation Maker page (Figma 17-1440; CLAUDE.md › pageBuilder blocks › Invitation Maker page):
 *  - ctaBanner + `glow` (lightswitchOff instance).
 *  - Content (only when the page has no banner heading): split banner with the eyebrow pill; then, in
 *    Figma's order around the existing comparison and FAQ blocks: gallery row (1), cards (2),
 *    comparison (3), reviews (4), rich text (5), FAQ (6), CTA (7).
 * PLACEHOLDER: copy and images come from the imported reference design; links → example.com.
 */
class m261120_430000_invitation_maker extends Migration
{
    private const APP = 'https://example.com/app'; // PLACEHOLDER

    private const TILES = [
        ['Kids’ Birthday', 'Birthday invitation with a pink cake on a rainbow striped background'],
        ['Birthday', 'Matcha-themed birthday invitation with a green heart-shaped cake'],
        ['Drinks', 'Drinks invitation with friends gathered around an oversized cocktail glass'],
        ['Save The Date', 'Save the date invitation with blue serif lettering on a soft gradient'],
        ['Dinner', 'Dinner invitation with hand-painted vegetables and nom nom nom lettering'],
        ['Graduation', 'Graduation invitation with a black cap and tassel on deep green'],
    ];

    public function safeUp(): bool
    {
        $this->addInstance('ctaBanner', 'lightswitchOff', 'glow', 'Soft glow background', 'Simple layout, light schemes.');

        $page = Entry::find()->section('pages')->slug('invitation-maker')->status(null)->one();
        if (!$page || $page->heading) {
            return true;
        }

        // Banner
        $banner = $this->asset('im-banner');
        if ($banner && !$banner->alt) {
            $banner->alt = 'Two phones showing colorful invitations, surrounded by festive balloons.';
            $this->save($banner, 'im-banner');
        }
        $page->setFieldValues([
            'heroLayout' => 'split', 'scheme' => 'light',
            'eyebrow' => 'Free · No Ads · No App Required',
            'heading' => 'The free invitation maker that actually sends the invite.',
            'text' => 'Design it, send it by text or email, and track every RSVP — all in one place. Other tools stop at the design.',
            'images' => $banner ? [$banner->id] : [],
        ]);
        $this->save($page, 'Invitation Maker banner');
        $this->button($page, 'buttons', 'Make a free invite →', 'primary', 1);

        // Existing blocks into Figma's order
        $pageBuilder = Craft::$app->getFields()->getFieldByHandle('pageBuilder');
        $query = fn() => Entry::find()->ownerId($page->id)->field($pageBuilder)->status(null);
        foreach (['comparison' => 3, 'faq' => 6] as $type => $sort) {
            $id = $query()->type($type)->ids()[0] ?? null;
            if ($id) {
                $this->update(Table::ELEMENTS_OWNERS, ['sortOrder' => $sort], ['elementId' => $id, 'ownerId' => $page->id], [], false);
            }
        }

        // 1. Find your vibe instantly
        $tileIds = [];
        foreach (self::TILES as $i => [$caption, $alt]) {
            $asset = $this->asset('im-tile-' . ($i + 1));
            if ($asset) {
                $asset->title = $caption;
                $asset->alt = $asset->alt ?: $alt;
                $this->save($asset, "tile $caption");
                $tileIds[] = $asset->id;
            }
        }
        $gallery = $this->block($page, 'imageGallery', 1, [
            'galleryLayout' => 'row', 'eyebrow' => 'Endless design options', 'heading' => 'Find your vibe instantly',
            'text' => 'Start with a template, or build from scratch. Free for any kind of event.', 'images' => $tileIds,
        ]);
        $this->button($gallery, 'buttons', 'View all →', 'primary', 1);

        // 2. More than a maker
        $cards = $this->block($page, 'cardGrid', 2, [
            'cardLayout' => 'textCards', 'scheme' => 'light', 'glow' => true,
            'heading' => 'More than a maker', 'text' => 'Other tools just design a card. Quick Craft does the whole party.',
        ]);
        foreach ([
            ['Design it your way', 'Customizable themes, fonts, and animations that actually move.'],
            ['Send by text in two taps', 'One link works for everyone. No app needed for guests.'],
            ['Track every RSVP', 'Real-time guest list, automatic reminders, comments, photos.'],
        ] as $i => [$heading, $text]) {
            $image = $this->asset('im-card-' . ($i + 1));
            $this->nested($cards, 'cards', 'card', $i + 1, [
                'heading' => $heading, 'text' => $text, 'image' => $image ? [$image->id] : [],
                'primaryButton' => ['type' => 'url', 'value' => self::APP, 'label' => 'Try it'],
            ]);
        }

        // 4. People are obsessed
        $reviews = $this->block($page, 'testimonials', 4, [
            'testimonialLayout' => 'reviews', 'eyebrow' => 'Reviews', 'heading' => 'People are obsessed',
            'text' => 'Don’t just take our word for it.',
        ]);
        foreach ([
            ['Best Party/Event App', 'Nov 14', 'MerpZilla', 'By far the best app for events. Puts Facebook events or evite to shame.'],
            ['Finally a good app that’s 100% free', 'Nov 7', 'Tlackshots girl', 'I LOVE THIS APP! It’s 100% free, it’s cute, and innovative! I love that you can change the theme, the backgrounds, add a link for the address and get RSVPs…'],
            ['i love quick craft', 'Nov 18', 'Juliette Legault', 'helped me coordinate a surprise party for my bf. I love it so much it makes planning parties so easy !!!'],
        ] as $i => [$heading, $date, $name, $text]) {
            $this->nested($reviews, 'testimonials', 'testimonial', $i + 1, [
                'heading' => $heading, 'personDetail' => $date, 'personName' => $name, 'text' => $text, 'rating' => 5,
            ]);
        }

        // 5. Rich text (SEO)
        $this->block($page, 'richText', 5, [
            'heading' => 'Everything to know about free invitation makers',
            'body' => implode('', [
                '<p>What is the best free invitation maker? The best free invitation maker depends on what you actually want it to do. If you just want to design a static card to print or post on social, plenty of design tools work fine. But most people making an invite actually need to <i>get people to come</i> — which means sending the invite, collecting RSVPs, sending reminders, and not losing track of who’s bringing what.</p>',
                '<p>That’s what Quick Craft does, all free, with no ads and no premium tiers. Pick a theme, customize it, send it by text or email, and watch the RSVPs roll in. You can make <a href="/birthday-invitations">birthday invitations</a>, wedding invitations, dinner parties, baby showers, housewarmings, holiday parties, game nights, and more — all from the same free tool.</p>',
                '<p>How to make a free invitation online: going from idea to invite takes about a minute — no app download, no signup wall. Pick a template or start from scratch, then customize the colors, fonts, and animations to match the vibe of your event. Add your date, location, start time, and any details guests need to know.</p>',
                '<p>Then send it by text, link, or email. Guests RSVP from any browser — they don’t need an account or an app. You’ll see who’s coming in real time, and Quick Craft sends the reminders automatically so you don’t have to nag anyone in the group chat.</p>',
                '<p>Is Quick Craft really free? Yes. No ads, no coins, no “free for the first 10 guests” trick. Every feature — text invitations, RSVP tracking, automatic reminders, guest polls, shared photo albums, comment walls, co-host support — is free for everyone, every time.</p>',
                '<p>What can you make with Quick Craft’s free invitation maker? Anything. The most popular categories are <a href="/birthday-invitations">birthday invitations</a>, wedding invitations (including save the dates and engagement parties), dinner party invitations, baby showers, housewarmings, graduation parties, holiday parties, game nights, BBQs, brunches, and pool parties. There are hundreds of customizable templates, and you can always start from a blank canvas.</p>',
            ]),
        ]);

        // 7. CTA
        $cta = $this->block($page, 'ctaBanner', 7, [
            'ctaLayout' => 'simple', 'scheme' => 'light', 'glow' => true,
            'heading' => 'Your party deserves a great invite.', 'text' => 'Create yours in 2 minutes — no credit card, no app, no catch.',
        ]);
        $this->button($cta, 'buttons', 'Create invite', 'primary', 1);

        echo "    > Invitation Maker: banner + gallery, cards, reviews, rich text, CTA (comparison 3, FAQ 6)\n";
        return true;
    }

    // Helpers (as in m261120_420000_birthday)

    private function asset(string $name): ?Asset
    {
        return Asset::find()->filename(["$name.png", "$name.jpg"])->one();
    }

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
