<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\ElementInterface;
use craft\db\Migration;
use craft\db\Query;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Dropdown;

/**
 * Pages the menus linked to but that had no page (no Figma design; the user's decisions, 2026-11-20).
 * CLAUDE.md › pageBuilder blocks › Pages without a Figma design.
 *  1. richText + `textLayout` (small | article).
 *  2. Legal pages (terms, privacy, cookies, community-guidelines): Banner simple + richText article,
 *     PLACEHOLDER body (the user pastes the real legal text).
 *  3. Page preset "Occasion invitations" (Birthday's generic blocks) + 8 occasion pages copied from Birthday.
 *  4. Download page (Banner simple + ctaBanner simple with store badges).
 *  5. Menus: those pages by entry ID; Create → Invitation Maker; Help Center → Contact; Explore removed.
 */
class m261120_480000_more_pages extends Migration
{
    private const LEGAL = [
        'terms' => 'Terms & Conditions',
        'privacy' => 'Privacy Policy',
        'cookies' => 'Cookie Policy',
        'community-guidelines' => 'Community Guidelines',
    ];

    /** menu URL => [slug, title, banner heading, banner text] */
    private const OCCASIONS = [
        '/invites/dinners' => ['dinner-invitations', 'Dinner Party Invitations', 'Dinner party invitations', 'Free dinner party invites for any size table. Create and share in seconds — no app needed.'],
        '/invites/housewarmings' => ['housewarming-invitations', 'Housewarming Invitations', 'Fun housewarming invitations', 'Show off the new place. Free housewarming invites you can create and share in seconds.'],
        '/invites/holidays' => ['holiday-invitations', 'Holiday Party Invitations', 'Festive holiday invitations', 'Free holiday party invites for every celebration. Create and share in seconds — no app needed.'],
        '/invites/game-day' => ['game-day-invitations', 'Game Day Invitations', 'Game day invitations', 'Rally the crew for the big game. Free watch party invites, ready in seconds.'],
        '/invites/friendsgiving' => ['friendsgiving-invitations', 'Friendsgiving Invitations', 'Friendsgiving invitations', 'Gather your people around the table. Free Friendsgiving invites with RSVPs in one place.'],
        '/invites/weddings' => ['wedding-invitations', 'Wedding Invitations', 'Wedding invitations', 'Free wedding invites and save-the-dates. Create, share and track RSVPs in seconds.'],
        '/invites/bach-parties' => ['bach-party-invitations', 'Bachelor & Bachelorette Party Invitations', 'Bach party invitations', 'Free bachelor and bachelorette party invites. Create and share in seconds — no app needed.'],
        '/invites/new-years-eve' => ['new-years-eve-invitations', 'New Year’s Eve Invitations', 'New Year’s Eve invitations', 'Ring in the new year. Free NYE party invites, ready in seconds.'],
    ];

    /** Birthday blocks that aren't about birthdays (kept in the copies and the preset) */
    private const GENERIC_BLOCKS = ['imageGallery', 'cardGrid', 'ctaBanner'];

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();
        $elements = Craft::$app->getElements();

        // 1. richText › textLayout
        $textLayout = $fields->getFieldByHandle('textLayout');
        if (!$textLayout) {
            $textLayout = new Dropdown([
                'name' => 'Text layout', 'handle' => 'textLayout',
                'uid' => $this->stableUid('field:textLayout'),
                'instructions' => 'Small: the muted SEO text under a page. Article: readable body text, e.g. legal pages.',
                'options' => [
                    ['label' => 'Small (SEO text, divider below)', 'value' => 'small', 'default' => true],
                    ['label' => 'Article (readable text, e.g. legal pages)', 'value' => 'article', 'default' => false],
                ],
            ]);
            $this->check($fields->saveField($textLayout), $textLayout, 'textLayout');
        }
        $richType = $entries->getEntryTypeByHandle('richText');
        $layout = $richType->getFieldLayout();
        if (!$layout->getFieldByHandle('textLayout')) {
            $tab = $layout->getTabs()[0];
            $els = $tab->getElements();
            array_unshift($els, new CustomField($textLayout, ['uid' => $this->stableUid('el:richText:textLayout')]));
            $tab->setElements($els);
            $richType->setFieldLayout($layout);
            $this->check($entries->saveEntryType($richType), $richType, 'richText');
            echo "    > richText: + textLayout\n";
        }

        $pages = $entries->getSectionByHandle('pages');
        $pageType = $entries->getEntryTypeByHandle('page');
        $urlToEntry = [];

        // 2. Legal pages
        foreach (self::LEGAL as $slug => $title) {
            $page = $this->page($slug);
            if (!$page) {
                $page = new Entry(['sectionId' => $pages->id, 'typeId' => $pageType->id, 'title' => $title, 'slug' => $slug]);
                $page->setFieldValues(['heroLayout' => 'simple', 'scheme' => 'light', 'heading' => $title, 'seo' => $this->seo($title, '')]);
                $this->save($page, $slug);
                $this->block($page, 'richText', 1, [
                    'textLayout' => 'article', 'spacing' => 'default',
                    'body' => '<p><strong>Placeholder.</strong> Paste the final ' . htmlspecialchars($title) . ' here before launch. This page must not go live with this text.</p>',
                ]);
                echo "    > page /$slug (placeholder body)\n";
            }
            $urlToEntry["/$slug"] = $page->id;
        }

