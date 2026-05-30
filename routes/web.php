<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientMemberController;
use App\Http\Controllers\ProfileController;
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
    Route::resource('clients', ClientController::class)->except(['destroy']);
    Route::post('/clients/{client}/members', [ClientMemberController::class, 'store'])->name('clients.members.store');
    Route::patch('/clients/{client}/members/{membership}', [ClientMemberController::class, 'update'])->name('clients.members.update');
    Route::delete('/clients/{client}/members/{membership}', [ClientMemberController::class, 'destroy'])->name('clients.members.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
