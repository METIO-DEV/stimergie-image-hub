<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBlogPostRequest;
use App\Http\Requests\UpdateBlogPostRequest;
use App\Models\BlogPost;
use App\Models\Client;
use App\Models\Image;
use App\Models\User;
use App\Support\ImageUrlResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class BlogPostController extends Controller
{
    public function __construct(
        private readonly ImageUrlResolver $imageUrls,
    ) {}

    public function resources(Request $request): Response
    {
        return $this->publicIndex($request, 'resource', 'Ressources', 'Découvrez nos ressources et guides pratiques.');
    }

    public function ensemble(Request $request): Response
    {
        return $this->publicIndex($request, 'ensemble', 'Ensemble', 'Retrouvez les actualités, projets et conseils Stimergie.');
    }

    public function show(Request $request, BlogPost $blogPost): Response
    {
        abort_unless($blogPost->is_published, 404);

        $blogPost->load('client:id,name');

        return Inertia::render('Blog/Show', [
            'post' => $this->postSummary($blogPost),
            'canEdit' => $this->canManagePost($request->user(), $blogPost),
        ]);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($this->canManageBlog($user), 403);

        $manageableClientIds = $this->manageableClientIds($user);

        $posts = BlogPost::query()
            ->with('client:id,name')
            ->tap(fn ($query) => $this->applyManageablePostScope($query, $manageableClientIds))
            ->latest()
            ->get()
            ->map(fn (BlogPost $post) => $this->postSummary($post));

        return Inertia::render('Blog/AdminIndex', [
            'posts' => $posts,
            'filters' => [
                'clients' => $this->clientOptions($manageableClientIds),
            ],
            'canCreatePost' => true,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        abort_unless($this->canManageBlog($user), 403);

        $manageableClientIds = $this->manageableClientIds($user);

        return Inertia::render('Blog/Edit', [
            'post' => null,
            'clients' => $this->clientOptions($manageableClientIds),
            'imageOptions' => $this->imageOptions($manageableClientIds),
            'canCreateGlobalPost' => $user->isSuperAdmin(),
        ]);
    }

    public function edit(Request $request, BlogPost $blogPost): Response
    {
        $user = $request->user();

        abort_unless($this->canManagePost($user, $blogPost), 403);

        $blogPost->load('client:id,name');
        $manageableClientIds = $this->manageableClientIds($user);

        return Inertia::render('Blog/Edit', [
            'post' => $this->postSummary($blogPost),
            'clients' => $this->clientOptions($manageableClientIds),
            'imageOptions' => $this->imageOptions($manageableClientIds),
            'canCreateGlobalPost' => $user->isSuperAdmin(),
        ]);
    }

    public function store(StoreBlogPostRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $featuredImage = $this->featuredImage($data['featured_image_id'] ?? null);
        $isPublished = (bool) ($data['is_published'] ?? false);
        $featuredImageObjectKey = $featuredImage?->object_key_web ?: $featuredImage?->object_key_original;

        BlogPost::create([
            'client_id' => $data['client_id'] ?? null,
            'author_id' => $request->user()->id,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title']),
            'content' => $data['content'],
            'content_type' => $data['content_type'],
            'category' => $data['content_type'] === 'ensemble' ? ($data['category'] ?? null) : null,
            'featured_image_object_key' => $featuredImageObjectKey,
            'external_links' => $this->externalLinksPayload($data),
            'is_published' => $isPublished,
            'published_at' => $isPublished ? now() : null,
        ]);

        return redirect()
            ->route('blog.admin.index')
            ->with('success', 'Article créé.');
    }

    public function update(UpdateBlogPostRequest $request, BlogPost $blogPost): RedirectResponse
    {
        $data = $request->validated();
        $featuredImage = $this->featuredImage($data['featured_image_id'] ?? null);
        $isPublished = (bool) ($data['is_published'] ?? false);
        $featuredImageObjectKey = $blogPost->featured_image_object_key;

        if ($featuredImage instanceof Image) {
            $featuredImageObjectKey = $featuredImage->object_key_web ?: $featuredImage->object_key_original;
        } elseif ((bool) ($data['remove_featured_image'] ?? false)) {
            $featuredImageObjectKey = null;
        }

        $blogPost->update([
            'client_id' => $data['client_id'] ?? null,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title'], $blogPost),
            'content' => $data['content'],
            'content_type' => $data['content_type'],
            'category' => $data['content_type'] === 'ensemble' ? ($data['category'] ?? null) : null,
            'featured_image_object_key' => $featuredImageObjectKey,
            'external_links' => $this->externalLinksPayload($data),
            'is_published' => $isPublished,
            'published_at' => $isPublished ? ($blogPost->published_at ?: now()) : null,
        ]);

        return redirect()
            ->route('blog.admin.index')
            ->with('success', 'Article mis à jour.');
    }

    public function destroy(Request $request, BlogPost $blogPost): RedirectResponse
    {
        abort_unless($this->canManagePost($request->user(), $blogPost), 403);

        $blogPost->delete();

        return back()->with('success', 'Article supprimé.');
    }

    private function publicIndex(Request $request, string $contentType, string $title, string $description): Response
    {
        $activeClientId = $request->query('client_id') ? max(1, (int) $request->query('client_id')) : null;

        $posts = BlogPost::query()
            ->with('client:id,name')
            ->where('content_type', $contentType)
            ->where('is_published', true)
            ->when($activeClientId !== null, fn ($query) => $query->where('client_id', $activeClientId))
            ->latest('published_at')
            ->latest()
            ->get()
            ->map(fn (BlogPost $post) => $this->postSummary($post));

        return Inertia::render('Blog/PublicIndex', [
            'posts' => $posts,
            'title' => $title,
            'description' => $description,
            'contentType' => $contentType,
            'filters' => [
                'clients' => $this->publicClientOptions($contentType),
            ],
            'activeFilters' => [
                'clientId' => $activeClientId ? (string) $activeClientId : '',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function postSummary(BlogPost $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'content' => $post->content,
            'excerpt' => Str::limit(trim(strip_tags($post->content)), 180),
            'contentType' => $post->content_type,
            'contentTypeLabel' => $post->content_type === 'ensemble' ? 'Ensemble' : 'Ressource',
            'category' => $post->category,
            'categoryLabel' => $this->categoryLabel($post->category),
            'clientId' => $post->client_id,
            'clientName' => $post->client?->name,
            'featuredImageUrl' => $this->featuredImageUrl($post->featured_image_object_key),
            'featuredImageObjectKey' => $post->featured_image_object_key,
            'externalLinks' => $this->externalLinksSummary($post),
            'isPublished' => $post->is_published,
            'publishedAt' => $post->published_at?->toIso8601String(),
            'createdAt' => $post->created_at->toIso8601String(),
            'updatedAt' => $post->updated_at->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{label: string|null, url: string}>|null
     */
    private function externalLinksPayload(array $data): ?array
    {
        $links = collect($data['external_links'] ?? [])
            ->filter(fn ($link) => is_array($link) && filled($link['url'] ?? null))
            ->map(fn (array $link) => [
                'label' => filled($link['label'] ?? null) ? trim((string) $link['label']) : null,
                'url' => trim((string) $link['url']),
            ])
            ->values()
            ->all();

        return $links === [] ? null : $links;
    }

    /**
     * @return array<int, array{label: string, url: string, host: string|null}>
     */
    private function externalLinksSummary(BlogPost $post): array
    {
        return collect($post->external_links ?? [])
            ->filter(fn ($link) => is_array($link) && filled($link['url'] ?? null))
            ->map(function (array $link): array {
                $url = trim((string) $link['url']);
                $host = parse_url($url, PHP_URL_HOST);

                return [
                    'label' => filled($link['label'] ?? null) ? trim((string) $link['label']) : ($host ?: $url),
                    'url' => $url,
                    'host' => is_string($host) ? $host : null,
                ];
            })
            ->values()
            ->all();
    }

    private function categoryLabel(?string $category): ?string
    {
        return match ($category) {
            'actualites' => 'Actualités',
            'projets' => 'Projets',
            'conseils' => 'Conseils',
            default => null,
        };
    }

    private function featuredImageUrl(?string $objectKey): ?string
    {
        if (! $objectKey) {
            return null;
        }

        try {
            return Storage::disk('scaleway')->url($objectKey);
        } catch (Throwable) {
            return null;
        }
    }

    private function featuredImage(?int $imageId): ?Image
    {
        if (! $imageId) {
            return null;
        }

        return Image::find($imageId);
    }

    private function uniqueSlug(string $title, ?BlogPost $post = null): string
    {
        $base = Str::slug($title) ?: 'article';
        $slug = $base;
        $suffix = 2;

        while (BlogPost::query()
            ->where('slug', $slug)
            ->when($post, fn ($query) => $query->whereKeyNot($post->id))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function canManageBlog(?User $user): bool
    {
        return $user instanceof User
            && ($user->isSuperAdmin() || $user->hasAnyClientRole(['owner', 'manager']));
    }

    private function canManagePost(?User $user, BlogPost $post): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($post->client_id === null) {
            return false;
        }

        $client = Client::find($post->client_id);

        return $client instanceof Client && $user->can('update', $client);
    }

    /**
     * @return array<int>|null
     */
    private function manageableClientIds(User $user): ?array
    {
        if ($user->isSuperAdmin()) {
            return null;
        }

        return $user->clientMemberships()
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'manager'])
            ->pluck('client_id')
            ->all();
    }

    private function applyManageablePostScope($query, ?array $manageableClientIds): void
    {
        if ($manageableClientIds === null) {
            return;
        }

        $query->whereIn('client_id', $manageableClientIds);
    }

    private function clientOptions(?array $clientIds): mixed
    {
        return Client::query()
            ->when($clientIds !== null, fn ($query) => $query->whereIn('id', $clientIds))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
            ]);
    }

    private function publicClientOptions(string $contentType): mixed
    {
        return Client::query()
            ->whereHas('blogPosts', fn ($query) => $query
                ->where('content_type', $contentType)
                ->where('is_published', true))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
            ]);
    }

    private function imageOptions(?array $manageableClientIds): mixed
    {
        return Image::query()
            ->with(['client:id,name', 'project:id,name'])
            ->when($manageableClientIds !== null, fn ($query) => $query->whereIn('client_id', $manageableClientIds))
            ->whereNotNull('object_key_original')
            ->latest()
            ->limit(80)
            ->get()
            ->map(fn (Image $image) => [
                'id' => $image->id,
                'title' => $image->title,
                'clientName' => $image->client?->name,
                'projectName' => $image->project?->name,
                'thumbUrl' => $this->imageUrls->temporaryThumbnailUrl($image),
                'objectKey' => $image->object_key_web ?: $image->object_key_original,
            ]);
    }
}
