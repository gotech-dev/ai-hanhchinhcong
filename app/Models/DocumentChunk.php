<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assistant_document_id',
        'chunk_index',
        'content',
        'embedding',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'metadata' => 'array',
            'chunk_index' => 'integer',
        ];
    }

    /**
     * Get the document that owns this chunk.
     */
    public function assistantDocument(): BelongsTo
    {
        return $this->belongsTo(AssistantDocument::class);
    }

    /**
     * Check if chunk has embedding.
     */
    public function hasEmbedding(): bool
    {
        return !empty($this->embedding);
    }
}
