<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendMessageRequest;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\KnowledgeBaseFile;
use App\Services\RagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    /**
     * Show the chat interface for a specific document.
     */
    public function show(Request $request, KnowledgeBaseFile $file, ?ChatSession $session = null): Response
    {
        $file->loadCount('ragChunks');

        if ($session !== null) {
            $session->load('messages');
        }

        $sessions = ChatSession::query()
            ->where('user_id', $request->user()->id)
            ->where('knowledge_base_file_id', $file->id)
            ->latest()
            ->get(['id', 'title', 'created_at']);

        return Inertia::render('KnowledgeBase/ChatShow', [
            'file' => [
                'id' => $file->id,
                'name' => $file->name,
                'status' => $file->status,
                'chunksCount' => $file->rag_chunks_count,
            ],
            'session' => $session ? [
                'id' => $session->id,
                'title' => $session->title,
                'messages' => $session->messages->map(fn (ChatMessage $m) => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'content' => $m->content,
                    'createdAt' => $m->created_at->toIso8601String(),
                ]),
            ] : null,
            'sessions' => $sessions->map(fn (ChatSession $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'createdAt' => $s->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Send a message and get an AI response.
     */
    public function sendMessage(SendMessageRequest $request, KnowledgeBaseFile $file, RagService $ragService): JsonResponse
    {
        $user = $request->user();
        $message = $request->validated()['message'];

        // Create or reuse session
        $sessionId = $request->input('session_id');
        $session = $sessionId
            ? ChatSession::query()
                ->where('id', $sessionId)
                ->where('user_id', $user->id)
                ->where('knowledge_base_file_id', $file->id)
                ->firstOrFail()
            : ChatSession::create([
                'user_id' => $user->id,
                'knowledge_base_file_id' => $file->id,
                'title' => str($message)->limit(60)->toString(),
            ]);

        // Store user message
        $session->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        // Get AI answer scoped to this document
        try {
            $result = $ragService->answer($message, $file->id);
            $answerText = $result['answer'];
        } catch (\Throwable $e) {
            $answerText = 'I could not find an answer in this document. Error: '.$e->getMessage();
        }

        // Store assistant message
        $assistantMessage = $session->messages()->create([
            'role' => 'assistant',
            'content' => $answerText,
        ]);

        return response()->json([
            'session_id' => $session->id,
            'session_title' => $session->title,
            'message' => [
                'id' => $assistantMessage->id,
                'role' => 'assistant',
                'content' => $answerText,
                'createdAt' => $assistantMessage->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Chat history page showing all sessions grouped by document.
     */
    public function history(Request $request): Response
    {
        $sessions = ChatSession::query()
            ->where('user_id', $request->user()->id)
            ->with('knowledgeBaseFile:id,name')
            ->withCount('messages')
            ->latest()
            ->get()
            ->map(fn (ChatSession $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'fileName' => $s->knowledgeBaseFile?->name ?? 'Deleted Document',
                'fileId' => $s->knowledge_base_file_id,
                'messagesCount' => $s->messages_count,
                'createdAt' => $s->created_at->toIso8601String(),
                'updatedAt' => $s->updated_at->toIso8601String(),
            ]);

        return Inertia::render('KnowledgeBase/ChatHistory', [
            'sessions' => $sessions,
        ]);
    }

    /**
     * Delete a chat session.
     */
    public function destroySession(Request $request, ChatSession $session): \Illuminate\Http\RedirectResponse
    {
        if ($session->user_id !== $request->user()->id) {
            abort(403);
        }

        $session->delete();

        return redirect()->route('chat.history');
    }
}
