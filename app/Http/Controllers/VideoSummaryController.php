<?php

namespace App\Http\Controllers;

use App\Http\Requests\VideoSummaryRequest;
use App\Services\VideoSummaryService;
use Illuminate\Http\JsonResponse;
use Throwable;

class VideoSummaryController extends Controller
{
    public function __invoke(VideoSummaryRequest $request, VideoSummaryService $service): JsonResponse
    {
        try {
            $result = $service->summarizeFromUrl($request->validated('url'));

            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
