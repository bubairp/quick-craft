<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use remoteprogrammer\simplerpmenu\models\SimpleRpMenuItemsModel;
use remoteprogrammer\simplerpmenu\SimpleRpMenu;
use yii\caching\TagDependency;

/**
 * Header mega-menu dropdowns (reference: partiful.com — no Figma design exists).
 *
 * Structure in Simple RP Menu `headerMenu` (rendered by templates/_partials/header.twig):
 *   level 1  nav item (e.g. "Invites")         → becomes a dropdown button when it has children
 *   level 2  column heading, noLink            → e.g. "Occasions"
 *   level 3  link                              → data_json "icon: 🎂" adds an icon
 *   level 2  class "menu-featured"             → featured card: name = title,
 *            customShortContent = description, data_json "label: …" / "image: /path.png"
 *
 * Copy is Quick Craft's own; URLs point at existing entries or PLACEHOLDER paths.
 * Idempotent: a nav item that already has children is skipped.
 */
class m261118_210000_header_dropdowns extends Migration
{
    private int $siteId;

    public function safeUp(): bool
    {
        $this->siteId = Craft::$app->getSites()->getPrimarySite()->id;
        $plugin = SimpleRpMenu::$plugin;
        $menu = $plugin->simplerpmenu->getMenuByHandle('headerMenu');
        if (!$menu) {
            echo "    > headerMenu not found — skipping.\n";
            return true;
        }

        $page = fn(string $slug) => Entry::find()->section('landingPages')->slug($slug)->one();
        $single = fn(string $section) => Entry::find()->section($section)->one();
        $entry = fn(?Entry $e, string $fallback) => $e ? ['entry_id' => $e->id] : ['custom_url' => $fallback];
        $url = fn(string $u, bool $external = false) => ['custom_url' => $u] + ($external ? ['target' => '_blank'] : []);
        $settings = Craft::$app->getGlobals()->getSetByHandle('siteSettings');
        $appStore = $settings?->appStoreLink?->url ?: 'https://apps.apple.com/';
        $googlePlay = $settings?->googlePlayLink?->url ?: 'https://play.google.com/store/apps';

        $dropdowns = [
            'Invites' => [
                'columns' => [
                    'Occasions' => [
                        ['Birthdays', $entry($page('birthday-invitations'), '/invites/birthdays'), '🎂'],
                        ['Dinners', $url('/invites/dinners'), '🍝'],
                        ['Housewarmings', $url('/invites/housewarmings'), '🏡'],
                        ['Holidays', $url('/invites/holidays'), '🎄'],
                        ['Game Day', $url('/invites/game-day'), '🏈'],
                        ['Friendsgiving', $url('/invites/friendsgiving'), '🦃'],
                        ['Weddings', $url('/invites/weddings'), '💍'],
                        ['Bach Parties', $url('/invites/bach-parties'), '🥂'],
                        ['New Year’s Eve', $url('/invites/new-years-eve'), '🎆'],
                    ],
                ],
                'featured' => ['Make it a thing', 'Create an invite, collect RSVPs and keep everyone in the loop.', 'Create', $entry($page('invitation-maker'), '/create'), '/uploads/images/placeholders/menu-invites.png'],
            ],
            'Explore' => [
                'columns' => [
                    'Explore' => [
                        ['All events', $url('/explore'), '✨'],
                        ['This weekend', $url('/explore/this-weekend'), '📅'],
                        ['Free events', $url('/explore/free'), '🎟️'],
                    ],
                    'Cities' => [
                        ['New York City', $url('/explore/new-york'), '🗽'],
                        ['Los Angeles', $url('/explore/los-angeles'), '🌴'],
                        ['San Francisco', $url('/explore/san-francisco'), '🌉'],
                        ['Chicago', $url('/explore/chicago'), '🌆'],
                    ],
                ],
                'featured' => ['Find your next plan', 'Discover the best events near you and see who else is going.', 'Explore', $url('/explore'), ''],
            ],
            'Products' => [
                'columns' => [
                    'Products' => [
                        ['Invitations', $entry($page('invitation-maker'), '/invitations'), '💌'],
                        ['Explore Events', $url('/explore'), '🧭'],
                        ['Sell Tickets', $entry($page('sell-tickets'), '/sell-tickets'), '🎫'],
                        ['Blog', $url('/blog'), '📰'],
                    ],
                    'Downloads' => [
                        ['App on iOS', $url($appStore, true), '📱'],
                        ['App on Android', $url($googlePlay, true), '🤖'],
                    ],
                ],
                'featured' => ['For a more social life', 'Invites, discovery and more — Quick Craft makes it easy to get together.', 'Products', $entry($page('invitation-maker'), '/invitations'), ''],
            ],
            'For Organizers' => [
                'columns' => [
                    'For Organizers' => [
                        ['Quick Craft for Organizers', $entry($page('organizers'), '/organizers'), '📣'],
                        ['Sell Tickets', $entry($page('sell-tickets'), '/sell-tickets'), '🎫'],
                        ['Get Started', $url('/create'), '🚀'],
                    ],
                ],
                'featured' => ['Grow your community', 'Sell tickets, reach new audiences and manage it all in one place.', 'Organizers', $entry($page('organizers'), '/organizers'), ''],
            ],
            'Company' => [
                'columns' => [
                    'Company' => [
                        ['About', $url('/about'), null],
                        ['Press', $entry($single('press'), '/press'), null],
                        ['Careers', $entry($single('careers'), '/careers'), null],
                    ],
                    'Support' => [
                        ['Help Center', $url('/help'), null],
                        ['Contact', $url('/contact'), null],
                    ],
                    'Legal' => [
                        ['Terms & Conditions', $url('/terms'), null],
                        ['Privacy Policy', $url('/privacy'), null],
                        ['Cookie Policy', $url('/cookies'), null],
                        ['Community Guidelines', $url('/community-guidelines'), null],
                    ],
                    'Social' => [
                        ['Instagram', $url('https://www.instagram.com/', true), null],
                        ['X/Twitter', $url('https://x.com/', true), null],
                        ['TikTok', $url('https://www.tiktok.com/', true), null],
                        ['LinkedIn', $url('https://www.linkedin.com/', true), null],
                        ['Substack', $url('https://substack.com/', true), null],
                    ],
                ],
                'featured' => ['Meet the team', 'Get to know the people and ideas behind Quick Craft.', 'Company', $entry($single('careers'), '/careers'), ''],
            ],
        ];

        $top = $plugin->simplerpmenuItems->getMenuItems($menu->id);
        foreach ($top as $navItem) {
            $config = $dropdowns[$navItem['name']] ?? null;
            if (!$config || !empty($navItem['children'])) {
                continue;
            }
            $order = 0;
            foreach ($config['columns'] as $heading => $links) {
                $columnId = $this->save($menu->id, (int)$navItem['id'], ++$order, $heading, ['noLink' => true]);
                foreach ($links as $i => [$label, $link, $icon]) {
                    $this->save($menu->id, $columnId, $i + 1, $label, $link + ($icon ? ['data_json' => "icon: $icon"] : []));
                }
            }
            [$title, $description, $label, $link, $image] = $config['featured'];
            $this->save($menu->id, (int)$navItem['id'], ++$order, $title, $link + [
                'class' => 'menu-featured',
                'customShortContent' => $description,
                'data_json' => "label: $label" . ($image ? "\nimage: $image" : ''),
            ]);
        }

        TagDependency::invalidate(Craft::$app->getCache(), ['simple-rp-menu']);
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }

    private function save(int $menuId, int $parentId, int $order, string $name, array $values): int
    {
        $id = SimpleRpMenu::$plugin->simplerpmenuItems->saveMenuItem(new SimpleRpMenuItemsModel(array_merge([
            'menu_id' => $menuId,
            'parent_id' => $parentId,
            'item_order' => $order,
            'name' => $name,
            'noLink' => false,
            'customShortContent' => '',
            'isMegaMenu' => false,
        ], $values)));
        if (!$id) {
            throw new \RuntimeException("Could not save menu item $name");
        }
        return (int)$id;
    }
}
