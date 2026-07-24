<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;

class SignatureAttachmentPlugin extends Plugin
{
    public static function getSubscribedEvents()
    {
        return [
            'onEmailMessage' => ['onEmailMessage', 0],
        ];
    }

    public function onEmailMessage($event)
    {
        $message = $event['message'];
        $form = $event['form'];

        $formFields = $form->value();
        foreach ($formFields as $name => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (preg_match('/^data:image\/png;base64,/', $value)) {
                $base64 = substr($value, strpos($value, ',') + 1);
                $imageData = base64_decode($base64);

                if ($imageData !== false) {
                    $symfonyEmail = $message->getEmail();
                    $symfonyEmail->attach($imageData, 'unterschrift.png', 'image/png');
                }
            }
        }
    }
}
