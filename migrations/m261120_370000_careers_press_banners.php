<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Asset;
use craft\elements\Entry;

/**
 * Banners for Careers (Figma 6-6336, fullBleed) and Press (Figma 18-6155, simple);
 * CLAUDE.md › Page tabs › banner layouts. Content only: the fields already exist.
 * PLACEHOLDER: the Careers photo (careers-banner.png) comes from the imported reference design.
 */
class m261120_370000_careers_press_banners extends Migration
{
    private const BANNER_ALT = 'A large group of people smiling and posing together, dressed in various styles, in a lively indoor setting.';

    public function safeUp(): bool
    {
        $elements = Craft::$app->getElements();

        $careers = Entry::find()->section('pages')->slug('careers')->status(null)->one();
        if ($careers && !$careers->heading) {
            $values = ['heroLayout' => 'fullBleed', 'scheme' => 'dark', 'heading' => 'Join the party', 'text' => 'Make fun happen. Have fun doing it.'];
            $asset = Asset::find()->filename('careers-banner.png')->one();
            if ($asset) {
                if (!$asset->alt) {
                    $asset->alt = self::BANNER_ALT;
                    $this->check($elements->saveElement($asset), $asset, 'careers-banner.png');
                }
                $values['images'] = [$asset->id];
            } else {
                echo "    > careers-banner.png not in the images volume — Careers banner without photo\n";
            }
            $careers->setFieldValues($values);
            $this->check($elements->saveElement($careers), $careers, 'Careers');
            echo "    > Careers: banner (fullBleed)\n";
        }

        $press = Entry::find()->section('pages')->slug('press')->status(null)->one();
        if ($press && !$press->heading) {
            $press->setFieldValues(['heroLayout' => 'simple', 'scheme' => 'white', 'heading' => 'Press']);
            $this->check($elements->saveElement($press), $press, 'Press');
            echo "    > Press: banner (simple)\n";
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
