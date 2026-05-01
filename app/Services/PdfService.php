<?php

declare(strict_types=1);

namespace App\Services;

use Smalot\PdfParser\Parser;
use Throwable;

class PdfService
{
    public function extractText(string $path): string
    {
        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($path);

            return $pdf->getText();
        } catch (Throwable $e) {
            return '';
        }
    }
}
