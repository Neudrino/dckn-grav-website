<?php
namespace Grav\Plugin\Shortcodes;

use Thunder\Shortcode\Shortcode\ShortcodeInterface;

class SplitShortcode extends Shortcode
{
    public function init()
    {
        $this->shortcode->getHandlers()->add('split', function (ShortcodeInterface $sc) {
            return '<div class="split-cols">' . $sc->getContent() . '</div>';
        });

        $this->shortcode->getHandlers()->add('col', function (ShortcodeInterface $sc) {
            $class = $sc->getParameter('class');
            return '<div' . ($class ? ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"' : '') . '>' . $sc->getContent() . '</div>';
        });
    }
}
