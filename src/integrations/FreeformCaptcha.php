<?php

namespace hostily\craftcaptcha\integrations;

use Craft;
use craft\helpers\Html;
use hostily\craftcaptcha\Plugin;
use Solspace\Freeform\Attributes\Integration\Type;
use Solspace\Freeform\Attributes\Property\VisibilityFilter;
use Solspace\Freeform\Attributes\Property\Input;
use Solspace\Freeform\Form\Form;
use Solspace\Freeform\Library\DataObjects\SpamReason;
use Solspace\Freeform\Library\Integrations\BaseIntegration;
use Solspace\Freeform\Library\Integrations\EnabledByDefault\EnabledByDefaultTrait;
use Solspace\Freeform\Library\Integrations\Types\Captchas\CaptchaIntegrationInterface;
use Solspace\Freeform\Services\Headless\HeadlessPayloadHelper;

/**
 * hostily Captcha als Freeform-Captcha. Die Zugangsdaten stehen in den Einstellungen des Plugins "hostily Captcha"
 * (Site-Key, Secret, Dienst-URL, Modus, Sprache, Verhalten bei Ausfall), nicht in Freeform.
 */
#[Type(
    name: 'hostily Captcha',
    type: Type::TYPE_CAPTCHAS,
    readme: __DIR__.'/freeform/README.md',
    iconPath: __DIR__.'/freeform/icon.svg',
)]
class FreeformCaptcha extends BaseIntegration implements CaptchaIntegrationInterface
{
    use EnabledByDefaultTrait;

    public const BEHAVIOR_DISPLAY_ERROR = 'display-error';
    public const BEHAVIOR_SEND_TO_SPAM = 'send-to-spam';

    #[VisibilityFilter('Boolean(enabled)')]
    #[Input\Select(
        label: 'Failure Behavior',
        instructions: 'What happens to a submission that the spam protection rejects. If the service is down, the setting "If the service is not reachable" of the plugin decides.',
        options: [
            self::BEHAVIOR_DISPLAY_ERROR => 'Display Error Message',
            self::BEHAVIOR_SEND_TO_SPAM => 'Send to Spam Folder',
        ],
    )]
    private string $failureBehavior = self::BEHAVIOR_DISPLAY_ERROR;

    public function getFailureBehavior(): string
    {
        return $this->failureBehavior;
    }

    public function validate(Form $form): void
    {
        $verifier = Plugin::getInstance()->verifier;
        if (!$verifier->isConfigured()) {
            return;
        }

        $token = $this->getCaptchaResponse($form);
        // Textfelder des Formulars fuer die Inhaltspruefung (Kennwoerter und Tokens nimmt contentFields heraus)
        $values = [];
        foreach ($form->getLayout()->getFields() as $field) {
            $value = method_exists($field, 'getValue') ? $field->getValue() : null;
            if (is_string($value) && $value !== '' && method_exists($field, 'getHandle')) {
                $values[$field->getHandle()] = $value;
            }
        }
        $fields = $verifier->contentFields($values);
        $result = $verifier->verify($token, $fields);
        if ($result['status'] === 'ok') {
            return;
        }

        $message = $verifier->messageFor($result);
        // Dienst nicht erreichbar und "blockieren" gewaehlt: immer Fehlermeldung, nie in den Spam-Ordner
        if ($result['status'] === 'rejected' && self::BEHAVIOR_SEND_TO_SPAM === $this->getFailureBehavior()) {
            $form->markAsSpam(SpamReason::TYPE_CAPTCHA, 'hostily Captcha ['.$form->getHandle().'] - '.implode(', ', $result['reasons']));

            return;
        }
        $form->addError($message);
    }

    public function getHtmlTag(Form $form): string
    {
        // Nicht eingerichtet: nichts anzeigen, nichts sperren.
        return (string)Plugin::getInstance()->widget->render();
    }

    public function getScriptPaths(): array
    {
        // Das Widget-Skript kommt vom Dienst und steht beim Element (getHtmlTag)
        return [];
    }

    public function getCaptchaHandle(): string
    {
        return 'hostily-captcha-token';
    }

    private function getCaptchaResponse(Form $form): string
    {
        $handle = $this->getCaptchaHandle();
        if ($form->isGraphQLPosted()) {
            $arguments = $form->getGraphQLArguments();
            $property = $arguments['captcha'] ?? [];

            return ($property['name'] ?? '') === $handle ? trim((string)($property['value'] ?? '')) : '';
        }
        if ($form->isHeadlessPosted()) {
            return trim((string)HeadlessPayloadHelper::getCaptchaResponse($form, $handle));
        }

        return Plugin::getInstance()->verifier->tokenFromRequest();
    }
}