        // 3. Occasion pages, copied from Birthday Invitations
        $birthday = $this->page('birthday-invitations');
        if ($birthday) {
            // the preset: Birthday's generic blocks
            $presetSection = $entries->getSectionByHandle('pagePresets');
            $preset = Entry::find()->section('pagePresets')->title('Occasion invitations')->status(null)->one();
            if ($presetSection && !$preset) {
                $preset = new Entry([
                    'sectionId' => $presetSection->id,
                    'typeId' => $entries->getEntryTypeByHandle('pagePreset')->id,
                    'title' => 'Occasion invitations',
                ]);
                $this->save($preset, 'preset');
                $sort = 1;
                foreach ($this->blocks($birthday) as $block) {
                    if (in_array($block->type->handle, self::GENERIC_BLOCKS, true)) {
                        $copy = $elements->duplicateElement($block, [
                            'ownerId' => $preset->id, 'primaryOwnerId' => $preset->id, 'sortOrder' => $sort++,
                        ]);
                        $this->genericHeading($copy);
                    }
                }
                echo '    > page preset “Occasion invitations” (' . ($sort - 1) . " blocks)\n";
            }

            foreach (self::OCCASIONS as $url => [$slug, $title, $heading, $text]) {
                $page = $this->page($slug);
                if (!$page) {
                    /** @var Entry $page */
                    $page = $elements->duplicateElement($birthday, ['title' => $title, 'slug' => $slug]);
                    $page->setFieldValues([
                        'heading' => $heading, 'text' => $text,
                        'seo' => $this->seo($title, $text),
                        'pagePreset' => $preset ? [$preset->id] : [],
                    ]);
                    $this->save($page, $slug);
                    foreach ($this->blocks($page) as $block) {
                        if (!in_array($block->type->handle, self::GENERIC_BLOCKS, true)) {
                            $elements->deleteElement($block, true); // Birthday-only: promo bar, SEO text
                        } else {
                            $this->genericHeading($block);
                        }
                    }
                    $this->renumber($page);
                    echo "    > page /$slug (from Birthday)\n";
                }
                $urlToEntry[$url] = $page->id;
            }
        }

        // 4. Download
        $download = $this->page('download');
        if (!$download) {
            $download = new Entry(['sectionId' => $pages->id, 'typeId' => $pageType->id, 'title' => 'Download', 'slug' => 'download']);
            $download->setFieldValues(['heroLayout' => 'simple', 'scheme' => 'light', 'heading' => 'Get the Quick Craft app', 'seo' => $this->seo('Download', '')]);
            $this->save($download, 'download');
            $this->block($download, 'ctaBanner', 1, [
                'ctaLayout' => 'simple', 'scheme' => 'light', 'spacing' => 'noTop', 'glow' => false,
                'text' => 'Create invites, send them by text and see who’s coming. Free on iPhone and Android.',
                'showAppBadges' => true,
            ]);
            echo "    > page /download\n";
        }
        $urlToEntry['/download'] = $download->id;
        if ($maker = $this->page('invitation-maker')) {
            $urlToEntry['/create'] = $maker->id;
        }
        if ($contact = $this->page('contact')) {
            $urlToEntry['/help'] = $contact->id;
        }

        // 5. Menus
        foreach ($urlToEntry as $url => $id) {
            $this->update('{{%simplerpmenu_items}}', ['entry_id' => $id, 'custom_url' => null], ['custom_url' => $url], [], false);
        }
        $this->delete('{{%simplerpmenu_items}}', ['like', 'custom_url', '/explore%', false]);
        // then the groups left without a parent (Explore › Explore / Cities)
        do {
            $orphans = (new Query())->select('i.id')->from('{{%simplerpmenu_items}} i')
                ->leftJoin('{{%simplerpmenu_items}} p', '[[p.id]] = [[i.parent_id]]')
                ->where(['not', ['i.parent_id' => 0]])->andWhere(['p.id' => null])->column();
            if ($orphans) {
                $this->delete('{{%simplerpmenu_items}}', ['id' => $orphans]);
            }
        } while ($orphans);
        echo "    > menu: Explore removed\n";

        return true;
    }

    // Helpers

    private function page(string $slug): ?Entry
    {
        return Entry::find()->section('pages')->slug($slug)->status(null)->one();
    }

    /** @return Entry[] */
    private function blocks(Entry $owner): array
    {
        return Entry::find()->ownerId($owner->id)->field('pageBuilder')->status(null)->orderBy('sortOrder')->all();
    }

    private function genericHeading(Entry $block): void
    {
        if ($block->type->handle === 'cardGrid' && str_contains((string)$block->getFieldValue('heading'), 'birthday')) {
            $block->setFieldValue('heading', 'Get your party started');
            $this->save($block, 'cardGrid heading');
        }
    }

    private function renumber(Entry $owner): void
    {
        $sort = 1;
        foreach ($this->blocks($owner) as $block) {
            $this->update('{{%elements_owners}}', ['sortOrder' => $sort++], ['elementId' => $block->id, 'ownerId' => $owner->id], [], false);
        }
    }

    private function seo(string $title, string $description): array
    {
        $social = ['handle' => null, 'title' => $title . ' - Quick Craft', 'imageId' => null, 'description' => $description];
        return [
            'titleRaw' => [], 'descriptionRaw' => $description, 'keywords' => [], 'score' => 'neutral',
            'social' => ['twitter' => $social, 'facebook' => $social],
            'advanced' => ['robots' => [], 'canonical' => null],
        ];
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
