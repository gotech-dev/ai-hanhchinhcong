<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\AiAssistant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    public function index(Request $request): Response
    {
        $assistants = AiAssistant::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return Inertia::render('Assistants/Index', [
            'assistants' => $assistants,
        ]);
    }
}








