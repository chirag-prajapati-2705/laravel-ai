<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Neuron\WindowsPdfReader;
use App\Services\RagService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class RagIngestPdfCommand extends Command
{
    protected $signature = 'rag:ingest-pdf {--recreate : Recreate the Qdrant collection before ingesting PDFs} {--bin-path= : Full path to pdftotext.exe}';

    protected $description = 'Ingest PDFs from storage/app/rag-pdf into the RAG index';

    public function handle(RagService $service, WindowsPdfReader $reader): int
    {
        $binPath = $this->option('bin-path');

        if (is_string($binPath) && $binPath !== '') {
            $reader->setBinPath($binPath);
        }

        $disk = Storage::disk('rag');
        $paths = $disk->allFiles('rag-pdf');
        // dd($disk,$paths);

        $paths = array_values(array_filter($paths, function (string $path): bool {
            return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf';
        }));

        if ($paths === []) {
            $this->error('No PDF files found in storage/app/rag-pdf.');

            return self::FAILURE;
        }

        $documents = [];

        foreach ($paths as $path) {
            try {
                $fullPath = $disk->path($path);
                $text = trim($reader->setPdf($fullPath)->text());

                if ($text === '') {
                    $this->warn("Skipped empty PDF: {$path}");

                    continue;
                }

                $documents[] = [
                    'source' => $path,
                    'content' => $text,
                ];
            } catch (Throwable $exception) {
                dd($exception->getMessage());
                $this->error("Failed to read {$path}: ".$exception->getMessage());

                return self::FAILURE;
            }
        }

        try {
            $count = $service->ingestDocuments($documents, (bool) $this->option('recreate'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Indexed {$count} chunks.");

        return self::SUCCESS;
    }
}
