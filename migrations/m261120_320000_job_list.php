<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;

/**
 * Job list block (Figma Careers 6-6899; CLAUDE.md › pageBuilder blocks › jobList):
 *  - jobList gets `glow` (lightswitchOff instance) and `spacing`.
 *  - Test data from the Figma frame: 5 departments, 10 jobs (generic titles; the posting links
 *    are placeholders), and a jobList block "Open Roles" on Careers with a "See all roles" button.
 */
class m261120_320000_job_list extends Migration
{
    private const DEPARTMENTS = ['Design', 'Engineering', 'General', 'Sales', 'Support'];
    /** [title, department, workplace] — location "New York, NY", full time */
    private const JOBS = [
        ['Product Designer', 'Design', 'onSite'],
        ['App Infrastructure (React Native) Engineer', 'Engineering', 'onSite'],
        ['Platform Engineer, Trust and Safety', 'Engineering', 'hybrid'],
        ['Platform Engineer (Full-Stack)', 'Engineering', 'hybrid'],
        ['Staff Platform Engineer (Full-Stack)', 'Engineering', 'onSite'],
        ['Head of Engineering', 'Engineering', 'hybrid'],
        ['Finance Lead', 'General', 'onSite'],
        ['Open Application', 'General', 'onSite'],
        ['Strategic Accounts Lead', 'Sales', 'remote'],
        ['Community Support Specialist', 'Support', 'onSite'],
    ];

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();
        $elements = Craft::$app->getElements();

        // 1. Layout
        $type = $entries->getEntryTypeByHandle('jobList');
        $layout = $type->getFieldLayout();
        $tab = $layout->getTabs()[0];
        $els = $tab->getElements();
        if (!$layout->getFieldByHandle('glow')) {
            $els[] = new CustomField($fields->getFieldByHandle('lightswitchOff'), [
                'handle' => 'glow', 'label' => 'Soft glow background', 'uid' => $this->stableUid('el:jobList:glow'),
            ]);
        }
        if (!$layout->getFieldByHandle('spacing')) {
            $els[] = new CustomField($fields->getFieldByHandle('spacing'), ['uid' => $this->stableUid('el:jobList:spacing')]);
        }
        $tab->setElements($els);
        $type->setFieldLayout($layout);
        $this->check($entries->saveEntryType($type), $type, 'jobList');

        // 2. Departments + jobs (only when the channel is empty)
        $deptSection = $entries->getSectionByHandle('departments');
        $jobsSection = $entries->getSectionByHandle('jobs');
        if ($deptSection && $jobsSection && !Entry::find()->section('jobs')->status(null)->exists()) {
            $deptType = $deptSection->getEntryTypes()[0];
            $deptIds = [];
            foreach (self::DEPARTMENTS as $name) {
                $dept = Entry::find()->section('departments')->title($name)->status(null)->one();
                if (!$dept) {
                    $dept = new Entry(['sectionId' => $deptSection->id, 'typeId' => $deptType->id, 'title' => $name]);
                    $this->check($elements->saveElement($dept), $dept, "department $name");
                }
                $deptIds[$name] = $dept->id;
            }
            $jobType = $jobsSection->getEntryTypes()[0];
            $date = new \DateTime('2026-11-01 09:00');
            foreach (self::JOBS as [$title, $dept, $workplace]) {
                $job = new Entry(['sectionId' => $jobsSection->id, 'typeId' => $jobType->id, 'title' => $title, 'postDate' => clone $date]);
                $date->modify('+1 minute'); // keeps the design's order (the block sorts by post date)
                $job->setFieldValues([
                    'department' => [$deptIds[$dept]],
                    'location' => 'New York, NY',
                    'employmentType' => 'fullTime',
                    'workplace' => $workplace,
                    'externalLink' => ['type' => 'url', 'value' => 'https://example.com/jobs', 'target' => '_blank'], // PLACEHOLDER
                ]);
                $this->check($elements->saveElement($job), $job, "job $title");
            }
            echo '    > ' . count(self::DEPARTMENTS) . ' departments, ' . count(self::JOBS) . " jobs\n";
        }

        // 3. jobList block on Careers
        $page = Entry::find()->section('pages')->slug('careers')->status(null)->one();
        $pageBuilder = $fields->getFieldByHandle('pageBuilder');
        if ($page && !Entry::find()->ownerId($page->id)->field($pageBuilder)->type('jobList')->status(null)->exists()) {
            $sort = (int)Entry::find()->ownerId($page->id)->field($pageBuilder)->status(null)->count();
            $block = new Entry([
                'fieldId' => $pageBuilder->id, 'ownerId' => $page->id, 'primaryOwnerId' => $page->id,
                'typeId' => $type->id, 'siteId' => $page->siteId, 'sortOrder' => $sort + 1,
            ]);
            $block->setFieldValues(['heading' => 'Open Roles', 'glow' => true]);
            $this->check($elements->saveElement($block), $block, 'jobList block');

            $buttons = $fields->getFieldByHandle('buttons');
            $button = new Entry([
                'fieldId' => $buttons->id, 'ownerId' => $block->id, 'primaryOwnerId' => $block->id,
                'typeId' => $entries->getEntryTypeByHandle('button')->id, 'siteId' => $page->siteId, 'sortOrder' => 1,
            ]);
            $button->setFieldValues([
                'buttonLink' => ['type' => 'url', 'value' => 'https://example.com/jobs', 'label' => 'See all roles'], // PLACEHOLDER
                'buttonStyle' => 'dark',
            ]);
            $this->check($elements->saveElement($button), $button, 'See all roles button');
            echo "    > Careers: + Open Roles\n";
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
