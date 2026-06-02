<?php

use App\Http\Controllers\AppPageController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientMemberController;
use App\Http\Controllers\DownloadController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\UserController;
use App\Models\Client;
use App\Models\Image;
use App\Models\Project;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/login');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard', [
        'stats' => [
            'clients' => Client::count(),
            'projects' => Project::count(),
            'images' => Image::count(),
        ],
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
    Route::get('/images/{image}/download', [ImageController::class, 'download'])->name('images.download');
    Route::post('/images', [ImageController::class, 'store'])->name('images.store');
    Route::patch('/images/bulk-project', [ImageController::class, 'bulkProject'])->name('images.bulk-project');
    Route::post('/images/{image}', [ImageController::class, 'update'])->name('images.update');
    Route::get('/projects', [AppPageController::class, 'projects'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::get('/users', [AppPageController::class, 'users'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::get('/access-periods', [AppPageController::class, 'accessPeriods'])->name('access-periods.index');

    Route::resource('clients', ClientController::class)->except(['destroy']);
    Route::post('/clients/{client}/members', [ClientMemberController::class, 'store'])->name('clients.members.store');
    Route::patch('/clients/{client}/members/{membership}', [ClientMemberController::class, 'update'])->name('clients.members.update');
    Route::delete('/clients/{client}/members/{membership}', [ClientMemberController::class, 'destroy'])->name('clients.members.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
