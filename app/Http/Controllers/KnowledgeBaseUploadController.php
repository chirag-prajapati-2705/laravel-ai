<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KnowledgeBaseUploadController extends Controller
{
    public function __invoke(
        \App\Http\Requests\KnowledgeBaseUploadRequest $request,
        \App\Services\PdfService $pdfService,
        \App\Services\RagService $ragService
    ): RedirectResponse {
        $file = $request->file('file');
        $filename = $this->uniqueFilename($file->getClientOriginalName());

        $path = Storage::disk('local')->putFileAs('knowledge-base', $file, $filename);
        $fullPath = Storage::disk('local')->path($path);

        // 1. Store in the database
        $knowledgeBaseFile = \App\Models\KnowledgeBaseFile::create([
            'name' => $filename,
            'path' => $path,
            'size' => $file->getSize(),
            'status' => 'processing',
        ]);

        // 2. Dispatch background job
        \App\Jobs\ProcessKnowledgeBaseFile::dispatch($knowledgeBaseFile);

        return redirect()->route('documents')->with('success', 'Document uploaded and processing started.');
    }

    private function uniqueFilename(string $originalName): string
    {
        $disk = Storage::disk('local');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $baseName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'document';
        $suffix = $extension !== '' ? '.'.$extension : '';
        $candidate = $baseName.$suffix;
        $counter = 1;

        while ($disk->exists('knowledge-base/'.$candidate)) {
            $candidate = $baseName.'-'.$counter.$suffix;
            $counter++;
        }

        return $candidate;
    }
}
