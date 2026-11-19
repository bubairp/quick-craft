<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\FieldInterface;
use craft\db\Migration;
use craft\db\Table;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Date;
use craft\fields\Email;
use craft\fields\Number;
use craft\helpers\Db;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\models\FieldLayout;
use craft\services\ProjectConfig;

/**
 * Base field library (CLAUDE.md › Base field library): generic fields, created once and added to
 * sections/blocks many times with their own handle and label (field-instance overrides).
 *
 *  1. Existing fields become base fields. Every instance first gets its current handle and label as
 *     an explicit override (so templates and content don't change), then the field is renamed:
 *       eyebrow → plainText · text → textarea · body → richText · primaryButton → linkWithLabel
 *       externalLink → urlLink · featured → lightswitch
 *  2. Merges into base fields (Craft's own fields/merge steps): heading → plainText (its 255-char
 *     limit goes), appStoreLink → urlLink, showAppBadges → lightswitch.
 *  3. Unused secondaryButton is deleted.
 *  4. New base fields: number, date, email.
 *
 * Content is keyed by layout element UIDs, which never change here. Layouts outside entry types
 * (the images volume uses `text`) are included via Fields::findFieldUsages().
 */
class m261120_130000_base_field_library extends Migration
{
    private const RENAMES = [
        // old handle => [base handle, base name]
        'eyebrow' => ['plainText', 'Plain text'],
        'text' => ['textarea', 'Textarea'],
        'body' => ['richText', 'Rich text'],
        'primaryButton' => ['linkWithLabel', 'Link (with label)'],
        'externalLink' => ['urlLink', 'URL link'],
        'featured' => ['lightswitch', 'Lightswitch'],
    ];
    private const MERGES = [
        // outgoing => base
        'heading' => 'plainText',
        'appStoreLink' => 'urlLink',
        'showAppBadges' => 'lightswitch',
    ];

    public function safeUp(): bool
    {
        $f = Craft::$app->getFields();

        // 1. Renames
        foreach (self::RENAMES as $old => [$handle, $name]) {
            $field = $f->getFieldByHandle($old);
            if (!$field) {
                continue; // already done (here or by the project config)
            }
            $oldName = $field->name;
            $this->eachUsage($field, function(CustomField $el) use ($old, $oldName) {
                $changed = false;
                if ($el->handle === null) {
                    $el->handle = $old;
                    $changed = true;
                }
                if ($el->label === null) {
                    $el->label = $oldName;
                    $changed = true;
                }
                return $changed;
            });
            $field->handle = $handle;
            $field->name = $name;
            if (!$f->saveField($field)) {
                throw new \RuntimeException("$old → $handle: " . json_encode($field->getErrors()));
            }
            echo "    > $old → base field $handle\n";
        }

        // 2. Merges
        foreach (self::MERGES as $outHandle => $baseHandle) {
            $out = $f->getFieldByHandle($outHandle);
            $base = $f->getFieldByHandle($baseHandle);
            if (!$out || !$base) {
                continue;
            }
            $reason = null;
            if (!$out->canMergeInto($base, $reason) || !$base->canMergeFrom($out, $reason)) {
                throw new \RuntimeException("$outHandle can't merge into $baseHandle: $reason");
            }
            $this->eachUsage($out, function(CustomField $el) use ($out, $base) {
                $el->label ??= $out->name;
                $el->handle ??= $out->handle;
                $el->instructions ??= $out->instructions ?: null;
                $el->oldFieldUid ??= $out->uid;
                $el->setField($base);
                return true;
            });
            $out->afterMergeInto($base);
            $base->afterMergeFrom($out);
            $f->deleteField($out);
            echo "    > $outHandle merged into $baseHandle\n";
        }

        // 3. Unused
        $unused = $f->getFieldByHandle('secondaryButton');
        if ($unused && empty($f->findFieldUsages($unused))) {
            $f->deleteField($unused);
            echo "    > secondaryButton (unused) deleted\n";
        }

        // 4. New base fields
        foreach ([
            'number' => [Number::class, 'Number', []],
            'date' => [Date::class, 'Date', ['showDate' => true, 'showTime' => false]],
            'email' => [Email::class, 'Email', []],
        ] as $handle => [$class, $name, $config]) {
            if ($f->getFieldByHandle($handle)) {
                continue;
            }
            $field = new $class($config + ['name' => $name, 'handle' => $handle, 'uid' => $this->stableUid("field:$handle")]);
            if (!$f->saveField($field)) {
                throw new \RuntimeException("$handle: " . json_encode($field->getErrors()));
            }
            echo "    > base field $handle created\n";
        }

        return true;
    }

    /**
     * Runs $fn on every layout element that uses $field, in every layout (entry types, volumes…),
     * and saves the changed layouts the way Craft's fields/merge command does
     * (vendor/craftcms/cms/src/console/controllers/FieldsController::updateFieldUsages()).
     */
    private function eachUsage(FieldInterface $field, callable $fn): void
    {
        $fieldsService = Craft::$app->getFields();
        $projectConfig = Craft::$app->getProjectConfig();

        foreach ($fieldsService->findFieldUsages($field) as $layout) {
            /** @var FieldLayout $layout */
            $changed = false;
            foreach ($layout->getCustomFieldElements() as $el) {
                if ($el->getFieldUid() === $field->uid && $fn($el)) {
                    $changed = true;
                }
            }
            if (!$changed) {
                continue;
            }

            $muted = $projectConfig->muteEvents;
            $projectConfig->muteEvents = true;
            if (!$layout->id && $layout->uid) {
                $layout->id = Db::idByUid(Table::FIELDLAYOUTS, $layout->uid);
            }
            if ($layout->id) {
                $fieldsService->saveLayout($layout);
            }
            if ($layout->uid) {
                $occurrences = $projectConfig->find(fn(array $item) => (
                    isset($item[$layout->uid]) ||
                    in_array($layout->uid, array_column($item[ProjectConfig::ASSOC_KEY] ?? [], 0), true)
                ));
                foreach ($occurrences as $path => $item) {
                    if (isset($item[ProjectConfig::ASSOC_KEY])) {
                        $item = ProjectConfigHelper::unpackAssociativeArray($item);
                        $item[$layout->uid] = $layout->getConfig();
                        $projectConfig->set($path, ProjectConfigHelper::packAssociativeArray($item));
                    } else {
                        $projectConfig->set("$path.$layout->uid", $layout->getConfig());
                    }
                }
            }
            $projectConfig->muteEvents = $muted;
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
