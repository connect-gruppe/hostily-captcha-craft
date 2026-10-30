<?php

namespace hostily\craftcaptcha\integrations;

use craft\base\Event;

/**
 * Meldet hostily Captcha bei Freeform 5 (Craft 4 und 5) als Captcha-Integration an, wenn Freeform installiert ist.
 */
class Freeform
{
    public static function register(): void
    {
        if (!class_exists(\Solspace\Freeform\Services\Integrations\IntegrationsService::class)
            || !interface_exists(\Solspace\Freeform\Library\Integrations\Types\Captchas\CaptchaIntegrationInterface::class)) {
            return;
        }
        Event::on(
            \Solspace\Freeform\Services\Integrations\IntegrationsService::class,
            \Solspace\Freeform\Services\Integrations\IntegrationsService::EVENT_REGISTER_INTEGRATION_TYPES,
            static function($event) {
                $event->addType(FreeformCaptcha::class);
            }
        );
    }
}
