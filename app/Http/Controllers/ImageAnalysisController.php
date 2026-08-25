<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeImageTagsRequest;
use App\Models\Image;
use App\Support\OpenAiImageTagAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ImageAnalysisController extends Controller
{
    public function __construct(private readonly OpenAiImageTagAnalyzer $analyzer) {}

    public function upload(AnalyzeImageTagsRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->analyzer->analyzeUploadedFile($request->file('file')));
    }

    public function image(Request $request, Image $image): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin()
            || ($image->client && $request->user()?->hasClientRole($image->client, ['owner', 'manager'])), 403);

        return $this->respond(fn () => $this->analyzer->analyzeStoredImage($image));
    }

    /**
     * @param  callable(): array<int, string>  $callback
     */
    private function respond(callable $callback): JsonResponse
    {
        try {
            return response()->json([
                'tags' => $callback(),
                'source' => 'ai',
            ]);
        } catch (RuntimeException $exception) {
            $status = str_contains($exception->getMessage(), 'OPENAI_API_KEY') ? 503 : 422;

            return response()->json([
                'message' => $exception->getMessage(),
            ], $status);
        }
    }
}
