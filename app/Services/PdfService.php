<?php

declare(strict_types=1);

namespace App\Services;

use Spatie\PdfToText\Pdf;
use Throwable;

class PdfService
{
    public function extractText(string $path): string
    {
        try {
            $binPath = config('app.pdftotext_path');

            if ($binPath) {
                return (new Pdf($binPath))->setPdf($path)->text();
            }

            return Pdf::getText($path);
        } catch (Throwable $e) {
            return '';
        }
    }
}
