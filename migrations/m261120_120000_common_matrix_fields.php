<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\FieldInterface;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Dropdown;
use craft\fields\Matrix;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

/**
 * Common Matrix fields + shared base fields (CLAUDE.md › Common Matrix fields / Shared base fields).
 *
 *  1. Shared base fields (Craft's own fields/merge steps, done non-interactively): the layout element
 *     is pointed at the shared field, keeping its UID (content is keyed by it), its handle and label,
 *     then the outgoing field is deleted.
 *       eyebrow ("Short text")         ← personName, personDetail, location, publication
 *       comparisonUs ("Comparison list") ← comparisonThem
 *       appStoreLink ("Store link")    ← googlePlayLink
 *  2. New common Matrix fields: buttons (button), socialLinks (socialLink), contactDetails (contactDetail).
 *  3. Every button group moves to `buttons`: page Banner, cardGrid, ctaBanner (its secondary button),
 *     imageGallery, jobList, siteSettings (header). The old Link values are read from each owner's raw
 *     content at the old layout element UIDs, so this works whether or not the project config was
 *     applied first. Single inline links stay Link fields (card, city, ctaBanner heading link, promoBar).
 *  4. siteSettings gets socialLinks (filled from the footerSocials menu) and contactDetails.
 *
 * New fields, entry types, tabs and layout elements get deterministic UIDs (stableUid()), so every
 * environment builds the same config as config/project/*.yaml.
 */
class m261120_120000_common_matrix_fields extends Migration
{
    private const MERGES = [
        // outgoing => shared
        'personName' => 'eyebrow',
        'personDetail' => 'eyebrow',
        'location' => 'eyebrow',
        'publication' => 'eyebrow',
        'comparisonThem' => 'comparisonUs',
        'googlePlayLink' => 'appStoreLink',
    ];
    private const SHARED_NAMES = ['eyebrow' => 'Short text', 'comparisonUs' => 'Comparison list', 'appStoreLink' => 'Store link'];

    /** Owner entry type => [old Link layout element UID => button style], from config/project before this migration. */
    private const BUTTON_OWNERS = [
        'home' => ['bf42095a-dd65-4482-bf12-163fd70a8ffc' => 'primary', '2e3416d1-210c-4ef3-ba67-f9bdb921c107' => 'secondary'],
        'landingPage' => ['eb796b79-ac46-41cb-a7df-e471b8ca9904' => 'primary', 'c8dbdbd1-87ae-410b-b14d-70d0a1cc67c1' => 'secondary'],
        'careers' => ['87e9d4de-6299-4ef3-8a18-90416af090f6' => 'primary', '792fb036-134f-4077-8e2e-e3ff29a17dda' => 'secondary'],
        'press' => ['29e5f640-047d-4f78-bdb8-c9e63d015288' => 'primary', '1781cb34-59f0-4e85-a353-e88a99e4fbaa' => 'secondary'],
        'cardGrid' => ['3c149dae-6014-40d1-8438-3a00cbe7fc88' => 'primary', 'da621b1a-24eb-4ee3-a5f5-72a8b44a0e5d' => 'secondary'],
        'ctaBanner' => ['8e879bb9-fedf-4245-b2a5-e607f898f2ba' => 'primary'], // its primaryButton stays: the heading link
        'imageGallery' => ['17821789-2ed7-4227-b2c9-93e04b79b379' => 'primary'],
        'jobList' => ['a0dface6-6585-47f6-bf3a-b10f9ebfb6f0' => 'primary'],
        'siteSettings' => ['1b39b1e9-6271-426e-8228-964070fd9c12' => 'primary', '5af5dc9a-3d89-4d28-b6c1-e7c95926b1d4' => 'secondary'],
    ];

    /** Labels for the single links that stay Link fields */
    private const LINK_LABELS = ['ctaBanner' => 'Heading link', 'card' => 'Link', 'promoBar' => 'Link'];

