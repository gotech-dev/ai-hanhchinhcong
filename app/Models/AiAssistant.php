<?php

namespace App\Models;

use App\Enums\AssistantType as AssistantTypeEnum;
use App\Models\AssistantType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAssistant extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'admin_id',
        'name',
        'description',
        'greeting_message',
        'assistant_type',
        'template_file_path',
        'documents',
        'config',
        'system_prompt_override', // ✅ MỚI: Override system prompt mặc định của loại
        'avatar_url',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // ✅ FIX: Bỏ cast enum vì assistant_type giờ là string từ bảng assistant_types
            // 'assistant_type' => AssistantType::class, // Đã bỏ - dùng relationship thay thế
            'documents' => 'array',
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }
    
    /**
     * Get the assistant type model relationship
     * ✅ MỚI: Relationship với bảng assistant_types
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(AssistantType::class, 'assistant_type', 'code');
    }
    
    /**
     * Get assistant type display name
     * ✅ CẢI TIẾN: Lấy từ relationship hoặc enum (backward compatibility)
     */
    public function getTypeDisplayNameAttribute(): string
    {
        // Ưu tiên lấy từ bảng assistant_types
        if ($this->type) {
            return $this->type->name;
        }
        
        // Fallback về enum nếu là giá trị cũ
        try {
            $enumValue = AssistantTypeEnum::tryFrom($this->attributes['assistant_type'] ?? '');
            if ($enumValue) {
                return $enumValue->displayName();
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        return $this->attributes['assistant_type'] ?? '';
    }
    
    /**
     * Get assistant type description
     * ✅ CẢI TIẾN: Lấy từ relationship hoặc enum (backward compatibility)
     */
    public function getTypeDescriptionAttribute(): string
    {
        // Ưu tiên lấy từ bảng assistant_types
        if ($this->type) {
            return $this->type->description ?? '';
        }
        
        // Fallback về enum nếu là giá trị cũ
        try {
            $enumValue = AssistantTypeEnum::tryFrom($this->attributes['assistant_type'] ?? '');
            if ($enumValue) {
                return $enumValue->description();
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        return '';
    }
    
    /**
     * Get assistant type value as string
     * ✅ MỚI: Helper method để lấy giá trị string của assistant_type
     * Thay thế cho ->value khi dùng enum
     */
    public function getAssistantTypeValue(): string
    {
        // Lấy trực tiếp từ attributes (string)
        return $this->attributes['assistant_type'] ?? '';
    }

    /**
     * Get the admin user that owns the assistant.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Get all chat sessions for this assistant.
     */
    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    /**
     * Get all documents for this assistant.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(AssistantDocument::class);
    }
    
    /**
     * Get all document templates for this assistant.
     */
    public function documentTemplates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class);
    }
    
    /**
     * Get all reference URLs for this assistant.
     */
    public function referenceUrls(): HasMany
    {
        return $this->hasMany(AssistantReferenceUrl::class);
    }
}
