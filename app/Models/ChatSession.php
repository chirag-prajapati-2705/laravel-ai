<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    /** @use HasFactory<\Database\Factories\ChatSessionFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'knowledge_base_file_id',
        'title',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<KnowledgeBaseFile, $this>
     */
    public function knowledgeBaseFile(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseFile::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<KnowledgeBaseFile, $this>
     */
    public function knowledgeBaseFiles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(KnowledgeBaseFile::class, 'chat_session_knowledge_base_file');
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }
}
