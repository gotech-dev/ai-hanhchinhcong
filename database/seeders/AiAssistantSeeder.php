<?php

namespace Database\Seeders;

use App\Models\AiAssistant;
use App\Models\User;
use Illuminate\Database\Seeder;

class AiAssistantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy admin user (từ UserSeeder)
        $admin = User::where('email', 'admin@gotechjsc.com')->first();
        
        if (!$admin) {
            $this->command->error('Admin user not found. Please run UserSeeder first.');
            return;
        }

        $assistants = [
            // Q&A Based Document
            [
                'name' => 'Trợ lý Q&A Tài liệu',
                'description' => 'Trả lời câu hỏi dựa trên tài liệu đã được upload. Hỗ trợ tìm kiếm và trích xuất thông tin từ các văn bản, tài liệu hành chính.',
                'assistant_type' => 'qa_based_document',
                'greeting_message' => 'Xin chào! Tôi là trợ lý Q&A tài liệu. Tôi có thể trả lời các câu hỏi dựa trên tài liệu bạn đã upload. Hãy đặt câu hỏi cho tôi!',
                'config' => [
                    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                ],
                'is_active' => true,
            ],

            // Document Drafting
            [
                'name' => 'Trợ lý Soạn thảo Văn bản',
                'description' => 'Soạn thảo các loại văn bản hành chính: Công văn, Quyết định, Tờ trình, Báo cáo, Biên bản, Thông báo, Nghị quyết.',
                'assistant_type' => 'document_drafting',
                'greeting_message' => 'Xin chào! Tôi là trợ lý soạn thảo văn bản hành chính. Tôi có thể giúp bạn soạn thảo các loại văn bản như Công văn, Quyết định, Tờ trình, Báo cáo... Bạn muốn soạn thảo loại văn bản nào?',
                'config' => [
                    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                ],
                'is_active' => true,
            ],

            // Document Management
            [
                'name' => 'Trợ lý Quản lý Văn bản',
                'description' => 'Quản lý văn bản đến, văn bản đi, phân loại, lưu trữ và nhắc nhở thời hạn xử lý.',
                'assistant_type' => 'document_management',
                'greeting_message' => 'Xin chào! Tôi là trợ lý quản lý văn bản. Tôi có thể giúp bạn quản lý văn bản đến, văn bản đi, phân loại và nhắc nhở thời hạn xử lý.',
                'config' => [
                    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                ],
                'is_active' => true,
            ],

            // HR Management
            [
                'name' => 'Trợ lý Quản lý Nhân sự',
                'description' => 'Quản lý nhân sự: tính lương, chấm công, nghỉ phép, báo cáo nhân sự.',
                'assistant_type' => 'hr_management',
                'greeting_message' => 'Xin chào! Tôi là trợ lý quản lý nhân sự. Tôi có thể giúp bạn quản lý nhân sự, tính lương, chấm công, nghỉ phép và tạo báo cáo nhân sự.',
                'config' => [
                    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                ],
                'is_active' => true,
            ],

            // Finance Management
            [
                'name' => 'Trợ lý Quản lý Tài chính',
                'description' => 'Quản lý tài chính: lập dự toán, theo dõi thu chi, cảnh báo vượt ngân sách.',
                'assistant_type' => 'finance_management',
                'greeting_message' => 'Xin chào! Tôi là trợ lý quản lý tài chính. Tôi có thể giúp bạn lập dự toán, theo dõi thu chi và cảnh báo vượt ngân sách.',
                'config' => [
                    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                ],
                'is_active' => true,
            ],

            // Project Management
            [
                'name' => 'Trợ lý Quản lý Dự án',
                'description' => 'Quản lý dự án đầu tư công: theo dõi tiến độ, ngân sách, phân tích rủi ro.',
                'assistant_type' => 'project_management',
                'greeting_message' => 'Xin chào! Tôi là trợ lý quản lý dự án đầu tư công. Tôi có thể giúp bạn theo dõi tiến độ, ngân sách và phân tích rủi ro dự án.',
                'config' => [
                    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                ],
                'is_active' => true,
            ],

            // Complaint Management
            [
                'name' => 'Trợ lý Quản lý Khiếu nại',
                'description' => 'Quản lý khiếu nại và tố cáo: tiếp nhận, phân loại, theo dõi tiến độ giải quyết.',
                'assistant_type' => 'complaint_management',
                'greeting_message' => 'Xin chào! Tôi là trợ lý quản lý khiếu nại và tố cáo. Tôi có thể giúp bạn tiếp nhận, phân loại và theo dõi tiến độ giải quyết khiếu nại.',
                'config' => [
                    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                ],
                'is_active' => true,
            ],

            // Event Management
            [
                'name' => 'Trợ lý Tổ chức Sự kiện',
                'description' => 'Tổ chức sự kiện và hội nghị: lập kế hoạch, quản lý khách mời, gửi thư mời tự động.',
                'assistant_type' => 'event_management',
                'greeting_message' => 'Xin chào! Tôi là trợ lý tổ chức sự kiện và hội nghị. Tôi có thể giúp bạn lập kế hoạch, quản lý khách mời và gửi thư mời tự động.',
                'config' => [
                    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                ],
                'is_active' => true,
            ],

            // Asset Management
            [
                'name' => 'Trợ lý Quản lý Tài sản',
                'description' => 'Quản lý tài sản công: theo dõi bảo trì, kiểm kê định kỳ, báo cáo tài sản.',
                'assistant_type' => 'asset_management',
                'greeting_message' => 'Xin chào! Tôi là trợ lý quản lý tài sản công. Tôi có thể giúp bạn theo dõi bảo trì, kiểm kê định kỳ và tạo báo cáo tài sản.',
                'config' => [
                    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                ],
                'is_active' => true,
            ],
        ];

        foreach ($assistants as $assistantData) {
            $assistant = AiAssistant::updateOrCreate(
                [
                    'name' => $assistantData['name'],
                    'admin_id' => $admin->id,
                ],
                array_merge($assistantData, [
                    'admin_id' => $admin->id,
                ])
            );

            $this->command->info("✅ Đã tạo/cập nhật trợ lý: {$assistantData['name']} ({$assistantData['assistant_type']})");
        }

        $this->command->info("\n🎉 Đã tạo thành công " . count($assistants) . " trợ lý!");
    }
}

