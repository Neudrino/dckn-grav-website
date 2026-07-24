<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Framework\Form\Interfaces\FormInterface;

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

        require_once __DIR__ . '/vendor/autoload.php';

        $twig = $this->grav['twig'];
        $vars = ['form' => $form];
        $html = $twig->processTemplate('pdf-template.html.twig', $vars);

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', sys_get_temp_dir());

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfData = $dompdf->output();

        if ($pdfData) {
            $symfonyEmail = $message->getEmail();
            $symfonyEmail->attach($pdfData, 'auslagenerstattung.pdf', 'application/pdf');
        }
    }
}
