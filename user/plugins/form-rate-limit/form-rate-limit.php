<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Uri;
use Grav\Plugin\FormRateLimit\RateLimiter;
use RocketTheme\Toolbox\Event\Event;

require_once __DIR__ . '/classes/RateLimiter.php';

class FormRateLimitPlugin extends Plugin
{
    public static function getSubscribedEvents()
    {
        // Priority 10 > email plugin's 0: Symfony krsort's listeners by
        // priority, so the file field guard runs before the email handler.
        return [
            'onFormProcessed' => ['onFormProcessed', 10],
        ];
    }

    /**
     * Action `rate-limit`: per-form-IP limit, plus a global
     * per-target-address limit when `target_field` is configured.
     * Action `email`: file field guard. Forged values (e.g. a string
     * posted as data[belege] instead of a file upload array) would make
     * the email plugin's attachment handling throw -> 500. Rejected here
     * instead as a validation error, like rate-limit rejections: fire
     * onFormValidationError (sets status/message the visitor sees) and
     * stop the dispatch so the email handler never runs. No exception.
     */
    public function onFormProcessed(Event $event)
    {
        $action = $event['action'];

        if ($action === 'email') {
            if ($this->config->get('plugins.form-rate-limit.file_field_guard', true)) {
                $this->guardFileFields($event);
            }
            return;
        }

        if ($action !== 'rate-limit') {
            return;
        }

        $form = $event['form'];
        $params = $event['params'];

        $target = null;
        if (is_array($params) && !empty($params['target_field'])) {
            $value = $form->value((string) $params['target_field']);
            if (is_scalar($value)) {
                $value = trim((string) $value);
                $target = $value !== '' ? $value : null;
            }
        }

        $limiter = new RateLimiter(
            $this->storageDir(),
            (int) $this->config->get('plugins.form-rate-limit.limits.min_interval', 60),
            (int) $this->config->get('plugins.form-rate-limit.limits.hourly_max', 3),
            (int) $this->config->get('plugins.form-rate-limit.limits.window', 3600),
            5000,
            fn (string $message) => $this->grav['log']->warning('form-rate-limit: ' . $message)
        );

        $message = $limiter->check($form->getName(), (string) Uri::ip(), $target);
        if ($message !== null) {
            $this->grav->fireEvent('onFormValidationError', new Event([
                'form' => $form,
                'message' => $message,
            ]));
            $event->stopPropagation();
        }
    }

    private function guardFileFields(Event $event): void
    {
        $form = $event['form'];
        $fields = $form->getBlueprint()->get('form/fields');

        foreach ($this->collectFileFieldNames($fields) as $name) {
            $value = $form->value($name);
            if (!$this->isForgedFileValue($value)) {
                continue;
            }

            $this->grav['log']->warning(sprintf(
                'form-rate-limit: form "%s" file field "%s" has forged value of type %s',
                $form->getName(),
                $name,
                gettype($value)
            ));
            $this->grav->fireEvent('onFormValidationError', new Event([
                'form' => $form,
                'message' => sprintf(
                    'Deine Datei-Übertragung konnte nicht verarbeitet werden (Feld „%s“). Bitte versuche es erneut.',
                    $name
                ),
            ]));
            $event->stopPropagation();
            return;
        }
    }

    private function collectFileFieldNames($fields, array $names = []): array
    {
        if (!is_array($fields)) {
            return $names;
        }
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            if (($field['type'] ?? '') === 'file' && isset($field['name']) && is_string($field['name'])) {
                $names[] = $field['name'];
            }
            if (isset($field['fields'])) {
                $names = $this->collectFileFieldNames($field['fields'], $names);
            }
        }
        return $names;
    }

    private function isForgedFileValue($value): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return false;
        }
        if (!is_array($value)) {
            return true;
        }
        foreach ($value as $item) {
            if (!is_array($item)) {
                return true;
            }
        }
        return false;
    }

    private function storageDir(): string
    {
        $dir = $this->grav['locator']->findResource('user://data', true) . '/form-rate-limit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }
}
