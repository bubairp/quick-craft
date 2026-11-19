<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fields\Lightswitch;

/**
 * Two Lightswitch base fields (the user's request): `lightswitch` → `lightswitchOff`
 * ("Lightswitch (default off)"; its instances featured/showAppBadges keep their handles), and a new
 * `lightswitchOn` ("Lightswitch (default on)").
 */
class m261120_140000_lightswitch_on_off extends Migration
{
    public function safeUp(): bool
    {
        $f = Craft::$app->getFields();

        $off = $f->getFieldByHandle('lightswitch');
        if ($off instanceof Lightswitch) {
            $off->handle = 'lightswitchOff';
            $off->name = 'Lightswitch (default off)';
            $off->default = false;
            if (!$f->saveField($off)) {
                throw new \RuntimeException('lightswitchOff: ' . json_encode($off->getErrors()));
            }
            echo "    > lightswitch → lightswitchOff\n";
        }

        if (!$f->getFieldByHandle('lightswitchOn')) {
            $on = new Lightswitch([
                'name' => 'Lightswitch (default on)',
                'handle' => 'lightswitchOn',
                'default' => true,
                'uid' => $this->stableUid('field:lightswitchOn'),
            ]);
            if (!$f->saveField($on)) {
                throw new \RuntimeException('lightswitchOn: ' . json_encode($on->getErrors()));
            }
            echo "    > lightswitchOn created\n";
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
