<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fields\Color;
use craft\helpers\Db;
use craft\helpers\StringHelper;

/**
 * Newsletter partial (Figma 18-5576; CLAUDE.md › Newsletter partial):
 *  - A Wheel Form "Newsletter" (one required email field + a hidden honeypot), saved to the
 *    database, no email sent (send_email off). Wheel Form forms are database records, not project config.
 *  - The `newsletter` Single: heading, note and form from Figma.
 *  - Live Preview target for `newsletter`: Blog (/blog).
 *  - Color Code palette: + danger (the new form-error token; palette = tokens).
 * PLACEHOLDER: the pitch email address in the note.
 */
class m261120_390000_newsletter extends Migration
{
    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();
        $fields = Craft::$app->getFields();

        // 1. Wheel Form
        $formId = (new \craft\db\Query())->from('{{%wheelform_forms}}')->where(['name' => 'Newsletter'])->scalar();
        if (!$formId) {
            $toEmail = (new \craft\db\Query())->from('{{%wheelform_forms}}')->select('to_email')->scalar() ?: 'user@example.com';
            $now = Db::prepareDateForDb(new \DateTime());
            $this->insert('{{%wheelform_forms}}', [
                'site_id' => Craft::$app->getSites()->getPrimarySite()->id,
                'name' => 'Newsletter', 'to_email' => $toEmail,
                'active' => 1, 'send_email' => 0, 'recaptcha' => 0, 'save_entry' => 1,
                'options' => json_encode(['honeypot' => 'website', 'honeypot_value' => '', 'submit_message' => 'Watch your inbox for the next issue.']),
                'dateCreated' => $now, 'dateUpdated' => $now, 'uid' => StringHelper::UUID(),
            ]);
            $formId = (int)$this->db->getLastInsertID();
            $this->insert('{{%wheelform_form_fields}}', [
                'form_id' => $formId, 'name' => 'email', 'type' => 'email', 'required' => 1, 'index_view' => 1,
                'order' => 1, 'active' => 1, 'options' => json_encode(['label' => 'Email address']),
                'dateCreated' => $now, 'dateUpdated' => $now, 'uid' => StringHelper::UUID(),
            ]);
            echo "    > Wheel Form \"Newsletter\" (#$formId)\n";
        }

        // 2. newsletter Single content
        $newsletter = Entry::find()->section('newsletter')->status(null)->one();
        if ($newsletter && !$newsletter->heading) {
            $newsletter->setFieldValues([
                'heading' => 'Get on the list.',
                'text' => '💡 Have an idea? Pitch us: theguestlist@quickcraft.com', // PLACEHOLDER address
                'newsletterForm' => (int)$formId,
            ]);
            if (!Craft::$app->getElements()->saveElement($newsletter)) {
                throw new \RuntimeException('newsletter: ' . json_encode($newsletter->getErrors()));
            }
            echo "    > newsletter: heading, note, form\n";
        }

        // 3. Live Preview target
        $section = $entries->getSectionByHandle('newsletter');
        if ($section && !$section->previewTargets) {
            $section->previewTargets = [['label' => 'Blog', 'urlFormat' => 'blog', 'refresh' => true]];
            if (!$entries->saveSection($section)) {
                throw new \RuntimeException('newsletter section: ' . json_encode($section->getErrors()));
            }
        }

        // 4. Palette: + danger
        $color = $fields->getFieldByHandle('colorcode');
        if ($color instanceof Color && !in_array('danger', array_column($color->palette, 'label'), true)) {
            $palette = $color->palette;
            $palette[] = ['color' => '#b42318', 'label' => 'danger', 'default' => false];
            $color->palette = $palette;
            if (!$fields->saveField($color)) {
                throw new \RuntimeException('colorcode: ' . json_encode($color->getErrors()));
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