    private const PLATFORMS = [
        'instagram' => 'Instagram', 'x' => 'X (Twitter)', 'tiktok' => 'TikTok', 'linkedin' => 'LinkedIn',
        'facebook' => 'Facebook', 'youtube' => 'YouTube', 'threads' => 'Threads', 'pinterest' => 'Pinterest',
        'substack' => 'Substack', 'other' => 'Other',
    ];
    /** footerSocials menu item name => platform */
    private const MENU_PLATFORMS = [
        'Instagram' => 'instagram', 'Twitter' => 'x', 'X' => 'x', 'TikTok' => 'tiktok', 'LinkedIn' => 'linkedin',
        'Facebook' => 'facebook', 'YouTube' => 'youtube', 'Threads' => 'threads', 'Pinterest' => 'pinterest', 'Substack' => 'substack',
    ];

    public function safeUp(): bool
    {
        $this->mergeSharedFields();

        $f = Craft::$app->getFields();
        $buttonStyle = $this->dropdown('buttonStyle', 'Style', [
            'primary' => 'Primary (automatic colour)', 'secondary' => 'Secondary (automatic colour)', 'dark' => 'Dark', 'light' => 'Light',
        ], 'Primary and Secondary follow the section’s colour scheme. Dark and Light are fixed colours.');
        $platform = $this->dropdown('platform', 'Platform', self::PLATFORMS);
        $contactType = $this->dropdown('contactType', 'Type', ['email' => 'Email', 'phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'address' => 'Address']);

        $short = $f->getFieldByHandle('eyebrow');
        $button = $this->blockType('button', 'Button', '{buttonLink.label}', [
            [$f->getFieldByHandle('primaryButton'), ['handle' => 'buttonLink', 'label' => 'Link']],
            [$buttonStyle, []],
            [$short, ['handle' => 'iconName', 'label' => 'Icon', 'instructions' => 'Optional. A Tabler icon name from https://tabler.io/icons, e.g. `arrow-right`.']],
        ]);
        $socialLink = $this->blockType('socialLink', 'Social link', '{platform.label}', [
            [$platform, []],
            [$f->getFieldByHandle('externalLink'), ['label' => 'URL']],
        ]);
        $contactDetail = $this->blockType('contactDetail', 'Contact detail', '{detail}', [
            [$contactType, []],
            [$short, ['handle' => 'detail', 'label' => 'Detail', 'instructions' => 'Email address, phone number (with country code) or postal address.']],
        ]);

        $buttons = $this->matrix('buttons', 'Buttons', [$button], 'Add button', ['maxEntries' => 3, 'viewMode' => Matrix::VIEW_MODE_BLOCKS]);
        $socialLinks = $this->matrix('socialLinks', 'Social links', [$socialLink], 'Add social link', ['viewMode' => Matrix::VIEW_MODE_BLOCKS]);
        $contactDetails = $this->matrix('contactDetails', 'Contact details', [$contactDetail], 'Add contact detail', ['viewMode' => Matrix::VIEW_MODE_BLOCKS]);

        // Layouts: `buttons` replaces the button Link fields; settings get socials + contact
        $entries = Craft::$app->getEntries();
        foreach (self::BUTTON_OWNERS as $typeHandle => $uids) {
            if ($type = $entries->getEntryTypeByHandle($typeHandle)) {
                $this->replaceWithButtons($type, array_keys($uids), $buttons);
            }
        }
        if ($settings = $entries->getEntryTypeByHandle('siteSettings')) {
            $this->append($settings, [$socialLinks, $contactDetails]);
        }
        foreach (self::LINK_LABELS as $typeHandle => $label) {
            if ($type = $entries->getEntryTypeByHandle($typeHandle)) {
                $this->relabel($type, 'primaryButton', $label);
            }
        }

        // Content
        $this->moveButtons($buttons, $button);
        $this->fillSocialLinks($socialLinks, $socialLink);

        return true;
    }

    // ── 1. Shared base fields ────────────────────────────────────────────────

    private function mergeSharedFields(): void
    {
        $f = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();

        // Existing instances of the shared fields keep their current name as a label override
        foreach (self::SHARED_NAMES as $handle => $newName) {
            $shared = $f->getFieldByHandle($handle);
            if (!$shared || $shared->name === $newName) {
                continue;
            }
            foreach ($entries->getAllEntryTypes() as $type) {
                $this->eachElement($type, function(CustomField $el) use ($shared) {
                    if ($el->getFieldUid() === $shared->uid && $el->label === null) {
                        $el->label = $shared->name;
                        return true;
                    }
                    return false;
                });
            }
            $shared->name = $newName;
            $f->saveField($shared);
        }

        foreach (self::MERGES as $outHandle => $sharedHandle) {
            $out = $f->getFieldByHandle($outHandle);
            $shared = $f->getFieldByHandle($sharedHandle);
            if (!$out || !$shared) {
                continue; // already merged (here, or by the project config)
            }
            if ($out::class !== $shared::class || json_encode($this->mergeSettings($out)) !== json_encode($this->mergeSettings($shared))) {
                echo "    > $outHandle: settings differ from $sharedHandle — not merged\n";
                continue;
            }
            $reason = null;
            if (!$out->canMergeInto($shared, $reason) || !$shared->canMergeFrom($out, $reason)) {
                echo "    > $outHandle can't merge into $sharedHandle: $reason\n";
                continue;
            }
            foreach ($entries->getAllEntryTypes() as $type) {
                $this->eachElement($type, function(CustomField $el) use ($out, $shared) {
                    if ($el->getFieldUid() !== $out->uid) {
                        return false;
                    }
                    $el->label ??= $out->name;
                    $el->handle ??= $out->handle;
                    $el->instructions ??= $out->instructions ?: null;
                    $el->oldFieldUid ??= $out->uid;
                    $el->setField($shared);
                    return true;
                });
            }
            $out->afterMergeInto($shared);
            $shared->afterMergeFrom($out);
            $f->deleteField($out);
            echo "    > $outHandle merged into $sharedHandle\n";
        }
    }

    private function mergeSettings(FieldInterface $field): array
    {
        $settings = $field->getSettings();
        unset($settings['placeholder']);
        return $settings;
    }

    // ── 2. Fields and entry types ────────────────────────────────────────────

    private function dropdown(string $handle, string $name, array $options, ?string $instructions = null): FieldInterface
    {
        $f = Craft::$app->getFields();
        if ($existing = $f->getFieldByHandle($handle)) {
            return $existing;
        }
        $opts = [];
        foreach ($options as $value => $label) {
            $opts[] = ['label' => $label, 'value' => $value, 'default' => $opts === []];
        }
        $field = new Dropdown(['name' => $name, 'handle' => $handle, 'options' => $opts, 'instructions' => $instructions, 'uid' => $this->stableUid("field:$handle")]);
        if (!$f->saveField($field)) {
            throw new \RuntimeException("$handle: " . json_encode($field->getErrors()));
        }
        return $field;
    }

    /** @param array<array{0: FieldInterface, 1: array}> $fields [field, instance overrides] */
    private function blockType(string $handle, string $name, string $labelFormat, array $fields): EntryType
    {
        $entries = Craft::$app->getEntries();
        if ($existing = $entries->getEntryTypeByHandle($handle)) {
            return $existing;
        }
        $layout = new FieldLayout(['type' => Entry::class, 'uid' => $this->stableUid("layout:$handle")]);
        $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout, 'uid' => $this->stableUid("tab:$handle:Content")]);
        $elements = [];
        foreach ($fields as [$field, $overrides]) {
            $key = $overrides['handle'] ?? $field->handle;
            $elements[] = new CustomField($field, $overrides + ['uid' => $this->stableUid("el:$handle:$key")]);
        }
        $tab->setElements($elements);
        $layout->setTabs([$tab]);
        $type = new EntryType([
            'name' => $name, 'handle' => $handle, 'hasTitleField' => false, 'showSlugField' => false,
            'uiLabelFormat' => $labelFormat, 'uid' => $this->stableUid("entryType:$handle"),
        ]);
        $type->setFieldLayout($layout);
        if (!$entries->saveEntryType($type)) {
            throw new \RuntimeException("$handle: " . json_encode($type->getErrors()));
        }
        return $type;
    }

