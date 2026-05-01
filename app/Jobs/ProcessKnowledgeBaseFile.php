<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessKnowledgeBaseFile implements ShouldQueue
{
    use Queueable;

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
