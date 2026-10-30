<?php

namespace hostily\craftcaptcha\integrations;

use Craft;
use craft\helpers\Html;
use hostily\craftcaptcha\Plugin;
use verbb\formie\base\Captcha;
use verbb\formie\elements\Form;
use verbb\formie\elements\Submission;

/**
 * hostily Captcha als Formie-Captcha. Die Zugangsdaten stehen in den Einstellungen des Plugins "hostily Captcha"
 * (Site-Key, Secret, Dienst-URL, Modus, Sprache, Verhalten bei Ausfall), nicht in Formie.
 *
 * Die Klasse laeuft mit Formie 2/3 (getFrontEndHtml) und Formie 4 (renderHtml); die Signaturen werden bewusst ohne
 * Typen fuer $page gehalten, damit sie zu beiden Basisklassen passen.
 */
class FormieCaptcha extends Captcha
{
    public ?string $handle = 'hostilyCaptcha';

    public static function displayName(): string
    {
        return 'hostily Captcha';
    }

    public function getName(): string
    {
        return 'hostily Captcha';
    }

    public function getDescription(): string
    {
        return Craft::t('hostily-captcha', 'hostily Captcha is a privacy-friendly spam protection without cookies. Site key, secret and the other settings are managed in Settings → Plugins → hostily Captcha.');
    }

    public function getSettingsHtml(): ?string
    {
        return Html::tag('p', Craft::t('hostily-captcha', 'hostily Captcha is a privacy-friendly spam protection without cookies. Site key, secret and the other settings are managed in Settings → Plugins → hostily Captcha.'))
            . Html::tag('p', Plugin::getInstance()->verifier->isConfigured()
                ? Craft::t('hostily-captcha', 'The plugin is configured.')
                : Craft::t('hostily-captcha', 'The plugin is not configured yet: nothing is protected and nothing is blocked.'), ['class' => 'light']);
    }

    public function hasValidSettings(): bool
    {
        return Plugin::getInstance()->verifier->isConfigured();
    }

    /** Formie 4 */
    public function renderHtml($form, $page = null): string
    {
        return $this->widgetHtml();
    }

    /** Formie 2 und 3 */
    public function getFrontEndHtml(Form $form, $page = null): string
    {
        return $this->widgetHtml();
    }

    public function getFrontEndJsVariables(Form $form, $page = null): ?array
    {
        return null;
    }

    private function widgetHtml(): string
    {
        // Nicht eingerichtet: nichts anzeigen, nichts sperren.
        return (string)Plugin::getInstance()->widget->render();
    }

    public function validateSubmission(Submission $submission): bool
    {
        $verifier = Plugin::getInstance()->verifier;
        if (!$verifier->isConfigured()) {
            return true;
        }
        $token = $verifier->tokenFromRequest();
        if ($token === '') {
            $value = method_exists($this, 'getCaptchaValue') ? $this->getCaptchaValue($submission, $verifier::TOKEN_FIELD) : null;
            $token = is_string($value) ? trim($value) : '';
        }

        // Inhaltsfelder der Einsendung (Textfelder) fuer die Inhaltspruefung
        $fields = [];
        try {
            // Formie 2 bis 4: getFields() liefert die Felder des Formulars; einfache Textwerte gehen an den Dienst
            $formFields = method_exists($submission, 'getFields') ? $submission->getFields() : ($submission->getFieldLayout()?->getCustomFields() ?? []);
            foreach ($formFields as $field) {
                $value = $submission->getFieldValue($field->handle);
                if (is_string($value) && $value !== '') {
                    $fields[$field->handle] = $value;
                }
            }
        } catch (\Throwable $e) {
            $fields = [];
        }

        $result = $verifier->verify($token, $verifier->contentFields($fields));
        if ($result['status'] === 'ok') {
            return true;
        }
        $this->spamReason = 'hostily Captcha: ' . implode(',', $result['reasons']);
        if ($result['status'] === 'error') {
            // Dienst nicht erreichbar und "blockieren" gewaehlt: Fehlermeldung statt "Spam"
            $submission->addError('form', $verifier->messageFor($result));
        }
        return false;
    }
}
