<?php

declare(strict_types=1);

namespace Qmdb\Shared\Presentation\View;

use Qmdb\Shared\Localization\Translator;
use Qmdb\Shared\Presentation\Asset\AssetUrlGenerator;
use Qmdb\Shared\Presentation\Html\HtmlEscaper;
use Qmdb\Shared\Presentation\Html\SafeHtml;
use RuntimeException;
use Throwable;

final readonly class PhpViewRenderer
{
    public function __construct(
        private ViewRegistry $registry,
        private HtmlEscaper $escape,
        private AssetUrlGenerator $assets,
    ) {
    }

    public function render(ViewName|string $name, ViewData $view, Translator $translator): SafeHtml
    {
        $viewName = is_string($name) ? new ViewName($name) : $name;
        $file = $this->registry->file($viewName);
        $escape = $this->escape;
        $assets = $this->assets;
        $renderer = $this;
        $level = ob_get_level();
        ob_start();
        try {
            (static function (
                string $template,
                ViewData $view,
                HtmlEscaper $escape,
                Translator $translator,
                AssetUrlGenerator $assets,
                PhpViewRenderer $renderer,
            ): void {
                require $template;
            })($file, $view, $escape, $translator, $assets, $renderer);
            $html = ob_get_clean();
            if (!is_string($html)) {
                throw new RuntimeException('View rendering failed.');
            }

            return SafeHtml::fromTrustedTemplate($html);
        } catch (Throwable $throwable) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw new RuntimeException('View rendering failed.', 0, $throwable);
        }
    }
}