    private function matrix(string $handle, string $name, array $types, string $buttonLabel, array $config): Matrix
    {
        $f = Craft::$app->getFields();
        if (($existing = $f->getFieldByHandle($handle)) instanceof Matrix) {
            return $existing;
        }
        $field = new Matrix($config + ['name' => $name, 'handle' => $handle, 'createButtonLabel' => $buttonLabel, 'uid' => $this->stableUid("field:$handle")]);
        $field->setEntryTypes($types);
        if (!$f->saveField($field)) {
            throw new \RuntimeException("$handle: " . json_encode($field->getErrors()));
        }
        return $field;
    }

    // ── 3. Layout edits ──────────────────────────────────────────────────────

    /** `buttons` goes where the first old button element was; the old button elements are removed. */
    private function replaceWithButtons(EntryType $type, array $oldUids, Matrix $buttons): void
    {
        $layout = $type->getFieldLayout();
        if ($layout->getFieldByHandle('buttons')) {
            return;
        }
        foreach ($layout->getTabs() as $tab) {
            $elements = [];
            $placed = false;
            $touched = false;
            foreach ($tab->getElements() as $el) {
                if (in_array($el->uid, $oldUids, true)) {
                    $touched = true;
                    if (!$placed) {
                        $elements[] = new CustomField($buttons, ['uid' => $this->stableUid("el:{$type->handle}:buttons")]);
                        $placed = true;
                    }
                    continue;
                }
                $elements[] = $el;
            }
            if ($touched) {
                $tab->setElements($elements);
            }
        }
        $this->saveType($type, $layout, 'buttons');
    }

