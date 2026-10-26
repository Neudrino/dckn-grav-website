<?php
namespace Grav\Plugin\SignatureAttachment;

use Psr\Log\LoggerInterface;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfReader\PageBoundaries;
use Throwable;

/**
 * Merges the rendered antrag PDF with uploaded belege into a single PDF:
 * PDF belege keep all their pages, PNG/JPG belege become one page each.
 * Belege that cannot be merged (TIFF, broken/unparseable files) are returned
 * as "skipped" so the caller can attach the original files to the email.
 */
final class BelegeMerger
{
    private const IMAGE_MAX_WIDTH = 180.0;
    private const IMAGE_MAX_HEIGHT = 250.0;

    public function __construct(
        private readonly LoggerInterface $log,
        private readonly string $formName
    ) {
    }

    /**
     * @param string $antragPdfData binary PDF data of the rendered form
     * @param array<int, array{name: string, path: string}> $belege absolute file paths in upload order
     * @return array{0: string, 1: array<int, array{name: string, path: string}>} merged PDF data and skipped belege
     * @throws RuntimeException when the whole merge fails (caller falls back to the unmerged PDF)
     */
    public function merge(string $antragPdfData, array $belege): array
    {
        $antragFile = tempnam(sys_get_temp_dir(), 'antrag-');
        if ($antragFile === false || file_put_contents($antragFile, $antragPdfData) === false) {
            throw new RuntimeException('could not write antrag PDF to temp file');
        }

        try {
            $pdf = new Fpdi();
            $this->importAllPages($pdf, $antragFile);

            $skipped = [];
            foreach ($belege as $beleg) {
                if (!$this->addBeleg($pdf, $beleg['name'], $beleg['path'])) {
                    $skipped[] = $beleg;
                }
            }

            $mergedPdf = $pdf->Output('S');
        } finally {
            @unlink($antragFile);
        }

        return [$mergedPdf, $skipped];
    }

    private function addBeleg(Fpdi $pdf, string $name, string $path): bool
    {
        if (!is_file($path) || filesize($path) === 0) {
            return $this->skip($name, 'missing or empty file');
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: pathinfo($path, PATHINFO_EXTENSION));

        try {
            if ($extension === 'pdf') {
                $this->importAllPages($pdf, $path);

                return true;
            }

            if ($extension === 'png' || $extension === 'jpg' || $extension === 'jpeg') {
                $imagePdf = $this->imageToPdf($path, $name);
                try {
                    $this->importAllPages($pdf, $imagePdf);
                } finally {
                    @unlink($imagePdf);
                }

                return true;
            }
        } catch (Throwable $e) {
            return $this->skip($name, $e->getMessage());
        }

        return $this->skip($name, 'unsupported file type .' . $extension);
    }

    private function importAllPages(Fpdi $pdf, string $pdfPath): int
    {
        $pageCount = $pdf->setSourceFile($pdfPath);
        for ($i = 1; $i <= $pageCount; $i++) {
            $templateId = $pdf->importPage($i, PageBoundaries::CROP_BOX, true, true);
            $size = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
        }

        return $pageCount;
    }

    private function imageToPdf(string $imagePath, string $name): string
    {
        $data = @file_get_contents($imagePath);
        $info = $data === false ? false : @getimagesize($imagePath);
        if ($info === false) {
            throw new RuntimeException('unreadable image');
        }
        [$width, $height] = $info;
        if ($width < 1 || $height < 1) {
            throw new RuntimeException(sprintf('zero image dimension (%dx%d)', $width, $height));
        }
        $mime = $info['mime'] ?? '';
        if ($mime !== 'image/png' && $mime !== 'image/jpeg') {
            throw new RuntimeException('sniffed content is not PNG/JPEG (' . ($mime ?: 'unknown') . ')');
        }

        $gd = @imagecreatefromstring($data);
        if ($gd === false) {
            throw new RuntimeException('GD full decode failed (truncated or corrupt image)');
        }
        imagedestroy($gd);

        $scale = min(self::IMAGE_MAX_WIDTH / $width, self::IMAGE_MAX_HEIGHT / $height);
        $dataUri = 'data:' . $mime . ';base64,' . base64_encode($data);
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            . 'body{font-family:sans-serif;font-size:10pt;margin:1cm;}'
            . '.head{margin-bottom:6mm;font-weight:bold;}'
            . '</style></head><body>'
            . '<div class="head">Beleg: ' . htmlspecialchars($name, ENT_QUOTES) . '</div>'
            . '<img src="' . $dataUri . '" style="width:' . ($width * $scale) . 'mm;height:' . ($height * $scale) . 'mm">'
            . '</body></html>';

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', sys_get_temp_dir());

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfData = $dompdf->output();
        if (!$pdfData) {
            throw new RuntimeException('dompdf produced no output');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'beleg-');
        if ($tempFile === false || file_put_contents($tempFile, $pdfData) === false) {
            throw new RuntimeException('could not write image beleg to temp file');
        }

        return $tempFile;
    }

    private function skip(string $name, string $reason): bool
    {
        $this->log->warning(
            'signature-attachment: form "' . $this->formName . '" beleg "' . $name
            . '" not merged into PDF (stays as email attachment): ' . $reason
        );

        return false;
    }
}
