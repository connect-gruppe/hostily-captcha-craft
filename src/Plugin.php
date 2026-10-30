<?php

namespace hostily\craftcaptcha;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\log\MonologTarget;
use hostily\craftcaptcha\integrations\Formie;
use hostily\craftcaptcha\integrations\Freeform;
use hostily\craftcaptcha\models\Settings;
use hostily\craftcaptcha\services\Verifier;
use hostily\craftcaptcha\services\Widget;
use hostily\craftcaptcha\twig\Extension;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use yii\base\ActionEvent;
use yii\base\Controller as BaseController;
use yii\base\Event;
use yii\log\Dispatcher;

/**
 * hostily Captcha fuer Craft CMS 4 und 5.
 *
 * @property-read Verifier $verifier
 * @property-read Widget $widget
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'verifier' => Verifier::class,
                'widget' => Widget::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        Craft::setAlias('@hostily/craftcaptcha', __DIR__);
        $this->registerLog();

        Craft::$app->getView()->registerTwigExtension(new Extension());

        // Account-Formulare der Website und weitere eigene Aktionen
        Event::on(BaseController::class, BaseController::EVENT_BEFORE_ACTION, [$this, 'onBeforeAction']);

        // Formie / Freeform, falls installiert
        Formie::register();
        Freeform::register();
    }

    /**
     * Eintrag im Protokoll des Systems (storage/logs/hostily-captcha-*.log), nie mit Secret.
     */
    public static function log(string $message, string $level = 'info'): void
    {
        $message = 'hostily Captcha: ' . $message;
        match ($level) {
            'error' => Craft::error($message, 'hostily-captcha'),
            'warning' => Craft::warning($message, 'hostily-captcha'),
            default => Craft::info($message, 'hostily-captcha'),
        };
    }

    private function registerLog(): void
    {
        $dispatcher = Craft::getLogger()->dispatcher;
        if (!$dispatcher instanceof Dispatcher || !class_exists(MonologTarget::class)) {
            return;
        }
        $dispatcher->targets['hostily-captcha'] = new MonologTarget([
            'name' => 'hostily-captcha',
            'categories' => ['hostily-captcha'],
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => false,
            'formatter' => new LineFormatter('%datetime% [%level_name%] %message%' . PHP_EOL, 'Y-m-d H:i:s', false, true),
        ]);
    }

    /**
     * Prueft vor der Aktion die Einsendung, wenn die Aktion in den Einstellungen als geschuetzt gilt.
     */
    public function onBeforeAction(ActionEvent $event): void
    {
        $request = Craft::$app->getRequest();
        if (!$event->isValid || $request->getIsConsoleRequest() || !$request->getIsPost() || !$request->getIsSiteRequest()) {
            return;
        }
        $verifier = $this->verifier;
        if (!$verifier->isConfigured()) {
            return;
        }
        $settings = $this->getSettings();
        $id = strtolower($event->action->uniqueId);
        $protected = in_array($id, $settings->getProtectedActionList(), true);
        // Registrierung nur fuer Besucher ohne Anmeldung (angemeldete Mitglieder bearbeiten ihr Profil mit derselben Aktion)
        if ($id === 'users/save-user' && $settings->protectRegistration && Craft::$app->getUser()->getIsGuest()) {
            $protected = true;
        }
        if ($id === 'users/send-password-reset-email' && $settings->protectPasswordReset) {
            $protected = true;
        }
        if ($id === 'users/login' && $settings->protectLogin) {
            $protected = true;
        }
        if (!$protected) {
            return;
        }
        $result = $verifier->verifyRequest();
        if ($result['status'] === 'ok') {
            return;
        }
        $message = $verifier->messageFor($result);
        $event->isValid = false;
        $controller = $event->action->controller;
        if (method_exists($controller, 'asFailure')) {
            // Formularseite wird mit Fehlermeldung (Flash "error" und Variable "errors") neu gerendert, bei Ajax kommt JSON
            $event->result = $controller->asFailure($message, ['errors' => ['hostilyCaptcha' => [$message]]], ['errors' => ['hostilyCaptcha' => [$message]]]);
        } else {
            throw new \yii\web\BadRequestHttpException($message);
        }
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('hostily-captcha/settings', [
            'settings' => $this->getSettings(),
            'plugin' => $this,
        ]);
    }
}
