<?php

use App\Http\Controllers\AppPageController;
use App\Http\Controllers\AssetTransferController;
use App\Http\Controllers\BlogPostController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientMemberController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\ImageAnalysisController;
use App\Http\Controllers\ImageAssetController;
use App\Http\Controllers\ImageClientShareController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ImageImportController;
use App\Http\Controllers\ImageTagAnalysisRunController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectAccessPeriodController;
use App\Http\Controllers\ProjectBucketSyncController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SharedAlbumController;
use App\Http\Controllers\UserController;
use App\Models\Client;
use App\Models\Image;
use App\Models\Import;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/gallery');

Route::get('/about', [LegalPageController::class, 'about'])->name('about');
Route::get('/a-propos', [LegalPageController::class, 'about'])->name('about.fr');
Route::get('/mentions-legales', [LegalPageController::class, 'about'])->name('legal-notice');
Route::get('/terms-of-service', [LegalPageController::class, 'terms'])->name('terms.legacy');
Route::get('/conditions-utilisation', [LegalPageController::class, 'terms'])->name('terms');
Route::get('/privacy-policy', [LegalPageController::class, 'privacy'])->name('privacy.legacy');
Route::get('/confidentialite', [LegalPageController::class, 'privacy'])->name('privacy');
Route::get('/licenses', [LegalPageController::class, 'licenses'])->name('licenses');
Route::get('/resources', [BlogPostController::class, 'resources'])->name('blog.resources');
Route::get('/ressources', [BlogPostController::class, 'resources'])->name('blog.resources.fr');
Route::get('/ensemble', [BlogPostController::class, 'ensemble'])->name('blog.ensemble');
Route::get('/image-assets/{image}', [ImageAssetController::class, 'show'])->name('images.asset');
Route::get('/shared-albums/{shareKey}', [SharedAlbumController::class, 'show'])->name('shared-albums.show');
Route::get('/shared-albums/{shareKey}/images/{image}/asset', [ImageAssetController::class, 'sharedAlbum'])->name('shared-albums.images.asset');
Route::get('/shared-albums/{shareKey}/download', [SharedAlbumController::class, 'download'])->name('shared-albums.download');

