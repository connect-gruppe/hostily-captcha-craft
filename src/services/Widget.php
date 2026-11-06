<?php

namespace hostily\craftcaptcha\services;

use craft\base\Component;
use craft\helpers\Html;
use hostily\craftcaptcha\models\Settings;
use hostily\craftcaptcha\Plugin;
use Twig\Markup;

/**
 * Das Widget (<hostily-captcha>) fuer Formulare.
 */
class Widget extends Component
{
    /** Das Widget-Skript wird je Anfrage nur einmal eingebunden. */
    private bool $scriptWritten = false;

    /**
     * Attribute des Elements <hostily-captcha> (leer, wenn das Plugin nicht eingerichtet ist).
     *
     * @return array<string, string>
     */
    public function attributes(array $options = []): array
    {
        $plugin = Plugin::getInstance();
        if (!$plugin->verifier->isConfigured()) {
            return [];
        }
        /** @var Settings $s */
        $s = $plugin->getSettings();
        // Leerer oder unbekannter (alter) Wert: sichtbar; 'portal' (Wie im Kundenportal): kein Attribut
        $mode = $options['mode'] ?? $s->mode;
        $mode = in_array($mode, Settings::MODES, true) ? $mode : 'sichtbar';
        $attrs = [
            'data-site-key' => $s->getSiteKey(),
            'data-api' => $s->getServiceUrl() . '/api/',
        ];
        if ($mode !== 'portal') {
            $attrs['data-modus'] = $mode;
        }
        $hold = (int)($options['holdTime'] ?? $s->holdTime);
        if ($mode === 'halten' && $hold > 0) {
            $attrs['data-haltezeit'] = max(800, min(3000, $hold));
        }
        $lang = $plugin->verifier->language($options['language'] ?? null);
        if ($lang !== '') {
            $attrs['data-sprache'] = $lang;
        }
        return $attrs;
    }

    public function scriptUrl(): string
    {
        return Plugin::getInstance()->getSettings()->getServiceUrl() . '/widget/hostily-captcha.js';
    }

    /**
     * <script>-Tag des Widgets; leer, wenn nicht eingerichtet oder schon geschrieben.
     */
    public function script(): Markup
    {
        if ($this->scriptWritten || !Plugin::getInstance()->verifier->isConfigured()) {
            return new Markup('', 'UTF-8');
        }
        $this->scriptWritten = true;
        return new Markup(Html::tag('script', '', ['src' => $this->scriptUrl(), 'defer' => true]), 'UTF-8');
    }

    /**
     * Widget-Element (mit Skript beim ersten Aufruf). Optionen: mode (portal, sichtbar, unsichtbar, halten, schieben), holdTime (ms), language, script (false = Skript nicht schreiben).
     */
    public function render(array $options = []): Markup
    {
        $attrs = $this->attributes($options);
        if ($attrs === []) {
            // Nicht eingerichtet: nichts anzeigen, nichts sperren.
            return new Markup('', 'UTF-8');
        }
        $html = Html::tag('hostily-captcha', '', $attrs);
        if (($options['script'] ?? true) !== false) {
            $html = $this->script() . $html;
        }
        return new Markup($html, 'UTF-8');
    }
}
