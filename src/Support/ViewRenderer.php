<?php

declare(strict_types=1);

namespace App\Support;

use Slim\Views\PhpRenderer;

final class ViewRenderer extends PhpRenderer
{
    public function templateExists(string $template): bool
    {
        $path = $this->templatePath . $template;
        if (!is_file($path)) {
            return false;
        }

        if (is_readable($path)) {
            return true;
        }

        $handle = @fopen($path, 'r');
        if ($handle !== false) {
            fclose($handle);
            return true;
        }

        return false;
    }
}
