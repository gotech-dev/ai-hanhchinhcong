<?php

namespace App\Enums;

enum DocumentType: string
{
    case CONG_VAN = 'cong_van';
    case QUYET_DINH = 'quyet_dinh';
    case TO_TRINH = 'to_trinh';
    case BAO_CAO = 'bao_cao';
    case BIEN_BAN = 'bien_ban';
    case THONG_BAO = 'thong_bao';
    case NGHI_QUYET = 'nghi_quyet';
    
    /**
     * Get display name for document type
     */
    public function displayName(): string
    {
        return match($this) {
            self::CONG_VAN => 'Công văn',
            self::QUYET_DINH => 'Quyết định',
            self::TO_TRINH => 'Tờ trình',
            self::BAO_CAO => 'Báo cáo',
            self::BIEN_BAN => 'Biên bản',
            self::THONG_BAO => 'Thông báo',
            self::NGHI_QUYET => 'Nghị quyết',
        };
    }
    
    /**
     * Get description for document type
     */
    public function description(): string
    {
        return match($this) {
            self::CONG_VAN => 'Công văn đi, công văn đến',
            self::QUYET_DINH => 'Quyết định (bổ nhiệm, khen thưởng, kỷ luật, ...)',
            self::TO_TRINH => 'Tờ trình (xin ý kiến, phê duyệt)',
            self::BAO_CAO => 'Báo cáo (định kỳ, đột xuất)',
            self::BIEN_BAN => 'Biên bản (họp, kiểm tra, nghiệm thu)',
            self::THONG_BAO => 'Thông báo',
            self::NGHI_QUYET => 'Nghị quyết',
        };
    }
    
    /**
     * Get template structure for document type
     */
    public function getTemplateStructure(): array
    {
        return match($this) {
            self::CONG_VAN => [
                'header' => ['so_van_ban', 'ngay_thang', 'noi_nhan'],
                'body' => ['mo_dau', 'noi_dung', 'ket'],
                'footer' => ['nguoi_ky', 'chuc_vu']
            ],
            self::QUYET_DINH => [
                'header' => ['so_quyet_dinh', 'ngay_ky', 'nguoi_ky'],
                'body' => ['can_cu', 'xet_de_nghi', 'quyet_dinh', 'nhiem_vu_quyen_han', 'hieu_luc'],
                'footer' => ['nguoi_ky', 'chuc_vu']
            ],
            self::TO_TRINH => [
                'header' => ['so_to_trinh', 'ngay', 'noi_gui'],
                'body' => ['mo_dau', 'muc_dich', 'thoi_gian_dia_diem', 'thanh_phan', 'du_toan', 'ket'],
                'footer' => ['nguoi_ky', 'chuc_vu']
            ],
            self::BAO_CAO => [
                'header' => ['so_bao_cao', 'ngay', 'noi_nhan'],
                'body' => ['mo_dau', 'noi_dung', 'ket_luan', 'kien_nghi'],
                'footer' => ['nguoi_ky', 'chuc_vu']
            ],
            self::BIEN_BAN => [
                'header' => ['so_bien_ban', 'ngay', 'dia_diem'],
                'body' => ['thanh_phan', 'noi_dung', 'ket_luan'],
                'footer' => ['chu_ky', 'chuc_vu']
            ],
            self::THONG_BAO => [
                'header' => ['so_thong_bao', 'ngay', 'noi_nhan'],
                'body' => ['mo_dau', 'noi_dung', 'ket'],
                'footer' => ['nguoi_ky', 'chuc_vu']
            ],
            self::NGHI_QUYET => [
                'header' => ['so_nghi_quyet', 'ngay', 'noi_nhan'],
                'body' => ['mo_dau', 'can_cu', 'nghi_quyet', 'hieu_luc'],
                'footer' => ['nguoi_ky', 'chuc_vu']
            ],
        };
    }
    
    /**
     * Get all document types as array
     */
    public static function all(): array
    {
        return array_map(
            fn($case) => $case->value,
            self::cases()
        );
    }
}