    private function append(EntryType $type, array $fields): void
    {
        $layout = $type->getFieldLayout();
        $tab = $layout->getTabs()[0];
        $elements = $tab->getElements();
        $added = false;
        foreach ($fields as $field) {
            if (!$layout->getFieldByHandle($field->handle)) {
                $elements[] = new CustomField($field, ['uid' => $this->stableUid("el:{$type->handle}:{$field->handle}")]);
                $added = true;
            }
        }
        if ($added) {
            $tab->setElements($elements);
            $this->saveType($type, $layout, 'socialLinks, contactDetails');
        }
    }

    private function relabel(EntryType $type, string $handle, string $label): void
    {
        $this->eachElement($type, function(CustomField $el) use ($handle, $label) {
            if ($el->handle === null && $el->getField()->handle === $handle && $el->label !== $label) {
                $el->label = $label;
                return true;
            }
            return false;
        });
    }

    /** Runs $fn on every custom field element of the type's layout; saves the type if any call returned true. */
    private function eachElement(EntryType $type, callable $fn): void
    {
        $layout = $type->getFieldLayout();
        $changed = false;
        foreach ($layout->getTabs() as $tab) {
            foreach ($tab->getElements() as $el) {
                if ($el instanceof CustomField && $fn($el)) {
                    $changed = true;
                }
            }
        }
        if ($changed) {
            $this->saveType($type, $layout, null);
        }
    }

    private function saveType(EntryType $type, FieldLayout $layout, ?string $what): void
    {
        $type->setFieldLayout($layout);
        if (!Craft::$app->getEntries()->saveEntryType($type)) {
            throw new \RuntimeException("{$type->handle}: " . json_encode($type->getErrors()));
        }
        if ($what) {
            echo "    > {$type->handle}: $what\n";
        }
    }

    // ── 4. Content ───────────────────────────────────────────────────────────

