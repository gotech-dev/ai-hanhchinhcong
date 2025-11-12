<?php

namespace App\Services;

use App\Enums\DocumentType;
use Illuminate\Support\Facades\Log;

class DocumentFormatChecker
{
    /**
     * Check document format compliance
     * 
     * @param array $documentData Document data
     * @param DocumentType $documentType Document type
     * @return array{is_valid: bool, errors: array, warnings: array, suggestions: array}
     */
    public function check(array $documentData, DocumentType $documentType): array
    {
        $errors = [];
        $warnings = [];
        $suggestions = [];
        
        // Check required fields
        $requiredFields = $this->getRequiredFields($documentType);
        foreach ($requiredFields as $field) {
            if (!isset($documentData[$field]) || empty($documentData[$field])) {
                $errors[] = "Thiếu trường bắt buộc: {$field}";
            }
        }
        
        // Check document number format
        if (isset($documentData['so_van_ban'])) {
            if (!$this->isValidDocumentNumber($documentData['so_van_ban'])) {
                $errors[] = "Số văn bản không đúng format. Ví dụ: 01/CV-ABC";
                $suggestions[] = "Sửa số văn bản theo format: Số/Ký hiệu-Cơ quan";
            }
        }
        
        // Check date format
        if (isset($documentData['ngay_thang'])) {
            if (!$this->isValidDate($documentData['ngay_thang'])) {
                $errors[] = "Ngày tháng không đúng format. Ví dụ: 15/12/2024";
                $suggestions[] = "Sửa ngày tháng theo format: dd/mm/yyyy";
            }
        }
        
        // Check organization name
        if (isset($documentData['ten_co_quan'])) {
            if (strlen($documentData['ten_co_quan']) < 5) {
                $warnings[] = "Tên cơ quan quá ngắn, cần kiểm tra lại";
            }
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'suggestions' => $suggestions,
        ];
    }
    
    /**
     * Get required fields for document type
     */
    protected function getRequiredFields(DocumentType $documentType): array
    {
        return match($documentType) {
            DocumentType::CONG_VAN => ['so_van_ban', 'ngay_thang', 'noi_nhan', 'noi_dung', 'nguoi_ky'],
            DocumentType::QUYET_DINH => ['so_van_ban', 'ngay_thang', 'can_cu', 'quyet_dinh', 'nguoi_ky'],
            DocumentType::TO_TRINH => ['so_van_ban', 'ngay', 'noi_gui', 'muc_dich', 'du_toan', 'nguoi_ky'],
            DocumentType::BAO_CAO => ['so_van_ban', 'ngay', 'noi_nhan', 'noi_dung', 'ket_luan', 'nguoi_ky'],
            DocumentType::BIEN_BAN => ['so_van_ban', 'ngay', 'dia_diem', 'thanh_phan', 'noi_dung', 'ket_luan'],
            DocumentType::THONG_BAO => ['so_van_ban', 'ngay', 'noi_nhan', 'noi_dung', 'nguoi_ky'],
            DocumentType::NGHI_QUYET => ['so_van_ban', 'ngay', 'can_cu', 'nghi_quyet', 'nguoi_ky'],
        };
    }
    
    /**
     * Check if document number is valid
     */
    protected function isValidDocumentNumber(string $number): bool
    {
        // Format: Số/Ký hiệu-Cơ quan (e.g., 01/CV-ABC)
        return preg_match('/^\d{1,3}\/[A-Z]{2,10}-[A-Z]{2,10}$/i', $number);
    }
    
    /**
     * Check if date is valid
     */
    protected function isValidDate(string $date): bool
    {
        // Format: dd/mm/yyyy
        if (!preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $date)) {
            return false;
        }
        
        [$day, $month, $year] = explode('/', $date);
        return checkdate((int)$month, (int)$day, (int)$year);
    }
}

