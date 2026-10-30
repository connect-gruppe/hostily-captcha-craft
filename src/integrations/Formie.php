<?php

namespace hostily\craftcaptcha\integrations;

use Craft;
use craft\base\Event;
use hostily\craftcaptcha\Plugin;

/**
 * Meldet hostily Captcha bei Formie (Craft 4: Formie 2/3, Craft 5: Formie 3/4) als Captcha an, wenn Formie installiert ist.
 */
class Formie
{
    public static function register(): void
    {
        if (!class_exists(\verbb\formie\Formie::class) || !class_exists(\verbb\formie\services\Integrations::class)) {
            return;
        }
        Event::on(
            \verbb\formie\services\Integrations::class,
            \verbb\formie\services\Integrations::EVENT_REGISTER_INTEGRATIONS,
            static function($event) {
                $event->captchas[] = FormieCaptcha::class;
            }
        );
    }
}
