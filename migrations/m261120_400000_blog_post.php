<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;

/**
 * Blog post page (Figma 18-5645; CLAUDE.md › Blog post). Content only:
 * "Introducing Cards on Quick Craft" gets the full article from the Figma frame (headings, text,
 * lists, two images as `{asset:ID:url}` reference tags, links) and is marked Featured.
 * PLACEHOLDER: copy and images come from the imported reference design; the two links point at
 * existing pages until the real ones exist.
 */
class m261120_400000_blog_post extends Migration
{
    public function safeUp(): bool
    {
        $post = Entry::find()->section('blog')->title('Introducing Cards on Quick Craft')->status(null)->one();
        if (!$post || str_contains((string)$post->body, '<h2>')) {
            return true;
        }

        $img = function(string $filename, string $alt): string {
            $asset = Asset::find()->filename($filename)->one();
            if (!$asset) {
                return '';
            }
            if (!$asset->alt) {
                $asset->alt = $alt;
                Craft::$app->getElements()->saveElement($asset);
            }
            return '<figure class="image"><img src="{asset:' . $asset->id . ':url}" alt="' . htmlspecialchars($alt) . '" width="' . $asset->width . '" height="' . $asset->height . '"></figure>';
        };

        $body = implode('', [
            '<h2>Stay connected, even after the party ends.</h2>',
            '<h3>Takes two seconds, looks like you tried.</h3>',
            '<p>Some moments just can’t be handled with a text. Like when you realize it’s your friend’s birthday… at 11pm. Or when you need to confess to the group chat that you saw your ex last night… <i>again</i>. Or when someone got you the sweetest gift and “thanks!!” isn’t enough.</p>',
            '<p>Texting works— until it doesn’t. So we built Cards: free, easy, and full of great designs actually worth sending, that don’t look like they came from your grandmother.</p>',
            '<p>Faster than snail mail. So much better than a text.</p>',
            '<h3>Stay in touch. Debrief. Be a flirt.</h3>',
            $img('blog-cards-1.png', 'A phone showing a Quick Craft card with a photo of two friends and the message “Miss you bad!!!”'),
            '<p>You’re busy. Cards are easy. Personalize endlessly to make yours feel like <i>you</i>.</p>',
            '<ul><li>Pick from thousands of designs, or upload your own image</li><li>Make it pop with confetti, balloons, and other effects</li><li>Send with a link that works everywhere: text, email, or DMs</li></ul>',
            '<h3>Collab with friends</h3>',
            $img('blog-cards-2.png', 'Two Quick Craft cards signed by friends, with notes and profile photos.'),
            '<p>Group-chat behavior built in. ‘Cause it’s a lot more fun when your friends are there.</p>',
            '<ul><li>Invite your friends to be cosigners and leave comments together</li><li>Simply send to the recipient once everyone’s signed</li><li>Perfect for shared birthday wishes, get well notes, and of course, group roasts</li></ul>',
            '<h3>Whatever your occasion, there’s a Card for it. Trust.</h3>',
            '<p><a href="/invitation-maker">Send a card now</a></p>',
            '<h3>Want to know more?</h3>',
            '<p><a href="/invitation-maker#faq">Check out our Cards FAQ</a></p>',
        ]);

        $post->setFieldValues(['body' => $body, 'featured' => true]);
        if (!Craft::$app->getElements()->saveElement($post)) {
            throw new \RuntimeException('post: ' . json_encode($post->getErrors()));
        }
        echo "    > \"Introducing Cards on Quick Craft\": full article, featured\n";
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
