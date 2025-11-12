<?php

namespace App\Services;

use App\Models\AdministrativeDocument;
use App\Models\AiAssistant;
use Illuminate\Support\Facades\Log;

class DocumentReminderService
{
    /**
     * Get reminders for documents
     * 
     * @param AiAssistant $assistant
     * @param int $daysBefore Days before deadline to remind
     * @return array
     */
    public function getReminders(AiAssistant $assistant, int $daysBefore = 1): array
    {
        $reminderDate = now()->addDays($daysBefore);
        
        $documents = AdministrativeDocument::where('ai_assistant_id', $assistant->id)
            ->where('deadline', '<=', $reminderDate)
            ->where('deadline', '>=', now())
            ->where('trang_thai', '!=', 'da_xu_ly')
            ->orderBy('deadline', 'asc')
            ->get();
        
        return $documents->map(function ($doc) {
            return [
                'id' => $doc->id,
                'so_van_ban' => $doc->so_van_ban,
                'trich_yeu' => $doc->trich_yeu,
                'deadline' => $doc->deadline->format('d/m/Y'),
                'days_until_deadline' => $doc->days_until_deadline,
                'muc_do' => $doc->muc_do,
                'phong_ban_xu_ly' => $doc->phong_ban_xu_ly,
                'noi_gui' => $doc->noi_gui,
                'noi_nhan' => $doc->noi_nhan,
            ];
        })->toArray();
    }
    
    /**
     * Get overdue documents
     */
    public function getOverdueDocuments(AiAssistant $assistant): array
    {
        $documents = AdministrativeDocument::where('ai_assistant_id', $assistant->id)
            ->where('deadline', '<', now())
            ->where('trang_thai', '!=', 'da_xu_ly')
            ->orderBy('deadline', 'asc')
            ->get();
        
        return $documents->map(function ($doc) {
            return [
                'id' => $doc->id,
                'so_van_ban' => $doc->so_van_ban,
                'trich_yeu' => $doc->trich_yeu,
                'deadline' => $doc->deadline->format('d/m/Y'),
                'days_overdue' => abs($doc->days_until_deadline ?? 0),
                'muc_do' => $doc->muc_do,
                'phong_ban_xu_ly' => $doc->phong_ban_xu_ly,
                'noi_gui' => $doc->noi_gui,
                'noi_nhan' => $doc->noi_nhan,
            ];
        })->toArray();
    }
    
    /**
     * Get documents due today
     */
    public function getDocumentsDueToday(AiAssistant $assistant): array
    {
        $documents = AdministrativeDocument::where('ai_assistant_id', $assistant->id)
            ->where('deadline', today())
            ->where('trang_thai', '!=', 'da_xu_ly')
            ->orderBy('muc_do', 'desc')
            ->orderBy('deadline', 'asc')
            ->get();
        
        return $documents->map(function ($doc) {
            return [
                'id' => $doc->id,
                'so_van_ban' => $doc->so_van_ban,
                'trich_yeu' => $doc->trich_yeu,
                'deadline' => $doc->deadline->format('d/m/Y'),
                'muc_do' => $doc->muc_do,
                'phong_ban_xu_ly' => $doc->phong_ban_xu_ly,
                'noi_gui' => $doc->noi_gui,
                'noi_nhan' => $doc->noi_nhan,
            ];
        })->toArray();
    }
    
    /**
     * Format reminder message
     */
    public function formatReminderMessage(array $reminders, array $overdue = [], array $dueToday = []): string
    {
        $message = '';
        
        if (!empty($dueToday)) {
            $message .= "⏰ **NHẮC NHỞ: Có " . count($dueToday) . " văn bản cần xử lý trong hôm nay:**\n\n";
            foreach ($dueToday as $doc) {
                $message .= "1. " . ($doc['so_van_ban'] ?? 'Chưa có số') . " - " . ($doc['trich_yeu'] ?? 'N/A') . "\n";
                $message .= "   - Thời hạn: Hôm nay (" . $doc['deadline'] . ")\n";
                if ($doc['phong_ban_xu_ly']) {
                    $message .= "   - Người xử lý: " . $doc['phong_ban_xu_ly'] . "\n";
                }
                $message .= "\n";
            }
        }
        
        if (!empty($overdue)) {
            $message .= "⚠️ **CẢNH BÁO: Có " . count($overdue) . " văn bản đã quá hạn:**\n\n";
            foreach ($overdue as $doc) {
                $message .= "1. " . ($doc['so_van_ban'] ?? 'Chưa có số') . " - " . ($doc['trich_yeu'] ?? 'N/A') . "\n";
                $message .= "   - Thời hạn: " . $doc['deadline'] . " (Quá hạn " . $doc['days_overdue'] . " ngày)\n";
                if ($doc['phong_ban_xu_ly']) {
                    $message .= "   - Người xử lý: " . $doc['phong_ban_xu_ly'] . "\n";
                }
                $message .= "\n";
            }
        }
        
        if (!empty($reminders)) {
            $message .= "📋 **Sắp đến hạn (trong " . count($reminders) . " ngày tới):**\n\n";
            foreach ($reminders as $doc) {
                $message .= "1. " . ($doc['so_van_ban'] ?? 'Chưa có số') . " - " . ($doc['trich_yeu'] ?? 'N/A') . "\n";
                $message .= "   - Thời hạn: " . $doc['deadline'] . " (Còn " . $doc['days_until_deadline'] . " ngày)\n";
                if ($doc['phong_ban_xu_ly']) {
                    $message .= "   - Người xử lý: " . $doc['phong_ban_xu_ly'] . "\n";
                }
                $message .= "\n";
            }
        }
        
        if (empty($dueToday) && empty($overdue) && empty($reminders)) {
            $message = "✅ Không có văn bản nào cần nhắc nhở trong thời gian này.";
        }
        
        return $message;
    }
}



