<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\AiAssistant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function assistants(Request $request): Response
    {
        $user = Auth::user();
        
        $assistants = AiAssistant::where('admin_id', $user->id)
            ->withCount(['chatSessions', 'documents'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return Inertia::render('Admin/Assistants', [
            'assistants' => $assistants,
        ]);
    }

    public function createAssistant(Request $request): Response
    {
        $assistantTypes = \App\Models\AssistantType::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        
        return Inertia::render('Admin/CreateAssistant', [
            'assistantTypes' => $assistantTypes,
        ]);
    }

    public function editAssistant(Request $request, int $assistantId): Response
    {
        $user = Auth::user();
        
        $assistant = AiAssistant::where('id', $assistantId)
            ->where('admin_id', $user->id)
            ->with(['documents' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->with(['documentTemplates' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('name');
            }])
            ->with(['referenceUrls' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->firstOrFail();
        
        $documents = $assistant->documents ?? collect([]);
        $documentTemplates = $assistant->documentTemplates ?? collect([]);
        $referenceUrls = $assistant->referenceUrls ?? collect([]);
        
        $assistantTypes = \App\Models\AssistantType::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        
        return Inertia::render('Admin/EditAssistant', [
            'assistant' => $assistant,
            'documents' => $documents->toArray(),
            'documentTemplates' => $documentTemplates->toArray(),
            'referenceUrls' => $referenceUrls->toArray(),
            'assistantTypes' => $assistantTypes,
        ]);
    }

    public function updateAssistant(Request $request, int $assistantId)
    {
        // Delegate to the non-Inertia AdminController for update logic
        $adminController = app(\App\Http\Controllers\AdminController::class);
        
        return $adminController->updateAssistant($request, $assistantId);
    }

    public function previewAssistant(Request $request, int $assistantId): Response
    {
        $user = Auth::user();
        
        $assistant = AiAssistant::where('id', $assistantId)
            ->where('admin_id', $user->id)
            ->with(['documents' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->with(['documentTemplates' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('name');
            }])
            ->firstOrFail();
        
        $documents = $assistant->documents ?? collect([]);
        $documentTemplates = $assistant->documentTemplates ?? collect([]);
        
        return Inertia::render('Admin/PreviewAssistant', [
            'assistant' => $assistant,
            'documents' => $documents->toArray(),
            'documentTemplates' => $documentTemplates->toArray(),
        ]);
    }

    public function assistantTypes(Request $request): Response
    {
        $assistantTypes = \App\Models\AssistantType::orderBy('sort_order')
            ->orderBy('name')
            ->get();
        
        return Inertia::render('Admin/AssistantTypes', [
            'assistantTypes' => $assistantTypes,
        ]);
    }

    public function createAssistantType(Request $request): Response
    {
        return Inertia::render('Admin/CreateAssistantType');
    }

    public function editAssistantType(Request $request, int $typeId): Response
    {
        $assistantType = \App\Models\AssistantType::findOrFail($typeId);
        
        return Inertia::render('Admin/EditAssistantType', [
            'assistantType' => $assistantType,
        ]);
    }
}

