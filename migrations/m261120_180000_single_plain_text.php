<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\FieldInterface;
use craft\db\Migration;
use craft\db\Table;
use craft\fieldlayoutelements\CustomField;
use craft\fields\PlainText;
use craft\helpers\Db;
use craft\helpers\ProjectConfig as ProjectConfigHelper;
use craft\models\FieldLayout;
use craft\services\ProjectConfig;

/**
 * One Plain Text field only (the user's rule, CLAUDE.md › Base field library):
 *  - `plainText` allows multi-line text, starting at 1 row (it grows), and has no character limit.
 *  - `textarea` (text, the volume's "Subtitle") and `emoji` are merged into it with Craft's own
 *    fields/merge steps; their instances keep their handles, labels and content.
 */
class m261120_180000_single_plain_text extends Migration
{
    private const MERGES = ['textarea', 'emoji'];

    public function safeUp(): bool
    {
        $f = Craft::$app->getFields();
        $base = $f->getFieldByHandle('plainText');
        if (!$base instanceof PlainText) {
            throw new \RuntimeException('plainText not found.');
        }

        if (!$base->multiline || $base->initialRows !== 1 || $base->charLimit !== null) {
            $base->multiline = true;
            $base->initialRows = 1;
            $base->charLimit = null;
            if (!$f->saveField($base)) {
                throw new \RuntimeException('plainText: ' . json_encode($base->getErrors()));
            }
            echo "    > plainText: multi-line, 1 starting row, no limit\n";
        }

        foreach (self::MERGES as $outHandle) {
            $out = $f->getFieldByHandle($outHandle);
            if (!$out) {
                continue; // already merged (here or by the project config)
            }
            $reason = null;
            if (!$out->canMergeInto($base, $reason) || !$base->canMergeFrom($out, $reason)) {
                throw new \RuntimeException("$outHandle can't merge into plainText: $reason");
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
            echo "    > $outHandle merged into plainText\n";
        }

        return true;
    }

    /** Same as m261120_130000_base_field_library::eachUsage() (Craft's fields/merge layout update). */
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

    public function safeDown(): bool
    {
        return false;
    }
}
