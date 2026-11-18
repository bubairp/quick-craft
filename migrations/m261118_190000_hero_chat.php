<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Table;

/**
 * Animated chat in the hero media (reference: partiful.com hero; Figma 6-2238).
 *  - new Table field `chatMessages` (sender, message, side, kind) on the `hero` entry type
 *  - Home hero test content: photo + invite art in `images`, avatars matched by asset title
 */
class m261118_190000_hero_chat extends Migration
{
    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();

        // 1. Field
        $field = $fields->getFieldByHandle('chatMessages');
        if (!$field) {
            $field = new Table([
                'name' => 'Chat messages',
                'handle' => 'chatMessages',
                'instructions' => 'Optional animated chat over the hero photo. Images: 1st = background photo, 2nd = invite card art. Avatars: upload an image whose title equals the sender name.',
                'addRowLabel' => 'Add message',
                'defaults' => [],
                'columns' => [
                    'col1' => ['heading' => 'Sender', 'handle' => 'sender', 'type' => 'singleline'],
                    'col2' => ['heading' => 'Message', 'handle' => 'message', 'type' => 'singleline'],
                    'col3' => ['heading' => 'Side', 'handle' => 'side', 'type' => 'select', 'options' => [
                        ['label' => 'Left (friend)', 'value' => 'left', 'default' => true],
                        ['label' => 'Right (host)', 'value' => 'right', 'default' => false],
                    ]],
                    'col4' => ['heading' => 'Kind', 'handle' => 'kind', 'type' => 'select', 'options' => [
                        ['label' => 'Message', 'value' => 'message', 'default' => true],
                        ['label' => 'Invite card', 'value' => 'invite', 'default' => false],
                        ['label' => 'Typing…', 'value' => 'typing', 'default' => false],
                    ]],
                ],
            ]);
            if (!$fields->saveField($field)) {
                throw new \RuntimeException('chatMessages: ' . json_encode($field->getErrors()));
            }
        }

        // 2. Add to hero layout after `images`
        $type = $entries->getEntryTypeByHandle('hero');
        $layout = $type->getFieldLayout();
        if (!$layout->getFieldByHandle('chatMessages')) {
            $tab = $layout->getTabs()[0];
            $elements = [];
            foreach ($tab->getElements() as $el) {
                $elements[] = $el;
                if ($el instanceof CustomField && $el->getField()->handle === 'images') {
                    $elements[] = new CustomField($field);
                }
            }
            $tab->setElements($elements);
            $type->setFieldLayout($layout);
            if (!$entries->saveEntryType($type)) {
                throw new \RuntimeException('hero: ' . json_encode($type->getErrors()));
            }
        }

        // 3. Home test content (only if the placeholder images exist locally)
        $find = fn(string $f) => Asset::find()->volume('images')->folderPath('placeholders/')->filename($f)->one();
        $photo = $find('hero-chat-photo.png');
        $invite = $find('hero-chat-invite.png');
        $avatars = ['Liv' => $find('avatar-liv.png'), 'Zoe' => $find('avatar-zoe.png'), 'Maya' => $find('avatar-maya.png')];
        if (!$photo || !$invite || in_array(null, $avatars, true)) {
            echo "    > Hero chat placeholder images not found — field added, content skipped.\n";
            return true;
        }

        foreach ($avatars as $name => $asset) {
            $asset->title = $name;
            $asset->alt = null; // decorative next to the sender's name
            Craft::$app->getElements()->saveElement($asset);
        }
        $photo->alt = 'Friends laughing together at a party';
        Craft::$app->getElements()->saveElement($photo);
        $invite->alt = 'Party invite artwork';
        Craft::$app->getElements()->saveElement($invite);

        $home = Entry::find()->section('home')->one();
        $hero = $home?->getFieldValue('pageBuilder')->type('hero')->one();
        if ($hero) {
            $hero->setFieldValues([
                'images' => array_merge([$photo->id, $invite->id], array_map(fn($a) => $a->id, array_values($avatars))),
                'chatMessages' => [
                    ['sender' => 'Zoe', 'message' => 'what’s happening friday?', 'side' => 'right', 'kind' => 'message'],
                    ['sender' => 'Liv', 'message' => 'nothing yet lol', 'side' => 'left', 'kind' => 'message'],
                    ['sender' => 'Zoe', 'message' => 'party @ mine?', 'side' => 'right', 'kind' => 'message'],
                    ['sender' => 'Zoe', 'message' => 'RSVP to Party!', 'side' => 'right', 'kind' => 'invite'],
                    ['sender' => 'Maya', 'message' => 'wait... perfect', 'side' => 'left', 'kind' => 'message'],
                    ['sender' => 'Liv', 'message' => '', 'side' => 'left', 'kind' => 'typing'],
                ],
            ]);
            if (!Craft::$app->getElements()->saveElement($hero)) {
                throw new \RuntimeException('Home hero: ' . json_encode($hero->getErrors()));
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
