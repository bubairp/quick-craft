<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use remoteprogrammer\simplerpmenu\models\SimpleRpMenuItemsModel;
use remoteprogrammer\simplerpmenu\models\SimpleRpMenuModel;
use remoteprogrammer\simplerpmenu\SimpleRpMenu;
use yii\caching\TagDependency;

/**
 * Seeds placeholder content needed by the header and footer:
 *  - the four Landing page entries (so menus can link to real entries)
 *  - Simple RP Menu menus from Figma header (6-2902) and footer (6-2058)
 *  - Site settings global: header buttons + app store links
 *
 * Items marked PLACEHOLDER point at URLs for pages that don't exist yet.
 * Idempotent: existing entries/menus/values are left untouched.
 */
class m261118_140000_seed_menus_and_settings extends Migration
{
    private int $siteId;

    public function safeUp(): bool
    {
        $this->siteId = Craft::$app->getSites()->getPrimarySite()->id;

        // ── Landing page entries ──────────────────────────────────────────────
        $landing = [];
        foreach ([
            'organizers' => 'Organizers',
            'birthday-invitations' => 'Birthday Invitations',
            'invitation-maker' => 'Invitation Maker',
            'sell-tickets' => 'Sell Tickets',
        ] as $slug => $title) {
            $landing[$slug] = $this->landingPage($slug, $title);
        }
        $careers = Entry::find()->section('careers')->one();
        $press = Entry::find()->section('press')->one();

        $entry = fn(?Entry $e) => $e ? ['entry_id' => $e->id] : [];
        $url = fn(string $u, bool $external = false) => ['custom_url' => $u] + ($external ? ['target' => '_blank'] : []);

        // ── Menus ─────────────────────────────────────────────────────────────
        $this->menu('headerMenu', 'Header menu', [
            ['Invites', $entry($landing['invitation-maker'])],
            ['Explore', $url('/explore')],                       // PLACEHOLDER
            ['Products', $entry($landing['sell-tickets'])],
            ['For Organizers', $entry($landing['organizers'])],
            ['Company', $entry($careers)],
        ]);
        $this->menu('footerCompany', 'Footer: Company', [
            ['About', $url('/about')],                           // PLACEHOLDER
            ['Press', $entry($press)],
            ['Careers', $entry($careers)],
        ]);
        $this->menu('footerProduct', 'Footer: Product', [
            ['For Organizers', $entry($landing['organizers'])],
            ['Sell Tickets', $entry($landing['sell-tickets'])],
            ['Explore Events', $url('/explore')],                // PLACEHOLDER
            ['Blog', $url('/blog')],
            ['Download', $url('/download')],                     // PLACEHOLDER
        ]);
        $this->menu('footerSocials', 'Footer: Socials', [        // PLACEHOLDER profile URLs
            ['Instagram', $url('https://www.instagram.com/', true)],
            ['Twitter', $url('https://x.com/', true)],
            ['TikTok', $url('https://www.tiktok.com/', true)],
            ['LinkedIn', $url('https://www.linkedin.com/', true)],
            ['Substack', $url('https://substack.com/', true)],
        ]);
        $this->menu('footerLegal', 'Footer: Legal', [            // PLACEHOLDER pages
            ['Terms & Conditions', $url('/terms')],
            ['Privacy Policy', $url('/privacy')],
            ['Cookie Policy', $url('/cookies')],
            ['Community Guidelines', $url('/community-guidelines')],
        ]);
        $this->menu('footerSupport', 'Footer: Support', [        // PLACEHOLDER pages
            ['Contact', $url('/contact')],
            ['Help Center', $url('/help')],
        ]);
        TagDependency::invalidate(Craft::$app->getCache(), ['simple-rp-menu']);

        // ── Site settings global ─────────────────────────────────────────────
        $set = Craft::$app->getGlobals()->getSetByHandle('siteSettings');
        if ($set && !$set->getFieldValue('primaryButton')) {
            $maker = $landing['invitation-maker'];
            $set->setFieldValues([
                'primaryButton' => ['type' => 'entry', 'value' => "{entry:{$maker->id}@{$this->siteId}:url}", 'label' => 'Create an invite'],
                'secondaryButton' => ['type' => 'url', 'value' => '/login', 'label' => 'Log in'], // PLACEHOLDER
                'appStoreLink' => ['type' => 'url', 'value' => 'https://apps.apple.com/'],       // PLACEHOLDER
                'googlePlayLink' => ['type' => 'url', 'value' => 'https://play.google.com/store/apps'], // PLACEHOLDER
            ]);
            if (!Craft::$app->getElements()->saveElement($set)) {
                throw new \RuntimeException('Site settings: ' . json_encode($set->getErrors()));
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261118_140000_seed_menus_and_settings cannot be reverted automatically.\n";
        return false;
    }

    private function landingPage(string $slug, string $title): Entry
    {
        $existing = Entry::find()->section('landingPages')->slug($slug)->status(null)->one();
        if ($existing) {
            return $existing;
        }
        $section = Craft::$app->getEntries()->getSectionByHandle('landingPages');
        $entry = new Entry([
            'sectionId' => $section->id,
            'typeId' => $section->getEntryTypes()[0]->id,
            'siteId' => $this->siteId,
            'title' => $title,
            'slug' => $slug,
        ]);
        if (!Craft::$app->getElements()->saveElement($entry)) {
            throw new \RuntimeException("Entry $slug: " . json_encode($entry->getErrors()));
        }
        return $entry;
    }

    /** @param array<array{0:string,1:array}> $items name + [entry_id|custom_url, target] */
    private function menu(string $handle, string $name, array $items): void
    {
        $plugin = SimpleRpMenu::$plugin;
        if ($plugin->simplerpmenu->getMenuByHandle($handle)) {
            return;
        }
        $saved = $plugin->simplerpmenu->saveMenu(new SimpleRpMenuModel([
            'name' => $name,
            'handle' => $handle,
            'site_id' => $this->siteId,
        ]));
        $menu = $plugin->simplerpmenu->getMenuByHandle($handle);
        if (!$saved || !$menu) {
            throw new \RuntimeException("Could not save menu $handle");
        }
        foreach ($items as $i => [$label, $link]) {
            $id = $plugin->simplerpmenuItems->saveMenuItem(new SimpleRpMenuItemsModel(array_merge([
                'menu_id' => $menu->id,
                'parent_id' => 0,
                'item_order' => $i + 1,
                'name' => $label,
                'noLink' => false,
                'customShortContent' => '',
                'isMegaMenu' => false,
            ], $link)));
            if (!$id) {
                throw new \RuntimeException("Could not save menu item $label in $handle");
            }
        }
    }
}
