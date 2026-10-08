<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RagChunk;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;
use Laravel\Ai\Streaming\Events\TextDelta;
use RuntimeException;

class RagService
{
    public function __construct(
        private TextSplitter $textSplitter,
    ) {}

    public function ingest(bool $recreateCollection = false): int
    {
        $disk = Storage::disk('rag');
        $paths = $disk->allFiles('rag');

        $paths = array_values(array_filter($paths, function (string $path): bool {
            return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['txt', 'md'], true);
        }));

        if ($paths === []) {
            return 0; // Don't throw error here to allow other document types or empty starts
        }

        $documents = [];

        foreach ($paths as $path) {
            $content = trim((string) $disk->get($path));

            if ($content === '') {
                continue;
            }

            $documents[] = [
                'source' => $path,
                'content' => $content,
            ];
        }

        return $this->ingestDocuments($documents, $recreateCollection);
    }

    /**
     * @return array{answer: string, sources: array<int, string>}
     */
    public function answer(string $question, int|array|null $knowledgeBaseFileId = null): array
    {
        $embedding = $this->embedText($question);

        // Retrieve more for reranking (top 20)
        $matches = $this->retrieveSimilarChunks($embedding, $knowledgeBaseFileId, 20);

        if ($matches->isEmpty()) {
            throw new RuntimeException('No RAG chunks match your question. Please upload more relevant documents.');
        }

        // Rerank to top 4 for the context
        $rerankedMatches = $this->rerankMatches($question, $matches, 4);

        $context = $rerankedMatches
            ->map(fn (RagChunk $chunk): string => $chunk->content)
            ->implode("\n\n");

        $prompt = $this->buildPrompt($question, $context);

        $response = (new AnonymousAgent('', [], []))->prompt($prompt);

        return [
            'answer' => trim($response->text),
            'sources' => $rerankedMatches
                ->map(fn (RagChunk $chunk): string => $chunk->source.'#'.$chunk->chunk_index)
                ->values()
                ->all(),
        ];
    }

    public function streamAnswer(string $question, callable $onChunk, int|array|null $knowledgeBaseFileId = null, ?callable $onThinking = null): array
    {
        if ($onThinking) {
            $onThinking('🔍 Searching knowledge base...');
        }

        $embedding = $this->embedText($question);

        // Retrieve more for reranking
        $matches = $this->retrieveSimilarChunks($embedding, $knowledgeBaseFileId, 20);

        if ($matches->isEmpty()) {
            throw new RuntimeException('No RAG chunks match your question. Please upload more relevant documents.');
        }

        if ($onThinking) {
            $onThinking('📊 Reranking '.$matches->count().' matching snippets for precision...');
        }

        // Rerank to top 4
        $rerankedMatches = $this->rerankMatches($question, $matches, 4);

        $context = $rerankedMatches
            ->map(fn (RagChunk $chunk): string => $chunk->content)
            ->implode("\n\n");

        $prompt = $this->buildPrompt($question, $context);

        (new AnonymousAgent('', [], []))->stream($prompt)->each(function ($event) use ($onChunk) {
            if ($event instanceof TextDelta) {
                $onChunk($event->delta);
            }
        });

        return [
            'sources' => $rerankedMatches
                ->map(fn (RagChunk $chunk): string => $chunk->source.'#'.$chunk->chunk_index)
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, RagChunk>  $matches
     * @return Collection<int, RagChunk>
     */
    private function rerankMatches(string $query, Collection $matches, int $limit): Collection
    {
        if ($matches->isEmpty()) {
            return $matches;
        }

        $response = Reranking::of($matches->pluck('content')->all())
            ->limit($limit)
            ->rerank($query);

        return $response->collect()->map(fn (RankedDocument $result) => $matches->values()[$result->index]);
    }

    /**
     * @param  array<int, array{source: string, content: string}>  $documents
     */
    public function ingestDocuments(array $documents, bool $recreateCollection = false, ?int $knowledgeBaseFileId = null): int
    {
        if ($documents === []) {
            return 0;
        }

        if ($recreateCollection) {
            RagChunk::query()->delete();
        }

        $count = 0;
        $batchSize = 50; // Process 50 chunks at a time

        foreach ($documents as $document) {
            $chunks = $this->chunkText($document['content']);

            $chunkBatches = array_chunk($chunks, $batchSize);

            foreach ($chunkBatches as $batchIndex => $chunkBatch) {
                $embeddings = $this->embedTexts($chunkBatch);

                foreach ($chunkBatch as $indexInBatch => $chunk) {
                    $globalIndex = ($batchIndex * $batchSize) + $indexInBatch;
                    $embedding = $embeddings[$indexInBatch];

                    RagChunk::create([
                        'knowledge_base_file_id' => $knowledgeBaseFileId,
                        'source' => $document['source'],
                        'chunk_index' => $globalIndex,
                        'content' => $chunk,
                        'embedding' => '['.implode(',', $embedding).']',
                    ]);

                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @return array<int, float>
     */
    private function embedText(string $text): array
    {
        return Embeddings::for([$text])->generate()->first();
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    private function embedTexts(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        return Embeddings::for($texts)->generate()->embeddings;
    }

    /**
     * @return array<int, string>
     */
    private function chunkText(string $text): array
    {
        return $this->textSplitter->split(
            $text,
            (int) config('rag.chunk_size', 1000),
            (int) config('rag.chunk_overlap', 200)
        );
    }

    /**
     * @return Collection<int, RagChunk>
     */
    private function retrieveSimilarChunks(array $queryEmbedding, int|array|null $knowledgeBaseFileId = null, ?int $limit = null): Collection
    {
        $maxResults = $limit ?? (int) config('rag.max_results', 4);
        $minSimilarity = (float) config('rag.min_similarity', 0.2);

        $vectorString = '['.implode(',', $queryEmbedding).']';

        // PostgreSQL pgvector cosine similarity search
        $query = RagChunk::query()
            ->select('*')
            ->selectRaw('1 - (embedding <=> ?) as similarity', [$vectorString])
            ->whereRaw('1 - (embedding <=> ?) >= ?', [$vectorString, $minSimilarity]);

        if ($knowledgeBaseFileId !== null) {
            if (is_array($knowledgeBaseFileId)) {
                $query->whereIn('knowledge_base_file_id', $knowledgeBaseFileId);
            } else {
                $query->where('knowledge_base_file_id', $knowledgeBaseFileId);
            }
        }

        return $query
            ->orderBy('similarity', 'desc')
            ->limit($maxResults)
            ->get();
    }

    private function buildPrompt(string $question, string $context): string
    {
        return <<<PROMPT
You are a helpful assistant. Answer using only the context below.

Context:
{$context}

Question:
{$question}

Answer:
PROMPT;
    }
}
