<?php

namespace App\Support;

use App\Models\Image;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OpenAiImageTagAnalyzer
{
    private const MAX_BYTES = 20_000_000;

    public function __construct(
        private readonly ImageTagNormalizer $tags,
        private readonly ImageUrlResolver $imageUrls,
    ) {}

    /**
     * @return array<int, string>
     */
    public function analyzeUploadedFile(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if (! $path) {
            throw new RuntimeException('Impossible de lire le fichier à analyser.');
        }

        return $this->analyzeBytes(
            file_get_contents($path) ?: '',
            $file->getMimeType() ?: 'image/jpeg',
        );
    }

    /**
     * @return array<int, string>
     */
    public function analyzeStoredImage(Image $image): array
    {
        $source = $this->imageUrls->downloadSource($image, 'web');
        $objectKey = $source['objectKey'];

        if (! $objectKey) {
            throw new RuntimeException('Aucun objet image disponible pour analyse.');
        }

        $disk = Storage::disk($source['disk']);

        if (! $disk->exists($objectKey)) {
            throw new RuntimeException('Objet image introuvable dans le stockage.');
        }

        if (($disk->size($objectKey) ?: 0) > self::MAX_BYTES) {
            throw new RuntimeException('Image trop volumineuse pour l analyse IA.');
        }

        return $this->analyzeBytes(
            $disk->get($objectKey),
            $image->mime_type ?: 'image/jpeg',
        );
    }

    /**
     * @return array<int, string>
     */
    private function analyzeBytes(string $bytes, string $mimeType): array
    {
        if ($bytes === '') {
            throw new RuntimeException('Image vide ou illisible.');
        }

        if (strlen($bytes) > self::MAX_BYTES) {
            throw new RuntimeException('Image trop volumineuse pour l analyse IA.');
        }

        $apiKey = (string) config('services.openai.api_key');

        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY non configurée.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(45)
            ->acceptJson()
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.image_tag_model', 'o4-mini'),
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => 'You are a helpful image tagging assistant. Generate 5-10 relevant tags for the image provided. Return only an array of tags in French, with no additional text or explanation.',
                            ],
                            [
                                'type' => 'input_image',
                                'image_url' => 'data:'.$mimeType.';base64,'.base64_encode($bytes),
                                'detail' => 'low',
                            ],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI a refusé l analyse image.');
        }

        $content = (string) ($response->json('output_text') ?: $this->extractText($response->json('output', [])));
        $decoded = json_decode($content, true);
        $rawTags = $this->extractTags($decoded, $content);

        $tags = $this->tags->normalizeArray($rawTags ?: []);

        if ($tags === []) {
            throw new RuntimeException('Aucun tag exploitable retourné par l IA.');
        }

        return $tags;
    }

    /**
     * @return array<int, mixed>
     */
    private function extractTags(mixed $decoded, string $content): array
    {
        if (is_array($decoded)) {
            if (isset($decoded['tags']) && is_array($decoded['tags'])) {
                return $decoded['tags'];
            }

            if (array_is_list($decoded)) {
                return $decoded;
            }
        }

        return preg_split('/[,;\n]+/', $content) ?: [];
    }

    /**
     * @param  array<int, mixed>  $output
     */
    private function extractText(array $output): ?string
    {
        foreach ($output as $item) {
            foreach (($item['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'output_text') {
                    return $content['text'] ?? null;
                }
            }
        }

        return null;
    }
}
