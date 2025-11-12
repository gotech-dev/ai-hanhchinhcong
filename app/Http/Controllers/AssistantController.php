<?php

namespace App\Http\Controllers;

use App\Models\AiAssistant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AssistantController extends Controller
{
    /**
     * List all active assistants
     */
    public function index(Request $request)
    {
        $query = AiAssistant::where('is_active', true)
            ->with('admin');
        
        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('assistant_type', $request->input('type'));
        }
        
        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->input('search') . '%');
        }
        
        $assistants = $query->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return response()->json([
            'assistants' => $assistants,
        ]);
    }

    /**
     * Get assistant details
     */
    public function show(int $id)
    {
        $assistant = AiAssistant::where('id', $id)
            ->where('is_active', true)
            ->with(['admin', 'documents'])
            ->firstOrFail();
        
        return response()->json([
            'assistant' => $assistant,
        ]);
    }

    /**
     * Create new assistant (for admin)
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assistant_type' => ['required', 'string', Rule::in(\App\Enums\AssistantType::all())],
            'template_file' => 'nullable|file|mimes:pdf,doc,docx|max:10240', // 10MB max
            'documents' => 'nullable|array',
            'documents.*' => 'file|mimes:pdf,doc,docx|max:10240',
            'config' => 'nullable|array',
            'avatar_url' => 'nullable|url',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }
        
        $data = $validator->validated();
        
        // Note: report_generator has been merged into document_drafting
        // Template files are now handled via document_templates table
        
        // Handle documents upload (for Q&A based)
        if ($request->hasFile('documents') && $data['assistant_type'] === 'qa_based_document') {
            $documents = [];
            foreach ($request->file('documents') as $doc) {
                $path = $doc->store('documents', 'public');
                $documents[] = [
                    'name' => $doc->getClientOriginalName(),
                    'path' => Storage::disk('public')->url($path),
                ];
            }
            $data['documents'] = $documents;
        }
        
        $assistant = AiAssistant::create([
            'admin_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'assistant_type' => $data['assistant_type'],
            'template_file_path' => $data['template_file_path'] ?? null,
            'documents' => $data['documents'] ?? null,
            'config' => $data['config'] ?? [],
            'avatar_url' => $data['avatar_url'] ?? null,
            'is_active' => true,
        ]);
        
        return response()->json([
            'assistant' => $assistant->load('admin'),
            'message' => 'Assistant created successfully',
        ], 201);
    }

    /**
     * Update assistant (for admin)
     */
    public function update(Request $request, int $id)
    {
        $user = Auth::user();
        
        $assistant = AiAssistant::where('id', $id)
            ->where('admin_id', $user->id)
            ->firstOrFail();
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'assistant_type' => ['sometimes', 'string', Rule::in(\App\Enums\AssistantType::all())],
            'template_file' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
            'documents' => 'nullable|array',
            'documents.*' => 'file|mimes:pdf,doc,docx|max:10240',
            'config' => 'nullable|array',
            'avatar_url' => 'nullable|url',
            'is_active' => 'sometimes|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422);
        }
        
        $data = $validator->validated();
        
        // Handle template file upload
        if ($request->hasFile('template_file')) {
            // Delete old template if exists
            if ($assistant->template_file_path) {
                $oldPath = str_replace('/storage/', '', parse_url($assistant->template_file_path, PHP_URL_PATH));
                Storage::disk('public')->delete($oldPath);
            }
            
            $templateFile = $request->file('template_file');
            $path = $templateFile->store('templates', 'public');
            $data['template_file_path'] = Storage::disk('public')->url($path);
        }
        
        // Handle documents upload
        if ($request->hasFile('documents')) {
            $documents = [];
            foreach ($request->file('documents') as $doc) {
                $path = $doc->store('documents', 'public');
                $documents[] = [
                    'name' => $doc->getClientOriginalName(),
                    'path' => Storage::disk('public')->url($path),
                ];
            }
            $data['documents'] = array_merge($assistant->documents ?? [], $documents);
        }
        
        $assistant->update($data);
        
        return response()->json([
            'assistant' => $assistant->load('admin'),
            'message' => 'Assistant updated successfully',
        ]);
    }

    /**
     * Delete assistant (for admin)
     */
    public function destroy(int $id)
    {
        $user = Auth::user();
        
        $assistant = AiAssistant::where('id', $id)
            ->where('admin_id', $user->id)
            ->firstOrFail();
        
        // Delete associated files
        if ($assistant->template_file_path) {
            $oldPath = str_replace('/storage/', '', parse_url($assistant->template_file_path, PHP_URL_PATH));
            Storage::disk('public')->delete($oldPath);
        }
        
        // Delete documents
        if ($assistant->documents) {
            foreach ($assistant->documents as $doc) {
                if (isset($doc['path'])) {
                    $oldPath = str_replace('/storage/', '', parse_url($doc['path'], PHP_URL_PATH));
                    Storage::disk('public')->delete($oldPath);
                }
            }
        }
        
        $assistant->delete();
        
        return response()->json([
            'message' => 'Assistant deleted successfully',
        ]);
    }

    /**
     * Get assistant statistics
     */
    public function getStatistics(int $id)
    {
        $assistant = AiAssistant::findOrFail($id);
        
        $stats = [
            'total_sessions' => $assistant->chatSessions()->count(),
            'total_messages' => $assistant->chatSessions()
                ->withCount('messages')
                ->get()
                ->sum('messages_count'),
            'total_documents' => $assistant->documents()->count(),
            'total_reports' => $assistant->chatSessions()
                ->withCount('reports')
                ->get()
                ->sum('reports_count'),
        ];
        
        return response()->json([
            'statistics' => $stats,
        ]);
    }
}






