<?php

namespace App\Http\Controllers\Inertia;

use App\Http\Controllers\Controller;
use App\Models\AiAssistant;
use App\Models\ChatSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $user = Auth::user();
        
        // Get all active assistants
        $assistants = AiAssistant::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get user's chat sessions
        $sessions = ChatSession::where('user_id', $user->id)
            ->with(['aiAssistant', 'latestMessage'])
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get();
        
        return Inertia::render('Chat/IndexNew', [
            'assistants' => $assistants,
            'sessions' => $sessions,
        ]);
    }

    public function index(Request $request, int $sessionId): Response
    {
        $user = Auth::user();
        
        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->with(['messages' => function ($query) {
                $query->orderBy('created_at');
            }, 'aiAssistant'])
            ->firstOrFail();
        
        // Get all active assistants for the dashboard
        $assistants = AiAssistant::where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get user's chat sessions
        $sessions = ChatSession::where('user_id', $user->id)
            ->with(['aiAssistant', 'latestMessage'])
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get();
        
        return Inertia::render('Chat/Dashboard', [
            'assistants' => $assistants,
            'sessions' => $sessions,
            'currentSession' => $session,
        ]);
    }
}

