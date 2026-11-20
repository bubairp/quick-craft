<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\ElementInterface;
use craft\db\Migration;
use craft\db\Query;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\enums\Color;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Dropdown;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

/**
 * Contact (Figma 76:2) + About (Figma 76:299) pages; CLAUDE.md › pageBuilder blocks › Contact page + About page.
 *  - contactDetail + `label`; socialLink + `handle`; textMedia + `cropImage`; ctaBanner + `imageAbove`.
 *  - heroLayout + "imageOnly"; testimonialLayout + "pressRow".
 *  - New block `contactInfo` ("Contact details", group Text, address-book, blue).
 *  - Wheel Form "Contact Form": name / email / message (textarea), labelled, in that order, honeypot.
 *  - Site settings: 3 contact details (labelled) + social handles, when empty.
 *  - Pages `contact` and `about` (when missing), and the footer menu items About / Contact → those entries.
 * PLACEHOLDER: emails, handles, copy and photos (from the imported reference design).
 */
class m261120_460000_contact_about extends Migration
{
    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();

        // 1. Content model
        $this->addInstance('contactDetail', 'plainText', 'label', 'Label', 'What it’s for, e.g. “Partnerships”. Shown above the detail on the contact page.', true);
        $this->addInstance('socialLink', 'plainText', 'handle', 'Shown text', 'e.g. “@quickcraft”. Shown on the contact page; falls back to the link.');
        $this->addInstance('textMedia', 'lightswitchOn', 'cropImage', 'Crop image to fit', 'Off: the image keeps its own shape (e.g. transparent polaroid collages).');
        $this->addInstance('ctaBanner', 'lightswitchOff', 'imageAbove', 'Small image above the heading', 'Simple layout: the first image as a small sticker above the heading, instead of a big image below.');
        $this->addOption('heroLayout', 'imageOnly', 'Image only (inset photo)');
        $this->addOption('testimonialLayout', 'pressRow', 'Press quotes (scrolling row)');

