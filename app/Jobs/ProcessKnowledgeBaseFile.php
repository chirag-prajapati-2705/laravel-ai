<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessKnowledgeBaseFile implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum number of attempts before the job is marked as failed.
     */
    public int $tries = 3;

    /**
     * Maximum seconds the job may run before it is killed.
     */
    public int $timeout = 300;

    /**
     * Seconds to wait before retrying after a failure.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public \App\Models\KnowledgeBaseFile $file
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        \App\Services\PdfService $pdfService,
        \App\Services\RagService $ragService
    ): void {
        try {
            $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($this->file->path);
            $text = $pdfService->extractText($fullPath);

            if ($text !== '') {
                $ragService->ingestDocuments([
                    [
                        'source' => $this->file->name,
                        'content' => $text,
                    ],
                ], knowledgeBaseFileId: $this->file->id);
            }

            $this->file->update(['status' => 'completed']);
        } catch (\Throwable $e) {
            $this->file->update(['status' => 'failed']);
            \Illuminate\Support\Facades\Log::error('RAG Ingestion failed: '.$e->getMessage());
            throw $e;
        }
    }
}
