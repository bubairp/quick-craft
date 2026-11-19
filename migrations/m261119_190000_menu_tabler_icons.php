<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use yii\caching\TagDependency;

/**
 * Header mega menu: the link icons switch from emoji to Tabler icon names (the site now uses
 * Tabler icons everywhere: templates/_components/icon.twig, package @tabler/icons).
 * Simple RP Menu keeps them in simplerpmenu_items.data_json as "icon: …" lines; editors can
 * use any name from https://tabler.io/icons there.
 */
class m261119_190000_menu_tabler_icons extends Migration
{
    private const MAP = [
        '🎂' => 'cake',
        '🍝' => 'tools-kitchen-2',
        '🏡' => 'home-heart',
        '🎄' => 'christmas-tree',
        '🏈' => 'ball-american-football',
        '🦃' => 'leaf',
        '💍' => 'diamond',
        '🥂' => 'glass-champagne',
        '🎆' => 'confetti',
        '✨' => 'sparkles',
        '📅' => 'calendar-event',
        '🎟️' => 'ticket',
        '🎟' => 'ticket',
        '🗽' => 'building-skyscraper',
        '🌴' => 'beach',
        '🌉' => 'building-bridge',
        '🌆' => 'buildings',
        '💌' => 'mail-heart',
        '🧭' => 'compass',
        '🎫' => 'ticket',
        '📰' => 'news',
        '📱' => 'brand-apple',
        '🤖' => 'brand-android',
        '📣' => 'speakerphone',
        '🚀' => 'rocket',
    ];

    public function safeUp(): bool
    {
        $rows = (new Query())
            ->select(['id', 'data_json'])
            ->from('{{%simplerpmenu_items}}')
            ->where(['like', 'data_json', 'icon:'])
            ->all();

        $changed = 0;
        foreach ($rows as $row) {
            $lines = preg_split('/\r?\n/', (string)$row['data_json']);
            foreach ($lines as &$line) {
                if (preg_match('/^\s*icon:\s*(.+?)\s*$/u', $line, $m) && isset(self::MAP[$m[1]])) {
                    $line = 'icon: ' . self::MAP[$m[1]];
                }
            }
            unset($line);
            $new = implode("\n", $lines);
            if ($new !== $row['data_json']) {
                $this->update('{{%simplerpmenu_items}}', ['data_json' => $new], ['id' => $row['id']]);
                $changed++;
            }
        }
        echo "    > $changed menu items now use Tabler icons.\n";
        TagDependency::invalidate(Craft::$app->getCache(), 'simple-rp-menu');
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
