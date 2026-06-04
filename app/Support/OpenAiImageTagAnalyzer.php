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
                'model' => config('services.openai.image_tag_model', 'gpt-4.1-mini'),
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => implode(' ', [
                                    'Analyse cette image pour une banque d images Stimergie.',
                                    'Retourne uniquement un JSON valide sous la forme {"tags":["tag"]}.',
                                    'Produis 5 à 10 tags courts, en français, sans hashtag, utiles pour la recherche métier.',
                                ]),
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
        $rawTags = is_array($decoded) && isset($decoded['tags']) && is_array($decoded['tags'])
            ? $decoded['tags']
            : preg_split('/[,;\n]+/', $content);

        $tags = $this->tags->normalizeArray($rawTags ?: []);

        if ($tags === []) {
            throw new RuntimeException('Aucun tag exploitable retourné par l IA.');
        }

        return $tags;
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
