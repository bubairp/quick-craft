<?php

namespace craft\contentmigrations;

use Craft;
use craft\base\FieldInterface;
use craft\ckeditor\Field as CkeditorField;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\GlobalSet;
use craft\fieldlayoutelements\assets\AltField;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Assets;
use craft\fields\Categories;
use craft\fields\Dropdown;
use craft\fields\Lightswitch;
use craft\fields\Link;
use craft\fields\Matrix;
use craft\fields\Number;
use craft\fields\PlainText;
use craft\fields\Table;
use craft\fs\Local;
use craft\models\CategoryGroup;
use craft\models\CategoryGroup_SiteSettings;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\models\Volume;
use ether\seo\fields\SeoField;
use wheelform\fields\FormField;

/**
 * Quick Craft content model (Figma WdjEJ9NeK2FTVsJcGF6lby).
 *
 * Creates: images volume, category groups, shared fields, nested entry types,
 * 15 pageBuilder blocks, sections (Home, Landing pages, Blog, Careers, Jobs,
 * Press, Press mentions) and global sets (Site settings, Newsletter).
 *
 * Idempotent: every item is looked up by handle first and skipped if it exists.
 */
class m261118_120000_content_model extends Migration
{
    private int $siteId;

    public function safeUp(): bool
    {
        $this->siteId = Craft::$app->getSites()->getPrimarySite()->id;

        // ── Assets ────────────────────────────────────────────────────────────
        $volume = $this->volume('images', 'Images', '@webroot/uploads/images', '@web/uploads/images');
        $volumeSource = 'volume:' . $volume->uid;

        // ── Taxonomies ────────────────────────────────────────────────────────
        $blogCatGroup = $this->categoryGroup('blogCategories', 'Blog categories');
        $deptGroup = $this->categoryGroup('departments', 'Departments');

        // ── Shared fields ─────────────────────────────────────────────────────
        $f = [];
        $f['heading'] = $this->field('heading', PlainText::class, 'Heading', ['charLimit' => 255]);
        $f['eyebrow'] = $this->field('eyebrow', PlainText::class, 'Eyebrow', [
            'instructions' => 'Small label above the heading.',
        ]);
        $f['text'] = $this->field('text', PlainText::class, 'Text', ['multiline' => true, 'initialRows' => 3]);
        $f['emoji'] = $this->field('emoji', PlainText::class, 'Emoji icon', [
            'instructions' => 'A single emoji, e.g. 🛠️', 'charLimit' => 8,
        ]);
        $f['personName'] = $this->field('personName', PlainText::class, 'Person name');
        $f['personDetail'] = $this->field('personDetail', PlainText::class, 'Person detail', [
            'instructions' => 'Role, organisation or date, e.g. “hackNY”.',
        ]);
        $f['location'] = $this->field('location', PlainText::class, 'Location');
        $f['publication'] = $this->field('publication', PlainText::class, 'Publication');

        $f['body'] = $this->field('body', CkeditorField::class, 'Body', [
            'toolbar' => [
                'heading', '|', 'bold', 'italic', 'link', '|',
                'bulletedList', 'numberedList', 'blockQuote', 'insertImage', 'horizontalLine',
                '|', 'sourceEditing',
            ],
            'availableVolumes' => [$volume->uid],
            'defaultUploadLocationVolume' => $volume->uid,
        ]);

        $f['image'] = $this->field('image', Assets::class, 'Image', [
            'sources' => [$volumeSource],
            'defaultUploadLocationSource' => $volumeSource,
            'restrictFiles' => true,
            'allowedKinds' => ['image'],
            'maxRelations' => 1,
            'viewMode' => 'large',
        ]);
        $f['images'] = $this->field('images', Assets::class, 'Images', [
            'instructions' => 'Images, videos or embedded media. Captions use the asset title; set alt text on each asset.',
            'sources' => [$volumeSource],
            'defaultUploadLocationSource' => $volumeSource,
            'restrictFiles' => true,
            'allowedKinds' => ['image', 'video', 'json'], // json = Embedded Assets
            'viewMode' => 'large',
        ]);

        $linkTypes = ['entry', 'url', 'email'];
        $f['primaryButton'] = $this->field('primaryButton', Link::class, 'Primary button', [
            'types' => $linkTypes, 'showLabelField' => true,
        ]);
        $f['secondaryButton'] = $this->field('secondaryButton', Link::class, 'Secondary button', [
            'types' => $linkTypes, 'showLabelField' => true,
        ]);
        $f['externalLink'] = $this->field('externalLink', Link::class, 'External link', [
            'types' => ['url'], 'advancedFields' => ['target'],
        ]);
        $f['appStoreLink'] = $this->field('appStoreLink', Link::class, 'App Store link', ['types' => ['url']]);
        $f['googlePlayLink'] = $this->field('googlePlayLink', Link::class, 'Google Play link', ['types' => ['url']]);

        $f['theme'] = $this->dropdown('theme', 'Theme', ['light' => 'Light', 'dark' => 'Dark']);
        $f['heroLayout'] = $this->dropdown('heroLayout', 'Hero layout', [
            'split' => 'Split (text + media)', 'centered' => 'Centered', 'fullBleed' => 'Full-bleed image', 'simple' => 'Simple heading',
        ]);
        $f['cardLayout'] = $this->dropdown('cardLayout', 'Card layout', [
            'grid' => 'Grid', 'carousel' => 'Carousel', 'bento' => 'Bento',
        ]);
        $f['featureLayout'] = $this->dropdown('featureLayout', 'Feature layout', [
            'alternating' => 'Alternating rows', 'stickyPhone' => 'Sticky phone',
        ]);
        $f['galleryLayout'] = $this->dropdown('galleryLayout', 'Gallery layout', [
            'grid' => 'Grid', 'marquee' => 'Marquee', 'fan' => 'Fan', 'row' => 'Row',
        ]);
        $f['testimonialLayout'] = $this->dropdown('testimonialLayout', 'Testimonial layout', [
            'carousel' => 'Carousel', 'quotes' => 'Quotes', 'reviews' => 'Review cards',
        ]);
        $f['mediaPosition'] = $this->dropdown('mediaPosition', 'Media position', ['right' => 'Right', 'left' => 'Left']);
        $f['employmentType'] = $this->dropdown('employmentType', 'Employment type', [
            'fullTime' => 'Full time', 'partTime' => 'Part time', 'contract' => 'Contract', 'internship' => 'Internship',
        ]);
        $f['workplace'] = $this->dropdown('workplace', 'Workplace', [
            'onSite' => 'On-site', 'hybrid' => 'Hybrid', 'remote' => 'Remote',
        ]);

        $f['rotatingWords'] = $this->field('rotatingWords', Table::class, 'Rotating words', [
            'instructions' => 'Optional words that cycle at the end of the heading.',
            'addRowLabel' => 'Add a word',
            'columns' => ['col1' => ['heading' => 'Word', 'handle' => 'word', 'type' => 'singleline']],
            'defaults' => [],
        ]);
        $comparisonColumns = [
            'col1' => ['heading' => 'Label', 'handle' => 'label', 'type' => 'singleline'],
            'col2' => ['heading' => 'Status', 'handle' => 'status', 'type' => 'select', 'options' => [
                ['label' => 'Yes', 'value' => 'yes', 'default' => true],
                ['label' => 'No', 'value' => 'no', 'default' => false],
                ['label' => 'Warning', 'value' => 'warning', 'default' => false],
            ]],
        ];
        $f['comparisonUs'] = $this->field('comparisonUs', Table::class, 'Comparison: us', [
            'columns' => $comparisonColumns, 'defaults' => [], 'addRowLabel' => 'Add row',
        ]);
        $f['comparisonThem'] = $this->field('comparisonThem', Table::class, 'Comparison: them', [
            'columns' => $comparisonColumns, 'defaults' => [], 'addRowLabel' => 'Add row',
        ]);

        $f['rating'] = $this->field('rating', Number::class, 'Rating', ['min' => 0, 'max' => 5, 'decimals' => 1]);
        $f['featured'] = $this->field('featured', Lightswitch::class, 'Featured');
        $f['showAppBadges'] = $this->field('showAppBadges', Lightswitch::class, 'Show app store badges');

        $f['blogCategories'] = $this->field('blogCategories', Categories::class, 'Blog categories', [
            'source' => 'group:' . $blogCatGroup->uid,
        ]);
        $f['department'] = $this->field('department', Categories::class, 'Department', [
            'source' => 'group:' . $deptGroup->uid, 'branchLimit' => 1,
        ]);

        $f['seo'] = $this->field('seo', SeoField::class, 'SEO');
        $f['newsletterForm'] = $this->field('newsletterForm', FormField::class, 'Newsletter form');

        // ── Nested entry types + their Matrix fields ──────────────────────────
        $card = $this->blockType('card', 'Card', [
            $f['image'], $f['emoji'], $f['heading'], $f['text'], $f['primaryButton'],
        ], '{heading}');
        $testimonial = $this->blockType('testimonial', 'Testimonial', [
            $f['text'], $f['heading'], $f['personName'], $f['personDetail'], $f['image'], $f['rating'],
        ], '{personName}');
        $stat = $this->blockType('stat', 'Stat', [$f['heading'], $f['text'], $f['image']], '{heading}');
        $faqItem = $this->entryType('faqItem', 'FAQ item', [$f['body']], [
            'showSlugField' => false,
        ], 'Question');

        $f['cards'] = $this->matrix('cards', 'Cards', [$card], 'Add card');
        $f['testimonials'] = $this->matrix('testimonials', 'Testimonials', [$testimonial, $stat], 'Add item');
        $f['faqItems'] = $this->matrix('faqItems', 'FAQ items', [$faqItem], 'Add question');

        // ── pageBuilder blocks ────────────────────────────────────────────────
        $blocks = [
            $this->blockType('hero', 'Hero', [
                $f['heroLayout'], $f['theme'], $f['eyebrow'], $f['heading'], $f['rotatingWords'], $f['text'],
                $f['primaryButton'], $f['secondaryButton'], $f['images'],
            ]),
            $this->blockType('cardGrid', 'Card grid', [
                $f['cardLayout'], $f['theme'], $f['eyebrow'], $f['heading'], $f['text'], $f['cards'], $f['primaryButton'],
            ]),
            $this->blockType('featureRows', 'Feature rows', [
                $f['featureLayout'], $f['theme'], $f['heading'], $f['cards'],
            ]),
            $this->blockType('imageGallery', 'Image gallery', [
                $f['galleryLayout'], $f['eyebrow'], $f['heading'], $f['text'], $f['images'], $f['primaryButton'],
            ]),
            $this->blockType('cityList', 'City list', [$f['heading'], $f['text'], $f['cards']]),
            $this->blockType('testimonials', 'Testimonials', [
                $f['testimonialLayout'], $f['eyebrow'], $f['heading'], $f['text'], $f['testimonials'],
            ]),
            $this->blockType('textMedia', 'Text + media', [
                $f['mediaPosition'], $f['heading'], $f['body'], $f['images'],
            ]),
            $this->blockType('comparison', 'Comparison', [
                $f['heading'], $f['text'], $f['image'], $f['comparisonUs'], $f['comparisonThem'], $f['body'],
            ]),
            $this->blockType('richText', 'Rich text', [$f['heading'], $f['body']]),
            $this->blockType('faq', 'FAQ', [$f['heading'], $f['faqItems']]),
            $this->blockType('ctaBanner', 'CTA banner', [
                $f['theme'], $f['heading'], $f['text'], $f['primaryButton'], $f['secondaryButton'], $f['images'], $f['showAppBadges'],
            ]),
            $this->blockType('promoBar', 'Promo bar', [$f['text'], $f['primaryButton']], '{text}'),
            $this->blockType('image', 'Image', [$f['image'], $f['text']], 'Image'),
            $this->blockType('jobList', 'Job list', [$f['heading'], $f['primaryButton']]),
            $this->blockType('pressList', 'Press list', [$f['heading']]),
        ];
        $f['pageBuilder'] = $this->matrix('pageBuilder', 'Page builder', $blocks, 'Add block', Matrix::VIEW_MODE_BLOCKS);

        // ── Page entry types ──────────────────────────────────────────────────
        $pageFields = [$f['pageBuilder'], $f['seo']];
        $homeType = $this->entryType('home', 'Home', $pageFields, ['showSlugField' => false]);
        $landingType = $this->entryType('landingPage', 'Landing page', $pageFields);
        $careersType = $this->entryType('careers', 'Careers', $pageFields, ['showSlugField' => false]);
        $pressType = $this->entryType('press', 'Press', $pageFields, ['showSlugField' => false]);
        $blogPostType = $this->entryType('blogPost', 'Blog post', [
            $f['image'], $f['text'], $f['blogCategories'], $f['featured'], $f['body'], $f['seo'],
        ]);
        $jobType = $this->entryType('job', 'Job', [
            $f['department'], $f['location'], $f['employmentType'], $f['workplace'], $f['externalLink'],
        ], [], 'Job title');
        $pressMentionType = $this->entryType('pressMention', 'Press mention', [
            $f['publication'], $f['externalLink'],
        ], [], 'Headline');

        // ── Sections ──────────────────────────────────────────────────────────
        $this->section('home', 'Home', Section::TYPE_SINGLE, [$homeType], '__home__', '_entries/page');
        $this->section('landingPages', 'Landing pages', Section::TYPE_STRUCTURE, [$landingType], '{slug}', '_entries/page', ['maxLevels' => 1]);
        $this->section('blog', 'Blog', Section::TYPE_CHANNEL, [$blogPostType], 'blog/{slug}', 'blog/_entry');
        $this->section('careers', 'Careers', Section::TYPE_SINGLE, [$careersType], 'careers', '_entries/page');
        $this->section('jobs', 'Jobs', Section::TYPE_CHANNEL, [$jobType], null, null);
        $this->section('press', 'Press', Section::TYPE_SINGLE, [$pressType], 'press', '_entries/page');
        $this->section('pressMentions', 'Press mentions', Section::TYPE_CHANNEL, [$pressMentionType], null, null);

        // ── Globals ───────────────────────────────────────────────────────────
        $this->globalSet('siteSettings', 'Site settings', [
            [$f['image'], ['label' => 'Logo']],
            [$f['primaryButton'], ['label' => 'Header primary button']],
            [$f['secondaryButton'], ['label' => 'Header secondary button']],
            $f['appStoreLink'],
            $f['googlePlayLink'],
        ]);
        $this->globalSet('newsletter', 'Newsletter', [
            [$f['image'], ['label' => 'Blog title image']],
            $f['heading'],
            $f['text'],
            $f['newsletterForm'],
        ]);

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261118_120000_content_model cannot be reverted automatically.\n";
        return false;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function fail(string $what, array $errors): never
    {
        throw new \RuntimeException("Could not save $what: " . json_encode($errors));
    }

    /** @param array<FieldInterface|CustomField|\craft\base\FieldLayoutElement|array{0:FieldInterface,1:array}> $elements */
    private function layout(array $elements, string $elementType): FieldLayout
    {
        $layout = new FieldLayout(['type' => $elementType]);
        $tabElements = array_map(function($el) {
            if ($el instanceof FieldInterface) {
                return new CustomField($el);
            }
            if (is_array($el)) {
                return new CustomField($el[0], $el[1]);
            }
            return $el;
        }, $elements);
        $layout->setTabs([new FieldLayoutTab(['name' => 'Content', 'layout' => $layout, 'elements' => $tabElements])]);
        return $layout;
    }

    private function field(string $handle, string $class, string $name, array $config = []): FieldInterface
    {
        $fields = Craft::$app->getFields();
        if ($existing = $fields->getFieldByHandle($handle)) {
            return $existing;
        }
        $field = new $class(array_merge(['name' => $name, 'handle' => $handle], $config));
        if (!$fields->saveField($field)) {
            $this->fail("field $handle", $field->getErrors());
        }
        return $field;
    }

    private function dropdown(string $handle, string $name, array $options): FieldInterface
    {
        $opts = [];
        $first = true;
        foreach ($options as $value => $label) {
            $opts[] = ['label' => $label, 'value' => $value, 'default' => $first];
            $first = false;
        }
        return $this->field($handle, Dropdown::class, $name, ['options' => $opts]);
    }

    private function matrix(string $handle, string $name, array $entryTypes, string $buttonLabel, string $viewMode = Matrix::VIEW_MODE_CARDS): FieldInterface
    {
        $fields = Craft::$app->getFields();
        if ($existing = $fields->getFieldByHandle($handle)) {
            return $existing;
        }
        $field = new Matrix(['name' => $name, 'handle' => $handle, 'viewMode' => $viewMode, 'createButtonLabel' => $buttonLabel]);
        $field->setEntryTypes($entryTypes);
        if (!$fields->saveField($field)) {
            $this->fail("matrix $handle", $field->getErrors());
        }
        return $field;
    }

    /** Entry type with a Title field (pages, FAQ items, jobs…) */
    private function entryType(string $handle, string $name, array $fields, array $config = [], ?string $titleLabel = null): EntryType
    {
        $entries = Craft::$app->getEntries();
        if ($existing = $entries->getEntryTypeByHandle($handle)) {
            return $existing;
        }
        $titleField = new EntryTitleField($titleLabel ? ['label' => $titleLabel] : []);
        $type = new EntryType(array_merge(['name' => $name, 'handle' => $handle], $config));
        $type->setFieldLayout($this->layout(array_merge([$titleField], $fields), Entry::class));
        if (!$entries->saveEntryType($type)) {
            $this->fail("entry type $handle", $type->getErrors());
        }
        return $type;
    }

    /** Entry type without a Title field (pageBuilder blocks, cards…) */
    private function blockType(string $handle, string $name, array $fields, string $labelFormat = '{heading}'): EntryType
    {
        $entries = Craft::$app->getEntries();
        if ($existing = $entries->getEntryTypeByHandle($handle)) {
            return $existing;
        }
        $type = new EntryType([
            'name' => $name,
            'handle' => $handle,
            'hasTitleField' => false,
            'showSlugField' => false,
            'uiLabelFormat' => $labelFormat,
        ]);
        $type->setFieldLayout($this->layout($fields, Entry::class));
        if (!$entries->saveEntryType($type)) {
            $this->fail("block type $handle", $type->getErrors());
        }
        return $type;
    }

    private function section(string $handle, string $name, string $type, array $entryTypes, ?string $uriFormat, ?string $template, array $config = []): Section
    {
        $entries = Craft::$app->getEntries();
        if ($existing = $entries->getSectionByHandle($handle)) {
            return $existing;
        }
        $section = new Section(array_merge(['name' => $name, 'handle' => $handle, 'type' => $type], $config));
        $section->setEntryTypes($entryTypes);
        $section->setSiteSettings([
            $this->siteId => new Section_SiteSettings([
                'siteId' => $this->siteId,
                'enabledByDefault' => true,
                'hasUrls' => $uriFormat !== null,
                'uriFormat' => $uriFormat,
                'template' => $template,
            ]),
        ]);
        if (!$entries->saveSection($section)) {
            $this->fail("section $handle", $section->getErrors());
        }
        return $section;
    }

    private function categoryGroup(string $handle, string $name): CategoryGroup
    {
        $categories = Craft::$app->getCategories();
        if ($existing = $categories->getGroupByHandle($handle)) {
            return $existing;
        }
        $group = new CategoryGroup(['name' => $name, 'handle' => $handle, 'maxLevels' => 1]);
        $group->setSiteSettings([
            $this->siteId => new CategoryGroup_SiteSettings(['siteId' => $this->siteId, 'hasUrls' => false]),
        ]);
        $group->setFieldLayout($this->layout([], Category::class));
        if (!$categories->saveGroup($group)) {
            $this->fail("category group $handle", $group->getErrors());
        }
        return $group;
    }

    private function volume(string $handle, string $name, string $path, string $url): Volume
    {
        $volumes = Craft::$app->getVolumes();
        if ($existing = $volumes->getVolumeByHandle($handle)) {
            return $existing;
        }
        $fsService = Craft::$app->getFs();
        if (!$fsService->getFilesystemByHandle($handle)) {
            $fs = $fsService->createFilesystem([
                'type' => Local::class,
                'name' => $name,
                'handle' => $handle,
                'path' => $path,
                'hasUrls' => true,
                'url' => $url,
            ]);
            if (!$fsService->saveFilesystem($fs)) {
                $this->fail("filesystem $handle", $fs->getErrors());
            }
        }
        $volume = new Volume(['name' => $name, 'handle' => $handle]);
        $volume->setFsHandle($handle);
        $volume->setFieldLayout($this->layout([new AltField()], Asset::class));
        if (!$volumes->saveVolume($volume)) {
            $this->fail("volume $handle", $volume->getErrors());
        }
        return $volume;
    }

    private function globalSet(string $handle, string $name, array $fields): GlobalSet
    {
        $globals = Craft::$app->getGlobals();
        if ($existing = $globals->getSetByHandle($handle)) {
            return $existing;
        }
        $set = new GlobalSet(['name' => $name, 'handle' => $handle]);
        $set->setFieldLayout($this->layout($fields, GlobalSet::class));
        if (!$globals->saveSet($set)) {
            $this->fail("global set $handle", $set->getErrors());
        }
        return $set;
    }
}
