<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\User;

/**
 * Blog listing (Figma 18-4865; CLAUDE.md › Blog listing). Content only (the fields exist):
 *  - Blog categories Party 101 / Culture / IMHO ("Latest" is the unfiltered list, not a category).
 *  - The first 6 posts from the Figma grid (images blog-post-1…6 when in the images volume), by the
 *    first admin user, one or two days apart so the design's order holds.
 *  - The blog wordmark (blog-title.png) as the newsletter Single's image.
 * PLACEHOLDER: titles, summaries and images come from the imported reference design.
 */
class m261120_380000_blog_listing extends Migration
{
    private const CATEGORIES = ['Party 101', 'Culture', 'IMHO'];

    /** [title, summary, image, category] — newest first */
    private const POSTS = [
        ['1000 People Did the World’s Largest FitnessGram Pacer Test in NYC', 'Around 1,000 runners gathered in Sheep Meadow for a tightly run, very nostalgic Pacer Test attempt.', 'blog-post-1.jpg', 'Culture'],
        ['Introducing Ticketing on Quick Craft', 'You can now sell tickets on the social platform where people already make plans.', 'blog-post-2.jpg', 'Party 101'],
        ['Your Year in Parties: The Quick Craft 2025 After Party Recap', 'A recap of the nights out, the people, and the plans you actually made.', 'blog-post-3.jpg', 'Culture'],
        ['Introducing Cards on Quick Craft', 'Our newest feature, built for the moments when a text won’t cut it.', 'blog-post-4.png', 'Party 101'],
        ['Parti Recap: BOUNCE has its 4th Pop-up Ping Pong Party at Jean’s', 'Because it seems as though everyone misses a house party.', 'blog-post-5.png', 'Culture'],
        ['Hot Literati: a Book Club for Hot, Cool, Well-Read People in NYC and Beyond', 'A convo with founder Hailey to talk about train rides, TikTok, and the philosophy behind “flirty fall.”', 'blog-post-6.png', 'IMHO'],
    ];

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $elements = Craft::$app->getElements();

        // 1. Categories
        $catSection = $entries->getSectionByHandle('blogCategories');
        $catIds = [];
        foreach (self::CATEGORIES as $title) {
            $cat = Entry::find()->section('blogCategories')->title($title)->status(null)->one();
            if (!$cat) {
                $cat = new Entry(['sectionId' => $catSection->id, 'typeId' => $catSection->getEntryTypes()[0]->id, 'title' => $title]);
                $this->check($elements->saveElement($cat), $cat, "category $title");
            }
            $catIds[$title] = $cat->id;
        }

        // 2. Posts (only when the blog is empty)
        $blog = $entries->getSectionByHandle('blog');
        if (!Entry::find()->section('blog')->status(null)->exists()) {
            $author = User::find()->admin()->status(null)->orderBy('id asc')->one();
            $date = new \DateTime('2026-11-15 09:00');
            foreach (self::POSTS as [$title, $summary, $filename, $category]) {
                $asset = Asset::find()->filename($filename)->one();
                if ($asset && !$asset->alt) {
                    $asset->alt = $title;
                    $this->check($elements->saveElement($asset), $asset, $filename);
                }
                $post = new Entry([
                    'sectionId' => $blog->id, 'typeId' => $blog->getEntryTypes()[0]->id,
                    'title' => $title, 'postDate' => clone $date,
                ]);
                if ($author) {
                    $post->setAuthorIds([$author->id]);
                }
                $post->setFieldValues([
                    'text' => $summary,
                    'blogCategories' => [$catIds[$category]],
                    'body' => '<p>' . htmlspecialchars($summary) . '</p>',
                ] + ($asset ? ['image' => [$asset->id]] : []));
                $this->check($elements->saveElement($post), $post, "post $title");
                $date->modify('-2 days');
            }
            echo '    > ' . count(self::POSTS) . " blog posts, " . count(self::CATEGORIES) . " categories\n";
        }

        // 3. Blog wordmark on the newsletter Single
        $newsletter = Entry::find()->section('newsletter')->status(null)->one();
        $title = Asset::find()->filename('blog-title.png')->one();
        if ($newsletter && $title && !$newsletter->image->exists()) {
            if (!$title->alt) {
                $title->alt = 'The Guest List';
                $this->check($elements->saveElement($title), $title, 'blog-title.png');
            }
            $newsletter->setFieldValue('image', [$title->id]);
            $this->check($elements->saveElement($newsletter), $newsletter, 'newsletter');
            echo "    > newsletter: blog wordmark\n";
        }

        return true;
    }

    private function check(bool $ok, $model, string $what): void
    {
        if (!$ok) {
            throw new \RuntimeException("$what: " . json_encode($model->getErrors()));
        }
    }

    public function safeDown(): bool
    {
        return false;
    }
}
