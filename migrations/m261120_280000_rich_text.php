<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;

/**
 * Rich text block (Figma 17-1343; CLAUDE.md › pageBuilder blocks › richText):
 *  - richText gets the `spacing` option (its padding sits on the <section>).
 *  - Test content: the SEO text on Birthday Invitations, after the promo bar.
 */
class m261120_280000_rich_text extends Migration
{
    private const BODY = <<<'HTML'
<h2>What makes a great birthday party invitation?</h2>
<p>The gathering is the easy part — getting everyone there is where Quick Craft comes in. Create a free birthday party invitation in minutes, send it straight to your guests’ phones by text, email, or link, and track RSVPs in real time. No ads, no fees, and most importantly, no group chat chaos. Just a beautiful free invitation and the people you love.</p>
<h2>Birthday party ideas</h2>
<p>Whether you’re turning 7 or 70, a birthday is always worth celebrating, and the best parties don’t need a big budget or an elaborate theme. They just need the right people and a great digital birthday invitation to get everyone in the door. Add a questionnaire to your invite so guests can coordinate on food, drinks, or a group gift ahead of time. You can update the details, send reminders, and track RSVPs all in one place, so you spend less time organizing and more time celebrating.</p>
<h2>How to send free birthday invitations online</h2>
<p>Going from idea to invite takes less than a minute. Easily create an event on <a href="/">quickcraft.com</a>, no app needed. Pick a theme, then customize it with colors, fonts, and fun animations to match the vibe of your party. Add your date, location, start time, and anything else guests need to know. Then send it by text, link, or email. Guests can RSVP from any browser, no app download required.</p>
HTML;

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();

        $type = $entries->getEntryTypeByHandle('richText');
        $layout = $type->getFieldLayout();
        if (!$layout->getFieldByHandle('spacing')) {
            $tab = $layout->getTabs()[0];
            $tab->setElements([...$tab->getElements(), new CustomField($fields->getFieldByHandle('spacing'), ['uid' => $this->stableUid('el:richText:spacing')])]);
            $type->setFieldLayout($layout);
            $this->check($entries->saveEntryType($type), $type, 'richText');
            echo "    > richText: + spacing\n";
        }

        $page = Entry::find()->section('pages')->slug('birthday-invitations')->status(null)->one();
        $pageBuilder = $fields->getFieldByHandle('pageBuilder');
        if ($page && !Entry::find()->ownerId($page->id)->field($pageBuilder)->type('richText')->status(null)->exists()) {
            $sort = (int)Entry::find()->ownerId($page->id)->field($pageBuilder)->status(null)->count() + 1;
            $block = new Entry([
                'fieldId' => $pageBuilder->id, 'ownerId' => $page->id, 'primaryOwnerId' => $page->id,
                'typeId' => $type->id, 'siteId' => $page->siteId, 'sortOrder' => $sort,
            ]);
            $block->setFieldValue('body', self::BODY);
            $this->check(Craft::$app->getElements()->saveElement($block), $block, 'rich text block');
            echo "    > Birthday Invitations: + rich text\n";
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
