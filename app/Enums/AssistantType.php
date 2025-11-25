<?php

namespace App\Enums;

enum AssistantType: string
{
    // Loại hiện có
    case QA_BASED_DOCUMENT = 'qa_based_document';
    
    // Loại mới - Hành chính công Việt Nam
    case DOCUMENT_DRAFTING = 'document_drafting';
    case REPORT_ASSISTANT = 'report_assistant';
    case DOCUMENT_MANAGEMENT = 'document_management';
    case HR_MANAGEMENT = 'hr_management';
    case FINANCE_MANAGEMENT = 'finance_management';
    case PROJECT_MANAGEMENT = 'project_management';
    case COMPLAINT_MANAGEMENT = 'complaint_management';
    case EVENT_MANAGEMENT = 'event_management';
    case ASSET_MANAGEMENT = 'asset_management';
    
    /**
     * Get display name for assistant type
     */
    public function displayName(): string
    {
        return match($this) {
            self::QA_BASED_DOCUMENT => 'Trả lời Q&A từ tài liệu',
            self::DOCUMENT_DRAFTING => 'Soạn thảo Văn bản Hành chính',
            self::REPORT_ASSISTANT => 'Trợ lý Báo cáo',
            self::DOCUMENT_MANAGEMENT => 'Quản lý Văn bản và Lưu trữ',
            self::HR_MANAGEMENT => 'Quản lý Nhân sự',
            self::FINANCE_MANAGEMENT => 'Quản lý Tài chính và Ngân sách',
            self::PROJECT_MANAGEMENT => 'Quản lý Dự án Đầu tư Công',
            self::COMPLAINT_MANAGEMENT => 'Quản lý Khiếu nại và Tố cáo',
            self::EVENT_MANAGEMENT => 'Tổ chức Sự kiện và Hội nghị',
            self::ASSET_MANAGEMENT => 'Quản lý Tài sản Công',
        };
    }
    
    /**
     * Get description for assistant type
     */
    public function description(): string
    {
        return match($this) {
            self::QA_BASED_DOCUMENT => 'Trả lời câu hỏi dựa trên tài liệu đã upload',
            self::DOCUMENT_DRAFTING => 'Soạn thảo các loại văn bản hành chính (Công văn, Quyết định, Tờ trình, Báo cáo, Biên bản, Thông báo, Nghị quyết)',
            self::REPORT_ASSISTANT => 'Trợ lý chuyên tạo và phân tích báo cáo dựa trên tài liệu đã upload (PDF/DOCX)',
            self::DOCUMENT_MANAGEMENT => 'Quản lý văn bản đến, văn bản đi, phân loại, lưu trữ và nhắc nhở thời hạn xử lý',
            self::HR_MANAGEMENT => 'Quản lý nhân sự: tính lương, chấm công, nghỉ phép, báo cáo nhân sự',
            self::FINANCE_MANAGEMENT => 'Quản lý tài chính: lập dự toán, theo dõi thu chi, cảnh báo vượt ngân sách',
            self::PROJECT_MANAGEMENT => 'Quản lý dự án đầu tư công: theo dõi tiến độ, ngân sách, phân tích rủi ro',
            self::COMPLAINT_MANAGEMENT => 'Quản lý khiếu nại và tố cáo: tiếp nhận, phân loại, theo dõi tiến độ giải quyết',
            self::EVENT_MANAGEMENT => 'Tổ chức sự kiện và hội nghị: lập kế hoạch, quản lý khách mời, gửi thư mời tự động',
            self::ASSET_MANAGEMENT => 'Quản lý tài sản công: theo dõi bảo trì, kiểm kê định kỳ, báo cáo tài sản',
        };
    }
    
    /**
     * Get all assistant types as array
     */
    public static function all(): array
    {
        return array_map(
            fn($case) => $case->value,
            self::cases()
        );
    }
    
    /**
     * Get all assistant types with display names
     */
    public static function allWithDisplayNames(): array
    {
        return array_map(
            fn($case) => [
                'value' => $case->value,
                'label' => $case->displayName(),
                'description' => $case->description(),
            ],
            self::cases()
        );
    }
}