    /** Old Link values (raw content at the old element UIDs) → `button` entries in each owner's `buttons`. */
    private function moveButtons(Matrix $buttons, EntryType $button): void
    {
        $moved = 0;
        foreach (self::BUTTON_OWNERS as $typeHandle => $uids) {
            $typeId = (new Query())->select('id')->from(Table::ENTRYTYPES)->where(['handle' => $typeHandle, 'dateDeleted' => null])->scalar();
            if (!$typeId) {
                continue;
            }
            $rows = (new Query())
                ->select(['es.elementId', 'es.siteId', 'es.content'])
                ->from(['es' => Table::ELEMENTS_SITES])
                ->innerJoin(['e' => Table::ENTRIES], '[[e.id]] = [[es.elementId]]')
                ->innerJoin(['el' => Table::ELEMENTS], '[[el.id]] = [[es.elementId]]')
                // Nested blocks of a draft/revision aren't drafts themselves: skip them via their primary
                // owner, or the draft gets extra buttons on top of the shared ones (fixed by m261120_260000)
                ->leftJoin(['po' => Table::ELEMENTS], '[[po.id]] = [[e.primaryOwnerId]]')
                ->where(['e.typeId' => $typeId, 'el.draftId' => null, 'el.revisionId' => null, 'el.dateDeleted' => null])
                ->andWhere(['or', ['e.primaryOwnerId' => null], ['and', ['po.draftId' => null], ['po.revisionId' => null]]])
                ->all();
            foreach ($rows as $row) {
                $content = json_decode((string)$row['content'], true) ?: [];
                $already = Entry::find()->ownerId($row['elementId'])->fieldId($buttons->id)->siteId($row['siteId'])->status(null)->exists();
                if ($already) {
                    continue;
                }
                $sort = 1;
                foreach ($uids as $uid => $style) {
                    $link = $content[$uid] ?? null;
                    if (!is_array($link) || empty($link['value'])) {
                        continue;
                    }
                    $entry = new Entry([
                        'fieldId' => $buttons->id,
                        'ownerId' => $row['elementId'],
                        'primaryOwnerId' => $row['elementId'],
                        'typeId' => $button->id,
                        'siteId' => $row['siteId'],
                        'sortOrder' => $sort++,
                    ]);
                    $entry->setFieldValues(['buttonLink' => $link, 'buttonStyle' => $style]);
                    if (!Craft::$app->getElements()->saveElement($entry)) {
                        throw new \RuntimeException("button for {$row['elementId']}: " . json_encode($entry->getErrors()));
                    }
                    $moved++;
                }
            }
        }
        echo "    > $moved buttons moved into `buttons`\n";
    }

    /** footerSocials menu items → siteSettings.socialLinks (the menu itself is kept). */
    private function fillSocialLinks(Matrix $socialLinks, EntryType $socialLink): void
    {
        $settings = Entry::find()->section('siteSettings')->status(null)->one();
        if (!$settings || Entry::find()->ownerId($settings->id)->fieldId($socialLinks->id)->status(null)->exists()) {
            return;
        }
        $menuId = (new Query())->select('id')->from('{{%simplerpmenu}}')->where(['handle' => 'footerSocials'])->scalar();
        if (!$menuId) {
            return;
        }
        $items = (new Query())->select(['name', 'custom_url'])->from('{{%simplerpmenu_items}}')
            ->where(['menu_id' => $menuId])->orderBy(['item_order' => SORT_ASC, 'id' => SORT_ASC])->all();
        $sort = 1;
        foreach ($items as $item) {
            $url = trim((string)$item['custom_url']);
            if ($url === '') {
                continue;
            }
            $entry = new Entry([
                'fieldId' => $socialLinks->id,
                'ownerId' => $settings->id,
                'primaryOwnerId' => $settings->id,
                'typeId' => $socialLink->id,
                'siteId' => $settings->siteId,
                'sortOrder' => $sort++,
            ]);
            $entry->setFieldValues([
                'platform' => self::MENU_PLATFORMS[trim($item['name'])] ?? 'other',
                'externalLink' => ['type' => 'url', 'value' => $url, 'target' => '_blank'],
            ]);
            if (!Craft::$app->getElements()->saveElement($entry)) {
                throw new \RuntimeException('social link: ' . json_encode($entry->getErrors()));
            }
        }
        echo '    > ' . ($sort - 1) . " social links copied from the footerSocials menu\n";
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
