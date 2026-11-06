<?php

namespace hostily\craftcaptcha\models;

use craft\base\Model;
use craft\helpers\App;

/**
 * Einstellungen des Plugins. Site-Key, Secret und Dienst-URL duerfen Umgebungsvariablen sein
 * (z. B. "$HOSTILY_CAPTCHA_SECRET"), Werte lassen sich auch in config/hostily-captcha.php festlegen.
 */
class Settings extends Model
{
    public const DEFAULT_URL = 'https://captcha.hostily.de';

    /** Site-Key aus dem Projekt im Dienst (oeffentlich). */
    public string $siteKey = '';

    /** Secret aus dem Projekt; bleibt auf dem Server. */
    public string $secret = '';

    /** Adresse des Dienstes. */
    public string $serviceUrl = self::DEFAULT_URL;

    /** Erlaubte Modi; 'portal' = Wie im Kundenportal (das Attribut data-modus fehlt). */
    public const MODES = ['portal', 'sichtbar', 'unsichtbar', 'halten', 'schieben'];

    /** Modus des Widgets, siehe MODES. Ein alter gespeicherter Wert bleibt; neu: 'sichtbar' (Kaestchen). */
    public string $mode = 'sichtbar';

    /** Haltezeit in ms (800 bis 3000), nur fuer den Modus 'halten'. */
    public int $holdTime = 1500;

    /** 'auto' (Sprache der Seite), 'de' oder 'en'. */
    public string $language = 'auto';

    /** Bei Ausfall des Dienstes: 'allow' (durchlassen, Vorgabe) oder 'block'. */
    public string $onError = 'allow';

    /** Registrierung (users/save-user, nur fuer Besucher ohne Anmeldung) pruefen. */
    public bool $protectRegistration = false;

    /** "Passwort vergessen" (users/send-password-reset-email) pruefen. */
    public bool $protectPasswordReset = false;

    /** Anmeldung im Frontend (users/login) pruefen. */
    public bool $protectLogin = false;

    /** Weitere Controller-Aktionen, eine je Zeile (z. B. "contact-form/send"); POST-Anfragen der Website werden geprueft. */
    public string $protectedActions = '';

    public function defineRules(): array
    {
        return [
            [['siteKey', 'secret', 'serviceUrl', 'protectedActions'], 'string'],
            [['mode'], 'in', 'range' => self::MODES],
            [['holdTime'], 'integer', 'min' => 800, 'max' => 3000],
            [['language'], 'in', 'range' => ['auto', 'de', 'en']],
            [['onError'], 'in', 'range' => ['allow', 'block']],
            [['protectRegistration', 'protectPasswordReset', 'protectLogin'], 'boolean'],
        ];
    }

    public function getSiteKey(): string
    {
        return trim((string)App::parseEnv($this->siteKey));
    }

    public function getSecret(): string
    {
        return trim((string)App::parseEnv($this->secret));
    }

    /**
     * Dienst-Adresse ohne Schraegstrich am Ende; nur http(s), sonst leer.
     */
    public function getServiceUrl(): string
    {
        $url = rtrim(trim((string)App::parseEnv($this->serviceUrl)), '/');
        if ($url === '') {
            $url = self::DEFAULT_URL;
        }
        return preg_match('#^https?://[^\s/]+#i', $url) === 1 ? $url : '';
    }

    /**
     * @return string[] Aktions-Pfade ohne Leerzeilen, in Kleinbuchstaben.
     */
    public function getProtectedActionList(): array
    {
        $list = [];
        foreach (preg_split('/[\r\n,]+/', $this->protectedActions) ?: [] as $line) {
            $line = strtolower(trim($line, " \t/"));
            if ($line !== '') {
                $list[] = $line;
            }
        }
        return $list;
    }
}
