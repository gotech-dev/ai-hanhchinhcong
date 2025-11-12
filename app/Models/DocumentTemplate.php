<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplate extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'ai_assistant_id',
        'document_type',
        'template_subtype',
        'name',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'metadata',
        'is_active',
    ];
    
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }
    
    /**
     * Get the assistant that owns this template.
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(AiAssistant::class, 'ai_assistant_id');
    }
}
