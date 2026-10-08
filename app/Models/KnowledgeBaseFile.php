<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBaseFile extends Model
{
    /** @use HasFactory<\Database\Factories\KnowledgeBaseFileFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'path',
        'size',
        'status',
    ];

    /**
     * @return HasMany<ChatSession, $this>
     */
    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    /**
     * @return HasMany<RagChunk, $this>
     */
    public function ragChunks(): HasMany
    {
        return $this->hasMany(RagChunk::class);
    }
}
