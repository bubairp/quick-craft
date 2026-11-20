<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * Every Wheel Form gets a success message (CLAUDE.md › Newsletter partial › "Every Wheel Form needs
 * a success message"). The plugin's own Success Message setting was never saved, so a successful
 * non-AJAX submit threw `setNotice(): null given` (500). The form's `submit_message` option wins
 * over the plugin setting, so each form without one gets a sensible default here.
 */
class m261120_391000_wheelform_submit_messages extends Migration
{
    private const DEFAULTS = [
        'Newsletter' => 'Watch your inbox for the next issue.',
    ];
    private const FALLBACK = 'Thanks! We’ll get back to you soon.';

    public function safeUp(): bool
    {
        $forms = (new Query())->from('{{%wheelform_forms}}')->select(['id', 'name', 'options'])->all();
        foreach ($forms as $form) {
            $options = json_decode((string)$form['options'], true) ?: [];
            if (!empty($options['submit_message'])) {
                continue;
            }
            $options['submit_message'] = self::DEFAULTS[$form['name']] ?? self::FALLBACK;
            $this->update('{{%wheelform_forms}}', ['options' => json_encode($options)], ['id' => $form['id']]);
            echo "    > {$form['name']}: submit_message set\n";
        }
        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
