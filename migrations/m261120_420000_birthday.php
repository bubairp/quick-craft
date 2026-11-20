<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\ElementInterface;
use craft\db\Migration;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Dropdown;

/**
 * Birthday Invitations page (Figma 17-890; CLAUDE.md › pageBuilder blocks › Birthday Invitations page):
 *  - ctaLayout + "simple"; imageGallery + `note` (plainText instance).
 *  - Gallery tiles: asset title = caption, alt from the Figma layer names.
 *  - Content (only when the page has no banner heading): split banner; gallery "Endless design options"
 *    and the "Get your birthday party started" cards inserted after the promo bar (before the existing
 *    rich text); a simple "Get the app" CTA at the end.
 * PLACEHOLDER: copy and images come from the imported reference design; links → example.com.
 */
class m261120_420000_birthday extends Migration
{
    private const APP = 'https://example.com/app'; // PLACEHOLDER

    /** [caption, alt] for bday-tile-01…18 */
    private const TILES = [
        ['Birthday Balloons', 'Retro-style birthday invitation with a person floating in the clouds and colorful balloons'],
        ['bday.mp3', 'Party playlist birthday invitation with a beer background and a pastel folder icon'],
        ['Dirty 30', 'Dirty thirty birthday invitation with a bold red and white design and floating hearts'],
        ['29 Club', 'Wine-themed 29th birthday invitation with confetti and a vintage look'],
        ['Raise a glass', 'Birthday invitation with a glass of wine above a heart-shaped cake'],
        ['House party', 'Minimalist birthday invitation featuring a cozy living room with gold Birthday balloons'],
        ['Surprise!', 'Moody surprise party invitation with an old-fashioned drink in smoky green hues'],
        ['End of an era', 'Quarter-life crisis birthday invitation with a pink heart-shaped cake and ribbons'],
        ['Queen 🐝', 'Bold purple 3D lettering on a pastel gradient background'],
        ['Minimal', 'A white napkin with the word birthday typed in lowercase on a soft purple gradient'],
        ['Sunset', 'A scalloped card reading it’s my birthday on a pink-orange sunset gradient'],
        ['Bday Woes', 'A handwritten note inviting the reader to a birthday on a glowing purple background'],
        ['blink-23', 'A parody invitation with bold white text on a wrinkled blue backdrop and a checkerboard border'],
        ['Bespoke', 'A black card with pink cursive You’re Invited text on a pastel watercolor background'],
        ['This is fine', 'A pixelated meme invitation reading Yay another year on a textured orange background'],
        ['Poolside', 'A tropical invitation with pink clouds and palm trees over turquoise water'],
        ['Celebrate', 'A confetti-covered dark blue invitation reading Birthday Bash in yellow and red'],
        ['Party Time', 'A holographic invitation with neon blue and purple tones and pixel graphics'],
    ];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $elements = Craft::$app->getElements();

        // 1. Content model
        $ctaLayout = $fields->getFieldByHandle('ctaLayout');
        if ($ctaLayout instanceof Dropdown && !in_array('simple', array_column($ctaLayout->options, 'value'), true)) {
            $options = $ctaLayout->options;
            $options[] = ['label' => 'Simple (heading, text, badges)', 'value' => 'simple', 'default' => false];
            $ctaLayout->options = $options;
            $this->check($fields->saveField($ctaLayout), $ctaLayout, 'ctaLayout');
        }
        $this->addInstance('imageGallery', 'plainText', 'note', 'Note above the buttons', 'Grid and row layouts, e.g. “Choose from thousands of options”.');

        // 2. Tile captions + alt
        $tileIds = [];
        foreach (self::TILES as $i => [$caption, $alt]) {
            $asset = $this->asset(sprintf('bday-tile-%02d', $i + 1));
            if (!$asset) {
                continue;
            }
            if ($asset->title !== $caption || !$asset->alt) {
                $asset->title = $caption;
                $asset->alt = $asset->alt ?: $alt;
                $this->save($asset, "tile $caption");
            }
            $tileIds[] = $asset->id;
        }

        // 3. Content
        $page = Entry::find()->section('pages')->slug('birthday-invitations')->status(null)->one();
        if (!$page || $page->heading) {
            return true;
        }
        $banner = $this->asset('bday-banner');
        if ($banner && !$banner->alt) {
            $banner->alt = 'Two phones showing colorful birthday invitations, surrounded by festive balloons.';
            $this->save($banner, 'bday-banner');
        }
        $page->setFieldValues([
            'heroLayout' => 'split', 'scheme' => 'light',
            'heading' => 'Fun birthday invitations',
            'text' => 'Free birthday party invites that match your vibe. Create and share in seconds — no app needed.',
            'images' => $banner ? [$banner->id] : [],
        ]);
        $this->save($page, 'Birthday banner');
        $this->button($page, 'buttons', 'Create free invite', 'primary', 1);

        // the gallery and the cards go right after the promo bar (position 2 and 3)
        $pageBuilder = $fields->getFieldByHandle('pageBuilder');
        $query = fn() => Entry::find()->ownerId($page->id)->field($pageBuilder)->status(null);
        $promo = $query()->type('promoBar')->one();
        $position = $promo ? $promo->getSortOrder() + 1 : 1;
        $this->update(Table::ELEMENTS_OWNERS, ['sortOrder' => new \yii\db\Expression('[[sortOrder]] + 2')], [
            'and', ['ownerId' => $page->id], ['>=', 'sortOrder', $position], ['elementId' => $query()->ids()],
        ], [], false);

        $gallery = $this->block($page, 'imageGallery', $position, [
            'galleryLayout' => 'grid', 'heading' => 'Endless design options',
            'text' => 'Start with a template, or customize your own', 'images' => $tileIds,
            'note' => 'Choose from thousands of options',
        ]);
        $this->button($gallery, 'buttons', 'Start creating', 'primary', 1);

        $cards = $this->block($page, 'cardGrid', $position + 1, [
            'cardLayout' => 'textCards', 'scheme' => 'light', 'glow' => true,
            'heading' => 'Get your birthday party started', 'text' => 'Free on any browser, iPhone, and Android',
        ]);
        foreach ([
            ['Fun themes & effects', 'Make the event yours with customizable themes & presets'],
            ['Send invites with one link', 'Invite friends with an RSVP link — your guests don’t need the app!'],
            ['Text Blast your friends', 'Running behind? Send an update to everyone at once'],
        ] as $i => [$heading, $text]) {
            $image = $this->asset('bday-card-' . ($i + 1));
            $this->nested($cards, 'cards', 'card', $i + 1, [
                'heading' => $heading, 'text' => $text, 'image' => $image ? [$image->id] : [],
                'primaryButton' => ['type' => 'url', 'value' => self::APP, 'label' => 'Try it'],
            ]);
        }

        $last = (int)$query()->count();
        $this->block($page, 'ctaBanner', $last + 1, [
            'ctaLayout' => 'simple', 'scheme' => 'light', 'showAppBadges' => true,
            'heading' => 'Want to keep the party going? Get the app', 'text' => 'Easily create and send unique invitations',
        ]);

        echo "    > Birthday Invitations: banner + gallery, cards, CTA\n";
        return true;
    }

    // Helpers (as in m261120_410000_organizers)

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
