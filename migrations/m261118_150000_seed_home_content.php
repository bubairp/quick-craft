<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;

/**
 * Test content for the Home page builder, copy taken from Figma 6-1252.
 * Images are left empty until placeholder images are exported.
 * Skipped entirely if Home already has pageBuilder blocks.
 */
class m261118_150000_seed_home_content extends Migration
{
    private int $siteId;

    public function safeUp(): bool
    {
        $this->siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $home = Entry::find()->section('home')->one();
        if (!$home || $home->pageBuilder->count() > 0) {
            return true;
        }

        $page = fn(string $slug) => Entry::find()->section('landingPages')->slug($slug)->one();
        $maker = $page('invitation-maker');
        $organizers = $page('organizers');
        $tickets = $page('sell-tickets');

        $entryLink = fn(?Entry $e, string $label) => $e
            ? ['type' => 'entry', 'value' => "{entry:{$e->id}@{$this->siteId}:url}", 'label' => $label]
            : null;
        $urlLink = fn(string $url, string $label) => ['type' => 'url', 'value' => $url, 'label' => $label];

        $order = 0;
        $add = function(string $type, array $fields, array $children = []) use ($home, &$order) {
            $block = $this->nested($home, 'pageBuilder', $type, $fields, ++$order);
            foreach ($children as $fieldHandle => $items) {
                foreach ($items as $i => [$childType, $childFields]) {
                    $this->nested($block, $fieldHandle, $childType, $childFields, $i + 1);
                }
            }
        };

        // 6-2210
        $add('hero', [
            'heroLayout' => 'split',
            'theme' => 'dark',
            'heading' => 'Parties are back',
            'text' => 'Create invites & discover events',
            'primaryButton' => $entryLink($maker, 'Create event'),
            'secondaryButton' => $urlLink('/download', 'Get the app'),
        ]);

        // 6-1320
        $add('cardGrid', [
            'cardLayout' => 'grid',
            'theme' => 'light',
            'heading' => 'One link. Everyone’s in.',
            'text' => 'Everything your guests need, all in one place.',
            'primaryButton' => $entryLink($maker, 'Create for free'),
        ], ['cards' => [
            ['card', ['heading' => 'Create your invite', 'text' => 'Add the details and make it yours']],
            ['card', ['heading' => 'Guests RSVP', 'text' => 'Send them a link. No app needed']],
            ['card', ['heading' => 'Stay on the same page', 'text' => 'One place for details & updates']],
        ]]);

        // 6-1384
        $add('imageGallery', [
            'galleryLayout' => 'fan',
            'heading' => 'A million reasons to hang',
            'text' => 'For any occasion, big or small',
        ]);

        // 6-4774
        $cityCards = [];
        foreach (['New York City' => 'Explore NYC', 'Los Angeles' => 'Explore LA', 'San Francisco' => 'Explore SF', 'Chicago' => 'Explore Chicago', 'Everywhere' => 'Explore all'] as $city => $label) {
            $cityCards[] = ['card', ['heading' => $city, 'primaryButton' => $urlLink('/explore', $label)]];
        }
        $add('cityList', [
            'heading' => 'The best events in your city',
            'text' => 'See what’s happening nearby & where your friends are going',
        ], ['cards' => $cityCards]);

        // 6-1499
        $add('featureRows', [
            'featureLayout' => 'stickyPhone',
            'theme' => 'dark',
        ], ['cards' => [
            ['card', ['heading' => 'Text blast your guests', 'text' => 'No more chaotic group chats. Send one message and reach all your guests individually.', 'primaryButton' => $entryLink($maker, 'Create event')]],
            ['card', ['heading' => 'Collect info upfront', 'text' => 'Ask for dietary restrictions, song requests, social handles, and more.', 'primaryButton' => $entryLink($maker, 'Try it out')]],
            ['card', ['heading' => 'Build hype early', 'text' => 'Let guests see who’s coming, leave comments, and share photos, all on your event page.', 'primaryButton' => $entryLink($maker, 'Try it out')]],
        ]]);

        // 6-1719
        $add('cardGrid', [
            'cardLayout' => 'grid',
            'theme' => 'light',
            'heading' => 'More than an invite',
            'text' => 'Everything you need to build community',
            'primaryButton' => $entryLink($organizers, 'For organizers'),
        ], ['cards' => [
            ['card', ['heading' => 'Sell tickets.', 'primaryButton' => $entryLink($tickets, 'Learn more')]],
            ['card', ['heading' => 'Grow your following.', 'primaryButton' => $entryLink($organizers, 'Learn more')]],
            ['card', ['heading' => 'Invite with a link. No app needed']],
            ['card', ['heading' => 'Automated reminders']],
        ]]);

        // 6-2347
        $add('testimonials', [
            'testimonialLayout' => 'carousel',
            'heading' => 'Social life runs on quick craft',
            'text' => 'Loved by people who party',
        ], ['testimonials' => [
            ['stat', ['heading' => '200k+', 'text' => 'ratings on the app store']],
            ['testimonial', ['text' => 'It’s exclusively how we plan hangouts, events, and get-togethers']],
            ['stat', ['heading' => 'Rated 5.0 stars', 'text' => 'On the app store']],
            ['testimonial', ['heading' => 'App Store Review', 'text' => 'Everything just works with no friction, and there’s a crazy amount of options for customizing your event.', 'personName' => 'Quiviler', 'personDetail' => 'Sept 3', 'rating' => 5]],
            ['testimonial', ['heading' => 'App Store Review', 'text' => 'I don’t even hang out with my friends if they don’t send me the quick craft first', 'personName' => 'r3boi', 'personDetail' => 'May 29', 'rating' => 5]],
            ['testimonial', ['heading' => 'App Store Review', 'text' => 'The best events come to me via this app', 'personName' => 'abundantcashflow', 'personDetail' => 'Aug 2', 'rating' => 5]],
        ]]);

        // 6-1787
        $add('ctaBanner', [
            'theme' => 'light',
            'heading' => 'Get the app',
            'text' => 'Your social life will thank you',
            'showAppBadges' => true,
        ]);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261118_150000_seed_home_content cannot be reverted automatically.\n";
        return false;
    }

    private function nested(Entry $owner, string $fieldHandle, string $typeHandle, array $fields, int $sortOrder): Entry
    {
        $field = Craft::$app->getFields()->getFieldByHandle($fieldHandle);
        $type = Craft::$app->getEntries()->getEntryTypeByHandle($typeHandle);
        $entry = new Entry([
            'fieldId' => $field->id,
            'ownerId' => $owner->id,
            'primaryOwnerId' => $owner->id,
            'typeId' => $type->id,
            'siteId' => $this->siteId,
            'sortOrder' => $sortOrder,
        ]);
        $entry->setFieldValues(array_filter($fields, fn($v) => $v !== null));
        if (!Craft::$app->getElements()->saveElement($entry)) {
            throw new \RuntimeException("$typeHandle in $fieldHandle: " . json_encode($entry->getErrors()));
        }
        return $entry;
    }
}
