<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;

/**
 * Class BrevoPlugin
 * Adds Brevo (formerly Sendinblue) contact list subscription
 * as a Grav Form plugin process action.
 *
 * @package Grav\Plugin
 */
class BrevoPlugin extends Plugin
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    /**
     * Initialize the plugin
     */
    public function onPluginsInitialized()
    {
        // Do not run in admin
        if ($this->isAdmin()) {
            return;
        }

        $this->enable([
            'onFormProcessed' => ['onFormProcessed', 0]
        ]);
    }

    /**
     * Handle the brevo form process action
     *
     * @param Event $event
     */
    public function onFormProcessed(Event $event)
    {
        $action = $event['action'];

        if ($action !== 'brevo') {
            return;
        }

        $form   = $event['form'];
        $params = $event['params'];

        // Get API key from plugin config
        $apiKey = $this->grav['config']->get('plugins.brevo.api_key');

        if (empty($apiKey)) {
            $this->grav['log']->error('Brevo plugin: api_key is not configured.');
            return;
        }

        // Get email from form data
        $email = $form->value('email');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->grav['log']->error('Brevo plugin: invalid or missing email address.');
            return;
        }

        // Build attributes from field_mappings
        // field_mappings in form frontmatter:
        //   BREVO_ATTRIBUTE: form_field_name
        $attributes = [];
        if (!empty($params['field_mappings']) && is_array($params['field_mappings'])) {
            foreach ($params['field_mappings'] as $brevoAttribute => $formField) {
                $value = $form->value($formField);
                if (!empty($value)) {
                    $attributes[$brevoAttribute] = $value;
                }
            }
        }

        // Build list IDs — from params or fall back to plugin config default
        $listIds = [];
        if (!empty($params['lists']) && is_array($params['lists'])) {
            $listIds = array_map('intval', $params['lists']);
        } elseif (!empty($this->grav['config']->get('plugins.brevo.default_list_id'))) {
            $listIds = [(int) $this->grav['config']->get('plugins.brevo.default_list_id')];
        }

        if (empty($listIds)) {
            $this->grav['log']->error('Brevo plugin: no list IDs configured.');
            return;
        }

        $doi = $this->grav['config']->get('plugins.brevo.doi', []);
        if (!is_array($doi)) {
            $doi = [];
        }

        $doiEnabled         = filter_var($doi['enabled'] ?? '', FILTER_VALIDATE_BOOLEAN);
        $doiTemplateId      = trim((string) ($doi['template_id'] ?? ''));
        $doiRedirectionUrl  = trim((string) ($doi['redirection_url'] ?? ''));

        // Call Brevo API
        $endpoint = 'contacts';
        $payload  = [
            'email'         => $email,
            'listIds'       => $listIds,
            'updateEnabled' => true,
        ];

        if (!empty($attributes)) {
            $payload['attributes'] = $attributes;
        }

        if ($doiEnabled) {
            // DOI is an all-or-nothing mode: both values are required, and a
            // misconfigured site must not silently fall back to single opt-in
            // (that would subscribe contacts without a confirmed consent).
            if ($doiTemplateId === '' || $doiRedirectionUrl === '') {
                $missing = $doiTemplateId === '' ? 'doi.template_id' : 'doi.redirection_url';
                $this->grav['log']->error("Brevo plugin: {$missing} is not configured — the Brevo API requires it when double opt-in is enabled. Subscription aborted.");
                $form->status  = 'error';
                $form->message = 'Subscription failed. Please try again.';
                $event->stopPropagation();
                return;
            }
            $endpoint = 'contacts/doubleOptinConfirmation';
            $payload  = [
                'email'          => $email,
                'attributes'     => $attributes,
                'includeListIds' => $listIds,
                'templateId'     => (int) $doiTemplateId,
                'redirectionUrl' => $doiRedirectionUrl,
            ];
        } else {
            $endpoint = 'contacts';
        }

        $result = $this->callBrevoApi($apiKey, $payload, $endpoint);

        if ($result === false) {
            $this->grav['log']->error('Brevo plugin: API call failed.');
            $form->status  = 'error';
            $form->message = 'Subscription failed. Please try again.';
            $event->stopPropagation();
        } else {
            if ($endpoint === 'contacts/doubleOptinConfirmation') {
                $this->grav['log']->info('Brevo plugin: DOI confirmation requested (template ' . $doiTemplateId . ').');
            }
            $form->status = 'success';
        }
    }

    /**
     * Make a POST request to the Brevo contacts API
     *
     * @param string $apiKey
     * @param array  $payload
     * @param string $endpoint
     * @return bool
     */
    protected function callBrevoApi($apiKey, $payload, $endpoint = 'contacts')
    {
        $ch = curl_init('https://api.brevo.com/v3/' . $endpoint);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'api-key: ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->grav['log']->error('Brevo plugin: curl error - ' . $error);
            return false;
        }

        // 201 = contact created, 204 = contact updated (updateEnabled)
        if ($status === 201 || $status === 204) {
            return true;
        }

        $this->grav['log']->error('Brevo plugin: unexpected API response ' . $status . ' - ' . $response);
        return false;
    }
}
