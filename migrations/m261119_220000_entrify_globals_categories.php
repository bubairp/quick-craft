<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\User;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\Section;
use craft\models\Section_SiteSettings;

/**
 * Everything is an entry (as in Site7 Engine):
 *  - category groups blogCategories, departments → Structure sections (same handles); the
 *    Categories fields (blogCategories, department) become Entries fields.
 *  - global sets siteSettings, newsletter → Single sections (same handles, no URLs).
 *
 * The sections and entry types are created here (non-interactive), then Craft's own
 * entrify/* console actions convert the elements in place: element IDs, content and
 * relations are kept (vendor/craftcms/cms/src/console/controllers/EntrifyController.php).
 * Templates read the settings once per request from `_globals` (templates/_layouts/base.twig).
 */
class m261119_220000_entrify_globals_categories extends Migration
{
    private const CATEGORY_GROUPS = [
        // group handle => [section name, entry type handle, entry type name]
        'blogCategories' => ['Blog categories', 'blogCategory', 'Blog category'],
        'departments' => ['Departments', 'department', 'Department'],
    ];

    private const GLOBAL_SETS = [
        // set handle => entry type handle
        'siteSettings' => 'siteSettings',
        'newsletter' => 'newsletter',
    ];

    /**
     * UIDs from the committed project config, so that on another environment the sections and
     * entry types this migration creates match config/project/*.yaml (no duplicates when the
     * project config is applied after the migration).
     */
    private const UIDS = [
        'section:blogCategories' => '162e4099-ac93-4cf4-a918-d40fb411e4d2',
        'section:departments' => 'eb55722c-ef4f-4086-8ded-7058f8a14efe',
        'section:siteSettings' => '117c7207-1578-412d-a871-66e431a364c7',
        'section:newsletter' => 'bcaffb4a-ef69-4bf9-93b7-7dffd6af1f1a',
        'type:blogCategory' => '6e289974-25fc-41ca-8426-66c8ce5dadfa',
        'type:department' => 'a44624b9-c2b7-48d3-a9a6-6b12900597ff',
        'type:siteSettings' => '78796148-46af-44d8-ba79-abf11861da78',
        'type:newsletter' => '2de22aae-55dd-43ca-84cb-499365bc077d',
    ];

    public function safeUp(): bool
    {
        $author = User::find()->admin()->status(null)->one();
        if (!$author) {
            throw new \RuntimeException('No admin user to own the converted entries.');
        }

        foreach (self::CATEGORY_GROUPS as $handle => [$name, $typeHandle, $typeName]) {
            // withTrashed: on another environment the project config may be applied first, which
            // trashes the group before this runs (entrify handles trashed groups).
            $group = Craft::$app->getCategories()->getGroupByHandle($handle, true);
            if (!$group) {
                echo "    > category group $handle not found — skipped.\n";
                continue;
            }
            $section = $this->section($handle, $name, Section::TYPE_STRUCTURE, $typeHandle, $typeName, fn() => $group->getFieldLayout(), $group->maxLevels);
            $this->run('entrify/categories', [$handle, 'section' => $section->handle, 'entryType' => $typeHandle, 'author' => $author->username]);
        }

        foreach (self::GLOBAL_SETS as $handle => $typeHandle) {
            $set = Craft::$app->getGlobals()->getSetByHandle($handle, withTrashed: true);
            if (!$set) {
                echo "    > global set $handle not found — skipped.\n";
                continue;
            }
            $section = $this->section($handle, $set->name, Section::TYPE_SINGLE, $typeHandle, $set->name, fn() => $set->getFieldLayout(), null);
            $this->run('entrify/global-set', [$handle, 'section' => $section->handle]);
        }

        return true;
    }

    /**
     * The section (and its entry type), created unless the project config already did.
     * $layout is only called when the entry type has to be created here.
     */
    private function section(string $handle, string $name, string $type, string $typeHandle, string $typeName, callable $layout, ?int $maxLevels): Section
    {
        $entries = Craft::$app->getEntries();
        if ($existing = $entries->getSectionByHandle($handle)) {
            return $existing;
        }

        $entryType = $entries->getEntryTypeByHandle($typeHandle);
        if (!$entryType) {
            $entryType = new EntryType([
                'name' => $typeName,
                'handle' => $typeHandle,
                'showStatusField' => $type !== Section::TYPE_SINGLE,
                'uid' => self::UIDS["type:$typeHandle"] ?? null,
            ]);
            // Same layout config (element UIDs included), so the existing content still maps to its fields
            $entryType->setFieldLayout(FieldLayout::createFromConfig($layout()?->getConfig() ?? []));
            if (!$entries->saveEntryType($entryType)) {
                throw new \RuntimeException("Entry type $typeHandle: " . json_encode($entryType->getErrors()));
            }
        }

        $section = new Section([
            'name' => $name,
            'handle' => $handle,
            'type' => $type,
            'enableVersioning' => true,
            'maxLevels' => $maxLevels,
            'previewTargets' => [],
            'uid' => self::UIDS["section:$handle"] ?? null,
        ]);
        $section->setSiteSettings(array_map(fn($site) => new Section_SiteSettings([
            'siteId' => $site->id,
            'hasUrls' => false,
        ]), Craft::$app->getSites()->getAllSites(true)));
        $section->setEntryTypes([$entryType]);
        if (!$entries->saveSection($section)) {
            throw new \RuntimeException("Section $handle: " . json_encode($section->getErrors()));
        }
        return $section;
    }

    private function run(string $route, array $params): void
    {
        $params['interactive'] = false;
        $code = Craft::$app->runAction($route, $params);
        if ($code !== 0) {
            throw new \RuntimeException("$route {$params[0]} failed (exit code $code).");
        }
    }

    public function safeDown(): bool
    {
        return false;
    }
}
