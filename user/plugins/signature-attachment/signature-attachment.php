<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Framework\Form\Interfaces\FormInterface;

class SignatureAttachmentPlugin extends Plugin
{
    private const TITLE_FIELDS = [
        'auslagenerstattung' => 'projektname',
        'projektantrag' => 'projekttitel',
    ];

    public static function getSubscribedEvents()
    {
        return [
            'onTwigLoader' => ['onTwigLoader', 0],
            'onEmailMessage' => ['onEmailMessage', 0],
        ];
    }

    public function onTwigLoader()
    {
        $this->grav['twig']->addPath(__DIR__ . '/templates');
    }

    public function onEmailMessage($event)
    {
        $message = $event['message'];
        $form = $event['form'];

        $has_signature = false;
        foreach ($form->getFields() as $field) {
            if (($field['type'] ?? null) === 'signature') {
                $has_signature = true;
                break;
            }
        }
        if (!$has_signature) {
            return;
        }

        require_once __DIR__ . '/vendor/autoload.php';

        $twig = $this->grav['twig'];
        $formName = $form->getName();

        $logoBinary = $this->readLogo();
        $vars = ['form' => $form];
        if ($logoBinary !== null) {
            $vars['logo_data_uri'] = 'data:image/png;base64,' . base64_encode($logoBinary);
        }

        $specificTemplate = 'pdf-template-' . $formName . '.html.twig';
        $templateName = is_file(__DIR__ . '/templates/' . $specificTemplate) ? $specificTemplate : 'pdf-template.html.twig';
        $html = $twig->processTemplate($templateName, $vars);

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
        $symfonyEmail = $message->getEmail();

        if ($pdfData) {
            $symfonyEmail->attach($pdfData, $this->buildFilename($form, $formName), 'application/pdf');
        }

        if ($logoBinary !== null) {
            $symfonyEmail->embed($logoBinary, 'dckn-logo', 'image/png');
        }

        $signature = (string) $form->value('unterschrift');
        if (preg_match('/^data:([^;]+);base64,(.*)$/s', $signature, $matches)) {
            $signatureBinary = base64_decode($matches[2], true);
            if ($signatureBinary !== false) {
                $symfonyEmail->embed($signatureBinary, $formName . '-signature', $matches[1]);
            }
        }
    }

    private function readLogo(): ?string
    {
        $locator = $this->grav['locator'];
        $logoPath = $locator->findResource('theme://images/logo/2024-06-10 DCKN Logo + Wortmarke.png', true);
        if (!$logoPath || !is_file($logoPath)) {
            return null;
        }

        $binary = file_get_contents($logoPath);

        return $binary === false ? null : $binary;
    }

    private function sanitizeTitle(string $title): string
    {
        $title = preg_replace('/\s+/u', '-', $title) ?? '';
        $title = preg_replace('/[\/:*?"<>|@#$%&+;=\\\\]/u', '', $title) ?? '';
        if (mb_strlen($title) > 60) {
            $title = mb_substr($title, 0, 60);
        }

        return rtrim($title, '-');
    }

    private function buildFilename(FormInterface $form, string $formName): string
    {
        $date = date('Y-m-d');
        $field = self::TITLE_FIELDS[$formName] ?? null;
        $title = $field ? $this->sanitizeTitle((string) $form->value($field)) : '';

        return $title !== '' ? $date . '-' . $title . '-' . $formName . '.pdf' : $date . '-' . $formName . '.pdf';
    }
}
