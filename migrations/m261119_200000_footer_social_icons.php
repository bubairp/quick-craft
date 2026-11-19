<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use yii\caching\TagDependency;

/**
 * Footer "Socials" menu: Tabler brand icons on each link (data_json "icon: …", rendered by
 * templates/_partials/footer.twig). Substack has no Tabler brand icon, so it uses "news".
 * Only fills items that have no data_json yet (editors' values are kept).
 */
class m261119_200000_footer_social_icons extends Migration
{
    private const ICONS = [
        'Instagram' => 'brand-instagram',
        'Twitter' => 'brand-x',
        'X' => 'brand-x',
        'TikTok' => 'brand-tiktok',
        'LinkedIn' => 'brand-linkedin',
        'Substack' => 'news',
        'Facebook' => 'brand-facebook',
        'YouTube' => 'brand-youtube',
        'Threads' => 'brand-threads',
        'Pinterest' => 'brand-pinterest',
    ];

    public function safeUp(): bool
    {
        $menuId = (new Query())->select('id')->from('{{%simplerpmenu}}')->where(['handle' => 'footerSocials'])->scalar();
        if (!$menuId) {
            echo "    > footerSocials menu not found — skipped.\n";
            return true;
        }
        $items = (new Query())->select(['id', 'name', 'data_json'])->from('{{%simplerpmenu_items}}')->where(['menu_id' => $menuId])->all();
        $n = 0;
        foreach ($items as $item) {
            $icon = self::ICONS[trim($item['name'])] ?? null;
            if ($icon && trim((string)$item['data_json']) === '') {
                $this->update('{{%simplerpmenu_items}}', ['data_json' => "icon: $icon"], ['id' => $item['id']]);
                $n++;
            }
        }
        echo "    > $n social links got an icon.\n";
        TagDependency::invalidate(Craft::$app->getCache(), 'simple-rp-menu');
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
