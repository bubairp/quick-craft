<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\ElementInterface;
use craft\db\Migration;
use craft\db\Query;
use craft\elements\Entry;
use craft\fields\Dropdown;

/**
 * Organizers page as partiful.com/org-profiles (the user's request, 2026-11-20).
 * CLAUDE.md › Organizers page (rotating words) + testimonials › logoRow.
 *  1. testimonialLayout + "logoRow" (Logo row: press logos, no quotes).
 *  2. Organizers: a logo row block right under the banner (NYT, WSJ, TIME, The Atlantic, USA Today:
 *     the logos already used by Home's press strip). PLACEHOLDER logos from the imported design.
 */
class m261120_490000_organizers_reference extends Migration
{
    private const OUTLETS = ['The New York Times', 'The Wall Street Journal', 'TIME', 'The Atlantic', 'USA Today'];

    public function safeUp(): bool
    {
        // 1. layout option
        $field = Craft::$app->getFields()->getFieldByHandle('testimonialLayout');
        if ($field instanceof Dropdown && !in_array('logoRow', array_column($field->options, 'value'), true)) {
            $options = $field->options;
            $options[] = ['label' => 'Logo row (press logos, no quotes)', 'value' => 'logoRow', 'default' => false];
            $field->options = $options;
            $this->check(Craft::$app->getFields()->saveField($field), $field, 'testimonialLayout');
        }

        // 2. Organizers: logo row first
        $page = Entry::find()->section('pages')->slug('organizers')->status(null)->one();
        if (!$page) {
            return true;
        }
        $pageBuilder = Craft::$app->getFields()->getFieldByHandle('pageBuilder');
        $exists = Entry::find()->ownerId($page->id)->field('pageBuilder')->type('testimonials')->testimonialLayout('logoRow')->status(null)->exists();
        if ($exists) {
            return true;
        }

        // shift the page's blocks down one place, then save the new block at the top
        $this->update('{{%elements_owners}}', ['sortOrder' => new \yii\db\Expression('[[sortOrder]] + 1')], [
            'ownerId' => $page->id,
            'elementId' => (new Query())->select('id')->from('{{%entries}}')->where(['fieldId' => $pageBuilder->id]),
        ], [], false);

        $row = new Entry([
            'fieldId' => $pageBuilder->id, 'ownerId' => $page->id, 'primaryOwnerId' => $page->id, 'siteId' => $page->siteId,
            'typeId' => Craft::$app->getEntries()->getEntryTypeByHandle('testimonials')->id, 'sortOrder' => 1,
        ]);
        $row->setFieldValues(['testimonialLayout' => 'logoRow', 'spacing' => 'default']);
        $this->save($row, 'logo row');

        foreach (self::OUTLETS as $i => $outlet) {
            $source = Entry::find()->type('testimonial')->personName($outlet)->status(null)->one();
            $logoId = $source ? ($source->image->ids()[0] ?? null) : null;
            $item = new Entry([
                'fieldId' => Craft::$app->getFields()->getFieldByHandle('testimonials')->id,
                'ownerId' => $row->id, 'primaryOwnerId' => $row->id, 'siteId' => $row->siteId,
                'typeId' => Craft::$app->getEntries()->getEntryTypeByHandle('testimonial')->id, 'sortOrder' => $i + 1,
            ]);
            $item->setFieldValues(['personName' => $outlet, 'image' => $logoId ? [$logoId] : []]);
            $this->save($item, $outlet);
        }
        echo "    > organizers: logo row (" . count(self::OUTLETS) . " logos) under the banner\n";

        return true;
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

    public function safeDown(): bool
    {
        return false;
    }
}
