<?php

namespace hostily\craftcaptcha\twig;

use hostily\craftcaptcha\Plugin;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig: {{ hostilyCaptcha() }} setzt das Widget ins Formular, {{ hostilyCaptchaVerify() }} prueft die Einsendung.
 */
class Extension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('hostilyCaptcha', [Plugin::getInstance()->widget, 'render'], ['is_safe' => ['html']]),
            new TwigFunction('hostilyCaptchaScript', [Plugin::getInstance()->widget, 'script'], ['is_safe' => ['html']]),
            new TwigFunction('hostilyCaptchaVerify', [$this, 'verify']),
            new TwigFunction('hostilyCaptchaError', [$this, 'error']),
        ];
    }

    /**
     * true, wenn die Einsendung angenommen ist (oder das Plugin nicht eingerichtet ist / der Dienst ausfaellt und
     * "durchlassen" gilt); false, wenn sie abgelehnt ist.
     */
    public function verify(): bool
    {
        return Plugin::getInstance()->verifier->verifyRequest()['status'] === 'ok';
    }

    /**
     * Meldung (in der Sprache der Seite) zu einer abgelehnten Einsendung; leer, wenn sie angenommen ist.
     */
    public function error(): string
    {
        $verifier = Plugin::getInstance()->verifier;
        $result = $verifier->verifyRequest();
        return $result['status'] === 'ok' ? '' : $verifier->messageFor($result);
    }
}
