<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RagQuestionRequest;
use App\Services\RagService;
use Illuminate\Http\JsonResponse;
use Throwable;

class RagQuestionController extends Controller
{
    public function __invoke(RagQuestionRequest $request, RagService $service): JsonResponse
    {
        try {
            $result = $service->answer($request->validated('question'));

            return response()->json($result);
        } catch (Throwable $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], 500);
        }
    }
}
