<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Framework\Form\Interfaces\FormInterface;
use Grav\Plugin\FormPdf\BelegeMerger;
use Symfony\Component\Mime\Email;
use Throwable;

class FormPdfPlugin extends Plugin
{
    private const TITLE_FIELDS = [
        'auslagenerstattung' => 'projektname',
        'projektantrag' => 'projekttitel',
    ];

    private const MERGE_UPLOAD_FIELDS = [
        'auslagenerstattung' => 'belege',
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
        $vars = [
            'form' => $form,
            'created_at' => date('Y-m-d H:i'),
            'pdf_css_path' => __DIR__ . '/templates/pdf.css',
        ];
        if ($logoBinary !== null) {
            $vars['logo_data_uri'] = 'data:image/png;base64,' . base64_encode($logoBinary);
        }

        $specificTemplate = 'pdf-template-' . $formName . '.html.twig';
        $templateName = is_file(__DIR__ . '/templates/' . $specificTemplate) ? $specificTemplate : 'pdf-template.html.twig';
        $html = $twig->processTemplate($templateName, $vars);

        require_once __DIR__ . '/classes/PdfRenderer.php';
        $pdfData = PdfRenderer::phpPdf($html);
        $symfonyEmail = $message->getEmail();

        $uploadField = self::MERGE_UPLOAD_FIELDS[$formName] ?? null;
        if ($uploadField !== null) {
            $pdfData = $this->mergeUploadsIntoPdf($pdfData, $formName, $form->value($uploadField), $symfonyEmail);
        }

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

    private function mergeUploadsIntoPdf(string $antragPdf, string $formName, $uploads, Email $symfonyEmail): string
    {
        $log = $this->grav['log'];

        if (!is_array($uploads)) {
            $log->warning('form-pdf: form "' . $formName . '" upload field has forged value of type ' . gettype($uploads) . ', merging skipped');
            return $antragPdf;
        }

        $locator = $this->grav['locator'];
        $belege = [];
        foreach ($uploads as $upload) {
            if (is_array($upload) && isset($upload['path'], $upload['name'])) {
                $path = $locator->findResource($upload['path'], true, true);
                if (is_string($path) && $path !== '') {
                    $belege[] = ['name' => (string) $upload['name'], 'path' => $path];
                    continue;
                }
            }
            $log->warning('form-pdf: form "' . $formName . '" beleg without usable path, merging skipped');
        }

        if ($belege === []) {
            return $antragPdf;
        }

        require_once __DIR__ . '/classes/BelegeMerger.php';

        try {
            [$pdfData, $skippedBelege] = (new BelegeMerger($log, $formName))->merge($antragPdf, $belege);
        } catch (Throwable $e) {
            $log->warning('form-pdf: form "' . $formName . '" merge failed completely, falling back to unmerged PDF + all original belege: ' . $e->getMessage());
            $this->attachBelege($symfonyEmail, $belege);

            return $antragPdf;
        }

        $this->attachBelege($symfonyEmail, $skippedBelege);

        return $pdfData;
    }

    private function attachBelege(Email $symfonyEmail, array $belege): void
    {
        $log = $this->grav['log'];
        foreach ($belege as $beleg) {
            if (!is_file($beleg['path'])) {
                $log->warning('form-pdf: beleg "' . $beleg['name'] . '" no longer exists, not attached');
                continue;
            }

            try {
                $symfonyEmail->attachFromPath($beleg['path'], $beleg['name']);
            } catch (Throwable $e) {
                $log->warning('form-pdf: could not attach beleg "' . $beleg['name'] . '": ' . $e->getMessage());
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
