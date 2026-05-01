<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeBaseFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeBaseController extends Controller
{
    /**
     * Display the document listing page.
     */
    public function index(Request $request): Response
    {
        $files = KnowledgeBaseFile::query()
            ->latest()
            ->get()
            ->map(fn ($file) => [
                'id' => $file->id,
                'name' => $file->name,
                'path' => $file->path,
                'size' => $file->size,
                'status' => $file->status,
                'uploadedAt' => $file->created_at->toIso8601String(),
            ])
            ->all();

        return Inertia::render('Documents', [
            'files' => $files,
        ]);
    }

    /**
     * Display the knowledge base upload page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('KnowledgeBase');
    }

    /**
     * Delete a knowledge base file and its associated storage file.
     */
    public function destroy(Request $request, KnowledgeBaseFile $file): RedirectResponse
    {
        // Delete the physical file
        if ($file->path) {
            Storage::disk('local')->delete($file->path);
        }

        // Cascade: rag_chunks and chat_sessions/messages are deleted via FK constraints
        $file->delete();

        return redirect()->back()->with('message', 'Document deleted successfully.');
    }
}
