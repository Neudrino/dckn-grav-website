<?php
namespace Grav\Plugin\FormPdf;

use Dskripchenko\PhpPdf\Document;
use Dskripchenko\PhpPdf\Html\HtmlParser;
use Dskripchenko\PhpPdf\Layout\Engine;
use Dskripchenko\PhpPdf\Section;
use Dskripchenko\PhpPdf\Style\PageSetup;
use Dskripchenko\PhpPdf\Pdf\PdfFont;
use Dskripchenko\PhpPdf\Font\DirectoryFontProvider;
use Dskripchenko\PhpPdf\Font\FontProvider;

final class PdfRenderer
{
    private const FONT_DIR = __DIR__ . '/../assets/fonts/ubuntu';
    private const BASE_FONT_SIZE_PT = 10.5;
    private const HEADER_DISTANCE_PT = 28.35;
    private const FOOTER_DISTANCE_PT = 26.0;

    public static function phpPdf(string $html): string
    {
        [$bodyHtml, $headerHtml, $footerHtml, $cssText] = self::splitHeaderFooter($html);

        $bodyParser = new HtmlParser(self::BASE_FONT_SIZE_PT);
        $bodyBlocks = $bodyParser->parse($bodyHtml);
        [$headerDistance, $footerDistance] = self::cssDistances($cssText);
        $setup = self::pageSetup($bodyParser->pageSetup() ?? new PageSetup, $headerDistance, $footerDistance);

        $headerBlocks = self::fragmentBlocks($headerHtml);
        $footerBlocks = self::fragmentBlocks($footerHtml);

        $engine = self::engine();
        if ($footerBlocks !== []) {
            $setup = self::safeFooterDistance($setup, $engine, $footerBlocks, $footerDistance);
        }

        $section = new Section(
            body: $bodyBlocks,
            pageSetup: $setup,
            headerBlocks: $headerBlocks,
            footerBlocks: $footerBlocks,
        );

        return (new Document(section: $section))->toBytes($engine);
    }

    private static function splitHeaderFooter(string $html): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $styles = '';
        $cssText = '';
        foreach ($xpath->query('//style | //link[contains(translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "stylesheet")]') ?: [] as $sheet) {
            $css = strtolower($sheet->nodeName) === 'style'
                ? $sheet->textContent
                : self::localStyleSheet($sheet);
            $cssText .= $css . "\n";
            $styles .= $dom->saveHTML($sheet);
        }

        $header = $xpath->query('//header')->item(0);
        $footer = $xpath->query('//footer')->item(0);
        $headerInner = $header !== null ? self::innerHtml($header) : '';
        $footerInner = $footer !== null ? self::innerHtml($footer) : '';
        $header?->parentNode?->removeChild($header);
        $footer?->parentNode?->removeChild($footer);

        $bodyHtml = $dom->saveHTML($dom->documentElement) ?: $html;

        return [$bodyHtml, $styles . $headerInner, $styles . $footerInner, $cssText];
    }

    private static function localStyleSheet(\DOMElement $link): string
    {
        $href = trim($link->getAttribute('href'));
        if ($href === '' || preg_match('#^[a-z][a-z0-9+.-]*://#i', $href) === 1) {
            return '';
        }

        return is_file($href) && is_readable($href) ? (string) file_get_contents($href) : '';
    }

    private static function innerHtml(\DOMElement $node): string
    {
        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $node->ownerDocument->saveHTML($child);
        }

        return trim($inner);
    }

    private static function cssDistances(string $cssText): array
    {
        return [
            self::cssLength($cssText, '--pdf-header-distance') ?? self::HEADER_DISTANCE_PT,
            self::cssLength($cssText, '--pdf-footer-distance') ?? self::FOOTER_DISTANCE_PT,
        ];
    }

    private static function cssLength(string $css, string $property): ?float
    {
        if (preg_match('/' . preg_quote($property, '/') . '\s*:\s*([\d.]+)\s*(pt|mm|cm)/i', $css, $m) !== 1) {
            return null;
        }

        return match (strtolower($m[2])) {
            'mm' => (float) $m[1] * 72 / 25.4,
            'cm' => (float) $m[1] * 72 / 2.54,
            default => (float) $m[1],
        };
    }

    private static function pageSetup(PageSetup $base, float $headerDistance, float $footerDistance): PageSetup
    {
        return new PageSetup(
            paperSize: $base->paperSize,
            orientation: $base->orientation,
            margins: $base->margins,
            customDimensionsPt: $base->customDimensionsPt,
            firstPageNumber: $base->firstPageNumber,
            headerDistancePt: $headerDistance,
            footerDistancePt: $footerDistance,
        );
    }

    private static function safeFooterDistance(PageSetup $setup, Engine $engine, array $footerBlocks, float $footerDistance): PageSetup
    {
        $measure = new \ReflectionMethod($engine, 'measureBlockHeight');
        $measure->setAccessible(true);
        $height = 0.0;
        foreach ($footerBlocks as $block) {
            $height += (float) $measure->invoke($engine, $block, $setup->contentWidthPt());
        }

        $max = max(4.0, $setup->margins->bottomPt - 4.0 - $height);
        if ($footerDistance <= $max) {
            return $setup;
        }

        return self::pageSetup($setup, $setup->headerDistancePt, $max);
    }

    private static function fragmentBlocks(string $fragmentHtml): array
    {
        if (trim($fragmentHtml) === '') {
            return [];
        }

        $parser = new HtmlParser(self::BASE_FONT_SIZE_PT);

        return $parser->parse($fragmentHtml);
    }

    private static function engine(): Engine
    {
        $provider = self::fontProvider();
        $family = 'Ubuntu';

        return new Engine(
            defaultFont: self::embeddedFont($provider, "{$family}-Regular"),
            boldFont: self::embeddedFont($provider, "{$family}-Bold"),
            italicFont: self::embeddedFont($provider, "{$family}-Italic"),
            boldItalicFont: self::embeddedFont($provider, "{$family}-BoldItalic"),
            fontProvider: $provider,
            defaultFontSizePt: self::BASE_FONT_SIZE_PT,
        );
    }

    private static function fontProvider(): FontProvider
    {
        return new DirectoryFontProvider(self::FONT_DIR);
    }

    private static function embeddedFont(FontProvider $provider, string $postScriptName): ?PdfFont
    {
        $ttf = $provider->resolve($postScriptName)
            ?? $provider->resolve('Ubuntu-Regular');

        return $ttf !== null ? new PdfFont($ttf) : null;
    }
}
