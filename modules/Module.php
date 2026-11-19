<?php

namespace modules;

use Craft;
use craft\events\ElementEvent;
use craft\services\Elements;
use yii\base\Event;

/**
 * Quick Craft module. Registered in config/app.php (modules + bootstrap); autoloaded via
 * composer.json ("modules\\": "modules/"). Keep it small: one class per feature.
 *
 *  - PagePresets: copies a page preset's blocks into a new page (CLAUDE.md › Page presets).
 */
class Module extends \yii\base\Module
{
    public function init(): void
    {
        Craft::setAlias('@modules', __DIR__);
        parent::init();

        Event::on(Elements::class, Elements::EVENT_AFTER_SAVE_ELEMENT, function(ElementEvent $event) {
            PagePresets::apply($event->element);
        });
    }
}
