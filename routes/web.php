<?php

use App\Http\Controllers\AppPageController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientMemberController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ImageImportController;
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
Route::get('/shared-albums/{shareKey}', [SharedAlbumController::class, 'show'])->name('shared-albums.show');
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
    Route::get('/images/{image}/download', [ImageController::class, 'download'])->name('images.download');
    Route::post('/images', [ImageController::class, 'store'])->name('images.store');
    Route::patch('/images/bulk-project', [ImageController::class, 'bulkProject'])->name('images.bulk-project');
    Route::post('/images/{image}', [ImageController::class, 'update'])->name('images.update');
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
    Route::get('/access-periods', [AppPageController::class, 'accessPeriods'])->name('access-periods.index');
    Route::post('/access-periods', [ProjectAccessPeriodController::class, 'store'])->name('access-periods.store');
    Route::patch('/access-periods/{accessPeriod}', [ProjectAccessPeriodController::class, 'update'])->name('access-periods.update');
    Route::delete('/access-periods/{accessPeriod}', [ProjectAccessPeriodController::class, 'destroy'])->name('access-periods.destroy');
    Route::patch('/legal-pages/{legalPage}', [LegalPageController::class, 'update'])->name('legal-pages.update');

    Route::resource('clients', ClientController::class);
    Route::post('/clients/{client}/members', [ClientMemberController::class, 'store'])->name('clients.members.store');
    Route::patch('/clients/{client}/members/{membership}', [ClientMemberController::class, 'update'])->name('clients.members.update');
    Route::delete('/clients/{client}/members/{membership}', [ClientMemberController::class, 'destroy'])->name('clients.members.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