        $type = $entries->getEntryTypeByHandle('contactInfo');
        if (!$type) {
            $layout = new FieldLayout(['type' => Entry::class, 'uid' => $this->stableUid('layout:contactInfo')]);
            $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout, 'uid' => $this->stableUid('tab:contactInfo:Content')]);
            $plain = $fields->getFieldByHandle('plainText');
            $tab->setElements([
                new CustomField($plain, ['handle' => 'heading', 'label' => 'Heading', 'uid' => $this->stableUid('el:contactInfo:heading')]),
                new CustomField($plain, ['handle' => 'text', 'label' => 'Text', 'uid' => $this->stableUid('el:contactInfo:text')]),
                new CustomField($plain, ['handle' => 'contactHeading', 'label' => 'Contact list heading', 'instructions' => 'Above the contact details from Site settings, e.g. “Have a question?”.', 'uid' => $this->stableUid('el:contactInfo:contactHeading')]),
                new CustomField($plain, ['handle' => 'socialHeading', 'label' => 'Social list heading', 'instructions' => 'Above the social links from Site settings, e.g. “Find us on”.', 'uid' => $this->stableUid('el:contactInfo:socialHeading')]),
                new CustomField($plain, ['handle' => 'formHeading', 'label' => 'Form heading', 'uid' => $this->stableUid('el:contactInfo:formHeading')]),
                new CustomField($fields->getFieldByHandle('newsletterForm'), ['handle' => 'contactForm', 'label' => 'Contact form', 'instructions' => 'A Wheel Form. Leave empty for no form.', 'uid' => $this->stableUid('el:contactInfo:contactForm')]),
                new CustomField($fields->getFieldByHandle('spacing'), ['uid' => $this->stableUid('el:contactInfo:spacing')]),
            ]);
            $layout->setTabs([$tab]);
            $type = new EntryType([
                'name' => 'Contact details', 'handle' => 'contactInfo',
                'hasTitleField' => false, 'showSlugField' => false, 'uiLabelFormat' => '{heading}',
                'icon' => 'address-book', 'color' => Color::Blue,
                'uid' => $this->stableUid('entryType:contactInfo'),
            ]);
            $type->setFieldLayout($layout);
            $this->check($entries->saveEntryType($type), $type, 'contactInfo');
        }
        $pageBuilder = $fields->getFieldByHandle('pageBuilder');
        $types = $pageBuilder->getEntryTypes();
        if (!in_array('contactInfo', array_map(fn($t) => $t->handle, $types), true)) {
            $config = array_map(fn($t) => ['uid' => $t->uid, 'group' => $t->group], $types);
            $config[] = ['uid' => $type->uid, 'group' => 'Text'];
            $pageBuilder->setEntryTypes($config);
            $this->check($fields->saveField($pageBuilder), $pageBuilder, 'pageBuilder');
            echo "    > pageBuilder: + contactInfo (group Text)\n";
        }

        // 2. Wheel Form "Contact Form": order, labels, textarea, honeypot
        $formId = (new Query())->from('{{%wheelform_forms}}')->where(['name' => 'Contact Form'])->scalar();
        if ($formId) {
            foreach (['name' => [1, 'text', 'Name'], 'email' => [2, 'email', 'Email'], 'message' => [3, 'textarea', 'Message']] as $name => [$order, $fieldType, $label]) {
                $this->update('{{%wheelform_form_fields}}', [
                    'order' => $order, 'type' => $fieldType, 'required' => 1,
                    'options' => json_encode(['label' => $label]),
                ], ['form_id' => $formId, 'name' => $name], [], false);
            }
            $options = json_decode((string)(new Query())->from('{{%wheelform_forms}}')->select('options')->where(['id' => $formId])->scalar(), true) ?: [];
            $options += ['honeypot' => 'website', 'honeypot_value' => ''];
            $this->update('{{%wheelform_forms}}', ['options' => json_encode($options)], ['id' => $formId], [], false);
            echo "    > Wheel Form Contact Form: name, email, message (textarea), honeypot\n";
        }

        // 3. Site settings: contact details + social handles
        $settings = Entry::find()->section('siteSettings')->one();
        if ($settings) {
            if (!$settings->contactDetails->exists()) {
                foreach ([
                    ['Partnerships', 'partnerships@quickcraft.com'],
                    ['General inquiries', 'hello@quickcraft.com'],
                    ['For product help', 'support@quickcraft.com'],
                ] as $i => [$label, $email]) {
                    $this->nested($settings, 'contactDetails', 'contactDetail', $i + 1, ['contactType' => 'email', 'detail' => $email, 'label' => $label]);
                }
                echo "    > siteSettings: 3 contact details\n";
            }
            foreach ($settings->socialLinks->all() as $social) {
                if (!$social->handle) {
                    $social->setFieldValue('handle', ($social->platform->value ?? '') === 'substack' ? 'Read The Guest List on Substack' : '@quickcraft');
                    $this->save($social, 'social handle');
                }
            }
        }

        // 4. Contact page
        $pagesSection = $entries->getSectionByHandle('pages');
        $pageType = $entries->getEntryTypeByHandle('page');
        $contact = Entry::find()->section('pages')->slug('contact')->status(null)->one();
        if (!$contact) {
            $photo = $this->asset('contact-photo', 'A large crowd of friends posing and celebrating together at a party.');
            $contact = new Entry(['sectionId' => $pagesSection->id, 'typeId' => $pageType->id, 'title' => 'Contact', 'slug' => 'contact']);
            $contact->setFieldValues(['heroLayout' => 'imageOnly', 'scheme' => 'light', 'images' => $photo ? [$photo->id] : []]);
            $this->save($contact, 'Contact page');
            $this->block($contact, 'contactInfo', 1, [
                'heading' => 'Contact Us', 'text' => 'Say hi! We don’t bite',
                'contactHeading' => 'Have a question?', 'socialHeading' => 'Find us on',
                'formHeading' => 'Send us a message', 'contactForm' => $formId ? (int)$formId : null,
            ]);
            echo "    > page: /contact\n";
        }

        // 5. About page
        $about = Entry::find()->section('pages')->slug('about')->status(null)->one();
        if (!$about) {
            $photo = $this->asset('about-photo', 'A black and white photo of a large group of friends smiling and posing together.');
            $emblem = $this->asset('about-emblem', '');
            $about = new Entry(['sectionId' => $pagesSection->id, 'typeId' => $pageType->id, 'title' => 'About', 'slug' => 'about']);
            $about->setFieldValues([
                'heroLayout' => 'simple', 'scheme' => 'white', 'heading' => 'About Quick Craft',
                'images' => array_values(array_filter([$photo?->id, $emblem?->id])),
            ]);
            $this->save($about, 'About page');

            // press quotes: the logos already used by Home's press strip
            $press = $this->block($about, 'testimonials', 1, ['testimonialLayout' => 'pressRow']);
            foreach ([
                ['The New York Times', 'I’d Rather Send a Quick Craft Invite'],
                ['The Atlantic', 'best solution to the party-invitation problem'],
                ['The Wall Street Journal', 'Evites are so last decade'],
                ['TIME', 'This is where my social calendar exists'],
                ['USA Today', 'Quick Craft is a mainstay of my social life'],
                ['Business Insider', 'the primary party platform'],
            ] as $i => [$outlet, $quote]) {
                $logoEntry = Entry::find()->type('testimonial')->personName($outlet)->status(null)->one();
                $logoId = $logoEntry ? ($logoEntry->image->ids()[0] ?? null) : null;
                $this->nested($press, 'testimonials', 'testimonial', $i + 1, [
                    'personName' => $outlet, 'text' => $quote, 'image' => $logoId ? [$logoId] : [],
                ]);
            }

            $rows = [
                ['I party, therefore I am', 'right', 'about-polaroids-1', 'Two polaroid photos of friends together.', [
                    'Since the beginning, we’ve found excuses to come together — around fires, at feasts, in clubs, in backyards. Gathering is one of the most human things we do.',
                    'But somewhere along the way, it got complicated. A dozen apps, three group chats, and a scheduling miracle just to get friends in the same room. We’ve never had more ways to connect, yet somehow, hanging out feels harder than ever.',
                ]],
                ['From “we should hang out” to “last night was a movie”', 'left', 'about-polaroids-2', 'Three polaroid photos of friends enjoying a lively party.', [
                    'That’s where Quick Craft comes in. We make it easy to go from idea, to invite, to actual memories. Whether you’re hosting a gathering or looking to join one, getting together should feel fun — not like a second job.',
                    'Quick Craft is the home for every kind of night you’ll want to remember: the milestone parties, the casual hangouts, the nights-in that accidentally turn into nights-on-the-town.',
                ]],
                ['Built for the way people actually gather today', 'right', 'about-polaroids-3', 'Two polaroid photos of friends smiling at a party.', [
                    'We’ve designed Quick Craft to take the work out of planning so you can focus on the fun. Send invites without wrangling a group chat. RSVP with just your name and number. Discover where the fun is.',
                    'Because when it’s easier to get together, you do it more often. And the truth is — life isn’t about being “connected.” It’s about being in the same room, laughing at the same stupid joke, making the kind of nights you’ll talk about for years.',
                ]],
                ['Who’s throwing this party?', 'left', 'about-polaroids-4', 'A collage of three photos of friends on a night out.', [
                    'We’re a small team who cares a little too much about getting people together. One of us will cook all day just to host the perfect dinner. Another can’t resist a warehouse rave. Someone else thinks planning an over-the-top birthday is a form of self-care, while another is happiest tucked in the corner of a house party talking to the same two people all night long.',
                    'Different vibes, same belief: gathering is what gives life meaning. That’s why we’re building Quick Craft — to make getting together easier, so more of those moments actually happen.',
                ]],
            ];
            foreach ($rows as $i => [$heading, $position, $file, $alt, $paragraphs]) {
                $image = $this->asset($file, $alt);
                $this->block($about, 'textMedia', $i + 2, [
                    'mediaPosition' => $position, 'heading' => $heading, 'cropImage' => false,
                    'body' => implode('', array_map(fn($p) => '<p>' . htmlspecialchars($p) . '</p>', $paragraphs)),
                    'images' => $image ? [$image->id] : [],
                    'spacing' => $i < 3 ? 'noBottom' : 'default',
                ]);
            }

            $sticker = $this->asset('about-party-sticker', '');
            $careers = Entry::find()->section('pages')->slug('careers')->one();
            $cta = $this->block($about, 'ctaBanner', 6, [
                'ctaLayout' => 'simple', 'scheme' => 'light', 'imageAbove' => true,
                'heading' => 'Want to join the party?', 'images' => $sticker ? [$sticker->id] : [],
            ]);
            $this->nested($cta, 'buttons', 'button', 1, [
                'buttonLink' => $careers
                    ? ['type' => 'entry', 'value' => "{entry:$careers->id@$about->siteId:url}", 'label' => 'Careers']
                    : ['type' => 'url', 'value' => '/careers', 'label' => 'Careers'],
                'buttonStyle' => 'primary',
            ]);
            echo "    > page: /about\n";
        }

        // 6. Footer menu: About / Contact → the entries (as Careers links by entry ID)
        foreach (['/about' => $about, '/contact' => $contact] as $url => $entry) {
            if ($entry) {
                $this->update('{{%simplerpmenu_items}}', ['entry_id' => $entry->id, 'custom_url' => null], ['custom_url' => $url], [], false);
            }
        }

        return true;
    }

    // Helpers

    private function addInstance(string $typeHandle, string $baseHandle, string $handle, string $label, string $instructions, bool $first = false): void
    {
        $entries = Craft::$app->getEntries();
        $type = $entries->getEntryTypeByHandle($typeHandle);
        $layout = $type->getFieldLayout();
        if ($layout->getFieldByHandle($handle)) {
            return;
        }
        $tab = $layout->getTabs()[0];
        $els = $tab->getElements();
        $el = new CustomField(Craft::$app->getFields()->getFieldByHandle($baseHandle), [
            'handle' => $handle, 'label' => $label, 'instructions' => $instructions,
            'uid' => $this->stableUid("el:$typeHandle:$handle"),
        ]);
        $first ? array_unshift($els, $el) : $els[] = $el;
        $tab->setElements($els);
        $type->setFieldLayout($layout);
        $this->check($entries->saveEntryType($type), $type, $typeHandle);
    }

    private function addOption(string $fieldHandle, string $value, string $label): void
    {
        $field = Craft::$app->getFields()->getFieldByHandle($fieldHandle);
        if ($field instanceof Dropdown && !in_array($value, array_column($field->options, 'value'), true)) {
            $options = $field->options;
            $options[] = ['label' => $label, 'value' => $value, 'default' => false];
            $field->options = $options;
            $this->check(Craft::$app->getFields()->saveField($field), $field, $fieldHandle);
        }
    }

    private function asset(string $name, string $alt): ?Asset
    {
        $asset = Asset::find()->filename(["$name.png", "$name.jpg"])->one();
        if ($asset && $alt !== '' && !$asset->alt) {
            $asset->alt = $alt;
            $this->save($asset, $name);
        }
        return $asset;
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
