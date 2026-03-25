<?php

namespace App\Http\Controllers;

use App\Http\Requests\PolicyQuestionRequest;
use App\Services\PolicyQuestionAnswerService;
use Illuminate\Http\JsonResponse;
use Throwable;

class PolicyQuestionController extends Controller
{
    public function __invoke(PolicyQuestionRequest $request, PolicyQuestionAnswerService $service): JsonResponse
    {
        try {
            $result = $service->answer(
                $request->validated('question'),
                $request->validated('document'),
            );

            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
