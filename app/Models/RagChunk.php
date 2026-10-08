<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RagChunk extends Model
{
    /** @use HasFactory<\Database\Factories\RagChunkFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'knowledge_base_file_id',
        'source',
        'chunk_index',
        'content',
        'embedding',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // 'embedding' => 'array',
        ];
    }

    /**
     * @return BelongsTo<KnowledgeBaseFile, $this>
     */
    public function knowledgeBaseFile(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseFile::class);
    }
}
