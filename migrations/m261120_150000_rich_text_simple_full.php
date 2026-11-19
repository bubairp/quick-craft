<?php

namespace craft\contentmigrations;

use Craft;
use craft\ckeditor\Field as Ckeditor;
use craft\db\Migration;

/**
 * Two CKEditor base fields (the user's request):
 *  - `richText` → `richTextFull` "Rich text (full)": full toolbar, headings h2–h4 only (the page
 *    title is the <h1>). Its instance `body` (blog post) keeps its handle and content.
 *  - new `richText` "Rich text": simple toolbar for short formatted text in blocks; no headings,
 *    no images.
 * Neither offers font size, colour or alignment (inline styles would break the tokens rule).
 * Toolbar button names: vendor/craftcms/ckeditor/src/helpers/CkeditorConfig.php.
 */
class m261120_150000_rich_text_simple_full extends Migration
{
    private const FULL_TOOLBAR = [
        'heading', '|',
        'bold', 'italic', 'underline', 'strikethrough', '|',
        'link', '|',
        'bulletedList', 'numberedList', 'outdent', 'indent', '|',
        'blockQuote', 'insertTable', 'insertImage', 'mediaEmbed', 'horizontalLine', '|',
        'removeFormat', 'fullscreen', 'sourceEditing',
    ];
    private const SIMPLE_TOOLBAR = ['bold', 'italic', 'link', '|', 'bulletedList', 'numberedList', '|', 'removeFormat'];

    public function safeUp(): bool
    {
        $f = Craft::$app->getFields();

        // 1. The existing CKEditor field becomes the full one
        $full = $f->getFieldByHandle('richTextFull');
        if (!$full) {
            $full = $f->getFieldByHandle('richText');
            if (!$full instanceof Ckeditor) {
                throw new \RuntimeException('richText CKEditor field not found.');
            }
            $full->handle = 'richTextFull';
            $full->name = 'Rich text (full)';
            $full->toolbar = self::FULL_TOOLBAR;
            $full->headingLevels = [2, 3, 4];
            if (!$f->saveField($full)) {
                throw new \RuntimeException('richTextFull: ' . json_encode($full->getErrors()));
            }
            echo "    > richText → richTextFull (full toolbar, h2–h4)\n";
        }

        // 2. New simple one
        if (!$f->getFieldByHandle('richText')) {
            $simple = new Ckeditor([
                'name' => 'Rich text',
                'handle' => 'richText',
                'uid' => $this->stableUid('field:richText'),
                'toolbar' => self::SIMPLE_TOOLBAR,
                'headingLevels' => false,
                'availableVolumes' => [],
                'sourceEditingGroups' => [],
            ]);
            if (!$f->saveField($simple)) {
                throw new \RuntimeException('richText: ' . json_encode($simple->getErrors()));
            }
            echo "    > richText created (simple toolbar)\n";
        }

        return true;
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
