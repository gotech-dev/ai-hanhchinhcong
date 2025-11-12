<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistantType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'system_prompt',        // ✅ MỚI: System prompt mặc định cho loại trợ lý
        'system_prompt_template', // ✅ MỚI: Template prompt với placeholders
        'is_active',
        'icon',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get all assistants of this type
     */
    public function assistants(): HasMany
    {
        return $this->hasMany(AiAssistant::class, 'assistant_type', 'code');
    }
}

