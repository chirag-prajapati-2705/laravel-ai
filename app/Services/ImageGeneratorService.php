<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImageGeneratorService
{
    /**
     * @return array{generated_url: string, input_image_url: string}
     */
    public function generate(UploadedFile $image, string $prompt): array
    {
        $path = $image->store('image-generator/input', 'public');
        $inputImageUrl = Storage::disk('public')->url($path);
        $inputImageBase64 = base64_encode((string) file_get_contents($image->getRealPath()));

        $apiKey = config('services.bfl.key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Missing BFL API key.');
        }

        $baseUrl = rtrim(config('services.bfl.base_url', 'https://api.bfl.ai'), '/');
        $model = ltrim(config('services.bfl.model', 'flux-2-pro-preview'), '/');
        $outputFormat = config('services.bfl.output_format', 'jpeg');

        $submitResponse = Http::withHeaders([
            'accept' => 'application/json',
            'x-key' => $apiKey,
        ])->post($baseUrl.'/v1/'.$model, [
            'prompt' => $prompt,
            'input_image' => $inputImageBase64,
            'output_format' => $outputFormat,
        ]);

        if (! $submitResponse->successful()) {
            throw new RuntimeException('BFL request failed: '.$submitResponse->body());
        }

        $pollingUrl = $submitResponse->json('polling_url');
        if (! is_string($pollingUrl) || $pollingUrl === '') {
            throw new RuntimeException('BFL did not return a polling URL.');
        }

        $generatedUrl = $this->pollForResult($pollingUrl, $apiKey);
        $storedUrl = $this->storeGeneratedImage($generatedUrl);

        return [
            'generated_url' => $storedUrl,
            'input_image_url' => $inputImageUrl,
        ];
    }

    private function pollForResult(string $pollingUrl, string $apiKey): string
    {
        $pollIntervalMs = (int) config('services.bfl.poll_interval_ms', 500);
        $maxAttempts = (int) config('services.bfl.max_poll_attempts', 30);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            usleep($pollIntervalMs * 1000);

            $pollResponse = Http::withHeaders([
                'accept' => 'application/json',
                'x-key' => $apiKey,
            ])->get($pollingUrl);

            if (! $pollResponse->successful()) {
                throw new RuntimeException('BFL polling failed: '.$pollResponse->body());
            }

            $status = $pollResponse->json('status');
            if ($status === 'Ready') {
                $sampleUrl = $pollResponse->json('result.sample');
                if (is_string($sampleUrl) && $sampleUrl !== '') {
                    return $sampleUrl;
                }
            }

            if (in_array($status, ['Error', 'Failed'], true)) {
                throw new RuntimeException('BFL generation failed: '.$pollResponse->body());
            }
        }

        throw new RuntimeException('BFL generation timed out.');
    }

    private function storeGeneratedImage(string $sampleUrl): string
    {
        $downloadResponse = Http::get($sampleUrl);
        if (! $downloadResponse->successful()) {
            throw new RuntimeException('Failed to download generated image.');
        }

        $extension = (string) config('services.bfl.output_format', 'jpeg');
        $filePath = 'image-generator/generated/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($filePath, $downloadResponse->body());

        return Storage::disk('public')->url($filePath);
    }
}
