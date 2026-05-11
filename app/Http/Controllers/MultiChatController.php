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
use Symfony\Component\HttpFoundation\StreamedResponse;

class MultiChatController extends Controller
{
    /**
     * Show the multi-document chat interface.
     */
    public function show(Request $request, ?ChatSession $session = null): Response
    {
        $fileIds = $request->input('files', []);

        if ($session !== null) {
            $session->load(['messages', 'knowledgeBaseFiles']);
            $files = $session->knowledgeBaseFiles;
        } else {
            $files = KnowledgeBaseFile::whereIn('id', $fileIds)->get();
        }

        // Load recent sessions for the user that are multi-document (file_id is null)
        $recentSessions = ChatSession::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('knowledge_base_file_id')
            ->latest()
            ->limit(10)
            ->get(['id', 'title', 'created_at']);

        return Inertia::render('KnowledgeBase/MultiChatShow', [
            'files' => $files->map(fn (KnowledgeBaseFile $f) => [
                'id' => $f->id,
                'name' => $f->name,
            ]),
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
            'recentSessions' => $recentSessions->map(fn (ChatSession $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'createdAt' => $s->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Send a message in a multi-document chat context.
     */
    public function sendMessage(SendMessageRequest $request, RagService $ragService): JsonResponse
    {
        $user = $request->user();
        $message = $request->validated()['message'];
        $fileIds = $request->input('file_ids', []);

        // Create or reuse session
        $sessionId = $request->input('session_id');
        $session = $sessionId
            ? ChatSession::query()
                ->where('id', $sessionId)
                ->where('user_id', $user->id)
                ->firstOrFail()
            : ChatSession::create([
                'user_id' => $user->id,
                'knowledge_base_file_id' => null, // Multi-document session
                'title' => str($message)->limit(60)->toString(),
            ]);

        // If it's a new session, sync the files
        if (! $sessionId && ! empty($fileIds)) {
            $session->knowledgeBaseFiles()->sync($fileIds);
        }

        // Store user message
        $session->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        // Get AI answer scoped to these documents
        try {
            // Get all file IDs from the session if it's an existing session
            $targetFileIds = $sessionId ? $session->knowledgeBaseFiles()->pluck('knowledge_base_files.id')->toArray() : $fileIds;

            $result = $ragService->answer($message, $targetFileIds);
            $answerText = $result['answer'];
        } catch (\Throwable $e) {
            $answerText = 'I could not find an answer in the selected documents. Error: '.$e->getMessage();
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
     * Stream a message response.
     */
    public function streamMessage(SendMessageRequest $request, RagService $ragService): StreamedResponse
    {
        $user = $request->user();
        $message = $request->validated()['message'];
        $fileIds = $request->input('file_ids', []);
        $sessionId = $request->input('session_id');

        // Create or reuse session
        $session = $sessionId
            ? ChatSession::query()
                ->where('id', $sessionId)
                ->where('user_id', $user->id)
                ->firstOrFail()
            : ChatSession::create([
                'user_id' => $user->id,
                'knowledge_base_file_id' => null,
                'title' => str($message)->limit(60)->toString(),
            ]);

        if (! $sessionId && ! empty($fileIds)) {
            $session->knowledgeBaseFiles()->sync($fileIds);
        }

        $session->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        return response()->stream(function () use ($ragService, $message, $session) {
            $fullAnswer = '';
            $targetFileIds = $session->knowledgeBaseFiles()->pluck('knowledge_base_files.id')->toArray();

            try {
                $result = $ragService->streamAnswer(
                    $message,
                    function ($chunk) use (&$fullAnswer) {
                        $fullAnswer .= $chunk;
                        echo 'data: '.json_encode(['chunk' => $chunk])."\n\n";
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    },
                    $targetFileIds,
                    function ($status) {
                        echo 'data: '.json_encode(['thinking' => $status])."\n\n";
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    }
                );

                // Store assistant message
                $session->messages()->create([
                    'role' => 'assistant',
                    'content' => $fullAnswer,
                ]);

                echo 'data: '.json_encode(['done' => true, 'sources' => $result['sources'], 'session_id' => $session->id])."\n\n";
            } catch (\Throwable $e) {
                echo 'data: '.json_encode(['error' => $e->getMessage()])."\n\n";
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // For Nginx
        ]);
    }
}
