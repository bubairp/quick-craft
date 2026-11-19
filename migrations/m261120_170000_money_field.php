<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fields\Money;

/**
 * Base field `money` "Money" (USD), the user's request (CLAUDE.md › Base field library).
 */
class m261120_170000_money_field extends Migration
{
    public function safeUp(): bool
    {
        $f = Craft::$app->getFields();
        if (!$f->getFieldByHandle('money')) {
            $field = new Money([
                'name' => 'Money',
                'handle' => 'money',
                'currency' => 'USD',
                'showCurrency' => true,
                'uid' => $this->stableUid('field:money'),
            ]);
            if (!$f->saveField($field)) {
                throw new \RuntimeException('money: ' . json_encode($field->getErrors()));
            }
            echo "    > money created\n";
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
