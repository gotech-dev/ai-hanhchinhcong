<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdministrativeDocument extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ai_assistant_id',
        'user_id',
        'so_van_ban',
        'ngay_van_ban',
        'loai_van_ban',
        'document_type',
        'noi_gui',
        'noi_nhan',
        'trich_yeu',
        'muc_do',
        'thoi_han_xu_ly',
        'deadline',
        'nguoi_xu_ly_id',
        'phong_ban_xu_ly',
        'trang_thai',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'metadata',
        'classification',
        'storage_path',
        'is_archived',
    ];
    
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ngay_van_ban' => 'date',
            'deadline' => 'date',
            'metadata' => 'array',
            'classification' => 'array',
            'file_size' => 'integer',
            'thoi_han_xu_ly' => 'integer',
            'is_archived' => 'boolean',
        ];
    }
    
    /**
     * Get the AI assistant that owns this document.
     */
    public function aiAssistant(): BelongsTo
    {
        return $this->belongsTo(AiAssistant::class);
    }
    
    /**
     * Get the user who created/processed this document.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the user assigned to process this document.
     */
    public function nguoiXuLy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nguoi_xu_ly_id');
    }
    
    /**
     * Check if document is overdue
     */
    public function isOverdue(): bool
    {
        if (!$this->deadline) {
            return false;
        }
        
        return $this->trang_thai !== 'da_xu_ly' && now()->isAfter($this->deadline);
    }
    
    /**
     * Check if document is due today
     */
    public function isDueToday(): bool
    {
        if (!$this->deadline) {
            return false;
        }
        
        return $this->deadline->isToday() && $this->trang_thai !== 'da_xu_ly';
    }
    
    /**
     * Get days until deadline
     */
    public function getDaysUntilDeadlineAttribute(): ?int
    {
        if (!$this->deadline) {
            return null;
        }
        
        return now()->diffInDays($this->deadline, false);
    }
}