Route::get('/dashboard', function (Request $request) {
    abort_unless($request->user()->isSuperAdmin(), 403);

    return Inertia::render('Dashboard', [
        'stats' => [
            'clients' => Client::count(),
            'projects' => Project::count(),
            'images' => Image::count(),
            'imports' => Import::query()->where('source', 'folder_upload')->count(),
            'activeImports' => Import::query()
                ->where('source', 'folder_upload')
                ->whereIn('status', ['pending', 'processing'])
                ->count(),
        ],
        'recentImports' => Import::query()
            ->with(['client:id,name', 'project:id,name'])
            ->where('source', 'folder_upload')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Import $import) => [
                'id' => $import->id,
                'status' => $import->status,
                'clientName' => $import->client?->name,
                'projectName' => $import->project?->name,
                'totalItems' => $import->total_items,
                'uploadedItems' => $import->uploaded_items,
                'processedItems' => $import->processed_items,
                'failedItems' => $import->failed_items,
                'duplicateItems' => $import->duplicate_items,
                'startedAt' => $import->started_at?->toIso8601String(),
                'finishedAt' => $import->finished_at?->toIso8601String(),
            ]),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/gallery', [AppPageController::class, 'gallery'])->name('gallery.index');
    Route::get('/contact', [AppPageController::class, 'contact'])->name('contact.index');
    Route::post('/contact', [AppPageController::class, 'sendContact'])->name('contact.send');
    Route::get('/downloads', [AppPageController::class, 'downloads'])->name('downloads.index');
    Route::post('/downloads', [DownloadController::class, 'store'])->name('downloads.store');
    Route::get('/downloads/{downloadJob}', [DownloadController::class, 'show'])->name('downloads.show');
    Route::get('/images', [AppPageController::class, 'images'])->name('images.index');
    Route::get('/imports', [AppPageController::class, 'imports'])->name('imports.index');
    Route::get('/asset-transfers', [AssetTransferController::class, 'index'])->name('asset-transfers.index');
    Route::get('/asset-transfers/sources', [AssetTransferController::class, 'sources'])->name('asset-transfers.sources');
    Route::post('/asset-transfers', [AssetTransferController::class, 'store'])->name('asset-transfers.store');
    Route::post('/asset-transfers/resync-bucket', [AssetTransferController::class, 'resyncBucket'])->name('asset-transfers.resync-bucket');
    Route::post('/asset-transfers/generate-web-variants', [AssetTransferController::class, 'generateWebVariants'])->name('asset-transfers.generate-web-variants');
    Route::get('/asset-transfers/{assetTransferJob}', [AssetTransferController::class, 'show'])->name('asset-transfers.show');
    Route::post('/asset-transfers/{assetTransferJob}/stop', [AssetTransferController::class, 'stop'])->name('asset-transfers.stop');
    Route::get('/images/{image}/download', [ImageController::class, 'download'])->name('images.download');
    Route::post('/images/{image}/rights-extension', [ImageController::class, 'requestRightsExtension'])->name('images.rights-extension');
    Route::post('/images/analyze-tags', [ImageAnalysisController::class, 'upload'])->name('images.analyze-tags');
    Route::post('/images/{image}/analyze-tags', [ImageAnalysisController::class, 'image'])->name('images.analyze-tags.existing');
    Route::post('/images', [ImageController::class, 'store'])->name('images.store');
    Route::patch('/images/bulk-project', [ImageController::class, 'bulkProject'])->name('images.bulk-project');
    Route::post('/images/{image}', [ImageController::class, 'update'])->name('images.update');
    Route::get('/image-tag-analysis-runs', [ImageTagAnalysisRunController::class, 'index'])->name('image-tag-analysis-runs.index');
    Route::post('/image-tag-analysis-runs', [ImageTagAnalysisRunController::class, 'store'])->name('image-tag-analysis-runs.store');
    Route::post('/image-tag-analysis-runs/{run}/stop', [ImageTagAnalysisRunController::class, 'stop'])->name('image-tag-analysis-runs.stop');
    Route::get('/images/{image}/client-shares', [ImageClientShareController::class, 'index'])->name('images.client-shares.index');
    Route::post('/images/{image}/client-shares', [ImageClientShareController::class, 'store'])->name('images.client-shares.store');
    Route::delete('/images/{image}/client-shares/{client}', [ImageClientShareController::class, 'destroy'])->name('images.client-shares.destroy');
    Route::post('/image-imports', [ImageImportController::class, 'store'])->name('image-imports.store');
    Route::get('/image-imports/{import}', [ImageImportController::class, 'show'])->name('image-imports.show');
    Route::post('/image-imports/{import}/items', [ImageImportController::class, 'item'])->name('image-imports.items.store');
    Route::post('/image-imports/{import}/retry-failed', [ImageImportController::class, 'retryFailed'])->name('image-imports.retry-failed');
    Route::post('/shared-albums', [SharedAlbumController::class, 'store'])->name('shared-albums.store');
    Route::get('/projects', [AppPageController::class, 'projects'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::post('/projects/{project}/sync-bucket-images', [ProjectBucketSyncController::class, 'store'])->name('projects.sync-bucket-images');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::get('/users', [AppPageController::class, 'users'])->name('users.index');
    Route::get('/users/search', [UserController::class, 'search'])->name('users.search');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/access-periods', [AppPageController::class, 'accessPeriods'])->name('access-periods.index');
    Route::post('/access-periods', [ProjectAccessPeriodController::class, 'store'])->name('access-periods.store');
    Route::patch('/access-periods/{accessPeriod}', [ProjectAccessPeriodController::class, 'update'])->name('access-periods.update');
    Route::delete('/access-periods/{accessPeriod}', [ProjectAccessPeriodController::class, 'destroy'])->name('access-periods.destroy');
    Route::patch('/legal-pages/{legalPage}', [LegalPageController::class, 'update'])->name('legal-pages.update');
    Route::get('/blog-admin', [BlogPostController::class, 'index'])->name('blog.admin.index');
    Route::get('/blog/new', [BlogPostController::class, 'create'])->name('blog.create');
    Route::get('/blog/edit/{blogPost}', [BlogPostController::class, 'edit'])->name('blog.edit');
    Route::get('/blog-editor', [BlogPostController::class, 'create'])->name('blog-editor.create');
    Route::get('/blog-editor/{blogPost}', [BlogPostController::class, 'edit'])->name('blog-editor.edit');
    Route::post('/blog', [BlogPostController::class, 'store'])->name('blog.store');
    Route::patch('/blog/{blogPost}', [BlogPostController::class, 'update'])->name('blog.update');
    Route::delete('/blog/{blogPost}', [BlogPostController::class, 'destroy'])->name('blog.destroy');

    Route::resource('clients', ClientController::class);
    Route::post('/clients/{client}/members', [ClientMemberController::class, 'store'])->name('clients.members.store');
    Route::patch('/clients/{client}/members/{membership}', [ClientMemberController::class, 'update'])->name('clients.members.update');
    Route::delete('/clients/{client}/members/{membership}', [ClientMemberController::class, 'destroy'])->name('clients.members.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/blog/{blogPost:slug}', [BlogPostController::class, 'show'])->name('blog.show');

require __DIR__.'/auth.php';
