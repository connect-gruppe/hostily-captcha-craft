<?php

namespace hostily\craftcaptcha\services;

use Craft;
use craft\base\Component;
use hostily\craftcaptcha\models\Settings;
use hostily\craftcaptcha\Plugin;
use yii\web\Request;

/**
 * Prueft ein Token beim Dienst (POST <Dienst>/api/v1/pruefen, Protokoll: dienst/docs/protokoll.md).
 *
 * Ergebnis: ['status' => 'ok'|'rejected'|'error', 'reasons' => string[]]. 'error' gibt es nur bei
 * "Bei Ausfall: blockieren"; bei "durchlassen" (Vorgabe) ist ein Ausfall oder ein Konfigurationsfehler 'ok'
 * mit dem Grund 'service' (und einem Eintrag im Protokoll).
 */
class Verifier extends Component
{
    public const VERSION = '0.1.2';
    public const TOKEN_FIELD = 'hostily-captcha-token';
    public const CONFIG_ERRORS = ['invalid-input-secret', 'missing-input-secret', 'bad-request'];

    /** Ergebnisse dieser Anfrage je Token (ein Token gilt nur einmal, die Pruefung kann aber mehrfach laufen). */
    private array $verified = [];

    private function settings(): Settings
    {
        return Plugin::getInstance()->getSettings();
    }

    public function isConfigured(): bool
    {
        $s = $this->settings();
        return $s->getSiteKey() !== '' && $s->getSecret() !== '' && $s->getServiceUrl() !== '';
    }

    /**
     * Sprache des Widgets: 'de', 'en' oder '' (das Widget entscheidet selbst).
     */
    public function language(?string $override = null): string
    {
        $lang = $override ?: $this->settings()->language;
        if ($lang === 'auto' || $lang === '') {
            $lang = (string)Craft::$app->language;
        }
        $lang = strtolower(explode('-', str_replace('_', '-', $lang))[0]);
        return in_array($lang, ['de', 'en'], true) ? $lang : '';
    }

    public function tokenFromRequest(?Request $request = null): string
    {
        $request = $request ?? Craft::$app->getRequest();
        if ($request->getIsConsoleRequest()) {
            return '';
        }
        $token = $request->getBodyParam(self::TOKEN_FIELD);
        return is_string($token) ? trim($token) : '';
    }

    /**
     * Textfelder der Anfrage fuer die Inhaltspruefung (ohne Kennwoerter, Tokens und technische Felder).
     *
     * @return array<string, string>
     */
    public function contentFields(array $values): array
    {
        $fields = [];
        foreach ($values as $key => $value) {
            if (!is_string($key) || !is_string($value) || $value === '' || count($fields) >= 30) {
                continue;
            }
            if (preg_match('/pass|pwd|token|secret|captcha|csrf|hash|payload|^action$|^redirect|^freeform|(?-i:^form[A-Z])|submit|honeypot|^fields$/i', $key)) {
                continue;
            }
            $fields[$key] = mb_substr($value, 0, 2000);
        }
        return $fields;
    }

    /**
     * Prueft die laufende Anfrage (Token aus dem Formular).
     *
     * @return array{status: string, reasons: string[]}
     */
    public function verifyRequest(bool $withContent = true): array
    {
        if (!$this->isConfigured()) {
            return ['status' => 'ok', 'reasons' => ['not-configured']];
        }
        $request = Craft::$app->getRequest();
        $fields = $withContent && !$request->getIsConsoleRequest() ? $this->contentFields((array)$request->getBodyParams()) : [];
        return $this->verify($this->tokenFromRequest($request), $fields);
    }

