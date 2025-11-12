<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantReferenceUrl extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ai_assistant_id',
        'url',
        'title',
        'description',
        'status',
        'crawled_content',
        'content_length',
        'last_crawled_at',
        'error_message',
    ];
    
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_crawled_at' => 'datetime',
            'content_length' => 'integer',
        ];
    }
    
    /**
     * Get the AI assistant that owns this reference URL.
     */
    public function aiAssistant(): BelongsTo
    {
        return $this->belongsTo(AiAssistant::class);
    }
}
