<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;

/**
 * Text + media block (Figma Careers 6-6646 / 6-6862; CLAUDE.md › pageBuilder blocks › textMedia):
 *  - textMedia gets `headingAbove` and `glow` (lightswitchOff instances) and `spacing`.
 *  - Test content on Careers: "Our mission…" (media right) and "Perks & Benefits" (media left,
 *    heading above, glow). Images: the Figma placeholders careers-mission-collage.png /
 *    careers-team-photo.png in the images volume, when present.
 */
class m261120_310000_text_media extends Migration
{
    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();
        $switch = $fields->getFieldByHandle('lightswitchOff');

        $type = $entries->getEntryTypeByHandle('textMedia');
        $layout = $type->getFieldLayout();
        $tab = $layout->getTabs()[0];
        $elements = $tab->getElements();
        $changed = false;
        $add = [
            'headingAbove' => [$switch, 'Heading above both columns', 'Centre the heading above the image and text (Figma “Perks & Benefits”).'],
            'glow' => [$switch, 'Soft glow background', null],
            'spacing' => [$fields->getFieldByHandle('spacing'), null, null],
        ];
        foreach ($add as $handle => [$field, $label, $instructions]) {
            if ($layout->getFieldByHandle($handle)) {
                continue;
            }
            $config = ['uid' => $this->stableUid("el:textMedia:$handle")];
            if ($field->handle !== $handle) {
                $config += ['handle' => $handle, 'label' => $label, 'instructions' => $instructions];
            }
            $elements[] = new CustomField($field, $config);
            $changed = true;
        }
        foreach ($elements as $el) {
            if ($el instanceof CustomField && ($el->handle ?? $el->getField()->handle) === 'body') {
                $el->label = 'Text';
            }
        }
        $tab->setElements($elements);
        $type->setFieldLayout($layout);
        $this->check($entries->saveEntryType($type), $type, 'textMedia');
        echo $changed ? "    > textMedia: + headingAbove, glow, spacing\n" : '';

        // Test content on Careers
        $page = Entry::find()->section('pages')->slug('careers')->status(null)->one();
        $pageBuilder = $fields->getFieldByHandle('pageBuilder');
        if ($page && !Entry::find()->ownerId($page->id)->field($pageBuilder)->type('textMedia')->status(null)->exists()) {
            $sort = (int)Entry::find()->ownerId($page->id)->field($pageBuilder)->status(null)->count();
            $asset = fn(string $file) => Asset::find()->filename($file)->ids();
            $blocks = [
                [
                    'mediaPosition' => 'right',
                    'heading' => 'Our mission is to power meaningful connections in the real world.',
                    'body' => '<p>We believe the best friendships are built in person. We’re here to make it easier to do that.</p><p>Whether you’re hosting, discovering events, or RSVPing “Going”, Quick Craft makes it happen.</p>',
                    'images' => $asset('careers-mission-collage.png'),
                ],
                [
                    'mediaPosition' => 'left', 'headingAbove' => true, 'glow' => true,
                    'heading' => 'Perks & Benefits',
                    'body' => '<p>🩺 Health, dental, and vision coverage</p><p>🏋️‍♀️ Fitness and wellness membership</p><p>⛱️ Unlimited PTO (minimum 3 weeks required)!</p><p>🚴 Commuter benefits</p><p>👩‍⚕️ Telehealth &amp; mental health services</p><p>💸 401k matching</p><p>🎉 Monthly party stipend</p>',
                    'images' => $asset('careers-team-photo.png'),
                ],
            ];
            foreach ($blocks as $values) {
                $block = new Entry([
                    'fieldId' => $pageBuilder->id, 'ownerId' => $page->id, 'primaryOwnerId' => $page->id,
                    'typeId' => $type->id, 'siteId' => $page->siteId, 'sortOrder' => ++$sort,
                ]);
                $block->setFieldValues($values);
                $this->check(Craft::$app->getElements()->saveElement($block), $block, 'textMedia block');
            }
            echo "    > Careers: + 2 text + media blocks\n";
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
