<?php
namespace Grav\Plugin\SignatureAttachment;

use Dskripchenko\PhpPdf\Pdf\Merge\PdfMerger;
use Dskripchenko\PhpPdf\Pdf\Merge\PdfSource;
use Psr\Log\LoggerInterface;
use RuntimeException;
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
     */
    public function merge(string $antragPdfData, array $belege): array
    {
        $merger = PdfMerger::create()->append(PdfSource::fromBytes($antragPdfData));

        $skipped = [];
        foreach ($belege as $beleg) {
            try {
                $source = $this->sourceFor($beleg['name'], $beleg['path']);
                if ($source === null) {
                    $skipped[] = $beleg;
                    continue;
                }

                // PdfSource parses lazily, so force the parse now to skip a
                // broken beleg instead of aborting the whole merge later.
                if ($source->document()->pageCount() < 1) {
                    throw new RuntimeException('document has no pages');
                }
                $merger->append($source);
            } catch (Throwable $e) {
                $this->skip($beleg['name'], $e->getMessage());
                $skipped[] = $beleg;
            }
        }

        return [$merger->toBytes(), $skipped];
    }

    private function sourceFor(string $name, string $path): ?PdfSource
    {
        if (!is_file($path) || filesize($path) === 0) {
            $this->skip($name, 'missing or empty file');

            return null;
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            return PdfSource::fromFile($path);
        }

        if ($extension === 'png' || $extension === 'jpg' || $extension === 'jpeg') {
            try {
                return PdfSource::fromBytes($this->imageToPdf($path, $name));
            } catch (Throwable $e) {
                $this->skip($name, $e->getMessage());

                return null;
            }
        }

        $this->skip($name, 'unsupported file type .' . $extension);

        return null;
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

        return $pdfData;
    }

    private function skip(string $name, string $reason): void
    {
        $this->log->warning(
            'signature-attachment: form "' . $this->formName . '" beleg "' . $name
            . '" not merged into PDF (stays as email attachment): ' . $reason
        );
    }
}