    /**
     * @param array<string, string> $fields Inhaltsfelder (leer = keine Inhaltspruefung)
     * @return array{status: string, reasons: string[]}
     */
    public function verify(string $token, array $fields = []): array
    {
        if (!$this->isConfigured()) {
            return ['status' => 'ok', 'reasons' => ['not-configured']];
        }
        // Ein leeres Token wird trotzdem gesendet: ist der Dienst erreichbar, ist die Einsendung abgelehnt; ist er es
        // nicht, gilt "Bei Ausfall" (sonst sperrt ein nicht ladendes Widget alle Besucher aus).
        if ($token !== '' && isset($this->verified[$token])) {
            return $this->verified[$token];
        }
        $result = $this->ask($token, $fields);
        if ($token !== '') {
            if (count($this->verified) >= 50) {
                $this->verified = [];
            }
            $this->verified[$token] = $result;
        }
        return $result;
    }

    /**
     * Meldung fuer Besucher zu einem Ergebnis (Deutsch/Englisch ueber die Sprachdateien des Plugins).
     */
    public function messageFor(array $result): string
    {
        if (($result['status'] ?? '') === 'error') {
            return Craft::t('hostily-captcha', 'The spam protection is currently not reachable. Please try again later.');
        }
        if (array_intersect($result['reasons'] ?? [], ['token_fehlt', 'token_abgelaufen'])) {
            return Craft::t('hostily-captcha', 'Please confirm that you are not a robot (the check is missing or has expired) and submit the form again.');
        }
        return Craft::t('hostily-captcha', 'Your input was rejected by the spam protection. Please try again.');
    }

    private function ask(string $token, array $fields): array
    {
        $request = Craft::$app->getRequest();
        $body = [
            'secret' => $this->settings()->getSecret(),
            'response' => $token,
            'remoteip' => $request->getIsConsoleRequest() ? '' : (string)$request->getUserIP(),
        ];
        if ($fields !== []) {
            $body['formular'] = $fields;
        }

        try {
            $client = Craft::createGuzzleClient([
                'timeout' => 8,
                'connect_timeout' => 5,
                'allow_redirects' => false,
                'http_errors' => false,
                'headers' => ['User-Agent' => 'hostily-captcha-craft/' . self::VERSION],
            ]);
            $response = $client->post($this->settings()->getServiceUrl() . '/api/v1/pruefen', ['json' => $body]);
            $code = $response->getStatusCode();
            $data = json_decode((string)$response->getBody(), true);
        } catch (\Throwable $e) {
            return $this->failure('service unreachable: ' . $e->getMessage());
        }

        if ($code !== 200 || !is_array($data) || !isset($data['success'])) {
            return $this->failure('unexpected answer from service (HTTP ' . $code . ')');
        }
        $codes = [];
        if (isset($data['error-codes']) && is_array($data['error-codes'])) {
            foreach ($data['error-codes'] as $c) {
                if (is_string($c)) {
                    $codes[] = (string)preg_replace('/[^a-z0-9_-]/i', '', $c);
                }
            }
        }
        if (array_intersect($codes, self::CONFIG_ERRORS) !== []) {
            return $this->failure('service rejected the configuration: ' . implode(',', $codes));
        }
        if ($data['success'] === true) {
            return ['status' => 'ok', 'reasons' => []];
        }
        $reasons = [];
        if (isset($data['gruende']) && is_array($data['gruende'])) {
            foreach ($data['gruende'] as $r) {
                if (is_string($r)) {
                    $reasons[] = substr((string)preg_replace('/[^a-z0-9_:.-]/i', '', $r), 0, 60);
                }
            }
        }
        // Zur Fehlersuche (z. B. Token an eine andere IP gebunden): Gruende der Ablehnung, ohne Inhalte.
        Plugin::log('submission rejected (' . implode(',', $reasons) . ')', 'info');
        return ['status' => 'rejected', 'reasons' => $reasons];
    }

    /**
     * Dienst- oder Konfigurationsproblem: durchlassen (Vorgabe) oder blockieren, immer protokollieren.
     */
    private function failure(string $why): array
    {
        // Das Secret steht in keiner dieser Meldungen; Guzzle-Fehler enthalten nur die Adresse.
        Plugin::log(substr($why, 0, 300), 'error');
        if ($this->settings()->onError === 'block') {
            return ['status' => 'error', 'reasons' => ['service']];
        }
        return ['status' => 'ok', 'reasons' => ['service']];
    }
}
