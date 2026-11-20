<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;

/**
 * Press list block (Figma Press 18-6161; CLAUDE.md › pageBuilder blocks › pressList):
 *  - pressList gets `spacing` (its padding sits on the <section>).
 *  - Test data: the 20 press mentions from the Figma frame (only when the channel is empty) and a
 *    pressList block on the Press page.
 * PLACEHOLDER: the headlines/publications come from the imported reference design and the links
 * are example.com. Replace them with Quick Craft's real coverage before launch.
 */
class m261120_340000_press_list extends Migration
{
    /** [headline, publication, date] — newest first, as in the design */
    private const MENTIONS = [
        ['Quick Craft Is Putting Ticket Payments on Its Platform', 'WIRED', '2026-06-02'],
        ['Quick Craft doesn’t want to be ‘unhinged’—it wants to be ubiquitous', 'Marketing Brew', '2026-03-09'],
        ["Gen Z's Favorite Party Platform Wants to Set You Up", 'The New York Times', '2026-02-14'],
        ['Buckle up: Quick Craft is the new Tinder', 'Business Insider', '2026-01-15'],
        ["How Quick Craft Became the Life of Gen-Z's IRL Party", 'Inc. Magazine', '2025-11-21'],
        ["Quick Craft Is One of 2025's TIME100 Most Influential Companies", 'TIME', '2025-06-25'],
        ["Meet Quick Craft, the Gen Z party-planning staple that's taking on Apple", 'CNBC', '2025-04-19'],
        ['The most innovative companies in social media for 2025', 'Fast Company', '2025-03-03'],
        ['The rise of IRL social apps: How startups are trying to get people to hang out in person and taking aim at loneliness', 'Business Insider', '2024-12-31'],
        ["If You Don't Send a Quick Craft Invite, Gen Z's Not Going", 'Bustle', '2024-12-10'],
        ["Quick Craft is Google's 'best app' of 2024", 'TechCrunch', '2024-11-18'],
        ["I Could Text You the Details, But I'd Rather Send a Quick Craft Invite", 'NYT Wirecutter', '2024-10-18'],
        ["It's my party, and I'll take it seriously if I want to: How Quick Craft revived the evite", 'USA TODAY', '2024-08-04'],
        ["Your Gen Z New Year's Eve party may have been planned on Quick Craft", 'The Washington Post', '2023-12-31'],
        ['The No-Email Way to Plan a Party', 'The Wall Street Journal', '2023-05-05'],
        ['How event planning platform Quick Craft is taking on Facebook Events', 'Ad Age', '2023-05-01'],
        ['How Quick Craft has become the hot invitation app for startup founders', 'ModernRetail', '2023-03-16'],
        ['Mashable: Top 10 Trends That Changed the Internet in 2022', 'Mashable', '2022-12-16'],
        ['The Internet, but for Hot People', 'The Atlantic', '2022-12-02'],
        ['Is This the Least Cringe Option to Get People to Your Party?', 'The New York Times', '2022-09-15'],
    ];

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();
        $elements = Craft::$app->getElements();

        // 1. Layout: + spacing
        $type = $entries->getEntryTypeByHandle('pressList');
        $layout = $type->getFieldLayout();
        if (!$layout->getFieldByHandle('spacing')) {
            $tab = $layout->getTabs()[0];
            $els = $tab->getElements();
            $els[] = new CustomField($fields->getFieldByHandle('spacing'), ['uid' => $this->stableUid('el:pressList:spacing')]);
            $tab->setElements($els);
            $type->setFieldLayout($layout);
            $this->check($entries->saveEntryType($type), $type, 'pressList');
        }

        // 2. Press mentions (only when the channel is empty)
        $section = $entries->getSectionByHandle('pressMentions');
        if ($section && !Entry::find()->section('pressMentions')->status(null)->exists()) {
            $mentionType = $section->getEntryTypes()[0];
            foreach (self::MENTIONS as [$headline, $publication, $date]) {
                $mention = new Entry([
                    'sectionId' => $section->id, 'typeId' => $mentionType->id,
                    'title' => $headline, 'postDate' => new \DateTime("$date 09:00"),
                ]);
                $mention->setFieldValues([
                    'publication' => $publication,
                    'externalLink' => ['type' => 'url', 'value' => 'https://example.com/press', 'target' => '_blank'], // PLACEHOLDER
                ]);
                $this->check($elements->saveElement($mention), $mention, "press mention $headline");
            }
            echo '    > ' . count(self::MENTIONS) . " press mentions\n";
        }

        // 3. pressList block on Press
        $page = Entry::find()->section('pages')->slug('press')->status(null)->one();
        $pageBuilder = $fields->getFieldByHandle('pageBuilder');
        if ($page && !Entry::find()->ownerId($page->id)->field($pageBuilder)->type('pressList')->status(null)->exists()) {
            $sort = (int)Entry::find()->ownerId($page->id)->field($pageBuilder)->status(null)->count();
            $block = new Entry([
                'fieldId' => $pageBuilder->id, 'ownerId' => $page->id, 'primaryOwnerId' => $page->id,
                'typeId' => $type->id, 'siteId' => $page->siteId, 'sortOrder' => $sort + 1,
            ]);
            $this->check($elements->saveElement($block), $block, 'pressList block');
            echo "    > Press: + press list\n";
        }

        return true;
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
