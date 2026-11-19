<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;

/**
 * FAQ block (Figma 17-2418; CLAUDE.md › pageBuilder blocks › faq):
 *  - faq gets the `spacing` option.
 *  - Test content on Invitation Maker: the six Figma questions. The answers are placeholders
 *    (the frame shows the questions closed); editors replace them.
 */
class m261120_300000_faq extends Migration
{
    private const ITEMS = [
        'Is Quick Craft really free?' => 'Yes. Creating and sending invitations is free, with no ads and no hidden fees.',
        'Can I send invitations by text message?' => 'Yes. Send your invite by text, email or a link — whatever your guests use most.',
        'Do my guests need to download an app?' => 'No. Guests open the invite and RSVP in any browser; the app is optional.',
        'How does Quick Craft compare to Canva or Zazzle?' => 'Design tools give you a picture. Quick Craft gives you the whole event page: RSVPs, reminders, updates and a shared photo album.',
        'What kinds of events can I make invitations for?' => 'Anything: birthdays, dinners, house parties, weddings, game nights, community events and more.',
        'Is there a guest limit on free Quick Craft invitations?' => 'Invite as many guests as you like.',
    ];

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();

        $type = $entries->getEntryTypeByHandle('faq');
        $layout = $type->getFieldLayout();
        if (!$layout->getFieldByHandle('spacing')) {
            $tab = $layout->getTabs()[0];
            $tab->setElements([...$tab->getElements(), new CustomField($fields->getFieldByHandle('spacing'), ['uid' => $this->stableUid('el:faq:spacing')])]);
            $type->setFieldLayout($layout);
            $this->check($entries->saveEntryType($type), $type, 'faq');
            echo "    > faq: + spacing\n";
        }

        $page = Entry::find()->section('pages')->slug('invitation-maker')->status(null)->one();
        $pageBuilder = $fields->getFieldByHandle('pageBuilder');
        if ($page && !Entry::find()->ownerId($page->id)->field($pageBuilder)->type('faq')->status(null)->exists()) {
            $elements = Craft::$app->getElements();
            $sort = (int)Entry::find()->ownerId($page->id)->field($pageBuilder)->status(null)->count() + 1;
            $block = new Entry([
                'fieldId' => $pageBuilder->id, 'ownerId' => $page->id, 'primaryOwnerId' => $page->id,
                'typeId' => $type->id, 'siteId' => $page->siteId, 'sortOrder' => $sort,
            ]);
            $block->setFieldValue('heading', 'Frequently Asked Questions');
            $this->check($elements->saveElement($block), $block, 'faq block');

            $faqItems = $fields->getFieldByHandle('faqItems');
            $itemType = $entries->getEntryTypeByHandle('faqItem');
            $i = 1;
            foreach (self::ITEMS as $question => $answer) {
                $item = new Entry([
                    'fieldId' => $faqItems->id, 'ownerId' => $block->id, 'primaryOwnerId' => $block->id,
                    'typeId' => $itemType->id, 'siteId' => $page->siteId, 'sortOrder' => $i++,
                    'title' => $question,
                ]);
                $item->setFieldValue('body', "<p>$answer</p>");
                $this->check($elements->saveElement($item), $item, 'faq item');
            }
            echo "    > Invitation Maker: + FAQ (6 questions)\n";
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
