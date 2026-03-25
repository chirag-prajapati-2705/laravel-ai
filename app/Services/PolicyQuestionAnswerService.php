<?php

declare(strict_types=1);

namespace App\Services;

use App\Neuron\PolicyDocumentAgent;
use Illuminate\Support\Facades\File;
use NeuronAI\Chat\Attachments\Document as PdfDocument;
use NeuronAI\Chat\Enums\AttachmentContentType;
use NeuronAI\Chat\Enums\MessageRole;
use NeuronAI\Chat\Messages\Message;
use RuntimeException;
use Throwable;

class PolicyQuestionAnswerService
{
    /**
     * @return array{answer: string, sources: array<int, string>, document: string}
     */
    public function answer(string $question, ?string $document = null): array
    {
        $pdfPath = $this->resolvePdfPath($document);

        return $this->answerViaAttachment($question, $pdfPath);
    }

    /**
     * @return array{answer: string, sources: array<int, string>, document: string}
     */
    protected function answerViaAttachment(string $question, string $pdfPath): array
    {
        $attachment = $this->makePdfAttachment($pdfPath);

        $message = new Message(
            role: MessageRole::USER,
            content: $question,
        );
        $message->addAttachment($attachment);

        $agent = new PolicyDocumentAgent;
        $response = $agent->chat($message);

        return [
            'answer' => trim($response->getContent()),
            'sources' => [],
            'document' => basename($pdfPath),
        ];
    }

    protected function resolvePdfPath(?string $document): string
    {
        $directory = storage_path('app/policies');

        if (! is_dir($directory)) {
            throw new RuntimeException('The policies folder was not found at storage/app/policies.');
        }

        $files = array_filter(
            File::files($directory),
            fn (\SplFileInfo $file): bool => strtolower($file->getExtension()) === 'pdf'
        );

        if ($files === []) {
            throw new RuntimeException('No PDF files were found in storage/app/policies.');
        }

        if (is_string($document) && $document !== '') {
            foreach ($files as $file) {
                if ($file->getFilename() === $document) {
                    return $file->getPathname();
                }
            }

            throw new RuntimeException('The requested policy document was not found.');
        }

        usort(
            $files,
            fn (\SplFileInfo $first, \SplFileInfo $second): int => $second->getMTime() <=> $first->getMTime()
        );

        return $files[0]->getPathname();
    }

    private function makePdfAttachment(string $pdfPath): PdfDocument
    {
        try {
            $contents = (string) file_get_contents($pdfPath);
        } catch (Throwable $exception) {
            throw new RuntimeException('Failed to read the policy PDF.', 0, $exception);
        }

        if ($contents === '') {
            throw new RuntimeException('The policy document appears to be empty.');
        }

        return new PdfDocument(
            document: base64_encode($contents),
            type: AttachmentContentType::BASE64,
            mediaType: 'application/pdf',
            filename: basename($pdfPath),
        );
    }
}
