<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\RagService;
use Illuminate\Console\Command;
use RuntimeException;

class RagIngestCommand extends Command
{
    protected $signature = 'rag:ingest {--recreate : Recreate the Qdrant collection before ingesting text files}';

    protected $description = 'Ingest static documents from storage/app/rag into the RAG index';

    public function handle(RagService $service): int
    {
        try {
            $count = $service->ingest((bool) $this->option('recreate'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Indexed {$count} chunks.");

        return self::SUCCESS;
    }
}
