<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImageGenerateRequest;
use App\Services\ImageGeneratorService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ImageGeneratorController extends Controller
{
    public function __invoke(ImageGenerateRequest $request, ImageGeneratorService $service): JsonResponse
    {
        try {
            $result = $service->generate(
                $request->validated('image'),
                $request->validated('prompt'),
            );

            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
